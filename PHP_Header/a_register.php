<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

/* ============================================================
   🔹 Admin alapadatok lekérése (név)
============================================================ */
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

/* ============================================================
🔹 Programok lekérése a legördülő listához
============================================================ */
$program_sql = "
SELECT 
    szak_szam, 
    name 
FROM programs 
ORDER BY name";
$program_result = $conn->query($program_sql);
$programs = $program_result->fetch_all(MYSQLI_ASSOC);
