<?php
session_start();
$error = '';
$adminUsername = 'admin';
$adminPassword = 'admin123';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if ($username === $adminUsername &&  $password === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: dashboard.php");
        exit;
    } else {$error = "Invalid username or password.";}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login</title>
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
    <h2>Admin login</h2>
    <p class="form-subtitle">Sign in to manage room authorizations.</p>

    <?php if ($error): ?>
        <p class="error-text">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" name="login" value="1">Login</button>
    </form>

</div>
</main>

</body>
</html>