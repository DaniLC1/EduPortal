<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// Tanár adatok lekérdezése
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

//Kurzus offering_id eltárolása
$offering_id = $_GET['offering_id'] ?? null;

// Dolgozat adatainak lekérdezése, ha van assignment_id
$is_edit = isset($_GET['assignment_id']);
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
    $sql_assignment = "
        SELECT a.title,
               a.description,
               a.due_date,
               a.available_from,
               a.max_attempts,
               c.name AS course_name
        FROM assignments a
        JOIN course_offerings co ON a.offering_id = co.id
        JOIN courses c ON co.kurzus_kod = c.kurzus_kod
        WHERE a.id = ?
    ";
    $stmt = $conn->prepare($sql_assignment);
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows) {
        $assignment = $result->fetch_assoc();
    }

    // Kérdések + válaszok
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

    $questions = [];
    while ($row = $questions_result->fetch_assoc()) {
        $questions[$row['question_id']] = $row;
        $questions[$row['question_id']]['answers'] = [];
    }

    if ($questions) {
        $question_ids = implode(",", array_keys($questions));
        $sql_answers = "SELECT * FROM question_answers WHERE question_id IN ($question_ids)";
        $answers_result = $conn->query($sql_answers);
        while ($answer = $answers_result->fetch_assoc()) {
            $qid = $answer['question_id'];
            $questions[$qid]['answers'][] = $answer;
        }
    }
} else {
    $questions = []; // új dolgozat esetén nincs kérdés
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= $is_edit ? "Dolgozat szerkesztése" : "Új dolgozat létrehozása" ?></title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/assignment.css">
</head>
<body>
<header>
    <!-- BAL OLDAL -->
    <div class="menu">
        <p><strong>Tantárgy:</strong> <?= htmlspecialchars($assignment['course_name']) ?: "Nincs megadva" ?></p>
        <?php if ($is_edit): ?>
            <p><strong>Elérhető:</strong> <?= $assignment['available_from'] ?><br>
                <strong>Határidő:</strong> <?= $assignment['due_date'] ?></p>
        <?php endif; ?>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <p><strong>Leírás:</strong>
            <?= $assignment['description'] ? nl2br(htmlspecialchars($assignment['description'])) : "Nincs leírás" ?>
        </p>
    </nav>

    <!-- JOBB OLDAL -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?= htmlspecialchars($user_name) ?> |
                <?= htmlspecialchars($eduportal_id) ?> |
                <?= htmlspecialchars($user_course) ?>
            </button>
        </div>
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main class="layout">
    <section class="main-content">
        <h1><?= $is_edit ? "✏️ Dolgozat szerkesztése" : "➕ Új dolgozat létrehozása" ?></h1>

        <!-- 🧾 Dolgozat alapadatok -->
        <div class="assignment-card">
            <form method="POST" action="../POST/assignment_post.php">
                <input type="hidden" name="offering_id" value="<?= $offering_id ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                <?php endif; ?>

                <div class="form-row">
                    <label for="title">Cím</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($assignment['title']) ?>" required>
                </div>

                <div class="form-row">
                    <label for="description">Leírás</label>
                    <textarea id="description" name="description" rows="3"><?= htmlspecialchars($assignment['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <label for="available_from">Elérhető ekkortól</label>
                    <input type="datetime-local" id="available_from" name="available_from" value="<?= htmlspecialchars($assignment['available_from']) ?>">
                </div>

                <div class="form-row">
                    <label for="due_date">Határidő</label>
                    <input type="datetime-local" id="due_date" name="due_date" value="<?= htmlspecialchars($assignment['due_date']) ?>">
                </div>

                <div class="form-row">
                    <label for="max_attempts">Maximális próbálkozások száma:</label>
                    <input type="number" id="max_attempts" name="max_attempts" value="<?= htmlspecialchars($assignment['max_attempts']) ?>">
                </div>

                <hr>

                <!-- 🔍 Kérdések -->
                <h2>Kérdések</h2>
                <div id="question-container">
                    <?php if ($is_edit && $questions): ?>
                        <?php foreach ($questions as $q): ?>
                            <div class="question-card">
                                <button type="button" class="remove-question">❌</button>
                                <textarea id="question-card-question"
                                          name="questions[<?= $q['question_id'] ?>][text]"
                                          placeholder="Kérdés szövege..."
                                          rows="1"><?= htmlspecialchars($q['question_text']) ?></textarea>

                                <div class="question-meta">
                                    <label>Típus:</label>
                                    <select name="questions[<?= $q['question_id'] ?>][type]">
                                        <option value="multiple_choice" <?= $q['question_type'] === 'multiple_choice' ? 'selected' : '' ?>>Feleletválasztós</option>
                                        <option value="true_false" <?= $q['question_type'] === 'true_false' ? 'selected' : '' ?>>Igaz / Hamis</option>
                                    </select>

                                    <label>Pontszám:</label>
                                    <input type="number" name="questions[<?= $q['question_id'] ?>][score]" value="<?= (int)$q['score'] ?>" min="1" step="1">
                                </div>

                                <?php foreach ($q['answers'] as $a): ?>
                                    <div class="answer-card">
                                        <input
                                                type="text"
                                                name="questions[<?= $q['question_id'] ?>][answers][<?= $a['id'] ?>][text]"
                                                value="<?= htmlspecialchars($a['answer_text']) ?>"
                                                placeholder="Válasz..."
                                        >
                                        <label>
                                            <input
                                                    type="checkbox"
                                                    name="questions[<?= $q['question_id'] ?>][answers][<?= $a['id'] ?>][is_correct]"
                                                    value="1"
                                                    <?= $a['is_correct'] ? 'checked' : '' ?>
                                            > Helyes
                                        </label>
                                        <button type="button" class="remove-answer">❌</button>
                                    </div>
                                <?php endforeach; ?>
                                <button type="button" class="add-answer">➕ Válasz hozzáadása</button>

                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Nincsenek kérdések hozzáadva ehhez a dolgozathoz.</p>
                    <?php endif; ?>
                </div>

                <!-- Gombok -->
                <div class="actions">
                    <button type="button" class="add-btn">➕ Új kérdés hozzáadása</button><br>
                    <button type="submit" class="send-btn">💾 Mentés</button>
                    <button type="button" class="cancel-btn" onclick="cancelEdit()">🚫 Mégse</button>
                </div>
            </form>
        </div>
    </section>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
