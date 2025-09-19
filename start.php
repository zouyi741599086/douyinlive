<?php
require_once __DIR__ . '/vendor/autoload.php';
use app\DouyinLive;

//$fetcher = new DouyinLive(null, '7550856622639860506');
$live_id = "";
$room_id = "7551660701779708691";
$cookie  = "";

$fetcher = new DouyinLive($live_id, $room_id, $cookie);
$fetcher->start();