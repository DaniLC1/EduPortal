<?php
require_once __DIR__ . '/../PHP_Header/t_sutdent_complete.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/enrolled_courses.css">
</head>
<body>
<header>
    <!-- BAL MENÜ -->
    <div class="menu">
        <div class="dropdown">
            <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
            <div id="dropdownMenuL" class="dropdown-menu left">
                <a href="message.php" >Üzenetek</a>
                <a href="assignment_result.php">Eredmények</a>
                <a href="#" id="active">Lezárások</a>
            </div>
        </div>
    </div>

    <!-- NAVIGÁCIÓ -->
    <nav class="main-nav">
        <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
        <a href="courses.php"><span class="icon">🧑‍🏫</span> Kurzusok</a>
        <a href="request.php"><span class="icon">📄</span> Kérelmek</a>
    </nav>

    <!-- JOBB OLDALI MENÜ -->
    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?php echo htmlspecialchars($user_name); ?> |
                <?php echo htmlspecialchars($eduportal_id); ?> |
                <?php echo htmlspecialchars($user_course); ?>
            </button>
            <div id="dropdownMenuR" class="dropdown-menu right">
                <a href="profile.php">Beállítások</a>
                <a href="../logout.php">Kijelentkezés</a>
            </div>
        </div>
        <!-- TÉMAVÁLTÓ GOMB -->
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main class="layout">
    <h1>Diákok jegyeinek lezárása</h1>
    <?php include __DIR__ . '/../feedback.php'; ?>

    <section class="filters">
        <label for="tsc_semesterFilter">Félév:</label>
        <select id="tsc_semesterFilter">
            <option value="all">Összes</option>
            <?php
            while ($row = $semester_result->fetch_assoc()) {
                echo "<option value=\"" . htmlspecialchars($row['label']) . "\">" . htmlspecialchars($row['label']) . "</option>";
            }
            ?>
        </select>

        <label for="tsc_courseFilter">Tárgy:</label>
        <select id="tsc_courseFilter">
            <option value="all">Összes</option>
            <?php while ($row = $filter_courses_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['offering_id']) ?>">
                    <?= htmlspecialchars($row['label']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="text" id="tsc_searchInput" placeholder="Keresés diák nevére...">
    </section>

    <?php if (empty($students)): ?>
        <p>Jelenleg nincsenek beiratkozott hallgatók.</p>
    <?php else: ?>
        <section id="courseList" class="course-grid">
            <?php foreach ($students as $st): ?>
                <div class="tsc_course-card"
                     data-semester="<?= htmlspecialchars($st['semester_label']) ?>"
                     data-code="<?= htmlspecialchars($st['offering_id']) ?>"
                     data-name="<?= strtolower(htmlspecialchars($st['student_name'])) ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($st['student_name']) ?></h3>
                        <span class="code"><?= htmlspecialchars($st['student_id']) ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Tárgy:</strong> <?= htmlspecialchars($st['subject_name']) ?></p>
                        <p><strong>Kód:</strong> <?= htmlspecialchars($st['subject_code']) ?></p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($st['semester_label']) ?></p>
                        <p><strong>Felvétel dátuma:</strong> <?= htmlspecialchars($st['enrolled_at']) ?></p>
                        <p><strong>Státusz:</strong>
                            <?php
                            switch ($st['enrollment_status']) {
                                case 'completed': echo '<span class="status completed">Elvégezte</span>'; break;
                                case 'failed': echo '<span class="status failed">Megbukott</span>'; break;
                                default: echo '<span class="status enrolled">Felvette</span>';
                            }
                            ?>
                        </p>

                        <p><strong>Lezárás dátuma:</strong>
                            <?= $st['current_grade'] !== null ? htmlspecialchars($st['completed_at']) : '<em>-</em>' ?>
                        </p>

                        <p><strong>Jegy:</strong>
                            <?= $st['current_grade'] !== null ? htmlspecialchars($st['current_grade']) : '<em>-</em>' ?>
                        </p>

                        <form method="post" action="../POST/complete_post.php" class="grade-form">
                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($st['student_id']) ?>">
                            <input type="hidden" name="offering_id" value="<?= htmlspecialchars($st['offering_id']) ?>">

                            <label for="grade_<?= htmlspecialchars($st['student_id']) ?>">Új jegy:</label>
                            <select name="grade" id="grade_<?= htmlspecialchars($st['student_id']) ?>" >
                                <option value="" <?= empty($st['current_grade']) ? 'selected' : '' ?>></option>
                                <option value="Elégtelen" <?= ($st['current_grade'] === 'Elégtelen') ? 'selected' : '' ?>>Elégtelen</option>
                                <option value="Elégséges" <?= ($st['current_grade'] === 'Elégséges') ? 'selected' : '' ?>>Elégséges</option>
                                <option value="Közepes" <?= ($st['current_grade'] === 'Közepes') ? 'selected' : '' ?>>Közepes</option>
                                <option value="Jó" <?= ($st['current_grade'] === 'Jó') ? 'selected' : '' ?>>Jó</option>
                                <option value="Kiváló" <?= ($st['current_grade'] === 'Kiváló') ? 'selected' : '' ?>>Kiváló</option>
                            </select>

                            <button type="submit" class="fill-btn">Lezárás</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>


<script src="../Scripts/scripts.js"></script>
</body>
</html>
