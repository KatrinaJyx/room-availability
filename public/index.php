<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Campus - Room Availability</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/landingstyle.css">
</head>
<body>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="logo">Smart Campus</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="rooms.php">Room Availability</a>
            <a href="../admin/login.php">Admin</a>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero">
        <div class="hero-content">
            <h1>Know if a room is free before you walk in.</h1>
            <p class="hero-text">
                Check which campus rooms are vacant, upcoming, or in use, and who is using them.
            </p>
            <div class="hero-buttons">
                <a href="rooms.php" class="main-button">View room availability</a>
            </div>
        </div>

        <div class="hero-display">
            <div class="display-card">
                <div class="display-header">
                    <span>Room status</span>
                    <span class="live"><i class="live-dot"></i>Live</span>
                </div>

                <div class="sample-room">
                    <span class="status-dot dot-vacant"></span>
                    <div>
                        <h3>Room 105</h3>
                        <p>Main Building</p>
                    </div>
                    <span class="status vacant">Vacant</span>
                </div>

                <div class="sample-room">
                    <span class="status-dot dot-upcoming"></span>
                    <div>
                        <h3>Room 106</h3>
                        <p>Upcoming at 10:00 AM</p>
                    </div>
                    <span class="status upcoming">Upcoming</span>
                </div>

                <div class="sample-room">
                    <span class="status-dot dot-in-use"></span>
                    <div>
                        <h3>Room 107</h3>
                        <p>Currently in use</p>
                    </div>
                    <span class="status in-use">In use</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Statuses -->
    <section class="features">
        <div class="section-title">
            <h2>Three statuses, one glance</h2>
        </div>

        <div class="feature-grid">
            <div class="feature-item">
                <span class="status-dot dot-vacant"></span>
                <h3>Vacant</h3>
                <p>The room is free and available for authorized use.</p>
            </div>

            <div class="feature-item">
                <span class="status-dot dot-upcoming"></span>
                <h3>Upcoming</h3>
                <p>A class or booking starts within the next hour.</p>
            </div>

            <div class="feature-item">
                <span class="status-dot dot-in-use"></span>
                <h3>In use</h3>
                <p>A teacher or authorized student is using the room.</p>
            </div>
        </div>
    </section>

    <!-- Room access -->
    <section class="checkin-section">
        <div class="section-title">
            <h2>Need a vacant room?</h2>
            <span>Choose your user type to see how access works.</span>
        </div>

        <div class="checkin-grid">
            <div class="checkin-card">
                <h3>Teacher</h3>
                <p>Register room use with your employee ID.</p>
                <a href="../checkin/teacher.php" class="secondary-button">Check room</a>
            </div>

            <div class="checkin-card">
                <h3>Student</h3>
                <p>You need approval from the administrator before using a room.</p>
                <a href="../checkin/student.php" class="secondary-button">Check room</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>Smart Campus Room Availability Monitoring System</p>
        <p>City College Campus</p>
    </footer>

</body>
</html>