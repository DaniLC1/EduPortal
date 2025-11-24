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
   🔹 Felhasználó taulmányi előrehaladásához létrehozott lekérdezések
      (plussz a szűrők is ebből dolgoznak)
============================================================ */
$all_courses_sql = <<<SQL
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'kötelező' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc ON pc.szak_szam = p.szak_szam AND pc.tipus = 'kotelezo'
    JOIN courses c ON c.kurzus_kod = pc.kurzus_kod
    WHERE u.eduportal_id = ?
)
UNION
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'kv' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )
            THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc ON pc.szak_szam = p.szak_szam AND pc.tipus = 'valaszthato'
    JOIN courses c ON c.kurzus_kod = pc.kurzus_kod
    WHERE u.eduportal_id = ?
)
UNION
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'szv' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )
            THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc_other ON pc_other.tipus = 'valaszthato'
    JOIN courses c ON c.kurzus_kod = pc_other.kurzus_kod
    LEFT JOIN program_courses pc_this 
        ON pc_this.kurzus_kod = c.kurzus_kod AND pc_this.szak_szam = p.szak_szam
    WHERE u.eduportal_id = ?
      AND pc_this.kurzus_kod IS NULL
)
ORDER BY subject_name
SQL;

$all_courses_stmt = $conn->prepare($all_courses_sql);
$all_courses_stmt->bind_param("sss", $eduportal_id, $eduportal_id, $eduportal_id);
$all_courses_stmt->execute();
$all_courses_result = $all_courses_stmt->get_result();
$subjects = $all_courses_result->fetch_all(MYSQLI_ASSOC);

$total_credits = 0;
$completed_credits = 0;

foreach ($subjects as $subject) {
    $total_credits += $subject['credit'];
    if ($subject['completed']) {
        $completed_credits += $subject['credit'];
    }
}
$missing_credits = $total_credits - $completed_credits;
?>
