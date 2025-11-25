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


/* ============================================================
   🔹 Felhasználó kurzusai
============================================================ */
$offerings_sql = "
SELECT 
    co.id AS offering_id,
    co.kurzus_kod,
    c.name AS course_name,
    s.label AS semester_label
FROM teacher_courses tc
JOIN course_offerings co ON tc.kurzus_kod = co.kurzus_kod
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE tc.teacher_id = ?
ORDER BY s.label DESC, c.name ASC
";
$offerings_stmt = $conn->prepare($offerings_sql);
$offerings_stmt->bind_param("s", $eduportal_id);
$offerings_stmt->execute();
$offerings_result = $offerings_stmt->get_result();

$offerings = [];
while ($row = $offerings_result->fetch_assoc()) {
    $offerings[] = $row;
}
$offerings_stmt->close();

$no_offerings = empty($offerings);

/* ============================================================
   🔹 Felhasználó kurzusaira jelentkezett diákok
============================================================ */
$enrollments_sql = "
SELECT 
    e.offering_id,
    e.users_eduportal_id AS student_id,
    u.name AS student_name,
    c.kurzus_kod AS subject_code,
    c.name AS subject_name,
    s.label AS semester_label,
    e.status AS enrollment_status
FROM enrollments e
JOIN users u ON u.eduportal_id = e.users_eduportal_id
JOIN course_offerings co ON co.id = e.offering_id
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE co.kurzus_kod IN (
    SELECT kurzus_kod FROM teacher_courses WHERE teacher_id = ?
)
ORDER BY s.label DESC, u.name ASC, c.name ASC
";

$enrollments_stmt = $conn->prepare($enrollments_sql);
$enrollments_stmt->bind_param("s", $eduportal_id);
$enrollments_stmt->execute();
$enrollments_result = $enrollments_stmt->get_result();

$enrollments = [];
while ($r = $enrollments_result->fetch_assoc()) {
    $enrollments[] = $r;
}
$enrollments_stmt->close();


/* ============================================================
   🔹 Felhasználó kurzusaira jelentkezett diákok dolgozatai
============================================================ */
$assignments_sql = "
SELECT 
    a.id AS assignment_id,
    a.offering_id,
    a.title,
    a.max_attempts,
    a.due_date
FROM assignments a
JOIN course_offerings co ON co.id = a.offering_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY a.offering_id, a.due_date ASC
";
$assignments_stmt = $conn->prepare($assignments_sql);
$assignments_stmt->bind_param("s", $eduportal_id);
$assignments_stmt->execute();
$assignments_result = $assignments_stmt->get_result();

$assignments = [];
while ($a = $assignments_result->fetch_assoc()) {
    $assignments[$a['offering_id']][] = $a;
}
$assignments_stmt->close();

/* ============================================================
   🔹 Felhasználó kurzusaira jelentkezett diákok dolgozatainak
      lekérdezése(beadott válaszok, beadás dátuma, stb)
============================================================ */
$subs_sql = "
SELECT 
    s.id AS submission_id,
    s.assignment_id,
    s.users_eduportal_id AS student_id,
    s.submitted_at,
    s.score
FROM assignment_submissions s
JOIN assignments a ON a.id = s.assignment_id
JOIN course_offerings co ON co.id = a.offering_id
JOIN teacher_courses tc ON tc.kurzus_kod = co.kurzus_kod
WHERE tc.teacher_id = ?
ORDER BY s.assignment_id, s.submitted_at ASC
";
$subs_stmt = $conn->prepare($subs_sql);
$subs_stmt->bind_param("s", $eduportal_id);
$subs_stmt->execute();
$subs_result = $subs_stmt->get_result();

$submissions = [];
while ($s = $subs_result->fetch_assoc()) {
    $submissions[$s['assignment_id']][] = $s;
}
$subs_stmt->close();

/* ============================================================
   🔹 Tárgy szűrőhöz szükséges lekérdezés
============================================================ */
// 1. Tárgyak (offeringek) a tanárhoz
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
   🔹 Félév szűrőhöz szükséges lekérdezés
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

?>
