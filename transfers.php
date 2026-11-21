<?php
require_once __DIR__.'/includes/v2.php';$user=require_login();$uid=(int)$user['user_id'];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 v2_require_post();$action=$_POST['action']??'';
 try{
  if($action==='create'){
   $rid=(int)($_POST['report_id']??0);$to=(int)($_POST['to_user_id']??0);$reason=trim($_POST['reason']??'');$notes=trim($_POST['notes']??'');
   if(!$rid||!v2_report($rid)||!v2_can_report($user,$rid)||$to===$uid||$reason===''||strlen($reason)>120||$notes===''||strlen($notes)>5000)throw new RuntimeException('Invalid incident, recipient, or handover details.');
   $q=db()->prepare("SELECT 1 FROM user WHERE user_id=? AND status='active' AND role='responder'");$q->execute([$to]);if(!$q->fetchColumn())throw new RuntimeException('Select an active responder.');
   db()->beginTransaction();$q=db()->prepare("SELECT COUNT(*) FROM incident_transfer WHERE report_id=? AND status='pending'");$q->execute([$rid]);if($q->fetchColumn())throw new RuntimeException('This incident already has a pending transfer.');
   db()->prepare('INSERT INTO incident_transfer(report_id,from_user_id,to_user_id,reason,handover_notes) VALUES (?,?,?,?,?)')->execute([$rid,$uid,$to,$reason,$notes]);v2_event($uid,'transfer_requested','Incident handover requested','Incident #'.$rid.' — '.$reason,$rid);db()->commit();redirect('transfers.php?sent=1');
  }
  if($action==='respond'){
   $tid=(int)($_POST['transfer_id']??0);$answer=$_POST['answer']??'';if(!in_array($answer,['accepted','rejected','cancelled'],true))throw new RuntimeException('Invalid response.');
   db()->beginTransaction();$q=db()->prepare('SELECT * FROM incident_transfer WHERE transfer_id=? FOR UPDATE');$q->execute([$tid]);$t=$q->fetch();if(!$t||$t['status']!=='pending')throw new RuntimeException('Transfer already handled.');
   if($answer==='cancelled'){if($user['role']!=='admin'&&(int)$t['from_user_id']!==$uid)throw new RuntimeException('Not authorized.');}
   elseif($user['role']!=='admin'&&(int)$t['to_user_id']!==$uid)throw new RuntimeException('Only the receiving responder can acknowledge.');
   db()->prepare('UPDATE incident_transfer SET status=?,responded_at=NOW() WHERE transfer_id=?')->execute([$answer,$tid]);
   if($answer==='accepted')db()->prepare('INSERT INTO incident_assignment(report_id,assigned_user_id) VALUES (?,?) ON DUPLICATE KEY UPDATE assigned_user_id=VALUES(assigned_user_id),assigned_at=NOW()')->execute([$t['report_id'],$t['to_user_id']]);
   v2_event($uid,'transfer_'.$answer,'Incident transfer '. $answer,'Incident #'.$t['report_id'],$t['report_id']);db()->commit();redirect('transfers.php');
  }
  throw new RuntimeException('Unknown action.');
 }catch(Throwable $ex){if(db()->inTransaction())db()->rollBack();$errors[]=$ex instanceof PDOException?'Database operation failed.':$ex->getMessage();}
}
$people=array_filter(v2_people(),fn($p)=>$p['role']==='responder');
$sql='SELECT t.*,a.first_name from_first,a.last_name from_last,b.first_name to_first,b.last_name to_last FROM incident_transfer t JOIN user a ON a.user_id=t.from_user_id JOIN user b ON b.user_id=t.to_user_id';
if($user['role']!=='admin')$sql.=' WHERE t.from_user_id=:u1 OR t.to_user_id=:u2';
$sql.=' ORDER BY t.transfer_id DESC LIMIT 100';$q=db()->prepare($sql);$q->execute($user['role']==='admin'?[]:['u1'=>$uid,'u2'=>$uid]);$transfers=$q->fetchAll();
$page_title='Incident Transfers';require __DIR__.'/includes/header.php';?>
<h1>Incident Handover &amp; Transfer</h1><p>A backup acceptance does not transfer incident responsibility. A handover requires acknowledgment by the receiving responder. The previous assignment stays in place until accepted.</p>
<div class="v2-grid"><section class="v2-card"><h2>Request a transfer</h2><?php foreach($errors as $error):?><p class="v2-error"><?=e($error)?></p><?php endforeach;?>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="create">
<label>Incident ID<input name="report_id" type="number" min="1" required value="<?= (int)($_GET['report_id']??0)?:'' ?>"></label>
<label>Receiving responder<select name="to_user_id" required><option value="">Choose a responder</option><?php foreach($people as $p):if((int)$p['user_id']===$uid)continue;?><option value="<?= (int)$p['user_id']?>"><?=e($p['first_name'].' '.$p['last_name'])?></option><?php endforeach;?></select></label>
<label>Reason<select name="reason"><option>Vehicle breakdown</option><option>Shift change</option><option>Operational escalation</option><option>Medical handover</option><option>Other</option></select></label>
<label>Current situation, actions completed, and pending tasks<textarea name="notes" maxlength="5000" required></textarea></label><button>Request Handover</button></form></section>
<section class="v2-card"><h2>Transfer History &amp; Pending Requests</h2><?php if(!$transfers):?><p>No transfers yet.</p><?php endif;?>
<?php foreach($transfers as $t):?><article class="v2-entry"><div class="v2-between"><strong>Incident #<?= (int)$t['report_id']?></strong><span class="v2-pill"><?=e($t['status'])?></span></div>
<p><?=e($t['from_first'].' '.$t['from_last'])?> → <?=e($t['to_first'].' '.$t['to_last'])?></p><p><?=e($t['reason'])?> · <?=e($t['handover_notes'])?></p><small><?=e($t['created_at'])?></small>
<?php if($t['status']==='pending'):?>
<?php if($user['role']==='admin'||(int)$t['to_user_id']===$uid):?><?php foreach(['accepted'=>'Acknowledge & Accept','rejected'=>'Reject'] as $action=>$label):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="respond"><input type="hidden" name="answer" value="<?=e($action)?>"><input type="hidden" name="transfer_id" value="<?= (int)$t['transfer_id']?>"><button><?=e($label)?></button></form><?php endforeach;?><?php endif;?>
<?php if($user['role']==='admin'||(int)$t['from_user_id']===$uid):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="respond"><input type="hidden" name="answer" value="cancelled"><input type="hidden" name="transfer_id" value="<?= (int)$t['transfer_id']?>"><button>Cancel Request</button></form><?php endif;?>
<?php endif;?></article><?php endforeach;?></section></div>
<?php require __DIR__.'/includes/footer.php';?>
