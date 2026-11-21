<?php
require_once __DIR__.'/includes/v2.php';
$user=require_login();$uid=(int)$user['user_id'];$errors=[];
$types=['Additional personnel','Replacement ambulance','Mechanical / towing support','Medical support','Fire & rescue support','Equipment / supplies','Fuel assistance','Other assistance'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 v2_require_post();$action=$_POST['action']??'';
 try {
  if($action==='create'){
   $type=trim($_POST['type']??'');$location=trim($_POST['location']??'');$details=trim($_POST['details']??'');$slots=(int)($_POST['slots']??0);$rid=(int)($_POST['report_id']??0);
   if(!in_array($type,$types,true)||$location===''||strlen($location)>255||$details===''||strlen($details)>4000||$slots<1||$slots>30)throw new RuntimeException('Complete the form with valid details (1–30 assistance slots).');
   if($rid && (!v2_report($rid)||!v2_can_report($user,$rid)))throw new RuntimeException('You cannot link that incident.');
   db()->beginTransaction();$q=db()->prepare('INSERT INTO backup_request(requested_by,report_id,assistance_type,location_text,description,slots_needed) VALUES (?,?,?,?,?,?)');$q->execute([$uid,$rid?:null,$type,$location,$details,$slots]);$bid=(int)db()->lastInsertId();v2_event($uid,'backup_created','Backup requested: '.$type,$location.' — '.$details,$rid?:null,$bid);db()->commit();redirect('backup.php?created=1');
  }
  if($action==='accept'){
   $bid=(int)($_POST['backup_id']??0);db()->beginTransaction();$q=db()->prepare('SELECT * FROM backup_request WHERE backup_id=? FOR UPDATE');$q->execute([$bid]);$b=$q->fetch();
   if(!$b||in_array($b['status'],['completed','cancelled'],true))throw new RuntimeException('Request is not available.');
   $q=db()->prepare('SELECT COUNT(*) FROM backup_acceptance WHERE backup_id=?');$q->execute([$bid]);$n=(int)$q->fetchColumn();
   $q=db()->prepare('SELECT COUNT(*) FROM backup_acceptance WHERE backup_id=? AND user_id=?');$q->execute([$bid,$uid]);if($q->fetchColumn())throw new RuntimeException('You have already accepted this request.');
   if($n>=(int)$b['slots_needed'])throw new RuntimeException('All assistance slots have been taken.');
   $q=db()->prepare('INSERT INTO backup_acceptance(backup_id,user_id) VALUES (?,?)');$q->execute([$bid,$uid]);$status=$n+1>=(int)$b['slots_needed']?'fully_accepted':'partially_accepted';db()->prepare('UPDATE backup_request SET status=? WHERE backup_id=?')->execute([$status,$bid]);v2_event($uid,'backup_accepted','Backup slot accepted','Request #'.$bid,$b['report_id']? (int)$b['report_id']:null,$bid);db()->commit();redirect('backup.php?accepted=1');
  }
  if($action==='status'){
   $bid=(int)($_POST['backup_id']??0);$next=$_POST['status']??'';
   db()->beginTransaction();$q=db()->prepare('SELECT * FROM backup_request WHERE backup_id=? FOR UPDATE');$q->execute([$bid]);$b=$q->fetch();if(!$b)throw new RuntimeException('Request not found.');
   $admin=$user['role']==='admin';$owner=(int)$b['requested_by']===$uid;
   $q=db()->prepare('SELECT COUNT(*) FROM backup_acceptance WHERE backup_id=? AND user_id=?');$q->execute([$bid,$uid]);$acceptor=(bool)$q->fetchColumn();
   $allowed=['fully_accepted'=>['en_route','cancelled'],'partially_accepted'=>['en_route','cancelled'],'en_route'=>['arrived','cancelled'],'arrived'=>['completed'],'waiting'=>['cancelled']];
   if(!in_array($next,$allowed[$b['status']]??[],true)||!($admin||($next==='cancelled'&&$owner)||($next!=='cancelled'&&$acceptor)))throw new RuntimeException('This status change is not allowed.');
   db()->prepare('UPDATE backup_request SET status=? WHERE backup_id=?')->execute([$next,$bid]);v2_event($uid,'backup_status','Backup status: '.str_replace('_',' ',$next),'Request #'.$bid,$b['report_id']? (int)$b['report_id']:null,$bid);db()->commit();redirect('backup.php');
  }
  throw new RuntimeException('Unknown action.');
 }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();$errors[]=$ex instanceof PDOException?'Database operation failed.':$ex->getMessage();}
}
$q=db()->query('SELECT b.*,u.first_name,u.last_name,(SELECT COUNT(*) FROM backup_acceptance a WHERE a.backup_id=b.backup_id) accepted_count FROM backup_request b JOIN user u ON u.user_id=b.requested_by ORDER BY b.backup_id DESC LIMIT 100');$backups=$q->fetchAll();
$acceptances=[];if($backups){$ids=array_column($backups,'backup_id');$in=implode(',',array_fill(0,count($ids),'?'));$q=db()->prepare("SELECT a.backup_id,a.user_id,u.first_name,u.last_name FROM backup_acceptance a JOIN user u ON u.user_id=a.user_id WHERE a.backup_id IN ($in)");$q->execute($ids);foreach($q as $r)$acceptances[$r['backup_id']][]=$r;}
$page_title='Backup Assistance';require __DIR__.'/includes/header.php';
?>
<h1>Backup Assistance</h1><p>Request help for any operational need, including incidents, vehicle breakdowns, equipment, or fuel. All authorized responders can view open requests.</p>
<div class="v2-grid"><section class="v2-card"><h2 id="request">Request Backup</h2>
<?php foreach($errors as $error):?><p class="v2-error"><?=e($error)?></p><?php endforeach;?>
<form method="post" class="v2-backup-form"><?=csrf_field()?><input type="hidden" name="action" value="create">
<label>Assistance type<select name="type" required><?php foreach($types as $type):?><option><?=e($type)?></option><?php endforeach;?></select></label>
<label for="backup-location">Location</label><input id="backup-location" type="text" name="location" class="v2-backup-location" maxlength="255" required autocomplete="street-address" placeholder="Where is assistance needed?">
<label>Related incident ID (optional)<input type="number" name="report_id" min="1" placeholder="Leave blank for standalone request"></label>
<label>Number of responders / resources needed<input type="number" name="slots" min="1" max="30" value="1" required></label>
<label>Situation and assistance needed<textarea name="details" maxlength="4000" required></textarea></label><button type="submit">Send Backup Request</button></form></section>
<section class="v2-card" id="requests"><h2>View Backup Requests</h2><p class="v2-muted">Newest first · <?=count($backups)?> recent requests</p>
<?php if(!$backups):?><p>No requests yet.</p><?php endif;?>
<?php foreach($backups as $b):$mine=false;foreach($acceptances[$b['backup_id']]??[] as $a)if((int)$a['user_id']===$uid)$mine=true; ?>
<article class="v2-entry"><div class="v2-between"><strong>#<?= (int)$b['backup_id']?> · <?=e($b['assistance_type'])?></strong><span class="v2-pill"><?=e(str_replace('_',' ',$b['status']))?></span></div>
<p><?=e($b['location_text'])?> · <?=e($b['description'])?></p><small>Requested by <?=e($b['first_name'].' '.$b['last_name'])?> · <?=e($b['created_at'])?> · <?= (int)$b['accepted_count']?> / <?= (int)$b['slots_needed']?> accepted</small>
<?php if($b['report_id']&&v2_can_report($user,(int)$b['report_id'])):?><p><a href="<?=e(url('report.php?id='.$b['report_id']))?>">Related incident #<?= (int)$b['report_id']?></a></p><?php endif;?>
<?php if(!empty($acceptances[$b['backup_id']])):?><p>Accepted by: <?php foreach($acceptances[$b['backup_id']] as $a):?><?=e($a['first_name'].' '.$a['last_name'])?>; <?php endforeach;?></p><?php endif;?>
<?php if(in_array($b['status'],['waiting','partially_accepted'],true)&&!$mine):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="accept"><input type="hidden" name="backup_id" value="<?= (int)$b['backup_id']?>"><button>Accept Backup</button></form><?php endif;?>
<?php if($user['role']==='admin'||$mine||(int)$b['requested_by']===$uid):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="status"><input type="hidden" name="backup_id" value="<?= (int)$b['backup_id']?>"><select name="status"><option value="">Update status…</option><?php foreach(['en_route'=>'En route','arrived'=>'Arrived','completed'=>'Completed','cancelled'=>'Cancel'] as $value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select><button>Update</button></form><?php endif;?></article>
<?php endforeach;?></section></div>
<?php require __DIR__.'/includes/footer.php';?>
