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
   🔹 Kitöltött kérelmek lekérdezése
============================================================ */
$request_sql = "
SELECT sr.id AS request_id,
       sr.template_id,
       rt.title,
       rt.description,
       rt.to_who,
       u.name AS student_name,
       sr.submitted_at,
       sr.status,
       sr.admin_comment
FROM student_requests sr
JOIN request_templates rt ON sr.template_id = rt.id
JOIN users u ON sr.users_eduportal_ID = u.eduportal_id
ORDER BY sr.submitted_at DESC";

$request_result = $conn->query($request_sql);

$requests = [];
while ($row = $request_result->fetch_assoc()) {
    $requests[$row['request_id']] = $row;
}

/* ============================================================
   🔹 Mezők lekérdezése minden sablonhoz és kitöltött értékekkel
============================================================ */
$fields_sql = "
SELECT f.id AS field_id,
       f.template_id,
       f.label,
       f.field_type,
       f.is_required,
       v.field_value,
       v.admin_suggestion,
       v.request_id
FROM request_template_fields f
LEFT JOIN student_request_field_values v ON f.id = v.field_id";

$fields_result = $conn->query($fields_sql);

$request_fields = [];
while ($row = $fields_result->fetch_assoc()) {
    if ($row['request_id']) {
        // már kitöltött kérelemhez
        $request_fields[$row['request_id']][] = $row;
    } else {
        // még nem kitöltött sablonmezők template_id alapján
        $request_fields['template_'.$row['template_id']][] = $row;
    }
}
