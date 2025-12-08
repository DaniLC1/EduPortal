<?php
require_once __DIR__ . '/../PHP_Header/s_course_offering.php';
?>
<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EduPortál</title>
        <link rel="stylesheet" href="../CSS/site_style.css">
        <link rel="stylesheet" href="../CSS/course_offering.css">
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
                        <a href="studies.php" >Tanulmányok</a>
                    </div>
                </div>
            </div>

            <!-- NAVIGÁCIÓ -->
            <nav class="main-nav">
                <a id="active" href="#"><span class="icon">📘</span> Tárgyfelvétel</a>
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
            <h1>Elérhető kurzusok</h1>
            <?php include __DIR__ . '/../feedback.php'; ?>

            <!--Szűrők-->
            <section class="filters">
                <!-- 🔹 Szűrők -->
                <form method="get" class="filters">
                    <label>Félév:</label>
                    <select name="semester_id">
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_semester_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Szűrés</button>
                </form>

                <label for="sco_typeFilter">Típus:</label>
                <select id="sco_typeFilter">
                    <option value="all">Összes</option>
                    <option value="kotelezo">Kötelező</option>
                    <option value="valaszthato">Kötelezően választható</option>
                    <option value="szv">Szabadon választható</option>
                </select>

                <label for="sco_searchInput">Keresés:</label>
                <input type="text" id="sco_searchInput" placeholder="Név, kód, tanár...">

                <label for="sco_completedFilter">Státusz:</label>
                <select id="sco_statusFilter">
                    <option value="all">Összes</option>
                    <option value="completed">Teljesített</option>
                    <option value="not-completed">Még nem teljesített</option>
                </select>
            </section>

            <section class="course-list">
                <?php foreach ($courses as $course): ?>
                    <div class="sco_course-card"
                         data-name="<?= strtolower($course['course_name']); ?>"
                         data-code="<?= strtolower($course['kurzus_kod']); ?>"
                         data-type="<?= htmlspecialchars($course['course_required_type']); ?>"
                         data-teacher="<?= strtolower($course['teachers']); ?>"
                         data-completed="<?= $course['already_completed'] ? 'completed' : 'not-completed' ?>" >

                    <div class="course-card<?= array_reduce($course['offerings'], fn($c, $v) => $c || array_filter($v, fn($o) => $o['already_enrolled']), false) ? ' enrolled' : '' ?>">
                        <div class="card-header">
                            <h2><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['kurzus_kod']) ?>)</h2>
                            <span class="credit"><?= $course['credit'] ?> kredit</span>
                        </div>
                        <div class="card-body">
                            <?php
                            $description = htmlspecialchars($course['leiras']);
                            $shortDesc = mb_strimwidth($description, 0, 150, '...');
                            ?>
                            <div class="collapsible-container">
                                <p class="short-description"><strong>Leírás:</strong> <?= $shortDesc ?></p>
                                <div class="collapsible-content">
                                    <p><strong>Leírás:</strong> <?= $description ?></p>
                                </div>
                                <?php if (strlen($description) > 150): ?>
                                    <button class="toggle-btn"
                                            data-more-text="Bővebben"
                                            data-less-text="Kevesebb"
                                            onclick="toggleDescription(this)">
                                        Bővebben
                                    </button>
                                <?php endif; ?>
                            </div>
                            <p><strong>Tanárok:</strong> <?= htmlspecialchars($course['teachers']) ?: 'Nincs megadva' ?></p>
                            <p><strong>Típus:</strong>
                                <?php
                                if ($course['course_required_type'] === 'valaszthato') {
                                    echo 'Kötelezően választható';
                                } elseif ($course['course_required_type'] === 'kotelezo') {
                                    echo 'Kötelező';
                                } elseif ($course['course_required_type'] === 'szv') {
                                    echo 'Szabadon választható';
                                } else {
                                    echo 'Ismeretlen típus';
                                }
                                ?>
                            </p>
                            <p><strong>Státusz:</strong>
                                <?php if ($course['already_completed']): ?>
                                    ✔️ Teljesítve
                                <?php elseif (array_reduce($course['offerings'], fn($carry, $types) => $carry || array_filter($types, fn($o) => $o['already_enrolled']), false)): ?>
                                    ✅ Jelentkezett
                                <?php else: ?>
                                    ❌ Még nem jelentkezett
                                <?php endif; ?>
                            </p>

                            <?php foreach ($course['offerings'] as $type => $offerings): ?>
                                <details class="offering-details">
                                    <summary><?= ucfirst($type) ?></summary>
                                    <ul class="offering-list">
                                        <?php foreach ($offerings as $offering): ?>
                                            <li>
                                                <strong>Időpont: <?php
                                                    if($offering['day_of_week'] === 'H') {
                                                        echo 'Hétfő';
                                                    }
                                                    elseif($offering['day_of_week'] === 'K') {
                                                        echo 'Kedd';
                                                    }
                                                    elseif($offering['day_of_week'] === 'Sz') {
                                                        echo 'Szerda';
                                                    }
                                                    elseif($offering['day_of_week'] === 'Cs') {
                                                        echo 'Csütörtök';
                                                    }
                                                    elseif($offering['day_of_week'] === 'P') {
                                                        echo 'Péntek';
                                                    }
                                                    elseif($offering['day_of_week'] === 'Szo') {
                                                      echo 'Szombat ';
                                                    }
                                                    else {
                                                        echo 'Ismeretlen nap/Hibás adat.';
                                                    }
                                                    ?>
                                                    <?= substr($offering['start_time'], 0, 5) ?></strong>
                                                Terem: <?= htmlspecialchars($offering['room']) ?> |
                                                Max létszám: <?= $offering['max_students'] ?> |
                                                Jelentkezettek: <?= $offering['enrolled_count'] ?> |
                                                <strong>Jelentkezési határidő: <?= $offering['end_date'] ?> </strong>

                                                <?php if (!$offering['already_enrolled'] && !$course['already_completed']): ?><form method="post" action="../POST/enrole_post.php" style="display:inline">
                                                    <input type="hidden" name="offering_id" value="<?= $offering['offering_id'] ?>">
                                                    <input type="hidden" name="kurzus_kod" value="<?= $course['kurzus_kod'] ?>">
                                                    <input type="hidden" name="semester_id" value="<?= $offering['semester_id'] ?>">
                                                    <input type="hidden" name="end_date" value="<?= $offering['end_date'] ?>">
                                                    <input type="hidden" name="action" value="enroll">
                                                    <button type="submit" class="send-btn">Jelentkezés</button>
                                                    </form>

                                                <?php elseif ($offering['already_enrolled'] && !$course['already_completed']): ?>
                                                    <form method="post" action="../POST/enrole_post.php" style="display:inline">
                                                        <input type="hidden" name="offering_id" value="<?= $offering['offering_id'] ?>">
                                                        <input type="hidden" name="kurzus_kod" value="<?= $course['kurzus_kod'] ?>">
                                                        <input type="hidden" name="semester_id" value="<?= $offering['semester_id'] ?>">
                                                        <input type="hidden" name="end_date" value="<?= $offering['end_date'] ?>">
                                                        <input type="hidden" name="action" value="unenroll">
                                                        <button type="submit" class="cancel">Lejelentkezés</button>
                                                    </form>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </div>
                <?php endforeach; ?>
            </section>
        </main>
        <script src="../Scripts/scripts.js"></script>
    </body>
</html>

