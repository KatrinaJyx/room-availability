<?php

session_start();

require '../config/db.php';
require '../public/functions.php';

$error = '';
$success = '';

$authorization = null;
$selectedRoom = null;

// Keeps what the student typed if validation fails
function oldValue($key, $default = '')
{
    return htmlspecialchars($_POST[$key] ?? $default);
}

// ======================================
// FLASH MESSAGE (authorization request sent)
// ======================================
if (isset($_SESSION['student_success'])) {
    $success = $_SESSION['student_success'];
    unset($_SESSION['student_success']);
}

// ======================================
// GET ROOM ID
// ======================================
$selectedRoomId = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;

// ======================================
// IF POST, GET ROOM ID
// ======================================
if (isset($_POST['lookup_authorized'])) {
    $selectedRoomId = (int) $_POST['room_id'];
}

if (isset($_POST['checkin'])) {
    $selectedRoomId = (int) $_POST['room_id'];
}

if (isset($_POST['send_request'])) {
    $selectedRoomId = (int) $_POST['room_id'];
}

// ======================================
// GET ROOM
// ======================================
if ($selectedRoomId > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM rooms
        WHERE id = :room_id
        LIMIT 1
    ");

    $stmt->execute([
        ':room_id' => $selectedRoomId
    ]);

    $selectedRoom = $stmt->fetch();
}

// ======================================
// CHECK EXISTING AUTHORIZATION
// ======================================
if (isset($_POST['lookup_authorized'])) {
    $studentId = trim($_POST['student_id_number']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM authorized_students
        WHERE student_id_number = :student_id
          AND room_id = :room_id
          AND status = 'approved'
          AND valid_until >= NOW()
        ORDER BY valid_from ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':student_id' => $studentId,
        ':room_id'    => $selectedRoomId
    ]);

    $authorization = $stmt->fetch();

    if (!$authorization) {
        $error = "No active authorization was found for this student and room.";
    }
}

// ======================================
// REGISTER AUTHORIZED STUDENT ROOM USE
// ======================================
if (isset($_POST['checkin'])) {
    $studentId       = trim($_POST['student_id_number']);
    $authorizationId = (int) $_POST['authorization_id'];
    $section         = trim($_POST['section']);
    $purpose         = trim($_POST['purpose']);

    // Re-check authorization on server
    $stmt = $pdo->prepare("
        SELECT *
        FROM authorized_students
        WHERE id = :id
          AND student_id_number = :student_id
          AND room_id = :room_id
          AND status = 'approved'
          AND valid_until >= NOW()
        LIMIT 1
    ");

    $stmt->execute([
        ':id'         => $authorizationId,
        ':student_id' => $studentId,
        ':room_id'    => $selectedRoomId
    ]);

    $authorization = $stmt->fetch();

    if (!$authorization) {
        $error = "Your authorization is invalid or has expired.";
    } else {
        // Use the time the student was authorized for
        // (starting now if the authorized window has already begun)
        $startTimestamp = max(strtotime($authorization['valid_from']), time());
        $endTimestamp   = strtotime($authorization['valid_until']);
        $startDateTime  = date('Y-m-d H:i:s', $startTimestamp);
        $endDateTime    = date('Y-m-d H:i:s', $endTimestamp);

        if ($purpose === '') {
            $error = "Please enter the purpose of your room usage.";
        } elseif ($endTimestamp <= $startTimestamp) {
            $error = "Your authorized time has already ended.";
        } else {
            // Check room overlap
            $overlap = hasRoomOverlap(
                $selectedRoomId,
                $startDateTime,
                $endDateTime,
                $pdo
            );

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
                        'student',
                        NULL,
                        :student_id,
                        :user_name,
                        :section,
                        :purpose,
                        :start_time,
                        :end_time
                    )
                ");

                $stmt->execute([
                    ':room_id'    => $selectedRoomId,
                    ':student_id' => $studentId,
                    ':user_name'  => $authorization['full_name'],
                    ':section'    => $section,
                    ':purpose'    => $purpose,
                    ':start_time' => $startDateTime,
                    ':end_time'   => $endDateTime
                ]);

                // rooms.php reads flash_success
                $_SESSION['flash_success'] = "Room usage registered successfully.";

                header("Location: ../public/rooms.php");
                exit;
            }
        }
    }
}

