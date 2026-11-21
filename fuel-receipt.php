<?php
require_once __DIR__.'/includes/v2.php';
$user=require_login();
if($user['role']!=='admin')v2_error('Admin only',403);
$id=(int)($_GET['id']??0);
$q=db()->prepare('SELECT receipt_path FROM fuel_approval WHERE fuel_id=?');$q->execute([$id]);$path=$q->fetchColumn();
if(!$path||!preg_match('~^uploads/fuel/[a-f0-9]{32}\.(jpg|png|pdf)$~',$path))v2_error('Receipt not found',404);
$full=__DIR__.'/'.$path;if(!is_file($full))v2_error('Receipt not found',404);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($full);
if(!in_array($mime,['image/jpeg','image/png','application/pdf'],true))v2_error('Invalid receipt',403);
header('Content-Type: '.$mime);header('Content-Disposition: inline; filename="fuel-receipt.'.pathinfo($full,PATHINFO_EXTENSION).'"');header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($full);
