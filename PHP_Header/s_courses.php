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

// Kiválasztott félév
$selected_semester_id = $_GET['semester_id'] ?? $current_semester_id;

// 🔹 Összes félév lekérdezése
$semesters_sql = "
SELECT id,
       label 
FROM semesters 
ORDER BY start_date DESC";

$semesters = $conn->query($semesters_sql)->fetch_all(MYSQLI_ASSOC);

/* ============================================================
   🔹 Értesítések lekérdezése
============================================================ */
$notif_sql = "
SELECT nr.read_at,
       nr.notification_id, 
       n.noti_type, 
       n.created_at,
       c.name AS course_name
FROM notification_reads nr
JOIN notifications n ON nr.notification_id = n.id
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
WHERE nr.users_eduportal_id = ? AND nr.read_at IS NULL
ORDER BY n.created_at DESC";

$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("s", $eduportal_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();

/* ============================================================
   🔹 Összes kurzus
============================================================ */
$courses_sql = "
SELECT  co.id AS offering_id,
        c.name AS course_name,
        c.kurzus_kod,
        c.leiras AS description,
        co.semester_id,
        s.label AS semester_label
FROM enrollments e
JOIN course_offerings co ON e.offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN semesters s ON co.semester_id = s.id
WHERE e.users_eduportal_ID = ? 
  AND co.semester_id = ?
ORDER BY c.name ASC ";

$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

/* ============================================================
   🔹 Kurzusokhoz tartozó hirdetmények
============================================================ */
$hirdetmeny_sql = "
SELECT n.message, 
       n.noti_type,
       n.created_at,
       c.name AS course_name,
       u.name AS user_name
FROM notifications n
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u on n.users_eduportal_id = u.eduportal_id
WHERE e.users_eduportal_ID = ? AND noti_type = 'hirdetmeny' AND co.semester_id = ?
ORDER BY created_at DESC ";

$hirdetmeny_stmt = $conn->prepare($hirdetmeny_sql);
$hirdetmeny_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$hirdetmeny_stmt->execute();
$hirdetmeny_result = $hirdetmeny_stmt->get_result();

$hirdsByCourse  = [];
while ($h = $hirdetmeny_result->fetch_assoc()) {
    $courseName = $h['course_name'];
    $hirdsByCourse[$courseName][] = $h;
}

/* ============================================================
   🔹 Kurzusokhoz tartozó fórum hozzászólások
============================================================ */
$forum_sql = "
SELECT n.message,
       n.noti_type,
       n.created_at,
       c.name AS course_name,
       n.updated_at,
       u.name AS user_name,
       n.users_eduportal_id,
       n.course_offering_id,
       n.id
FROM notifications n
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u on n.users_eduportal_id = u.eduportal_id
WHERE e.users_eduportal_ID = ? AND noti_type = 'forum' AND co.semester_id = ?
ORDER BY created_at DESC ";

$forum_stmt = $conn->prepare($forum_sql);
$forum_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$forum_stmt->execute();
$forum_result = $forum_stmt->get_result();

$forumsByCourse = [];
while ($f = $forum_result->fetch_assoc()) {
    $courseName = $f['course_name'];
    $forumsByCourse[$courseName][] = $f;
}

/* ============================================================
   🔹 Kurzusokhoz tartozó dolgozatok
============================================================ */
$assignment_sql = "
SELECT 
    a.title,
    a.due_date,
    a.description,
    c.name AS course_name,
    a.id,
    a.max_attempts,
    COALESCE(SUM(aq.score), 0) AS max_score
FROM assignments a
JOIN course_offerings co ON a.offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
LEFT JOIN assignment_questions aq ON aq.assignment_id = a.id
WHERE e.users_eduportal_ID = ? 
  AND co.semester_id = ?
GROUP BY a.id, a.title, a.due_date, a.description, c.name, a.max_attempts
ORDER BY a.due_date ASC";

$assignment_stmt = $conn->prepare($assignment_sql);
$assignment_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$assignment_stmt->execute();
$assignment_result = $assignment_stmt->get_result();

$assignmentsByCourse = [];
while ($a = $assignment_result->fetch_assoc()) {
    $courseName = $a['course_name'];
    $assignmentsByCourse[$courseName][] = $a;
}

/* ============================================================
   🔹 Diák korábbi beadásai és próbálkozások száma
============================================================ */
$submission_sql = "
SELECT 
    s.assignment_id,
    s.id AS submission_id,
    s.submitted_at,
    s.score,
    a.max_attempts
FROM assignment_submissions s
JOIN assignments a ON a.id = s.assignment_id
WHERE s.users_eduportal_ID = ?
ORDER BY s.assignment_id, s.submitted_at ASC";

$submission_stmt = $conn->prepare($submission_sql);
$submission_stmt->bind_param("s", $eduportal_id);
$submission_stmt->execute();
$submission_result = $submission_stmt->get_result();

$submissions_by_assignment = [];
while ($s = $submission_result->fetch_assoc()) {
    $aid = $s['assignment_id'];
    if (!isset($submissions_by_assignment[$aid])) {
        $submissions_by_assignment[$aid] = [];
    }
    $submissions_by_assignment[$aid][] = $s;
}
