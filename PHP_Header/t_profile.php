<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

/* ============================================================
   🔹 Felhasználó alapadatok lekérése
============================================================ */
$user_sql = "
SELECT name 
FROM users
WHERE eduportal_id = ?";

$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? 'Ismeretlen';
$user_course = "Tanár";

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

/* ============================================================
   🔹 Beküldött kérelmek lekérdezése
============================================================ */
$submitted_sql = "
SELECT sr.id AS request_id,
       sr.submitted_at,
       sr.status,
       sr.admin_comment,
       rt.title,
       rt.description
FROM student_requests sr
JOIN request_templates rt ON rt.id = sr.template_id
WHERE sr.users_eduportal_ID = ?
ORDER BY sr.submitted_at DESC
";

$submitted_stmt = $conn->prepare($submitted_sql);
$submitted_stmt->bind_param("s", $eduportal_id);
$submitted_stmt->execute();
$submitted_result = $submitted_stmt->get_result();

$submitted_requests = [];
while ($row = $submitted_result->fetch_assoc()) {
    $submitted_requests[$row['request_id']] = $row;
}

/* ============================================================
   🔹 Mezők & értékek lekérdezése a kérelmekhez
============================================================ */
$field_values_sql = "
SELECT fv.request_id,
       tf.label,
       fv.field_value,
       fv.admin_suggestion
FROM student_request_field_values fv
JOIN request_template_fields tf ON tf.id = fv.field_id
WHERE fv.request_id IN (" . implode(',', array_keys($submitted_requests) ?: [0]) . ")
ORDER BY fv.request_id, tf.id
";

$field_values_result = $conn->query($field_values_sql);
while ($row = $field_values_result->fetch_assoc()) {
    $submitted_requests[$row['request_id']]['fields'][] = $row;
}
