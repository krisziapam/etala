<?php
require_once __DIR__.'/includes/v2.php';$user=require_login();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$month=$_GET['month']??date('Y-m');if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)){http_response_code(400);echo json_encode(['error'=>'Invalid month']);exit;}
$start=$month.'-01 00:00:00';$end=date('Y-m-d H:i:s',strtotime($start.' +1 month'));
$sql='SELECT r.report_id,r.incident_datetime,t.name type_name,t.category,l.address FROM incident_report r JOIN incident_type t ON t.type_id=r.type_id JOIN location l ON l.location_id=r.location_id WHERE r.deleted_at IS NULL AND r.incident_datetime>=? AND r.incident_datetime<?';$args=[$start,$end];
if($user['role']!=='admin'){$sql.=' AND (r.reported_by=? OR EXISTS (SELECT 1 FROM incident_assignment a WHERE a.report_id=r.report_id AND a.assigned_user_id=?))';$args[]=$user['user_id'];$args[]=$user['user_id'];}
$sql.=' ORDER BY r.incident_datetime';$q=db()->prepare($sql);$q->execute($args);echo json_encode(['items'=>$q->fetchAll()],JSON_INVALID_UTF8_SUBSTITUTE);
