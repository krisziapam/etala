<?php
require_once __DIR__.'/includes/v2.php';$user=require_login();$admin=$user['role']==='admin';$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 v2_require_post();if(!$admin)v2_error('Admin only',403);
 $action=$_POST['action']??'';$id=(int)($_POST['entry_id']??0);
 try{
  if($action==='save'){
   $type=$_POST['entry_type']??'';$name=trim($_POST['name']??'');$phone=trim($_POST['phone']??'');$address=trim($_POST['address_text']??'');$notes=trim($_POST['notes']??'');$visible=isset($_POST['visible'])?1:0;
   if(!in_array($type,['hotline','hospital','contact'],true)||!$name||mb_strlen($name)>160||mb_strlen($phone)>60||mb_strlen($address)>255||mb_strlen($notes)>500)throw new RuntimeException('Invalid directory entry.');
   if($id){$q=db()->prepare('UPDATE directory_entry SET entry_type=?,name=?,phone=?,address_text=?,notes=?,visible_to_responders=? WHERE entry_id=?');$q->execute([$type,$name,$phone,$address,$notes,$visible,$id]);}
   else{$q=db()->prepare('INSERT INTO directory_entry(entry_type,name,phone,address_text,notes,visible_to_responders,created_by) VALUES (?,?,?,?,?,?,?)');$q->execute([$type,$name,$phone,$address,$notes,$visible,$user['user_id']]);}
   v2_event((int)$user['user_id'],'directory_change','Directory entry saved',$name);redirect('directory.php');
  }
  if($action==='delete'){$q=db()->prepare('DELETE FROM directory_entry WHERE entry_id=?');$q->execute([$id]);v2_event((int)$user['user_id'],'directory_change','Directory entry deleted','Entry #'.$id);redirect('directory.php');}
 }catch(Throwable $e){$errors[]=$e instanceof PDOException?'Database error':$e->getMessage();}
}
$q=db()->prepare('SELECT * FROM directory_entry '.($admin?'':'WHERE visible_to_responders=1 ').'ORDER BY entry_type,name');$q->execute();$entries=$q->fetchAll();$page_title='Emergency Directory';require __DIR__.'/includes/header.php';?>
<h1>Emergency Directory</h1><p>Contact details must be verified before use. Call 911 for the Philippine national emergency hotline.</p>
<?php foreach($errors as $error):?><p class="v2-error"><?=e($error)?></p><?php endforeach;?>
<?php if($admin):?><section class="v2-card"><h2>Add Directory Entry</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><label>Type<select name="entry_type"><option value="hotline">Emergency hotline</option><option value="hospital">Hospital</option><option value="contact">Authorized contact</option></select></label><label>Name<input name="name" maxlength="160" required></label><label>Phone<input name="phone" maxlength="60"></label><label>Address<input name="address_text" maxlength="255"></label><label>Notes<textarea name="notes" maxlength="500"></textarea></label><label><input type="checkbox" name="visible" checked> Visible to Responders</label><button>Save entry</button></form></section><?php endif;?>
<?php foreach(['hotline'=>'Emergency Hotlines','hospital'=>'Hospitals','contact'=>'Authorized Contacts'] as $type=>$heading):?><section class="v2-card"><h2><?=e($heading)?></h2>
<?php if($type==='hotline'):?><p><strong>National Emergency Hotline: <a href="tel:911">911</a></strong></p><?php endif;?>
<?php $found=false;foreach($entries as $entry):if($entry['entry_type']!==$type)continue;$found=true;?><article class="v2-entry"><strong><?=e($entry['name'])?></strong> <?php if($entry['phone']):?><a href="tel:<?=e(preg_replace('/[^+0-9]/','',$entry['phone']))?>"><?=e($entry['phone'])?></a><?php endif;?><p><?=e($entry['address_text'])?></p><small><?=e($entry['notes'])?></small>
<?php if($admin):?><form method="post" class="v2-inline" onsubmit="return confirm('Delete this directory entry?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="entry_id" value="<?=(int)$entry['entry_id']?>"><button>Delete</button></form><?php endif;?></article><?php endforeach;?>
<?php if(!$found):?><p>No verified entries have been added yet.</p><?php endif;?></section><?php endforeach;?>
<?php require __DIR__.'/includes/footer.php';?>
