<?php
session_start();
require_once __DIR__. '/../connection.php';

if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

$submission_id = (int) $_GET['submission_id'];

// Felhasználó adatainak lekérdezése (név és szak)
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

// === 1️⃣ Beadás adatainak lekérdezése ===
$submission_sql = "
SELECT 
    s.id AS submission_id,
    s.assignment_id,
    s.users_eduportal_id AS student_id,
    s.graded_at,
    u.name AS student_name,
    a.title AS assignment_title,
    a.description,
    a.due_date,
    a.available_from,
    c.name AS course_name
FROM assignment_submissions s
JOIN users u ON u.eduportal_id = s.users_eduportal_id
JOIN assignments a ON a.id = s.assignment_id
JOIN course_offerings co ON a.offering_id = co.id
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
WHERE s.id = ?";

$submission_stmt = $conn->prepare($submission_sql);
$submission_stmt->bind_param("i", $submission_id);
$submission_stmt->execute();
$submission_result = $submission_stmt->get_result();

if ($submission_result->num_rows !== 1) {
    echo "Nem található ilyen beadás.";
    exit;
}

$submission = $submission_result->fetch_assoc();
$assignment_id = $submission['assignment_id'];
$student_id = $submission['student_id'];


// === 2️⃣ Kérdések lekérdezése ===
$questions_sql = "
SELECT 
    q.id AS question_id,
    q.question_text,
    q.question_type,
    q.score
FROM assignment_questions q
WHERE q.assignment_id = ?
ORDER BY q.id";

$questions_stmt = $conn->prepare($questions_sql);
$questions_stmt->bind_param("i", $assignment_id);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[$row['question_id']] = $row;
    $questions[$row['question_id']]['answers'] = [];
}
$questions_stmt->close();


// === 3️⃣ Válaszlehetőségek lekérdezése (egységesen, prepare-rel) ===
if (!empty($questions)) {
    $question_ids = implode(",", array_keys($questions));

    $answers_sql = "
    SELECT 
        qa.id AS answer_id,
        qa.question_id,
        qa.answer_text,
        qa.is_correct
    FROM question_answers qa
    WHERE qa.question_id IN ($question_ids)";

    $answers_stmt = $conn->prepare($answers_sql);
    $answers_stmt->execute();
    $answers_result = $answers_stmt->get_result();

    while ($answer = $answers_result->fetch_assoc()) {
        $qid = $answer['question_id'];
        $questions[$qid]['answers'][] = $answer;
    }
    $answers_stmt->close();
}


// === 4️⃣ Diák választásainak lekérdezése ===
$student_answers_sql = "
SELECT 
    selected_answer_id
FROM submission_answers
WHERE submission_id = ?";

$student_answers_stmt = $conn->prepare($student_answers_sql);
$student_answers_stmt->bind_param("i", $submission_id);
$student_answers_stmt->execute();
$student_answers_result = $student_answers_stmt->get_result();

$student_answers = [];
while ($row = $student_answers_result->fetch_assoc()) {
    $student_answers[] = $row['selected_answer_id'];
}
$student_answers_stmt->close();
