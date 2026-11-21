<?php
require_once __DIR__.'/includes/v2.php';$user=require_login();$admin=$user['role']==='admin';$uid=(int)$user['user_id'];$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 v2_require_post();$action=$_POST['action']??'';
 try{
  if($action==='add'){
   if(!$admin)v2_error('Admin only.',403);
   $name=trim($_POST['name']??'');$category=trim($_POST['category']??'');$kind=$_POST['kind']??'';$qty=(int)($_POST['quantity']??-1);$low=(int)($_POST['low']??-1);
   if(!$name||strlen($name)>120||!$category||strlen($category)>80||!in_array($kind,['consumable','returnable'],true)||$qty<0||$low<0)throw new RuntimeException('Invalid equipment details.');
   db()->beginTransaction();$q=db()->prepare('INSERT INTO equipment(name,category,kind,quantity,low_stock_at) VALUES (?,?,?,?,?)');$q->execute([$name,$category,$kind,$qty,$low]);$id=(int)db()->lastInsertId();if($qty)db()->prepare('INSERT INTO inventory_transaction(equipment_id,user_id,change_qty,reason) VALUES (?,?,?,?)')->execute([$id,$uid,$qty,'Opening balance']);v2_event($uid,'inventory_added','Equipment added: '.$name,'Initial quantity '.$qty);db()->commit();redirect('inventory.php');
  }
  if($action==='request'){
   $id=(int)($_POST['equipment_id']??0);$qty=(int)($_POST['quantity']??0);$reason=trim($_POST['reason']??'');if($qty<1||$qty>10000||!$reason||strlen($reason)>1000)throw new RuntimeException('Enter a valid quantity and reason.');
   $q=db()->prepare('SELECT equipment_id FROM equipment WHERE equipment_id=?');$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('Equipment not found.');
   db()->prepare('INSERT INTO equipment_request(equipment_id,requested_by,quantity,reason) VALUES (?,?,?,?)')->execute([$id,$uid,$qty,$reason]);v2_event($uid,'inventory_request','Equipment requested','Item #'.$id.' · Qty '.$qty);redirect('inventory.php');
  }
  if($action==='review'){
   if(!$admin)v2_error('Admin only.',403);$rid=(int)($_POST['request_id']??0);$decision=$_POST['decision']??'';if(!in_array($decision,['approved','rejected'],true))throw new RuntimeException('Invalid decision.');
   db()->beginTransaction();$q=db()->prepare('SELECT r.*,e.quantity AS available FROM equipment_request r JOIN equipment e ON e.equipment_id=r.equipment_id WHERE r.request_id=? FOR UPDATE');$q->execute([$rid]);$r=$q->fetch();if(!$r||$r['status']!=='pending')throw new RuntimeException('Request already handled.');
   if($decision==='approved'){
    $q=db()->prepare('UPDATE equipment SET quantity=quantity-? WHERE equipment_id=? AND quantity>=?');$q->execute([$r['quantity'],$r['equipment_id'],$r['quantity']]);if(!$q->rowCount())throw new RuntimeException('Insufficient stock.');
    db()->prepare('INSERT INTO inventory_transaction(equipment_id,user_id,request_id,change_qty,reason) VALUES (?,?,?,?,?)')->execute([$r['equipment_id'],$uid,$rid,-(int)$r['quantity'],'Approved equipment request']);
   }
   db()->prepare('UPDATE equipment_request SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE request_id=?')->execute([$decision,$uid,$rid]);v2_event($uid,'inventory_review','Equipment request '.$decision,'Request #'.$rid);db()->commit();redirect('inventory.php');
  }
  if($action==='adjust'){
   if(!$admin)v2_error('Admin only.',403);
   $id=(int)($_POST['equipment_id']??0);$change=(int)($_POST['change_qty']??0);$reason=trim($_POST['reason']??'');
   if(!$change||abs($change)>100000||strlen($reason)<4||strlen($reason)>255)throw new RuntimeException('Provide a valid stock adjustment and reason.');
   db()->beginTransaction();$q=db()->prepare('UPDATE equipment SET quantity=quantity+? WHERE equipment_id=? AND quantity+?>=0');$q->execute([$change,$id,$change]);if(!$q->rowCount())throw new RuntimeException('Item missing or insufficient stock.');
   db()->prepare('INSERT INTO inventory_transaction(equipment_id,user_id,change_qty,reason) VALUES (?,?,?,?)')->execute([$id,$uid,$change,$reason]);v2_event($uid,'inventory_adjustment','Stock adjusted','Equipment #'.$id.' · '.$change.' · '.$reason);db()->commit();redirect('inventory.php');
  }
  if($action==='return_request'){
   $rid=(int)($_POST['request_id']??0);$qty=(int)($_POST['quantity']??0);$notes=trim($_POST['notes']??'');
   db()->beginTransaction();$q=db()->prepare('SELECT r.*,e.kind FROM equipment_request r JOIN equipment e ON e.equipment_id=r.equipment_id WHERE r.request_id=? FOR UPDATE');$q->execute([$rid]);$r=$q->fetch();
   if(!$r||$r['kind']!=='returnable'||$r['status']!=='approved'||(!$admin&&(int)$r['requested_by']!==$uid)||$qty<1)throw new RuntimeException('Return is not allowed.');
   $q=db()->prepare("SELECT COALESCE(SUM(CASE WHEN status='accepted' THEN quantity ELSE 0 END),0) accepted,COALESCE(SUM(CASE WHEN status='pending' THEN quantity ELSE 0 END),0) pending FROM equipment_return WHERE request_id=?");$q->execute([$rid]);$counts=$q->fetch();
   if($qty+(int)$counts['accepted']+(int)$counts['pending']>(int)$r['quantity'])throw new RuntimeException('Return quantity exceeds outstanding issued quantity.');
   db()->prepare('INSERT INTO equipment_return(request_id,requested_by,quantity,notes) VALUES (?,?,?,?)')->execute([$rid,$uid,$qty,substr($notes,0,500)]);v2_event($uid,'inventory_return','Equipment return submitted','Request #'.$rid.' · Qty '.$qty);db()->commit();redirect('inventory.php');
  }
  if($action==='review_return'){
   if(!$admin)v2_error('Admin only.',403);$id=(int)($_POST['return_id']??0);$decision=$_POST['decision']??'';if(!in_array($decision,['accepted','rejected'],true))throw new RuntimeException('Invalid return decision.');
   db()->beginTransaction();$q=db()->prepare('SELECT er.*,r.equipment_id,r.quantity AS issued,r.status AS request_status FROM equipment_return er JOIN equipment_request r ON r.request_id=er.request_id WHERE er.return_id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();if(!$r||$r['status']!=='pending'||$r['request_status']!=='approved')throw new RuntimeException('Return not pending.');
   if($decision==='accepted'){
    db()->prepare('UPDATE equipment SET quantity=quantity+? WHERE equipment_id=?')->execute([$r['quantity'],$r['equipment_id']]);
    db()->prepare('INSERT INTO inventory_transaction(equipment_id,user_id,request_id,change_qty,reason) VALUES (?,?,?,?,?)')->execute([$r['equipment_id'],$uid,$r['request_id'],$r['quantity'],'Verified equipment return']);
   }
   db()->prepare('UPDATE equipment_return SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE return_id=?')->execute([$decision,$uid,$id]);
   $q=db()->prepare("SELECT COALESCE(SUM(quantity),0) FROM equipment_return WHERE request_id=? AND status='accepted'");$q->execute([$r['request_id']]);if((int)$q->fetchColumn()===(int)$r['issued'])db()->prepare("UPDATE equipment_request SET status='returned' WHERE request_id=?")->execute([$r['request_id']]);
   v2_event($uid,'inventory_return','Equipment return '.$decision,'Return #'.$id);db()->commit();redirect('inventory.php');
  }
  throw new RuntimeException('Unknown action.');
 }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$errors[]=$e instanceof PDOException?'Database operation failed.':$e->getMessage();}
}
$items=db()->query('SELECT * FROM equipment ORDER BY name')->fetchAll();$sql='SELECT r.*,e.name,u.first_name,u.last_name FROM equipment_request r JOIN equipment e ON e.equipment_id=r.equipment_id JOIN user u ON u.user_id=r.requested_by';if(!$admin)$sql.=' WHERE r.requested_by=?';$sql.=' ORDER BY r.request_id DESC LIMIT 100';$q=db()->prepare($sql);$q->execute($admin?[]:[$uid]);$requests=$q->fetchAll();
$returnSql='SELECT er.*,r.equipment_id,e.name,u.first_name,u.last_name FROM equipment_return er JOIN equipment_request r ON r.request_id=er.request_id JOIN equipment e ON e.equipment_id=r.equipment_id JOIN user u ON u.user_id=er.requested_by';if(!$admin)$returnSql.=' WHERE er.requested_by=?';$returnSql.=' ORDER BY er.return_id DESC LIMIT 100';$q=db()->prepare($returnSql);$q->execute($admin?[]:[$uid]);$returns=$q->fetchAll();
$tx=$admin?db()->query('SELECT t.*,e.name FROM inventory_transaction t JOIN equipment e ON e.equipment_id=t.equipment_id ORDER BY t.transaction_id DESC LIMIT 60')->fetchAll():[];
$page_title='Equipment & Supplies';require __DIR__.'/includes/header.php';?>
<h1>Equipment &amp; Supplies</h1><p>Stock availability, responder requests, and Admin approvals.</p>
<?php foreach($errors as $err):?><p class="v2-error"><?=e($err)?></p><?php endforeach;?>
<?php if($admin):?><section class="v2-card"><h2>Add equipment</h2><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="add"><input name="name" placeholder="Equipment name" required><input name="category" placeholder="Category" required><select name="kind"><option value="consumable">Consumable</option><option value="returnable">Returnable</option></select><input name="quantity" type="number" min="0" placeholder="Quantity" required><input name="low" type="number" min="0" placeholder="Low stock threshold" value="0" required><button>Add Item</button></form></section><?php endif;?>
<div class="v2-grid"><section class="v2-card"><h2>Available Equipment</h2><table><thead><tr><th>Item</th><th>Category</th><th>Stock</th></tr></thead><tbody><?php foreach($items as $i):?><tr><td><?=e($i['name'])?></td><td><?=e($i['category'])?></td><td><?= (int)$i['quantity']?><?= (int)$i['quantity']<=(int)$i['low_stock_at']?' ⚠ Low':''?></td></tr><?php endforeach;?></tbody></table>
<h2>Request Equipment</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="request"><label>Item<select name="equipment_id" required><?php foreach($items as $i):?><option value="<?= (int)$i['equipment_id']?>"><?=e($i['name'])?></option><?php endforeach;?></select></label><label>Quantity<input type="number" name="quantity" min="1" required></label><label>Reason<textarea name="reason" maxlength="1000" required></textarea></label><button>Submit Request</button></form></section>
<section class="v2-card"><h2><?= $admin?'All Equipment Requests':'My Equipment Requests'?></h2><?php foreach($requests as $r):?><article class="v2-entry"><div class="v2-between"><strong><?=e($r['name'])?> · <?= (int)$r['quantity']?></strong><span class="v2-pill"><?=e($r['status'])?></span></div><p><?=e($r['reason'])?></p><small><?=e($r['first_name'].' '.$r['last_name'])?> · <?=e($r['created_at'])?></small><?php if($admin&&$r['status']==='pending'):?><?php foreach(['approved'=>'Approve & Issue','rejected'=>'Reject'] as $value=>$label):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="review"><input type="hidden" name="request_id" value="<?= (int)$r['request_id']?>"><input type="hidden" name="decision" value="<?=e($value)?>"><button><?=e($label)?></button></form><?php endforeach;?><?php endif;?></article><?php endforeach;?></section></div>
<?php if($admin):?><section class="v2-card"><h2>Stock In / Out and Corrections</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="adjust"><label>Equipment<select name="equipment_id"><?php foreach($items as $i):?><option value="<?=(int)$i['equipment_id']?>"><?=e($i['name'])?></option><?php endforeach;?></select></label><label>Quantity change (negative for stock out)<input type="number" name="change_qty" required></label><label>Reason<input name="reason" required minlength="4" maxlength="255"></label><button>Record Stock Adjustment</button></form></section><?php endif;?>
<section class="v2-card"><h2>Equipment Returns</h2><?php foreach($requests as $r):?><?php if($r['status']==='approved'):?><?php foreach($items as $i):?><?php if((int)$i['equipment_id']===(int)$r['equipment_id']&&$i['kind']==='returnable'):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="return_request"><input type="hidden" name="request_id" value="<?=(int)$r['request_id']?>"><span><?=e($r['name'])?> (issue #<?=(int)$r['request_id']?>)</span><input type="number" min="1" max="<?=(int)$r['quantity']?>" name="quantity" placeholder="Qty to return" required><input name="notes" placeholder="Condition / notes" maxlength="500"><button>Request Return</button></form><?php endif;?><?php endforeach;?><?php endif;?><?php endforeach;?>
<?php foreach($returns as $r):?><article class="v2-entry"><strong><?=e($r['name'])?> · Qty <?=(int)$r['quantity']?></strong><p><?=e($r['first_name'].' '.$r['last_name'])?> · <?=e($r['notes'])?> · <?=e($r['status'])?></p><?php if($admin&&$r['status']==='pending'):?><?php foreach(['accepted'=>'Confirm Receipt','rejected'=>'Reject Return'] as $d=>$label):?><form method="post" class="v2-inline"><?=csrf_field()?><input type="hidden" name="action" value="review_return"><input type="hidden" name="return_id" value="<?=(int)$r['return_id']?>"><input type="hidden" name="decision" value="<?=e($d)?>"><button><?=e($label)?></button></form><?php endforeach;?><?php endif;?></article><?php endforeach;?></section>
<?php if($admin):?><section class="v2-card"><h2>Stock Transaction History</h2><?php foreach($tx as $t):?><p><?=e($t['created_at'])?> · <?=e($t['name'])?> · <?= (int)$t['change_qty']?> · <?=e($t['reason'])?></p><?php endforeach;?></section><?php endif;?>
<?php require __DIR__.'/includes/footer.php';?>
