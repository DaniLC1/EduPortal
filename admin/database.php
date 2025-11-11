<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php");
    exit;
}

// 🧑‍🏫 Csak adminok léphetnek be
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// 🔹 Admin adatainak lekérése
$user_sql = "
SELECT 
    name 
FROM users 
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? 'Ismeretlen';
$user_course = "Admin";

#TODO

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/profile.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <div class="dropdown">
            <div id="dropdownMenuL" class="dropdown-menu left" hidden="hidden">
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="#" id="active" ><span class="icon">📘</span> Adatbázis</a>
        <a href="request.php"><span class="icon">📄</span> Kérelmek</a>
    </nav>

    <!-- JOBB OLDALI MENÜ -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?php echo htmlspecialchars($user_name); ?> |
                <?php echo htmlspecialchars($eduportal_id); ?> |
                <?php echo htmlspecialchars($user_course); ?>
            </button>
            <div id="dropdownMenuR" class="dropdown-menu right">
                <a href="profile.php">Beállítások</a>
                <a href="../logout.php">Kijelentkezés</a>
            </div>
        </div>
        <!-- TÉMAVÁLTÓ GOMB -->
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>
<main>

</main>
<script src="../Scripts/scripts.js"></script>
</body>
</html>
