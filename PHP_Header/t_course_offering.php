<?php
// Globális session és connection
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

// 🔹 Mai nap/dátum
$today = date('Y-m-d');

// 🔹 Aktuális félév lekérdezése
$semester_sql = "
SELECT id, 
       label 
FROM semesters 
WHERE start_date <= ? AND end_date >= ? LIMIT 1";

$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("ss", $today, $today);
$semester_stmt->execute();
$current_semester = $semester_stmt->get_result()->fetch_assoc();
$current_semester_id = $current_semester['id'] ?? null;

// 🔹 Kiválasztott félév
$selected_semester_id = $_GET['semester_id'] ?? $current_semester_id;

// 🔹 Összes félév lekérdezése
$semesters_sql = "
SELECT id,
       label 
FROM semesters 
ORDER BY start_date DESC";

$semesters = $conn->query($semesters_sql)->fetch_all(MYSQLI_ASSOC);

// 🔹 Tanárhoz rendelt kurzusok (legördülőhöz)
$teacher_courses_sql = "
SELECT c.kurzus_kod,
       c.name 
FROM teacher_courses tc
JOIN courses c ON c.kurzus_kod = tc.kurzus_kod
WHERE tc.teacher_id = ?
";

$tc_stmt = $conn->prepare($teacher_courses_sql);
$tc_stmt->bind_param("s", $eduportal_id);
$tc_stmt->execute();
$teacher_courses = $tc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 🔹 Meghirdetett kurzusok lekérdezése
$offering_sql = "
SELECT 
    co.id AS offering_id,
    c.kurzus_kod,
    c.name AS course_name,
    c.leiras,
    co.day_of_week,
    co.start_time,
    co.room,
    co.max_students,
    co.semester_id,
    co.course_type,
    co.end_date,
    s.label AS semester_label
FROM course_offerings co
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
JOIN teacher_courses tc ON c.kurzus_kod = tc.kurzus_kod
WHERE tc.teacher_id = ?
";

$params = [$eduportal_id];
$types = "s";

if (!empty($selected_semester_id)) {
    $offering_sql .= " AND co.semester_id = ?";
    $params[] = $selected_semester_id;
    $types .= "i";
}

$offering_sql .= " ORDER BY s.label DESC, c.name ASC";

$offering_stmt = $conn->prepare($offering_sql);
$offering_stmt->bind_param($types, ...$params);
$offering_stmt->execute();
$offerings = $offering_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
