<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

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
WHERE u.eduportal_id = ?
";

$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? "Ismeretlen";
$user_course = $user['szak_nev'] ?? "N/A";

/* ============================================================
   🔹 Kérlemek adatainak és szűréshez szükséges lekérdezés
============================================================ */
$request_sql = "
    SELECT rt.id,
           rt.title,
           rt.description
    FROM request_templates rt
    WHERE rt.to_who = 'hallgato'
    ORDER BY rt.created_at DESC
";

$request_result = $conn->query($request_sql);

$templates = [];
while ($row = $request_result->fetch_assoc()) {
    $templates[] = $row;
}

/* ============================================================
   🔹 Kérlemek mezőihez szükséges lekérdezés
============================================================ */
$fields_sql = "
    SELECT f.id,
           f.template_id,
           f.label,
           f.field_type,
           f.is_required
    FROM request_template_fields f
    ORDER BY f.template_id, f.id
";
$fields_result = $conn->query($fields_sql);

$template_fields = [];
while ($row = $fields_result->fetch_assoc()) {
    $template_fields[$row['template_id']][] = $row;
}
?>
