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

// 🔹 Kurzus offering_id eltárolása
$offering_id = $_GET['offering_id'] ?? null;

// 🔹 Dolgozat adatainak lekérdezése, ha van assignment_id
$is_edit = isset($_GET['assignment_id']);


// 🔹 Alapértelmezett üres dolgozat tömb, új dolgozat létrehozásához
$assignment = [
    'title' => '',
    'description' => '',
    'available_from' => '',
    'due_date' => '',
    'max_attempts' => '',
    'course_name' => ''
];

if ($is_edit) {
    $assignment_id = (int) $_GET['assignment_id'];
    // 🔹 Meglévő dolgozat adatai lekérdezése
    $sql_assignment = "
        SELECT a.title,
               a.description,
               a.available_from,
               a.due_date,
               a.max_attempts,
               c.name AS course_name
        FROM assignments a
        JOIN course_offerings co ON a.offering_id = co.id
        JOIN courses c ON co.kurzus_kod = c.kurzus_kod
        WHERE a.id = ?";

    $stmt = $conn->prepare($sql_assignment);
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $assignment = $result->fetch_assoc();
    }

    // ========================================================
    // 🔹 Kérdések + válaszok lekérdezése a dolgozathoz
    // ========================================================
    $sql_questions = "
        SELECT aq.id AS question_id, 
               aq.question_text, 
               aq.question_type, 
               aq.score
        FROM assignment_questions aq
        WHERE aq.assignment_id = ?
        ORDER BY aq.id";

    $stmt = $conn->prepare($sql_questions);
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $questions_result = $stmt->get_result();

    // 🔹 Kérdések tömb inicializálása
    $questions = [];
    while ($row = $questions_result->fetch_assoc()) {
        $questions[$row['question_id']] = $row;
        $questions[$row['question_id']]['answers'] = [];
    }

    // 🔹 Ha vannak kérdések, lekérdezzük hozzájuk a válaszlehetőségeket
    if ($questions) {
        $question_ids = implode(",", array_keys($questions));
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

// ========================================================
// 🔹 Új dolgozat létrehozása esetén nincs kérdés
// ========================================================
} else {
    $questions = [];
}
