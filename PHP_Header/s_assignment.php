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

if (!isset($_GET['assignment_id'])) {
    header("Location: ../student/courses.php?error=Nincs dolgozat kiválasztva!");
    exit;
}

$assignment_id = (int) $_GET['assignment_id'];
global $conn;

/* ============================================================
   🔹 Felhasználó alapadatok lekérése
============================================================ */
$user_sql = "
SELECT 
    u.name,
    p.name AS szak_nev,
    u.financing_type
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
$user_financing_type = $user['financing_type'] ?? "N/A";

/* ============================================================
   🔹 Dolgozat adatai
============================================================ */
$sql_assignment = "
SELECT a.title,
       a.description,
       a.due_date,
       a.available_from,
       c.name AS course_name
FROM assignments a
JOIN course_offerings co ON a.offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
WHERE a.id = ?";

$stmt = $conn->prepare($sql_assignment);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../student/courses.php?error=Nem található ilyen dolgozat!");
    exit;
}

$assignment = $result->fetch_assoc();

/* ============================================================
   🔹 Kérdések és válaszok lekérdezése
============================================================ */
$sql_questions = "
SELECT q.id AS question_id,
       q.question_text,
       q.question_type,
       q.score
FROM assignment_questions q
WHERE q.assignment_id = ?
ORDER BY q.id";

$stmt = $conn->prepare($sql_questions);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$questions_result = $stmt->get_result();

// 🔹 Kérdés-válasz párok beolvasása tömbbe
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[$row['question_id']] = $row;
    $questions[$row['question_id']]['answers'] = [];
}

/* ============================================================
   🔹 Válaszlehetőségek
============================================================ */
$question_ids = implode(",", array_keys($questions));
if ($question_ids) {
    $sql_answers = "
    SELECT * 
    FROM question_answers 
    WHERE question_id IN ($question_ids)";

    $answers_result = $conn->query($sql_answers);
    while ($answer = $answers_result->fetch_assoc()) {
        $qid = $answer['question_id'];
        $questions[$qid]['answers'][] = $answer;
    }
}
