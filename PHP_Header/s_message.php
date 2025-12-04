<?php
// Globális session és connection
session_start();
require_once __DIR__. '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'hallgato') {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

/* ============================================================
   🔹 Felhasználó alapadatok lekérése
============================================================ */
$user_sql = "
SELECT
    u.name,
    p.name AS szak_nev
FROM users u
LEFT JOIN programs p ON p.szak_szam = u.course_code
WHERE u.eduportal_id = ?";

$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? "Ismeretlen";
$user_course = $user['szak_nev'] ?? "N/A";

/* ============================================================
   🔹 Címzett lekérdezése (URL-ben: message.php?to=ABC123)
============================================================ */
$selected_user = $_GET['to'] ?? null;

/* ============================================================
   🔹 Felhasználók listája (kinek írhatsz)
============================================================ */
$users_sql = "
SELECT 
    eduportal_id,
    name, 
    role 
FROM users 
WHERE eduportal_id != ?
ORDER BY name ASC";

$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param("s", $eduportal_id);
$users_stmt->execute();
$users_result = $users_stmt->get_result();

/* ============================================================
   🔹 Ha van kiválasztott felhasználó → beszélgetés lekérése
============================================================ */
$messages = [];
if ($selected_user) {
    $msg_sql = "
        SELECT 
            m.*, 
            u_from.name AS from_name,
            u_to.name AS to_name
        FROM messages m
        JOIN users u_from ON m.from_eduportal_id = u_from.eduportal_id
        JOIN users u_to ON m.to_eduportal_id = u_to.eduportal_id
        WHERE 
           (m.from_eduportal_id = ? AND m.to_eduportal_id = ?)
        OR (m.from_eduportal_id = ? AND m.to_eduportal_id = ?)
        ORDER BY m.created_at ASC";

    $msg_stmt = $conn->prepare($msg_sql);
    $msg_stmt->bind_param("ssss",$eduportal_id, $selected_user, $selected_user, $eduportal_id);
    $msg_stmt->execute();
    $messages = $msg_stmt->get_result();
}
