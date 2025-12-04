<?php
require_once __DIR__ . '/../PHP_Header/t_assignment.php';
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
                <input type="hidden" name="action" value="teacher_save_assignment">

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
                                    <input type="number" name="questions[<?= $q['question_id'] ?>][score]" value="<?= (int)$q['score'] ?>" min="1">
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
