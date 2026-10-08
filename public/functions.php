<!-- function.php -->
<?php

function getRoomStatus($roomId, $pdo)
{
    // 1. Check if the room is currently being used
    $stmt = $pdo->prepare("
        SELECT *
        FROM room_usage
        WHERE room_id = :room_id
        AND NOW() >= start_time
        AND NOW() < end_time
        ORDER BY start_time ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':room_id' => $roomId
    ]);

    $current = $stmt->fetch();

    // 2. Get the next scheduled usage
    $stmt = $pdo->prepare("
        SELECT *
        FROM room_usage
        WHERE room_id = :room_id
        AND start_time > NOW()
        ORDER BY start_time ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':room_id' => $roomId
    ]);

    $nextUsage = $stmt->fetch();

    // 3. If currently in use
    if ($current) {

        return [
            'status' => 'in_use',
            'usage' => $current,
            'next_usage' => $nextUsage
        ];
    }

    // 4. If next usage starts within 60 minutes
    if ($nextUsage) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM room_usage
            WHERE room_id = :room_id
            AND start_time > NOW()
            AND start_time <= DATE_ADD(NOW(), INTERVAL 60 MINUTE)
            ORDER BY start_time ASC
            LIMIT 1
        ");

        $stmt->execute([
            ':room_id' => $roomId
        ]);

        $upcoming = $stmt->fetch();

        if ($upcoming) {

            return [
                'status' => 'upcoming',
                'usage' => $upcoming,
                'next_usage' => $nextUsage
            ];
        }
    }

    // 5. Otherwise vacant
    return [
        'status' => 'vacant',
        'usage' => null,
        'next_usage' => $nextUsage
    ];
}


function hasRoomOverlap($roomId, $startDateTime, $endDateTime, $pdo)
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM room_usage
        WHERE room_id = :room_id
        AND :start_time < end_time
        AND :end_time > start_time
        LIMIT 1
    ");

    $stmt->execute([
        ':room_id' => $roomId,
        ':start_time' => $startDateTime,
        ':end_time' => $endDateTime
    ]);

    return $stmt->fetch();
}