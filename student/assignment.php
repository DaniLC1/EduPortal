<?php
require_once __DIR__ . '/../PHP_Header/s_assignment.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($assignment['title']) ?> - Dolgozat</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/assignment.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <p><strong>Tantárgy:</strong> <?= htmlspecialchars($assignment['course_name']) ?></p>
        <p><strong>Elérhető:</strong> <?= $assignment['available_from'] ?><br>
            <strong>Határidő:</strong> <?= $assignment['due_date'] ?></p>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">

        <p><strong>Leírás:</strong> <?= nl2br(htmlspecialchars($assignment['description'])) ?></p>

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
        <!-- TÉMAVÁLTÓ GOMB -->
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>
<main class="layout">
    <section class="main-content">
        <h1>📝 <?= htmlspecialchars($assignment['title']) ?></h1>


        <!-- Dolgozat kérdőív -->
        <form method="POST" action="../POST/assignment_post.php">
            <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
            <input type="hidden" name="action" value="student_submit">

            <?php foreach ($questions as $q): ?>
                <fieldset class="question-block">
                    <legend><strong><?= htmlspecialchars($q['question_text']) ?></strong></legend>

                    <?php if ($q['question_type'] === 'true_false'): ?>
                        <?php foreach ($q['answers'] as $answer): ?>
                            <label>
                                <input type="radio"
                                       name="answers[<?= $q['question_id'] ?>]"
                                       value="<?= $answer['id'] ?>"
                                       required>
                                <?= htmlspecialchars($answer['answer_text']) ?>
                            </label><br>
                        <?php endforeach; ?>

                    <?php elseif ($q['question_type'] === 'multiple_choice'): ?>
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
<script src="../Scripts/scripts.js"></script>
</body>
</html>

