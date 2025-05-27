<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

if (!isset($_GET['assignment_id'])) {
    echo "Nincs kiválasztva dolgozat.";
    exit;
}

$assignment_id = (int) $_GET['assignment_id'];
global $conn;

// Dolgozat adatai
$sql_assignment = "
SELECT a.title, a.description, a.due_date, a.available_from, c.name AS course_name
FROM assignments a
JOIN course_offerings co ON a.offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
WHERE a.id = ?
";
$stmt = $conn->prepare($sql_assignment);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows !== 1) {
    echo "Nem található ilyen dolgozat.";
    exit;
}
$assignment = $result->fetch_assoc();

// Kérdések és válaszok lekérdezése
$sql_questions = "
SELECT q.id AS question_id, q.question_text, q.question_type, q.score
FROM assignment_questions q
WHERE q.assignment_id = ?
ORDER BY q.id
";
$stmt = $conn->prepare($sql_questions);
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$questions_result = $stmt->get_result();

// Kérdés-válasz párok beolvasása tömbbe
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $questions[$row['question_id']] = $row;
    $questions[$row['question_id']]['answers'] = [];
}

// Válaszlehetőségek
$question_ids = implode(",", array_keys($questions));
if ($question_ids) {
    $sql_answers = "SELECT * FROM question_answers WHERE question_id IN ($question_ids)";
    $answers_result = $conn->query($sql_answers);
    while ($answer = $answers_result->fetch_assoc()) {
        $qid = $answer['question_id'];
        $questions[$qid]['answers'][] = $answer;
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($assignment['title']) ?> - Dolgozat</title>
    <link rel="stylesheet" href="CSS/site_style.css">
    <link rel="stylesheet" href="CSS/assignment.css">
</head>
<body>
<main class="layout">
    <section class="main-content">
        <h1>📝 <?= htmlspecialchars($assignment['title']) ?></h1>
        <p><strong>Tantárgy:</strong> <?= htmlspecialchars($assignment['course_name']) ?></p>
        <p><strong>Leírás:</strong> <?= nl2br(htmlspecialchars($assignment['description'])) ?></p>
        <p><strong>Elérhető:</strong> <?= $assignment['available_from'] ?><br>
            <strong>Határidő:</strong> <?= $assignment['due_date'] ?></p>

        <!-- Dolgozat kérdőív -->
        <form method="POST" action="assignment_post.php">
            <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">

            <?php foreach ($questions as $q): ?>
                <fieldset class="question-block">
                    <legend><strong><?= htmlspecialchars($q['question_text']) ?></strong></legend>

                    <?php if ($q['question_type'] === 'multiple_choice'): ?>
                        <?php foreach ($q['answers'] as $answer): ?>
                            <label>
                                <input type="radio"
                                       name="answers[<?= $q['question_id'] ?>]"
                                       value="<?= $answer['id'] ?>"
                                       required>
                                <?= htmlspecialchars($answer['answer_text']) ?>
                            </label><br>
                        <?php endforeach; ?>

                    <?php elseif ($q['question_type'] === 'true_false'): ?>
                        <?php foreach ($q['answers'] as $answer): ?>
                            <label>
                                <input type="checkbox"
                                       name="answers[<?= $q['question_id'] ?>][<?= $answer['id'] ?>]"
                                       value="1">
                                <?= htmlspecialchars($answer['answer_text']) ?>
                            </label><br>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <p>Ismeretlen kérdéstípus.</p>
                    <?php endif; ?>
                </fieldset>
            <?php endforeach; ?>

            <button type="submit" class="send-btn">📤 Dolgozat beküldése</button>
        </form>
    </section>
</main>
</body>
</html>

