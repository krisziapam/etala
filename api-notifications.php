<?php
require_once __DIR__.'/includes/v2.php';$user=require_login();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$events=v2_events($user,200);$q=db()->prepare('SELECT event_id FROM notification_read WHERE user_id=?');$q->execute([(int)$user['user_id']]);$read=array_fill_keys(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)),true);$unread=0;foreach($events as $ev)if(!isset($read[(int)$ev['event_id']]))$unread++;
echo json_encode(['unread'=>$unread,'latest_event_id'=>$events?(int)$events[0]['event_id']:0]);
