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

$today = date('Y-m-d');

// Aktuális félév lekérdezése
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

// Kiválasztott félév
$selected_semester_id = $_GET['semester_id'] ?? $current_semester_id;

// Összes félév lekérdezése
$semesters_sql = "
SELECT id,
       label 
FROM semesters 
ORDER BY start_date DESC";

$semesters = $conn->query($semesters_sql)->fetch_all(MYSQLI_ASSOC);

// Kurzusok lekérdezése (NINCS search, type, completed szűrés)
$course_offering_sql = "
SELECT 
    co.id AS offering_id,
    co.kurzus_kod,
    co.semester_id,
    co.end_date,
    c.name AS course_name,
    c.leiras,
    c.credit,
    co.course_type,
    co.day_of_week,
    co.start_time,
    co.room,
    co.max_students,
    COUNT(DISTINCT e.users_eduportal_ID) AS enrolled_count,
    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS teachers,

    /* szakhoz tartozás → kötelező / választható / szabadon választható */
    COALESCE(pc.tipus, 'szv') AS course_required_type,

    /* jelentkezett-e erre az offeringre */
    EXISTS (
        SELECT 1 
        FROM enrollments e2 
        WHERE e2.users_eduportal_ID = ? 
        AND e2.offering_id = co.id
    ) AS already_enrolled,

    /* teljesítette-e a kurzuskódot */
    EXISTS (
        SELECT 1 
        FROM enrollments e3 
        JOIN course_offerings co3 ON e3.offering_id = co3.id
        WHERE e3.users_eduportal_ID = ?
        AND co3.kurzus_kod = c.kurzus_kod
        AND e3.status = 'completed'
    ) AS already_completed

FROM course_offerings co
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
LEFT JOIN teacher_courses tc ON tc.kurzus_kod = c.kurzus_kod
LEFT JOIN users t ON t.eduportal_id = tc.teacher_id
LEFT JOIN enrollments e ON e.offering_id = co.id

JOIN users u ON u.eduportal_id = ?
JOIN programs p ON p.szak_szam = u.course_code

LEFT JOIN program_courses pc 
    ON pc.kurzus_kod = c.kurzus_kod 
    AND pc.szak_szam = p.szak_szam

WHERE co.semester_id = ?

GROUP BY co.id
ORDER BY c.name ASC, co.course_type ASC
";

$course_stmt = $conn->prepare($course_offering_sql);
$course_stmt->bind_param("ssss", $eduportal_id, $eduportal_id, $eduportal_id, $selected_semester_id);
$course_stmt->execute();
$raw_courses = $course_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ======= OFFERINGEK CSOPORTOSÍTÁSA KURZUS SZINTRE =======
$courses = [];

foreach ($raw_courses as $row) {

    $key = $row['kurzus_kod'];

    if (!isset($courses[$key])) {
        $courses[$key] = [
            'kurzus_kod' => $row['kurzus_kod'],
            'course_name' => $row['course_name'],
            'leiras' => $row['leiras'],
            'credit' => $row['credit'],
            'teachers' => $row['teachers'],
            'course_required_type' => $row['course_required_type'],
            'already_completed' => $row['already_completed'],
            'offerings' => []
        ];
    }

    $type = $row['course_type'] ?: 'egyéb';

    if (!isset($courses[$key]['offerings'][$type])) {
        $courses[$key]['offerings'][$type] = [];
    }

    $courses[$key]['offerings'][$type][] = [
        'offering_id' => $row['offering_id'],
        'semester_id' => $row['semester_id'],
        'end_date' => $row['end_date'],
        'day_of_week' => $row['day_of_week'],
        'start_time' => $row['start_time'],
        'room' => $row['room'],
        'max_students' => $row['max_students'],
        'enrolled_count' => $row['enrolled_count'],
        'already_enrolled' => $row['already_enrolled']
    ];
}
