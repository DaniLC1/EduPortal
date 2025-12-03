<?php
require_once __DIR__ . '/../PHP_Header/t_course_offering.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kurzus meghirdetése</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/course_offering.css">
</head>
<body>
<header>
    <div class="menu">
        <div class="dropdown">
            <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
            <div id="dropdownMenuL" class="dropdown-menu left">
                <a href="assignment_result.php">Eredmények</a>
                <a href="student_complete.php">Lezárások</a>
            </div>
        </div>
    </div>

    <nav class="main-nav">
        <a id="active" href="#"><span class="icon">📘</span> Tárgyfelvétel</a>
        <a href="courses.php"><span class="icon">🧑‍🏫</span> Kurzusok</a>
        <a href="request.php"><span class="icon">📄</span> Kérelmek</a>
    </nav>

    <div class="user-menu">
        <div class="dropdown">
            <button id="dropdownToggleR" class="dropbtn">
                <?= htmlspecialchars($user_name) ?> | <?= htmlspecialchars($eduportal_id) ?> | Tanár
            </button>
            <div id="dropdownMenuR" class="dropdown-menu right">
                <a href="profile.php">Beállítások</a>
                <a href="../logout.php">Kijelentkezés</a>
            </div>
        </div>
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </div>
</header>

<main>
    <h1>Kurzus meghirdetések kezelése</h1>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">
            ✅ A tárgy sikeresen mentve/módosítva!
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="error-message">
            ⚠️ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <!-- 🔹 Új offering létrehozása -->
    <section class="create-offering">
        <details>
            <summary><h2 style="display:inline;">Új kurzus meghirdetése</h2></summary>
            <div class="course-card">
                <form method="post" action="../POST/enrole_post.php" class="create-form">
                    <input type="hidden" name="create_offering">

                    <label>Tárgy:</label>
                    <select name="kurzus_kod" required>
                        <option value="">Válassz tárgyat...</option>
                        <?php foreach ($teacher_courses as $c): ?>
                            <option value="<?= htmlspecialchars($c['kurzus_kod']) ?>">
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Félév:</label>
                    <select name="semester_id" required>
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_semester_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <br>

                    <label>Leírás:</label>
                    <textarea class="auto-resize-textarea" name="course_description" required></textarea>

                    <br>

                    <label>Kurzus jellege:</label>
                    <select name="course_type" required>
                        <option value="eloadas">Előadás</option>
                        <option value="gyakorlat">Gyakorlat</option>
                    </select>

                    <br>

                    <label>Nap:</label>
                    <select name="day_of_week" required>
                        <option value="H">Hétfő</option>
                        <option value="K">Kedd</option>
                        <option value="Sz">Szerda</option>
                        <option value="Cs">Csütörtök</option>
                        <option value="P">Péntek</option>
                        <option value="Szo">Szombat</option>
                    </select>

                    <label>Kezdési idő:</label>
                    <input type="time" name="start_time" required>

                    <label>Terem:</label>
                    <input type="text" name="room" required>

                    <br>

                    <label>Jelentkezési idő vége:</label>
                    <input type="datetime-local" name="end_date" required>

                    <label>Max létszám:</label>
                    <input type="number" name="max_students" min="0" required>

                    <button type="submit" class="fill-btn">Mentés</button>
                </form>
            </div>
        </details>
    </section>

    <!-- 🔹 Szűrők -->
    <section class="filters">
        <!-- Félévre szűrés -->
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

        <!-- Tárgyra szűrés -->
        <label for="tco_courseFilter">Tárgy:</label>
        <select id="tco_courseFilter">
            <option value="all">Összes</option>
            <?php foreach ($teacher_courses as $c): ?>
                <option value="<?= strtolower($c['kurzus_kod']) ?>">
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- Keresés -->
        <label for="tco_searchInput">Keresés:</label>
        <input type="text" id="tco_searchInput" placeholder="Név, kód...">

    </section>

    <!-- 🔹 Meghirdetett kurzusok listája -->
    <section class="course-list">
        <?php if (empty($offerings)): ?>
            <p>Nincs elérhető kurzus.</p>
        <?php else: ?>
            <?php
            // 🔹 Csoportosítás kurzus_kod szerint
            $grouped_offerings = [];
            foreach ($offerings as $off) {
                $grouped_offerings[$off['kurzus_kod']]['course_name'] = $off['course_name'];
                $grouped_offerings[$off['kurzus_kod']]['leiras'] = $off['leiras'];
                $grouped_offerings[$off['kurzus_kod']]['semester_label'] = $off['semester_label'];
                $grouped_offerings[$off['kurzus_kod']]['offerings'][] = $off;
            }
            ?>

            <?php foreach ($grouped_offerings as $kurzus_kod => $course): ?>
                <div class="tco_course-card"
                     data-name="<?= strtolower($course['course_name']); ?>"
                     data-code="<?= strtolower($kurzus_kod); ?>" >

                <div class="course-card">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($kurzus_kod) ?>)</h2>
                        <span><?= htmlspecialchars($course['semester_label']) ?></span>
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
                        <?php foreach ($course['offerings'] as $off): ?>
                            <details class="offering-details">
                                <summary><?= ucfirst($off['course_type'] ?? 'Kurzus') ?> szerkesztése (<?= htmlspecialchars($off['day_of_week']) ?> <?= substr($off['start_time'], 0, 5) ?>)</summary>
                                <ul class="offering-info">
                                    <li><strong>Nap:</strong> <?= htmlspecialchars($off['day_of_week']) ?></li>
                                    <li><strong>Kezdés:</strong> <?= substr($off['start_time'], 0, 5) ?></li>
                                    <li><strong>Terem:</strong> <?= htmlspecialchars($off['room']) ?></li>
                                    <li><strong>Jelentkezési idő vége:</strong> <?= htmlspecialchars($off['end_date']) ?></li>
                                    <li><strong>Max létszám:</strong> <?= htmlspecialchars($off['max_students']) ?></li>
                                </ul>

                                <form method="post" action="../POST/enrole_post.php">
                                    <input type="hidden" name="edit_offering">
                                    <input type="hidden" name="kurzus_kod" value="<?= $off['kurzus_kod'] ?>" >
                                    <input type="hidden" name="offering_id" value="<?= $off['offering_id'] ?>">

                                    <label>Leírás:</label>
                                    <textarea class="auto-resize-textarea" name="course_description" ><?= htmlspecialchars($off['leiras']) ?></textarea>

                                    <label>Kurzus jellege:</label>
                                    <select name="course_type" required>
                                        <option value="eloadas" <?= $off['course_type'] == 'eloadas' ? 'selected' : '' ?>>Előadás</option>
                                        <option value="gyakorlat" <?= $off['course_type'] == 'gyakorlat' ? 'selected' : '' ?>>Gyakorlat</option>
                                    </select>

                                    <label>Nap:</label>
                                    <select name="day_of_week">
                                        <option value="H" <?= $off['day_of_week'] == 'H' ? 'selected' : '' ?>>Hétfő</option>
                                        <option value="K" <?= $off['day_of_week'] == 'K' ? 'selected' : '' ?>>Kedd</option>
                                        <option value="Sz" <?= $off['day_of_week'] == 'Sz' ? 'selected' : '' ?>>Szerda</option>
                                        <option value="Cs" <?= $off['day_of_week'] == 'Cs' ? 'selected' : '' ?>>Csütörtök</option>
                                        <option value="P" <?= $off['day_of_week'] == 'P' ? 'selected' : '' ?>>Péntek</option>
                                        <option value="Szo" <?= $off['day_of_week'] == 'Szo' ? 'selected' : '' ?>>Szombat</option>
                                    </select>

                                    <label>Kezdési idő:</label>
                                    <input type="time" name="start_time" value="<?= htmlspecialchars(substr($off['start_time'], 0, 5)) ?>">

                                    <label>Terem:</label>
                                    <input type="text" name="room" value="<?= htmlspecialchars($off['room']) ?>">

                                    <label>Jelentkezési idő vége:</label>
                                    <input type="datetime-local" name="end_date" value="<?= htmlspecialchars($off['end_date']) ?>">

                                    <label>Max létszám:</label>
                                    <input type="number" name="max_students" value="<?= htmlspecialchars($off['max_students']) ?>" min="0">

                                    <button type="submit" class="send-btn">Mentés</button>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
