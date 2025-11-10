<?php
session_start();
require_once __DIR__. '/../connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php");
    exit;
}

// 🧑‍🏫 Csak tanárok léphetnek be
if ($_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$submission_id = (int) $_GET['submission_id'];
$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// Felhasználó adatainak lekérdezése (név és szak)
$user_sql = "
SELECT name 
FROM users
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 1) {
    $user = $user_result->fetch_assoc();
    $user_name = $user['name'];
    $user_course = "Tanár";
} else {
    $user_name = "Ismeretlen";
    $user_course = "N/A";
}

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
WHERE s.id = ?
";

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
ORDER BY q.id
";

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
    WHERE qa.question_id IN ($question_ids)
    ";

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
WHERE submission_id = ?
";

$student_answers_stmt = $conn->prepare($student_answers_sql);
$student_answers_stmt->bind_param("i", $submission_id);
$student_answers_stmt->execute();
$student_answers_result = $student_answers_stmt->get_result();

$student_answers = [];
while ($row = $student_answers_result->fetch_assoc()) {
    $student_answers[] = $row['selected_answer_id'];
}
$student_answers_stmt->close();

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($submission['assignment_title']) ?> - Megtekintés</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/assignment.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <p><strong>Tantárgy:</strong> <?= htmlspecialchars($submission['course_name']) ?></p>
        <p><strong>Hallgató:</strong> <?= htmlspecialchars($submission['student_name']) ?> (<?= htmlspecialchars($student_id) ?>)</p>
        <p><strong>Kitöltés dátuma:</strong> <?= htmlspecialchars($submission['graded_at']) ?></p>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <p><strong>Leírás:</strong> <?= nl2br(htmlspecialchars($submission['description'])) ?></p>
    </nav>

    <!-- JOBB OLDALI MENÜ -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?php echo htmlspecialchars($user_name); ?> |
                <?php echo htmlspecialchars($eduportal_id); ?> |
                <?php echo htmlspecialchars($user_course); ?>
            </button>
        </div>
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main class="layout">
    <section class="main-content">
        <h1>📝 <?= htmlspecialchars($submission['assignment_title']) ?> – Hallgatói beadás</h1>

        <?php foreach ($questions as $q): ?>
            <fieldset class="question-block">
                <legend><strong><?= htmlspecialchars($q['question_text']) ?></strong></legend>

                <?php if ($q['question_type'] === 'multiple_choice'): ?>
                    <?php foreach ($q['answers'] as $a): ?>
                        <label>
                            <input type="radio"
                                   disabled
                                <?= in_array($a['answer_id'], $student_answers) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($a['answer_text']) ?>
                        </label><br>
                    <?php endforeach; ?>

                <?php elseif ($q['question_type'] === 'true_false'): ?>
                    <?php foreach ($q['answers'] as $a): ?>
                        <label>
                            <input type="checkbox"
                                   disabled
                                <?= in_array($a['answer_id'], $student_answers) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($a['answer_text']) ?>
                        </label><br>
                    <?php endforeach; ?>

                <?php else: ?>
                    <p>Ismeretlen kérdéstípus.</p>
                <?php endif; ?>
            </fieldset>
        <?php endforeach; ?>
    </section>
    <div class="actions">
        <button onclick="window.location.href='assignment_result.php';" class="close-btn">
            ⬅️ Bezárás / Vissza
        </button>
    </div>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
