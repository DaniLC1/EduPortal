<?php
// Globális session és connectio
session_start();
require_once __DIR__. '/../connection.php';

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
   🔹 Felhasználóhoz tartozó tárgyak és
      az arra jelentkezett diákok jegyei/állapota
============================================================ */
$students_sql = "
SELECT 
    e.offering_id AS offering_id,
    e.users_eduportal_id AS student_id,
    u.name AS student_name,
    c.kurzus_kod AS subject_code,
    c.name AS subject_name,
    s.label AS semester_label,
    e.status AS enrollment_status,
    e.grade AS current_grade,
    e.enrolled_at,
    e.completed_at
FROM enrollments e
JOIN users u ON u.eduportal_id = e.users_eduportal_id
JOIN course_offerings co ON co.id = e.offering_id
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
GROUP BY e.users_eduportal_id, c.kurzus_kod, s.label, c.name
ORDER BY s.label DESC, u.name ASC, c.name ASC
";

$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("s", $eduportal_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();

$students = [];
while ($r = $students_result->fetch_assoc()) {
    $students[] = $r;
}
$students_stmt->close();

/* ============================================================
   🔹 Tárgyakhoz szükséges szűrő lekérdezése
============================================================ */
$filter_courses_sql = "
SELECT 
    co.id AS offering_id,
    CONCAT(c.name, ' (', s.label, ', ', co.day_of_week, ' ', TIME_FORMAT(co.start_time, '%H:%i'), ')') AS label
FROM teacher_courses tc
JOIN course_offerings co ON tc.kurzus_kod = co.kurzus_kod
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE tc.teacher_id = ?
ORDER BY c.name ASC, s.start_date DESC
";
$filter_courses_stmt = $conn->prepare($filter_courses_sql);
$filter_courses_stmt->bind_param("s", $eduportal_id);
$filter_courses_stmt->execute();
$filter_courses_result = $filter_courses_stmt->get_result();

/* ============================================================
   🔹 Félévhez szükséges szűrő lekérdezése
============================================================ */
$semester_sql = "
SELECT DISTINCT s.label
FROM semesters s
JOIN course_offerings co ON s.id = co.semester_id
JOIN teacher_courses tc ON co.kurzus_kod = tc.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY s.label DESC
";

$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("s", $eduportal_id);
$semester_stmt->execute();
$semester_result = $semester_stmt->get_result();
