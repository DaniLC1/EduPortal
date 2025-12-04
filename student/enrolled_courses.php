<?php
require_once __DIR__ . '/../PHP_Header/s_enrolled_courses.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <a id="active" href="#">Felvett kurzusok</a>
                    <a href="studies.php" >Tanulmányok</a>
                </div>
            </div>
        </div>

        <!-- NAVIGÁCIÓ -->
        <nav class="main-nav">
            <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
            <a href="courses.php" ><span class="icon">🧑‍🏫</span> Kurzusok</a>
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
    <main>
        <h1>Felvett kurzusok</h1>

        <!--Szűrők -->
        <section class="filters">
            <label for="sec_semesterFilter">Félév:</label>
            <select id="sec_semesterFilter">
                <option value="all">Összes</option>
                <?php
                while ($row = $semester_result->fetch_assoc()) {
                    echo "<option value=\"" . htmlspecialchars($row['label']) . "\">" . htmlspecialchars($row['label']) . "</option>";
                }
                ?>
            </select>

            <label for="sec_typeFilter">Típus:</label>
            <select id="sec_typeFilter">
                <option value="all">Összes</option>
                <option value="kotelezo">Kötelező</option>
                <option value="valaszthato">Kötelezően választható</option>
                <option value="szv">Szabadon választható</option>
            </select>

            <input type="text" id="sec_searchInput" placeholder="Keresés tárgy nevére...">
        </section>

        <section id="courseList" class="course-grid">
            <?php foreach ($enrolled_subjects as $subject): ?>
                <div class="sec_course-card"
                     data-semester="<?= htmlspecialchars($subject['semester_label']); ?>"
                     data-type="<?= htmlspecialchars($subject['subject_type']); ?>"
                     data-name="<?= strtolower(htmlspecialchars($subject['subject_name'])); ?>">

                    <div class="card-header">
                        <h3><?= htmlspecialchars($subject['subject_name']); ?></h3>
                        <span class="code"><?= htmlspecialchars($subject['kurzus_kod']); ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Kredit:</strong> <?= htmlspecialchars($subject['credit']); ?></p>
                        <p><strong>Típus:</strong>
                            <?php
                            if ($subject['subject_type'] === 'valaszthato') {
                                echo 'Kötelezően választható';
                            } elseif ($subject['subject_type'] === 'szv') {
                                echo 'Szabadon választható';
                            } else {
                                echo 'Kötelező';
                            }
                            ?>
                        </p>
                        <p><strong>Félév:</strong> <?= htmlspecialchars($subject['semester_label']); ?></p>
                        <p class="status <?= $subject['enrollment_status']; ?>">
                            <strong>Státusz:</strong>
                            <?php
                            echo match ($subject['enrollment_status']) {
                                'completed' => '✔️ Teljesítve',
                                'enrolled' => '⏳ Folyamatban',
                                'failed' => '❌ Nem teljesítve',
                                default => 'ℹ️ Ismeretlen',
                            };
                            ?>
                        </p>
                        <p><strong>Eredmény:</strong>
                            <?php
                            if ($subject['enrollment_status'] === 'completed' or $subject['enrollment_status'] === 'failed' ) {
                                echo $subject['grade'];
                            } else {
                                echo '-';
                            }
                            ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
    <script src="../Scripts/scripts.js"></script>
</body>
</html>
