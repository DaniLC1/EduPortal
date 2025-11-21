<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
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
   🔹 Profil adatok lekérése
============================================================ */
$profile_sql = "
SELECT u.name,
       u.birth_date,
       u.mothers_name,
       u.email,
       u.phone,
       u.postal_code,
       u.city,
       u.address
FROM users u
WHERE eduportal_id = ?";

$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("s", $eduportal_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();

/* ============================================================
   🔹 Típusok lekérdezése (input mezők miatt)
============================================================ */
$field_types = [];
$type_query = $conn->query("SHOW COLUMNS FROM users");
while ($col = $type_query->fetch_assoc()) {
    $field_types[$col['Field']] = $col['Type'];
}

?>