// ======================================
// SUBMIT AUTHORIZATION REQUEST
// ======================================
if (isset($_POST['send_request'])) {
    $studentId   = trim($_POST['student_id_number']);
    $fullName    = trim($_POST['full_name']);
    $section     = trim($_POST['section']);
    $purpose     = trim($_POST['purpose']);
    $requestDate = $_POST['request_date'];
    $startTime   = $_POST['start_time'];
    $endTime     = $_POST['end_time'];

    $startDateTime = $requestDate . ' ' . $startTime . ':00';
    $endDateTime   = $requestDate . ' ' . $endTime . ':00';

    if (!$selectedRoom) {
        $error = "No room was selected.";
    } elseif ($purpose === '') {
        $error = "Please enter the purpose of your room usage.";
    } elseif (strtotime($endDateTime) <= strtotime($startDateTime)) {
        $error = "End time must be later than start time.";
    } elseif (strtotime($startDateTime) < time()) {
        $error = "The requested start time cannot be in the past.";
    } else {
        // Check if there is already a pending request
        $stmt = $pdo->prepare("
            SELECT *
            FROM authorized_students
            WHERE student_id_number = :student_id
              AND room_id = :room_id
              AND status = 'pending'
              AND valid_from = :valid_from
              AND valid_until = :valid_until
            LIMIT 1
        ");

        $stmt->execute([
            ':student_id'  => $studentId,
            ':room_id'     => $selectedRoomId,
            ':valid_from'  => $startDateTime,
            ':valid_until' => $endDateTime
        ]);

        $existingRequest = $stmt->fetch();

        if ($existingRequest) {
            $error = "You already have a pending request for this room and time.";
        } else {
            // Save request to database
            $stmt = $pdo->prepare("
                INSERT INTO authorized_students (
                    student_id_number,
                    full_name,
                    section,
                    purpose,
                    room_id,
                    valid_from,
                    valid_until,
                    status,
                    requested_at
                ) VALUES (
                    :student_id,
                    :full_name,
                    :section,
                    :purpose,
                    :room_id,
                    :valid_from,
                    :valid_until,
                    'pending',
                    NOW()
                )
            ");

            $stmt->execute([
                ':student_id'  => $studentId,
                ':full_name'   => $fullName,
                ':section'     => $section,
                ':purpose'     => $purpose,
                ':room_id'     => $selectedRoomId,
                ':valid_from'  => $startDateTime,
                ':valid_until' => $endDateTime
            ]);

            $_SESSION['student_success'] = "Your authorization request has been submitted. Please wait for the administrator's decision.";

            header("Location: student.php?room_id=" . $selectedRoomId . "&mode=request");
            exit;
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
    <title>Student Room Check-In</title>
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
    <h2>Student room check-in</h2>

    <?php if ($error): ?>
        <p class="error-text"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success-text"><?= htmlspecialchars($success) ?></p>
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

    <?php else: ?>

        <div class="selected-room-box">
            <strong>Selected room:</strong>
            <?= htmlspecialchars($selectedRoom['room_name']) ?>
        </div>

        <!-- AUTHORIZATION CHOICE -->
        <?php if (
            !isset($_GET['mode']) &&
            !isset($_POST['lookup_authorized']) &&
            !isset($_POST['checkin']) &&
            !isset($_POST['send_request'])
        ): ?>

            <h3>Are you already authorized?</h3>

            <div class="choice-buttons">
                <a href="student.php?room_id=<?= $selectedRoomId ?>&mode=authorized" class="choice-button">
                    <strong>Yes, I am authorized</strong>
                    <span>Check my authorization</span>
                </a>

                <a href="student.php?room_id=<?= $selectedRoomId ?>&mode=request" class="choice-button">
                    <strong>No, I am not authorized</strong>
                    <span>Submit a request to the administrator</span>
                </a>
            </div>

        <!-- AUTHORIZED LOOKUP -->
        <?php elseif (
            isset($_GET['mode']) &&
            $_GET['mode'] === 'authorized' &&
            !$authorization
        ): ?>

            <h3>Check your authorization</h3>

            <form method="POST">
                <input type="hidden" name="room_id" value="<?= $selectedRoomId ?>">

                <label for="student_id_number">Student ID</label>
                <input type="text" id="student_id_number" name="student_id_number" required placeholder="Example: 2026-001" value="<?= oldValue('student_id_number') ?>">

                <button type="submit" name="lookup_authorized" value="1">
                    Check authorization
                </button>
            </form>

        <!-- AUTHORIZED CHECK-IN FORM -->
        <?php elseif ($authorization): ?>

            <div class="authorized-box">
                <h3>Authorization approved</h3>
                <p><strong><?= htmlspecialchars($authorization['full_name']) ?></strong></p>
                <p>Student ID: <?= htmlspecialchars($authorization['student_id_number']) ?></p>
                <p>Authorized room: <?= htmlspecialchars($selectedRoom['room_name']) ?></p>
                <?php
                $authStart = strtotime($authorization['valid_from']);
                $authEnd   = strtotime($authorization['valid_until']);
                $sameDay   = date('Y-m-d', $authStart) === date('Y-m-d', $authEnd);
                ?>
                <p>Authorized time: <?= date('M d, Y g:i A', $authStart) ?> &ndash; <?= date($sameDay ? 'g:i A' : 'M d, Y g:i A', $authEnd) ?></p>
            </div>

            <p class="form-subtitle">Your room usage will be registered for the time you were authorized.</p>

            <form method="POST">
                <input type="hidden" name="room_id" value="<?= $selectedRoomId ?>">
                <input type="hidden" name="authorization_id" value="<?= $authorization['id'] ?>">
                <input type="hidden" name="student_id_number" value="<?= htmlspecialchars($authorization['student_id_number']) ?>">

                <label for="section">Section</label>
                <input type="text" id="section" name="section" value="<?= oldValue('section', $authorization['section'] ?? '') ?>" required>

                <label for="purpose">Purpose</label>
                <input type="text" id="purpose" name="purpose" maxlength="100" value="<?= oldValue('purpose', $authorization['purpose'] ?? '') ?>" required placeholder="Example: Group study for thesis defense">

                <div class="warning-box">
                    <strong>Room usage rules</strong>
                    <p>Only authorized students may use this room. Unauthorized use is prohibited and may be subject to school rules and regulations.</p>

                    <label>
                        <input type="checkbox" required>
                        I understand and agree to follow the room rules.
                    </label>
                </div>

                <button type="submit" name="checkin" value="1">
                    Register room usage
                </button>
            </form>

        <!-- REQUEST AUTHORIZATION FORM -->
        <?php elseif (
            isset($_GET['mode']) &&
            $_GET['mode'] === 'request'
        ): ?>

            <h3>Request room authorization</h3>
            <p class="form-subtitle">Submit your request below. The administrator will review it before you can use the room.</p>

            <form method="POST">
                <input type="hidden" name="room_id" value="<?= $selectedRoomId ?>">

                <label for="student_id_number">Student ID</label>
                <input type="text" id="student_id_number" name="student_id_number" required placeholder="Example: 2026-001" value="<?= oldValue('student_id_number') ?>">

                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required placeholder="Juan Dela Cruz" value="<?= oldValue('full_name') ?>">

                <label for="section">Section</label>
                <input type="text" id="section" name="section" required placeholder="Example: BSIS 2A" value="<?= oldValue('section') ?>">

                <label for="purpose">Purpose</label>
                <input type="text" id="purpose" name="purpose" maxlength="100" value="<?= oldValue('purpose') ?>" required placeholder="Example: Group study for thesis defense">

                <label for="request_date">Requested date</label>
                <input type="date" id="request_date" name="request_date" value="<?= oldValue('request_date', date('Y-m-d')) ?>" min="<?= date('Y-m-d') ?>" required>

                <label for="start_time">Start time</label>
                <input type="time" id="start_time" name="start_time" value="<?= oldValue('start_time') ?>" required>

                <label for="end_time">End time</label>
                <input type="time" id="end_time" name="end_time" value="<?= oldValue('end_time') ?>" required>

                <div class="warning-box">
                    <strong>Important</strong>
                    <p>Submitting this form does not automatically authorize you to use the room. You must wait for the administrator to approve your request.</p>
                </div>

                <button type="submit" name="send_request" value="1">
                    Submit authorization request
                </button>
            </form>

        <?php endif; ?>

    <?php endif; ?>

    <a href="../public/rooms.php" class="back-link">&larr; Back to Room Availability</a>
</div>
</main>

</body>
</html>