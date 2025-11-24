<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'hallgato') {
    header("Location: ../index.php");
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
   🔹 Felvett kurzusokhoz szükséges adatok lekérdezése
============================================================ */
$enrolled_courses_sql = "
SELECT 
    c.kurzus_kod,
    c.name AS subject_name,
    c.credit,
    COALESCE(pc.tipus, 'szv') AS subject_type,
    e.status AS enrollment_status,
    s.label AS semester_label,
    e.grade AS grade
FROM enrollments e
JOIN course_offerings co ON e.offering_id = co.id
JOIN semesters s ON s.id = co.semester_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u ON u.eduportal_id = e.users_eduportal_id
LEFT JOIN programs p ON p.szak_szam = u.course_code
LEFT JOIN program_courses pc 
    ON pc.kurzus_kod = c.kurzus_kod AND pc.szak_szam = p.szak_szam
WHERE e.users_eduportal_id = ?
ORDER BY s.label DESC, c.name ASC";

$enrolled_stmt = $conn->prepare($enrolled_courses_sql);
$enrolled_stmt->bind_param("s", $eduportal_id);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
$enrolled_subjects = $enrolled_result->fetch_all(MYSQLI_ASSOC);

/* ============================================================
   🔹 Félév szűréséhez létrehozott lekérdezé
============================================================ */
$semester_sql = "
SELECT DISTINCT s.label 
FROM enrollments e
JOIN course_offerings co ON co.id = e.offering_id
JOIN semesters s ON s.id = co.semester_id
WHERE e.users_eduportal_id = ?
ORDER BY s.label DESC";

$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("s", $eduportal_id);
$semester_stmt->execute();
$semester_result = $semester_stmt->get_result();

?>
