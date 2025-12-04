<?php
require_once __DIR__ . '/../PHP_Header/s_studies.php';
?>
<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EduPortál</title>
        <link rel="stylesheet" href="../CSS/site_style.css">
        <link rel="stylesheet" href="../CSS/studies.css">
    </head>
    <body>
        <header>
            <!-- BAL MENÜ -->
            <div class="menu">
                <div class="dropdown">
                    <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                    <div id="dropdownMenuL" class="dropdown-menu left">
                        <a href="message.php" >Üzenetek</a>
                        <a href="enrolled_courses.php">Felvett kurzusok</a>
                        <a href="#" id="active">Tanulmányok</a>
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
            <!-- Bal oldal: diagram -->
            <section class="progress-chart">
                <h2>Tanulmányi előrehaladás</h2>
                <div id="credit-data"
                     data-completed="<?= $completed_credits ?>"
                     data-total="<?= $total_credits ?>">
                </div>
                <canvas id="creditChart" width="300" height="300"></canvas>
                <div class="credit-info">
                    <p>Összes kredit: <?= $total_credits ?></p>
                    <p>Teljesített: <?= $completed_credits ?></p>
                    <p>Hiányzik: <?= $missing_credits ?></p>
                </div>
            </section>

            <!-- Jobb oldal: szűrők + tárgyak -->
            <section class="subject-panel">
                <h1 class="subject-heading">Tanulmányok: <?= $user_course ?> </h1>

                <section class="filters">
                    <input type="text" id="ss_searchInput" placeholder="🔎 Keresés tárgy névre...">

                    <select id="ss_statusFilter">
                        <option value="all">Összes</option>
                        <option value="completed">Elvégzett</option>
                        <option value="not-completed">Még nem elvégzett</option>
                    </select>

                    <select id="ss_typeFilter">
                        <option value="all">Mindegyik típus</option>
                        <option value="kot">Kötelező</option>
                        <option value="kotval">Kötelezően választható</option>
                        <option value="szabval">Szabadon választható</option>
                    </select>
                </section>

                <section class="subject-list">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="ss_subject-card"
                             data-name="<?= strtolower($subject['subject_name']) ?>"
                             data-completed="<?= $subject['completed'] ? 'completed' : 'not-completed' ?>"
                             data-type="<?=
                             $subject['subject_type'] === 'kötelező' ? 'kot' :
                                 ($subject['subject_type'] === 'kv' ? 'kotval' : 'szabval')
                             ?>">
                            <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>
                            <p>Típus:
                                <?php
                                if ($subject['subject_type'] === 'kv') {
                                    echo 'Kötelezően választható';
                                } elseif ($subject['subject_type'] === 'szv') {
                                    echo 'Szabadon választható';
                                } else {
                                    echo 'Kötelező';
                                }
                                ?>
                            </p>
                            <p>Kredit: <?= $subject['credit'] ?></p>
                            <p>Állapot:
                                <?= $subject['completed']
                                    ? '✅ Elvégezte'
                                    : '❌ Nem végezte el' ?>
                            </p>
                            <?php if ($subject['completed']): ?>
                                <p>Teljesítve: <?= htmlspecialchars($subject['completed_semester']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            </section>
        </main>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="../Scripts/scripts.js"></script>
    </body>
</html>