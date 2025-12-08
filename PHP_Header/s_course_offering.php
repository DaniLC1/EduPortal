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
    u.course_code,
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

//Aktuális dátum (mai nap)
$today = date('Y-m-d');

/* ============================================================
   🔹 Félév szűréséhez létrehozott lekérdezé
============================================================ */
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

/* ============================================================
   🔹 1️⃣ Hallgató kurzusai és tanárok
============================================================ */
$kurzusok_sql = "
SELECT 
    c.kurzus_kod,
    c.name AS course_name,
    c.leiras,
    c.credit,
    pc.tipus AS course_required_type,
    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS teachers
FROM courses c
LEFT JOIN program_courses pc ON pc.kurzus_kod = c.kurzus_kod AND pc.szak_szam = ?
LEFT JOIN teacher_courses tc ON tc.kurzus_kod = c.kurzus_kod
LEFT JOIN users t ON t.eduportal_id = tc.teacher_id
WHERE c.kurzus_kod IN (
    SELECT co.kurzus_kod
    FROM course_offerings co
    WHERE co.semester_id = ?
)
GROUP BY c.kurzus_kod
ORDER BY c.name ASC;";

$kurzus_stmt = $conn->prepare($kurzusok_sql);
$kurzus_stmt->bind_param("si", $user['course_code'], $selected_semester_id);
$kurzus_stmt->execute();
$kurzusok_raw = $kurzus_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ============================================================
   🔹 2️⃣ Meghirdetett offeringek
============================================================ */
$offerings_sql = "
SELECT 
    co.id AS offering_id,
    co.kurzus_kod,
    co.semester_id,
    co.end_date,
    co.day_of_week,
    co.start_time,
    co.room,
    co.max_students,
    co.course_type,
    (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = co.id AND co.semester_id = ?) AS enrolled_count
FROM course_offerings co
WHERE co.semester_id = ?
ORDER BY co.kurzus_kod, co.course_type";

$offerings_stmt = $conn->prepare($offerings_sql);
$offerings_stmt->bind_param("ii", $selected_semester_id,$selected_semester_id);
$offerings_stmt->execute();
$offerings_raw = $offerings_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/* ============================================================
   🔹 3️⃣ Jelentkezések és teljesítések
============================================================ */
$enrolled_sql = "
SELECT offering_id
FROM enrollments 
WHERE users_eduportal_ID = ?";

$enrolled_stmt = $conn->prepare($enrolled_sql);
$enrolled_stmt->bind_param("s", $eduportal_id);
$enrolled_stmt->execute();
$already_enrolled_offerings = $enrolled_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$already_enrolled_offerings = array_column($already_enrolled_offerings, 'offering_id');

$completed_sql = "
SELECT co.kurzus_kod
FROM enrollments e
JOIN course_offerings co ON e.offering_id = co.id
WHERE e.users_eduportal_ID = ? AND e.status = 'completed'";

$completed_stmt = $conn->prepare($completed_sql);
$completed_stmt->bind_param("s", $eduportal_id);
$completed_stmt->execute();
$completed_courses = $completed_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$completed_courses = array_column($completed_courses, 'kurzus_kod');

/* ============================================================
   🔹 4️⃣ Szabadon választható logika
============================================================ */
// lekérdezzük a többi szak kötelezően választható kurzusait
$other_pc_sql = "
SELECT kurzus_kod
FROM program_courses 
WHERE tipus = 'valaszthato' AND szak_szam != ?";

$other_pc_stmt = $conn->prepare($other_pc_sql);
$other_pc_stmt->bind_param("s", $user['course_code']);
$other_pc_stmt->execute();
$other_valaszthato = $other_pc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$other_valaszthato = array_column($other_valaszthato, 'kurzus_kod');

/* ============================================================
   🔹 5️⃣ PHP-ben összeállítjuk a kurzus–offering struktúrát
============================================================ */
$courses = [];

foreach ($kurzusok_raw as $row) {
    $kurzus_kod = $row['kurzus_kod'];

    // Szabadon választható logika
    $type = $row['course_required_type'];
    if (empty($type)) {
        $type = 'szv';
    }

    $courses[$kurzus_kod] = [
        'kurzus_kod' => $kurzus_kod,
        'course_name' => $row['course_name'],
        'leiras' => $row['leiras'],
        'credit' => $row['credit'],
        'teachers' => $row['teachers'],
        'course_required_type' => $type,
        'already_completed' => in_array($kurzus_kod, $completed_courses),
        'offerings' => []
    ];
}

// Offeringek hozzárendelése
foreach ($offerings_raw as $offering) {
    $type = $offering['course_type'] ?: 'egyéb';
    $offering['already_enrolled'] = in_array($offering['offering_id'], $already_enrolled_offerings);

    if (!isset($courses[$offering['kurzus_kod']]['offerings'][$type])) {
        $courses[$offering['kurzus_kod']]['offerings'][$type] = [];
    }

    $courses[$offering['kurzus_kod']]['offerings'][$type][] = $offering;
}

