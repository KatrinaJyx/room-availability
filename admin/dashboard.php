<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

// ======================================
// APPROVE REQUEST
// ======================================
if (isset($_POST['approve_request'])) {
    $requestId = (int) $_POST['request_id'];

    $stmt = $pdo->prepare("
        UPDATE authorized_students
        SET
            status = 'approved',
            approved_at = NOW()
        WHERE id = :id
        AND status = 'pending'
    ");

    $stmt->execute([
        ':id' => $requestId
    ]);

    if ($stmt->rowCount() > 0) {
        $message = "Student authorization request approved.";
    } else {
        $error = "This request was already processed.";
    }
}

// ======================================
// DENY REQUEST
// ======================================
if (isset($_POST['deny_request'])) {
    $requestId = (int) $_POST['request_id'];

    $stmt = $pdo->prepare("
        UPDATE authorized_students
        SET
            status = 'denied',
            approved_at = NULL
        WHERE id = :id
        AND status = 'pending'
    ");

    $stmt->execute([
        ':id' => $requestId
    ]);

    if ($stmt->rowCount() > 0) {
        $message = "Student authorization request denied.";
    } else {
        $error = "This request was already processed.";
    }
}

// ======================================
// REVOKE APPROVED AUTHORIZATION
// ======================================
if (isset($_POST['revoke_authorization'])) {
    $requestId = (int) $_POST['request_id'];

    $stmt = $pdo->prepare("
        UPDATE authorized_students
        SET
            status = 'denied',
            approved_at = NULL
        WHERE id = :id
        AND status = 'approved'
    ");

    $stmt->execute([
        ':id' => $requestId
    ]);

    if ($stmt->rowCount() > 0) {
        $message = "Student authorization has been revoked.";
    } else {
        $error = "This authorization was already revoked.";
    }
}

// ======================================
// CANCEL ROOM USAGE
// ======================================
if (isset($_POST['cancel_usage'])) {
    $usageId = (int) $_POST['usage_id'];

    $stmt = $pdo->prepare("
        DELETE FROM room_usage
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $usageId
    ]);

    $message = "Room usage has been cancelled.";
}

// ======================================
// UPDATE ROOM INFO
// ======================================
if (isset($_POST['update_room'])) {
    $roomId   = (int) $_POST['room_id'];
    $roomName = trim($_POST['room_name'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $capacity = filter_var(
        $_POST['capacity'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 1000]]
    );

    if ($roomName === '' || $building === '') {
        $error = "Room name and building cannot be empty.";
    } elseif ($capacity === false) {
        $error = "Capacity must be a whole number from 1 to 1000.";
    } else {
        // Room names must stay unique
        $stmt = $pdo->prepare("
            SELECT id
            FROM rooms
            WHERE room_name = :room_name
              AND id <> :id
            LIMIT 1
        ");

        $stmt->execute([
            ':room_name' => $roomName,
            ':id'        => $roomId
        ]);

        if ($stmt->fetch()) {
            $error = "Another room already uses that name.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE rooms
                SET
                    room_name = :room_name,
                    building = :building,
                    capacity = :capacity
                WHERE id = :id
            ");

            $stmt->execute([
                ':room_name' => $roomName,
                ':building'  => $building,
                ':capacity'  => $capacity,
                ':id'        => $roomId
            ]);

            $message = "Room information has been updated.";
        }
    }
}

// ======================================
// ADD TEACHER
// ======================================
if (isset($_POST['add_teacher'])) {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $fullName   = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if ($employeeId === '' || $fullName === '' || $department === '') {
        $error = "Employee ID, full name and department are all required.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id
            FROM teachers
            WHERE employee_id = :employee_id
            LIMIT 1
        ");

        $stmt->execute([':employee_id' => $employeeId]);

        if ($stmt->fetch()) {
            $error = "A teacher with that Employee ID already exists.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO teachers (employee_id, full_name, department)
                VALUES (:employee_id, :full_name, :department)
            ");

            $stmt->execute([
                ':employee_id' => $employeeId,
                ':full_name'   => $fullName,
                ':department'  => $department
            ]);

            $message = "Teacher has been added.";
        }
    }
}

// ======================================
// UPDATE TEACHER
// ======================================
if (isset($_POST['update_teacher'])) {
    $teacherId  = (int) $_POST['teacher_id'];
    $employeeId = trim($_POST['employee_id'] ?? '');
    $fullName   = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if ($employeeId === '' || $fullName === '' || $department === '') {
        $error = "Employee ID, full name and department are all required.";
    } else {
        // Employee IDs must stay unique
        $stmt = $pdo->prepare("
            SELECT id
            FROM teachers
            WHERE employee_id = :employee_id
              AND id <> :id
            LIMIT 1
        ");

        $stmt->execute([
            ':employee_id' => $employeeId,
            ':id'          => $teacherId
        ]);

        if ($stmt->fetch()) {
            $error = "Another teacher already uses that Employee ID.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE teachers
                SET
                    employee_id = :employee_id,
                    full_name = :full_name,
                    department = :department
                WHERE id = :id
            ");

            $stmt->execute([
                ':employee_id' => $employeeId,
                ':full_name'   => $fullName,
                ':department'  => $department,
                ':id'          => $teacherId
            ]);

            $message = "Teacher information has been updated.";
        }
    }
}

// ======================================
// GET ROOMS
// ======================================
$stmt = $pdo->query("
    SELECT *
    FROM rooms
    ORDER BY room_name ASC
");
$rooms = $stmt->fetchAll();

// ======================================
// GET TEACHERS
// ======================================
$stmt = $pdo->query("
    SELECT *
    FROM teachers
    ORDER BY full_name ASC
");
$teachers = $stmt->fetchAll();

// ======================================
// GET PENDING REQUESTS
// ======================================
$stmt = $pdo->query("
    SELECT
        authorized_students.*,
        rooms.room_name
    FROM authorized_students
    LEFT JOIN rooms
        ON authorized_students.room_id = rooms.id
    WHERE authorized_students.status = 'pending'
    ORDER BY authorized_students.requested_at ASC
");
$pendingRequests = $stmt->fetchAll();

// ======================================
// GET APPROVED STUDENTS
// ======================================
$stmt = $pdo->query("
    SELECT
        authorized_students.*,
        rooms.room_name
    FROM authorized_students
    LEFT JOIN rooms
        ON authorized_students.room_id = rooms.id
    WHERE authorized_students.status = 'approved'
      AND authorized_students.valid_until >= NOW()
    ORDER BY authorized_students.approved_at DESC
");
$approvedStudents = $stmt->fetchAll();

// ======================================
// GET RECENT DENIED REQUESTS
// ======================================
$stmt = $pdo->query("
    SELECT
        authorized_students.*,
        rooms.room_name
    FROM authorized_students
    LEFT JOIN rooms
        ON authorized_students.room_id = rooms.id
    WHERE authorized_students.status = 'denied'
    ORDER BY authorized_students.requested_at DESC
");
$deniedRequests = $stmt->fetchAll();

// ======================================
// GET ROOM USAGE
// ======================================
$stmt = $pdo->query("
    SELECT
        room_usage.*,
        rooms.room_name,
        teachers.employee_id
    FROM room_usage
    LEFT JOIN rooms
        ON room_usage.room_id = rooms.id
    LEFT JOIN teachers
        ON room_usage.teacher_id = teachers.id
    ORDER BY room_usage.start_time DESC
");
$roomUsage = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/adminstyle.css">
</head>

<body>

    <div class="layout">

        <!-- ===================================== -->
        <!-- SIDEBAR -->
        <!-- ===================================== -->
        <aside class="sidebar">
            <div class="sidebar-logo">SMART CAMPUS</div>
            <p class="sidebar-label">Navigation</p>
            <nav class="sidebar-nav">
                <a href="#overview">Overview</a>
                <a href="#pending">Pending Requests</a>
                <a href="#approved">Approved Students</a>
                <a href="#denied">Denied Requests</a>
                <a href="#usage">Room Usage</a>
                <a href="#rooms">Manage Rooms</a>
                <a href="#teachers">Manage Teachers</a>
            </nav>
            <a href="logout.php" class="sidebar-logout">Logout</a>
        </aside>

        <!-- ===================================== -->
        <!-- MAIN -->
        <!-- ===================================== -->
        <main class="main">

            <!-- HEADER -->
            <div class="admin-header" id="overview">
                <div>
                    <h1>Room Availability Admin</h1>
                    <p>Review student room authorization requests.</p>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="success-box">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error-box">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- STAT CARDS -->
            <div class="stat-grid">
                <div class="stat-card">
                    <span class="stat-number"><?= count($pendingRequests) ?></span>
                    <span class="stat-label">Pending requests</span>
                    <span class="stat-dot dot-yellow"></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= count($approvedStudents) ?></span>
                    <span class="stat-label">Approved students</span>
                    <span class="stat-dot dot-green"></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= count($deniedRequests) ?></span>
                    <span class="stat-label">Denied requests</span>
                    <span class="stat-dot dot-red"></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?= count($roomUsage) ?></span>
                    <span class="stat-label">Room usage records</span>
                    <span class="stat-dot dot-purple"></span>
                </div>
            </div>

            <!-- ===================================== -->
            <!-- PENDING REQUESTS -->
            <!-- ===================================== -->
            <section class="admin-section" id="pending">
                <h2>Pending Authorization Requests</h2>

                <?php if (!$pendingRequests): ?>
                <p>No pending requests.</p>
                <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Room</th>
                                <th>Purpose</th>
                                <th>Requested Date</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Requested At</th>
                                <th>Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['student_id_number']) ?></td>
                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                <td><?= htmlspecialchars($request['section']) ?></td>
                                <td><?= htmlspecialchars($request['room_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($request['purpose']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['valid_from'])) ?></td>
                                <td><?= date('g:i A', strtotime($request['valid_from'])) ?></td>
                                <td><?= date('g:i A', strtotime($request['valid_until'])) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($request['requested_at'])) ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <form method="POST" onsubmit="return confirm('Approve this request?');">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" name="approve_request" value="1" class="approve-button">
                                                Approve
                                            </button>
                                        </form>

                                        <form method="POST" onsubmit="return confirm('Deny this request?');">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <button type="submit" name="deny_request" value="1" class="danger-button">
                                                Deny
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <!-- ===================================== -->
            <!-- APPROVED STUDENTS -->
            <!-- ===================================== -->
            <section class="admin-section" id="approved">
                <h2>Approved Students</h2>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Room</th>
                                <th>Purpose</th>
                                <th>Valid From</th>
                                <th>Valid Until</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvedStudents as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['student_id_number']) ?></td>
                                <td><?= htmlspecialchars($student['full_name']) ?></td>
                                <td><?= htmlspecialchars($student['section']) ?></td>
                                <td><?= htmlspecialchars($student['room_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($student['purpose']) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($student['valid_from'])) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($student['valid_until'])) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Revoke this authorization?');">
                                        <input type="hidden" name="request_id" value="<?= $student['id'] ?>">
                                        <button type="submit" name="revoke_authorization" value="1" class="danger-button">
                                            Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!$approvedStudents): ?>
                            <tr>
                                <td colspan="8">No approved students.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ===================================== -->
            <!-- DENIED REQUESTS -->
            <!-- ===================================== -->
            <section class="admin-section" id="denied">
                <h2>Denied Requests</h2>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Room</th>
                                <th>Purpose</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deniedRequests as $request): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['student_id_number']) ?></td>
                                <td><?= htmlspecialchars($request['full_name']) ?></td>
                                <td><?= htmlspecialchars($request['room_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($request['purpose']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['valid_from'])) ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!$deniedRequests): ?>
                            <tr>
                                <td colspan="5">No denied requests.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ===================================== -->
            <!-- ROOM USAGE -->
            <!-- ===================================== -->
            <section class="admin-section" id="usage">
                <h2>Room Usage</h2>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>ID</th>
                                <th>Section</th>
                                <th>Purpose</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roomUsage as $usage): ?>
                            <tr>
                                <td><?= htmlspecialchars($usage['room_name']) ?></td>
                                <td><?= htmlspecialchars($usage['user_name']) ?></td>
                                <td><?= htmlspecialchars(ucfirst($usage['user_type'])) ?></td>
                                <td>
                                    <?php if ($usage['user_type'] === 'student'): ?>
                                        <?= htmlspecialchars($usage['student_id_number']) ?>
                                    <?php elseif (!empty($usage['employee_id'])): ?>
                                        <?= htmlspecialchars($usage['employee_id']) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($usage['section'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($usage['purpose']) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($usage['start_time'])) ?></td>
                                <td><?= date('M d, Y g:i A', strtotime($usage['end_time'])) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Cancel this room usage?');">
                                        <input type="hidden" name="usage_id" value="<?= $usage['id'] ?>">
                                        <button type="submit" name="cancel_usage" value="1" class="danger-button">
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!$roomUsage): ?>
                            <tr>
                                <td colspan="9">No room usage records.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ===================================== -->
            <!-- MANAGE ROOMS -->
            <!-- ===================================== -->
            <section class="admin-section" id="rooms">
                <h2>Manage Rooms</h2>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Room Name</th>
                                <th>Building</th>
                                <th>Capacity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td>
                                    <input type="text" name="room_name" class="room-input"
                                        form="room-form-<?= (int) $room['id'] ?>"
                                        value="<?= htmlspecialchars($room['room_name']) ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="building" class="room-input"
                                        form="room-form-<?= (int) $room['id'] ?>"
                                        value="<?= htmlspecialchars($room['building']) ?>" required>
                                </td>
                                <td>
                                    <input type="number" name="capacity" class="room-input room-input-small"
                                        form="room-form-<?= (int) $room['id'] ?>"
                                        value="<?= (int) $room['capacity'] ?>" min="1" max="1000" required>
                                </td>
                                <td>
                                    <form method="POST" id="room-form-<?= (int) $room['id'] ?>">
                                        <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>">
                                        <button type="submit" name="update_room" value="1" class="approve-button">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!$rooms): ?>
                            <tr>
                                <td colspan="4">No rooms found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ===================================== -->
            <!-- MANAGE TEACHERS -->
            <!-- ===================================== -->
            <section class="admin-section" id="teachers">
                <h2>Manage Teachers</h2>
                <p>Teachers sign in to book rooms with their Employee ID.</p>

                <h3 class="subsection-title">Add teacher</h3>

                <form method="POST" class="teacher-add-form">
                    <div class="edit-field">
                        <label for="new-employee-id">Employee ID</label>
                        <input type="text" id="new-employee-id" name="employee_id" class="edit-input"
                            placeholder="Example: T-001" maxlength="50" required>
                    </div>

                    <div class="edit-field">
                        <label for="new-full-name">Full name</label>
                        <input type="text" id="new-full-name" name="full_name" class="edit-input"
                            placeholder="Juan Dela Cruz" maxlength="100" required>
                    </div>

                    <div class="edit-field">
                        <label for="new-department">Department</label>
                        <input type="text" id="new-department" name="department" class="edit-input"
                            placeholder="Example: BSIS" maxlength="100" required>
                    </div>

                    <button type="submit" name="add_teacher" value="1" class="approve-button add-button">
                        Add teacher
                    </button>
                </form>

                <h3 class="subsection-title">Current teachers</h3>

                <div class="table-wrapper">
                    <table class="edit-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Full Name</th>
                                <th>Department</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td>
                                    <input type="text" name="employee_id" class="edit-input"
                                        form="teacher-form-<?= (int) $teacher['id'] ?>"
                                        value="<?= htmlspecialchars($teacher['employee_id']) ?>" maxlength="50" required>
                                </td>
                                <td>
                                    <input type="text" name="full_name" class="edit-input"
                                        form="teacher-form-<?= (int) $teacher['id'] ?>"
                                        value="<?= htmlspecialchars($teacher['full_name']) ?>" maxlength="100" required>
                                </td>
                                <td>
                                    <input type="text" name="department" class="edit-input"
                                        form="teacher-form-<?= (int) $teacher['id'] ?>"
                                        value="<?= htmlspecialchars($teacher['department']) ?>" maxlength="100" required>
                                </td>
                                <td>
                                    <form method="POST" id="teacher-form-<?= (int) $teacher['id'] ?>">
                                        <input type="hidden" name="teacher_id" value="<?= (int) $teacher['id'] ?>">
                                        <button type="submit" name="update_teacher" value="1" class="approve-button">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!$teachers): ?>
                            <tr>
                                <td colspan="4" class="empty-cell">No teachers yet. Add one above.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>

</body>

</html>