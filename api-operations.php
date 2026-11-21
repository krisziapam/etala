<?php
require_once __DIR__.'/includes/v2.php';
$user=require_login();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
 $today=date('Y-m-d');$events=array_values(array_filter(v2_events($user,160),fn($r)=>substr($r['created_at'],0,10)===$today));
 $q=db()->query("SELECT COUNT(*) FROM backup_request WHERE status IN ('waiting','partially_accepted')");$pending=(int)$q->fetchColumn();
 $q=db()->query("SELECT COUNT(*) FROM incident_report WHERE deleted_at IS NULL AND status='ongoing'");$ongoing=(int)$q->fetchColumn();
 echo json_encode(['ok'=>true,'events'=>$events,'counts'=>['pending_backups'=>$pending,'ongoing_incidents'=>$ongoing],'checked_at'=>date('Y-m-d H:i:s')],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'message'=>'Unable to retrieve updates.']);}
