<?php
require '../config/db.php';
require 'functions.php';

$stmt = $pdo->query("SELECT id, room_name FROM rooms");
$rooms = $stmt->fetchAll();

foreach ($rooms as $room) {
    $result = getRoomStatus($room['id'], $pdo);
    echo $room['room_name'] . " → " . $result['status'] . "\n";
    if ($result['usage']) {
        print_r($result['usage']);
    }
    echo "---\n";
}