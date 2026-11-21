<?php
require_once __DIR__.'/includes/v2.php';
$user=require_login();$uid=(int)$user['user_id'];$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 v2_require_post();$action=$_POST['action']??'';
 if($action==='read' || $action==='all'){
  $visible=v2_events($user,200);$ids=array_map(static fn($e)=>(int)$e['event_id'],$visible);
  if($action==='read'){$id=(int)($_POST['event_id']??0);$ids=in_array($id,$ids,true)?[$id]:[];}
  $stmt=db()->prepare('INSERT IGNORE INTO notification_read(user_id,event_id) VALUES (?,?)');
  foreach($ids as $id)$stmt->execute([$uid,$id]);
  redirect('notifications.php');
 }
}
$events=v2_events($user,200);$q=db()->prepare('SELECT event_id FROM notification_read WHERE user_id=?');$q->execute([$uid]);$read=array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);
$page_title='Notifications';require __DIR__.'/includes/header.php';?>
<h1>Notifications</h1><p>Operational updates visible to your account. Updates are checked while this page is open.</p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="all"><button>Mark all as read</button></form>
<section class="v2-card" id="notification-list">
<?php foreach($events as $ev):$id=(int)$ev['event_id'];$isread=isset($read[$id]);?>
<article class="v2-entry" style="<?= $isread?'':'border-left:4px solid #d73b44;padding-left:12px' ?>"><div class="v2-between"><strong><?=e($ev['title'])?></strong><small><?=e($ev['created_at'])?></small></div><p><?=e($ev['details'])?></p><small><?=e($ev['event_type'])?></small>
<?php if($ev['report_id']):?> <a href="<?=e(url('report.php?id='.(int)$ev['report_id']))?>">Incident details</a><?php elseif($ev['backup_id']):?> <a href="<?=e(url('backup.php#requests'))?>">Backup requests</a><?php endif;?>
<?php if(!$isread):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="read"><input type="hidden" name="event_id" value="<?=$id?>"><button>Mark as read</button></form><?php endif;?></article>
<?php endforeach;?>
<?php if(!$events):?><p>No notifications yet.</p><?php endif;?></section>
<script>setInterval(async()=>{try{let r=await fetch('<?=e(url('api-notifications.php'))?>',{credentials:'same-origin'});if(!r.ok)return;let d=await r.json();let el=document.querySelector('#v2-unread');if(el)el.textContent=d.unread?String(d.unread):'';}catch(e){}},15000);</script>
<?php require __DIR__.'/includes/footer.php';?>
