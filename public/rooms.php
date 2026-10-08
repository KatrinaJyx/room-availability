<?php
session_start();

require '../config/db.php';
require 'functions.php';

function statusColor($status) {
    return match ($status) {
        'vacant' => 'status-vacant',
        'upcoming' => 'status-upcoming',
        'in_use' => 'status-in-use',
        default => 'status-vacant'
    };
}

function statusIcon($status) {
    return match($status) {
        'vacant' => '<img src="../assets/image/door.png" alt="Vacant">',
        'upcoming' => '<img src="../assets/image/open.png" alt="Upcoming">',
        'in_use' => '<img src="../assets/image/close.png" alt="In Use">',
        default => '<img src="../assets/image/door.png" alt="Vacant">',
    };
}

function statusLabel($status) {
    return match ($status) {
        'vacant' => 'Vacant',
        'upcoming' => 'Upcoming Use',
        'in_use' => 'Currently In Use',
        default => 'Vacant'
    };
}

$success = '';

if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Get rooms
$stmt = $pdo->query("
    SELECT *
    FROM rooms
    ORDER BY room_name ASC
");

$rooms = $stmt->fetchAll();

$roomData = [];

foreach ($rooms as $room) {
    $roomData[] = [
        'room' => $room,
        'result' => getRoomStatus($room['id'], $pdo)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Availability</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/roomstyle.css">
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="logo">Smart Campus</a>
    <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="rooms.php">Room Availability</a>
        <a href="../admin/login.php">Admin</a>
    </div>
</nav>

<main class="page">

    <div class="topbar">
        <h1>Room Availability</h1>
        <p>Click a room to see details or check in.</p>
    </div>

    <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="room-grid">
        <?php foreach ($roomData as $entry): ?>
            <?php
            $room = $entry['room'];
            $status = $entry['result']['status'];
            $usage = $entry['result']['usage'];
            $nextUsage = $entry['result']['next_usage'];
            ?>

            <div class="room-card <?= statusColor($status) ?>" data-room-id="<?= $room['id'] ?>">
                <div class="room-icon">
                    <?= statusIcon($status) ?>
                </div>

                <h3><?= htmlspecialchars($room['room_name']) ?></h3>

                <p class="room-building">
                    <?= htmlspecialchars($room['building']) ?> &middot; Capacity: <?= htmlspecialchars($room['capacity']) ?>
                </p>

                <span class="status-badge"><?= statusLabel($status) ?></span>

                <?php if ($usage): ?>
                    <div class="usage-info">
                        <p><strong><?= htmlspecialchars($usage['user_name']) ?></strong></p>
                        <p><?= ucfirst(htmlspecialchars($usage['user_type'])) ?> &middot; <?= htmlspecialchars($usage['section']) ?></p>
                        <p><?= htmlspecialchars($usage['purpose']) ?></p>
                        <p><?= date('g:i A', strtotime($usage['start_time'])) ?> - <?= date('g:i A', strtotime($usage['end_time'])) ?></p>
                    </div>
                <?php elseif ($nextUsage): ?>
                    <div class="usage-info">
                        <p><strong>Next scheduled use</strong></p>
                        <p><?= htmlspecialchars($nextUsage['user_name']) ?></p>
                        <p><?= date('M d, g:i A', strtotime($nextUsage['start_time'])) ?></p>
                    </div>
                <?php else: ?>
                    <div class="usage-info">
                        <p>Click to check in</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- ROOM MODAL -->
<div class="modal-overlay" id="roomModal">
    <div class="modal-box">
        <button class="modal-close" id="modalClose" aria-label="Close">&times;</button>
        <div id="modalContent"></div>
    </div>
</div>

<script>
const rooms = <?= json_encode(
    array_map(function ($entry) {
        return [
            'id' => $entry['room']['id'],
            'name' => $entry['room']['room_name'],
            'building' => $entry['room']['building'],
            'capacity' => $entry['room']['capacity'],
            'status' => $entry['result']['status'],
            'usage' => $entry['result']['usage'],
            'next_usage' => $entry['result']['next_usage']
        ];
    }, $roomData),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatTime(dateString) {
    const date = new Date(dateString.replace(' ', 'T'));
    return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

// ROOM CARD CLICK
document.querySelectorAll('.room-card').forEach(card => {
    card.addEventListener('click', function () {
        const roomId = card.dataset.roomId;
        const room = rooms.find(r => String(r.id) === String(roomId));
        if (room) {
            showRoomDetail(room);
        }
    });
});

// SHOW ROOM
function showRoomDetail(room) {
    const modal = document.getElementById('roomModal');
    const content = document.getElementById('modalContent');

    // VACANT ROOM
    if (room.status === 'vacant') {
        content.innerHTML = `
            <h2>${escapeHtml(room.name)}</h2>
            <p>This room is currently vacant.</p>
            <p><strong>Building:</strong> ${escapeHtml(room.building)}</p>
            <p><strong>Capacity:</strong> ${escapeHtml(room.capacity)}</p>
            <h3>Who are you?</h3>
            <div class="checkin-options">
                <a class="checkin-option" href="javascript:void(0)" onclick="goToTeacher(${room.id})">
                    <strong>Teacher</strong>
                    <span>Check in using Employee ID</span>
                </a>
                <a class="checkin-option" href="javascript:void(0)" onclick="goToStudent(${room.id})">
                    <strong>Student</strong>
                    <span>Check authorization first</span>
                </a>
            </div>
        `;
    }
    // UPCOMING ROOM
    else if (room.status === 'upcoming') {
        let html = `
            <h2>${escapeHtml(room.name)}</h2>
            <p>This room has an upcoming scheduled use.</p>
        `;

        if (room.usage) {
            html += `
                <div class="modal-usage">
                    <h3>Upcoming usage</h3>
                    <p><strong>User:</strong> ${escapeHtml(room.usage.user_name)}</p>
                    <p><strong>User type:</strong> ${escapeHtml(room.usage.user_type)}</p>
                    <p><strong>Section:</strong> ${escapeHtml(room.usage.section)}</p>
                    <p><strong>Purpose:</strong> ${escapeHtml(room.usage.purpose)}</p>
                    <p><strong>Start:</strong> ${formatTime(room.usage.start_time)}</p>
                    <p><strong>End:</strong> ${formatTime(room.usage.end_time)}</p>
                </div>
            `;
        }
        content.innerHTML = html;
    }
    // IN USE
    else {
        let html = `
            <h2>${escapeHtml(room.name)}</h2>
            <p>This room is currently being used.</p>
        `;

        if (room.usage) {
            html += `
                <div class="modal-usage">
                    <h3>Current usage</h3>
                    <p><strong>User:</strong> ${escapeHtml(room.usage.user_name)}</p>
                    <p><strong>User type:</strong> ${escapeHtml(room.usage.user_type)}</p>
                    <p><strong>Section:</strong> ${escapeHtml(room.usage.section)}</p>
                    <p><strong>Purpose:</strong> ${escapeHtml(room.usage.purpose)}</p>
                    <p><strong>Start:</strong> ${formatTime(room.usage.start_time)}</p>
                    <p><strong>End:</strong> ${formatTime(room.usage.end_time)}</p>
                </div>
            `;
        }
        content.innerHTML = html;
    }

    modal.classList.add('active');
}

// GO TO TEACHER
function goToTeacher(roomId) {
    window.location.href = '../checkin/teacher.php?room_id=' + encodeURIComponent(roomId);
}

// GO TO STUDENT
function goToStudent(roomId) {
    window.location.href = '../checkin/student.php?room_id=' + encodeURIComponent(roomId);
}

// CLOSE MODAL
document.getElementById('modalClose').addEventListener('click', function () {
    document.getElementById('roomModal').classList.remove('active');
});

document.getElementById('roomModal').addEventListener('click', function (event) {
    if (event.target.id === 'roomModal') {
        document.getElementById('roomModal').classList.remove('active');
    }
});

// Refresh every 30 seconds, but never while a room popup is open
setInterval(function () {
    if (!document.getElementById('roomModal').classList.contains('active')) {
        location.reload();
    }
}, 30000);
</script>

</body>
</html>