<?php
require_once __DIR__ . '/../PHP_Header/t_assignment_result.php';
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
                <a href="#" id="active">Eredmények</a>
                <a href="student_complete.php">Lezárások</a>
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
    <h1>📚 Diákok és dolgozataik</h1>
    <section class="filters">

        <label for="tar_semesterFilter">Félév:</label>
        <select id="tar_semesterFilter">
            <option value="all">Összes</option>
            <?php
            while ($row = $semester_result->fetch_assoc()) {
                echo "<option value=\"" . htmlspecialchars($row['label']) . "\">" . htmlspecialchars($row['label']) . "</option>";
            }
            ?>
        </select>

        <label for="tar_courseFilter">Tárgy:</label>
        <select id="tar_courseFilter">
            <option value="all">Összes</option>
            <?php while ($row = $filter_courses_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($row['offering_id']) ?>">
                    <?= htmlspecialchars($row['label']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="text" id="tar_searchInput" placeholder="Keresés diák nevére...">
    </section>

    <?php if ($no_offerings): ?>
        <p>Nincs hozzád rendelt kurzus.</p>
    <?php elseif (empty($enrollments)): ?>
        <p>Jelenleg nincsenek beiratkozott hallgatók.</p>
    <?php else: ?>
        <section id="courseList" class="course-grid">
            <?php foreach ($enrollments as $en): ?>
                <div class="tar_course-card"
                     data-semester="<?= htmlspecialchars($en['semester_label']) ?>"
                     data-code="<?= htmlspecialchars($en['offering_id']) ?>"
                     data-name="<?= strtolower(htmlspecialchars($en['student_name'])) ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($en['student_name']) ?></h3>
                        <span class="code"><?= htmlspecialchars($en['subject_code']) ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Tárgy:</strong> <?= htmlspecialchars($en['subject_name']) ?></p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($en['semester_label']) ?></p>
                        <details>
                            <summary class="assignment-summary">📝 Dolgozatok</summary>
                            <div class="assignment-details">
                                <?php
                                $student_assignments = $assignments[$en['offering_id']] ?? [];
                                if (empty($student_assignments)): ?>
                                    <p>Nincsenek dolgozatok ehhez a kurzushoz.</p>
                                <?php else: ?>
                                    <ul>
                                        <?php foreach ($student_assignments as $a): ?>
                                            <?php
                                            $subs_for_student = array_filter($submissions[$a['assignment_id']] ?? [], function($s) use ($en) {
                                                return $s['student_id'] === $en['student_id'];
                                            });
                                            ?>
                                            <li class="assignment-item">
                                                <details>
                                                    <summary>
                                                        <div class="assignment-header">
                                                            <div class="assignment-title">
                                                                <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                                                                <span class="date-range">
                                                                    Határidő: <?= date('Y.m.d', strtotime($a['due_date'])) ?>
                                                                </span>
                                                            </div>
                                                            <div class="assignment-stats">
                                                                Próbálkozások: <?= count($subs_for_student) ?> / <?= htmlspecialchars($a['max_attempts']) ?>
                                                            </div>
                                                        </div>
                                                    </summary>

                                                    <div class="assignment-details">
                                                        <?php if (!empty($subs_for_student)): ?>
                                                            <ul class="attempt-list">
                                                                <?php foreach ($subs_for_student as $i => $s): ?>
                                                                    <li>
                                                                        <?= $i + 1 ?>. próbálkozás —
                                                                        <?= date('Y.m.d H:i', strtotime($s['submitted_at'])) ?> |
                                                                        Eredmény: <?= $s['score'] ?? '-' ?> pont
                                                                        <form method="get" action="view_submission.php" style="display:inline;">
                                                                            <input type="hidden" name="submission_id" value="<?= (int)$s['submission_id'] ?>">
                                                                            <button type="submit" class="fill-btn">Megnyitás</button>
                                                                        </form>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php else: ?>
                                                            <p>Még nincs beadás.</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </details>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
