<?php
session_start();

require '../config/db.php';
require '../public/functions.php';

$error = '';
$teacher = null;
$selectedRoom = null;

// Preserve room_id from GET or POST
$selectedRoomId = 0;
if (isset($_POST['room_id'])) {
    $selectedRoomId = (int) $_POST['room_id'];
} elseif (isset($_GET['room_id'])) {
    $selectedRoomId = (int) $_GET['room_id'];
}

// Fetch selected room details
if ($selectedRoomId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM rooms
        WHERE id = :room_id
        LIMIT 1
    ");
    $stmt->execute([':room_id' => $selectedRoomId]);
    $selectedRoom = $stmt->fetch();
}

// STEP 1: LOOKUP TEACHER
if (isset($_POST['lookup'])) {
    $employeeId = trim($_POST['employee_id'] ?? '');

    if (!empty($employeeId)) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM teachers
            WHERE employee_id = :employee_id
            LIMIT 1
        ");
        $stmt->execute([':employee_id' => $employeeId]);
        $teacher = $stmt->fetch();

        if (!$teacher) {
            $error = "No teacher found with that ID.";
        }
    } else {
        $error = "Please enter an Employee ID.";
    }
}

// STEP 2: CHECK IN
if (isset($_POST['checkin'])) {
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $usageDate = $_POST['usage_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';
    $section   = trim($_POST['section'] ?? '');
    $purpose   = trim($_POST['purpose'] ?? '');

    // Re-fetch teacher to maintain view state if validation fails
    $stmt = $pdo->prepare("
        SELECT *
        FROM teachers
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $teacherId]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        $error = "Teacher could not be found.";
    } elseif (!$selectedRoom) {
        $error = "No room was selected.";
    } else {
        $startDateTime = $usageDate . ' ' . $startTime . ':00';
        $endDateTime   = $usageDate . ' ' . $endTime . ':00';

        if (strtotime($endDateTime) <= strtotime($startDateTime)) {
            $error = "End time must be later than start time.";
        } elseif (strtotime($startDateTime) < time()) {
            $error = "Start time cannot be in the past.";
        } else {
            // Check overlap
            $overlap = hasRoomOverlap($selectedRoomId, $startDateTime, $endDateTime, $pdo);

            if ($overlap) {
                $error = "This room already has a booking during the selected time.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO room_usage (
                        room_id,
                        user_type,
                        teacher_id,
                        student_id_number,
                        user_name,
                        section,
                        purpose,
                        start_time,
                        end_time
                    ) VALUES (
                        :room_id,
                        'teacher',
                        :teacher_id,
                        NULL,
                        :user_name,
                        :section,
                        :purpose,
                        :start_time,
                        :end_time
                    )
                ");

                $stmt->execute([
                    ':room_id'    => $selectedRoomId,
                    ':teacher_id' => $teacher['id'],
                    ':user_name'  => $teacher['full_name'],
                    ':section'    => $section,
                    ':purpose'    => $purpose,
                    ':start_time' => $startDateTime,
                    ':end_time'   => $endDateTime
                ]);

                $_SESSION['flash_success'] = "Room usage registered successfully.";
                header("Location: ../public/rooms.php");
                exit;
            }
        }
    }
}

// ======================================
// VACANT ROOMS (for the room dropdown)
// ======================================
$vacantRooms = [];
if (!$selectedRoom) {
    $allRooms = $pdo->query("
        SELECT *
        FROM rooms
        ORDER BY room_name ASC
    ")->fetchAll();

    foreach ($allRooms as $r) {
        if (getRoomStatus($r['id'], $pdo)['status'] === 'vacant') {
            $vacantRooms[] = $r;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Room Check-In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/formstyle.css">
</head>
<body>

<nav class="navbar">
    <a href="../public/index.php" class="logo">Smart Campus</a>
    <div class="nav-links">
        <a href="../public/index.php">Home</a>
        <a href="../public/rooms.php">Room Availability</a>
        <a href="../admin/login.php">Admin</a>
    </div>
</nav>

<main class="form-page">
<div class="form-container">
    <h2>Teacher room check-in</h2>

    <?php if ($error): ?>
        <p class="error-text"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!$selectedRoom): ?>
        <p class="form-subtitle">Choose a vacant room to continue.</p>

        <?php if ($vacantRooms): ?>
            <form method="GET">
                <label for="room_id">Room</label>
                <select name="room_id" id="room_id" required>
                    <option value="" disabled selected>Select a room</option>
                    <?php foreach ($vacantRooms as $r): ?>
                        <option value="<?= (int) $r['id'] ?>">
                            <?= htmlspecialchars($r['room_name']) ?> &middot; <?= htmlspecialchars($r['building']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Continue</button>
            </form>
        <?php else: ?>
            <p>There are no vacant rooms right now.</p>
        <?php endif; ?>

    <?php elseif (!$teacher): ?>
        <div class="selected-room-box">
            <strong>Selected room:</strong>
            <?= htmlspecialchars($selectedRoom['room_name']) ?>
        </div>

        <form method="POST">
            <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">

            <label for="employee_id">Employee ID</label>
            <input
                type="text"
                id="employee_id"
                name="employee_id"
                required
                placeholder="Example: T-001"
                value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>"
            >

            <button type="submit" name="lookup" value="1">Continue</button>
        </form>

    <?php else: ?>
        <div class="selected-room-box">
            <strong>Selected room:</strong>
            <?= htmlspecialchars($selectedRoom['room_name']) ?>
        </div>

        <p>Welcome, <strong><?= htmlspecialchars($teacher['full_name']) ?></strong></p>
        <p class="form-subtitle">Department: <?= htmlspecialchars($teacher['department']) ?></p>

        <form method="POST">
            <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
            <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">

            <label for="usage_date">Date</label>
            <input
                type="date"
                id="usage_date"
                name="usage_date"
                value="<?= htmlspecialchars($_POST['usage_date'] ?? date('Y-m-d')) ?>"
                min="<?= date('Y-m-d') ?>"
                required
            >

            <label for="start_time">Start time</label>
            <input
                type="time"
                id="start_time"
                name="start_time"
                value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                required
            >

            <label for="end_time">End time</label>
            <input
                type="time"
                id="end_time"
                name="end_time"
                value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                required
            >

            <label for="section">Section</label>
            <input
                type="text"
                id="section"
                name="section"
                value="<?= htmlspecialchars($_POST['section'] ?? '') ?>"
                required
                placeholder="Example: BSIS 2A"
            >

            <label for="purpose">Purpose</label>
            <select name="purpose" id="purpose" required>
                <?php
                $options = ['Class', 'Practice', 'Bootcamp', 'Meeting', 'Other'];
                $selectedPurpose = $_POST['purpose'] ?? 'Class';
                foreach ($options as $option):
                ?>
                    <option value="<?= $option ?>" <?= $selectedPurpose === $option ? 'selected' : '' ?>>
                        <?= $option ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="checkin" value="1">Register room usage</button>
        </form>
    <?php endif; ?>

    <a href="../public/rooms.php" class="back-link">&larr; Back to Room Availability</a>
</div>
</main>

</body>
</html>