<?php
require_once __DIR__ . '/../PHP_Header/t_view_submission.php';
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

                <?php if ($q['question_type'] === 'true_false'): ?>
                    <?php foreach ($q['answers'] as $a): ?>
                        <label>
                            <input type="radio"
                                   disabled
                                <?= in_array($a['answer_id'], $student_answers) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($a['answer_text']) ?>
                        </label><br>
                    <?php endforeach; ?>

                <?php elseif ($q['question_type'] === 'multiple_choice'): ?>
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
