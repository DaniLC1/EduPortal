<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php");
    exit;
}

// Csak tanárok léphetnek be
if ($_SESSION['role'] !== 'tanar') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// 🔹 Tanár adatai
$user_sql = "
SELECT 
    name 
FROM users 
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? 'Ismeretlen';
$user_course = "Tanár";

// 🔹 Aktuális félév
$today = date('Y-m-d');
$semester_sql = "SELECT * FROM semesters WHERE start_date <= ? AND end_date >= ? LIMIT 1";
$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("ss", $today, $today);
$semester_stmt->execute();
$current_semester = $semester_stmt->get_result()->fetch_assoc();
$current_semester_id = $current_semester['id'] ?? null;

$selected_semester_id = $_GET['semester_id'] ?? $current_semester_id;
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course_filter'] ?? '';

// 🔹 Félévlista
$semesters = $conn->query("SELECT * FROM semesters ORDER BY start_date DESC")->fetch_all(MYSQLI_ASSOC);

// 🔹 Tanárhoz rendelt kurzusok (legördülőhöz)
$teacher_courses_sql = "
    SELECT c.kurzus_kod, c.name 
    FROM teacher_courses tc
    JOIN courses c ON c.kurzus_kod = tc.kurzus_kod
    WHERE tc.teacher_id = ?
";
$tc_stmt = $conn->prepare($teacher_courses_sql);
$tc_stmt->bind_param("s", $eduportal_id);
$tc_stmt->execute();
$teacher_courses = $tc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 🔹 Meghirdetett kurzusok lekérdezése
$offering_sql = "
SELECT 
    co.id AS offering_id,
    c.kurzus_kod,
    c.name AS course_name,
    c.leiras,
    co.day_of_week,
    co.start_time,
    co.room,
    co.max_students,
    co.semester_id,
    co.course_type,
    co.end_date,
    s.label AS semester_label
FROM course_offerings co
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
JOIN semesters s ON s.id = co.semester_id
WHERE co.teacher_id = ?
";

$params = [$eduportal_id];
$types = "s";

if (!empty($selected_semester_id)) {
    $offering_sql .= " AND co.semester_id = ?";
    $params[] = $selected_semester_id;
    $types .= "i";
}

if (!empty($course_filter)) {
    $offering_sql .= " AND c.kurzus_kod = ?";
    $params[] = $course_filter;
    $types .= "s";
}

if (!empty($search)) {
    $offering_sql .= " AND (c.name LIKE ? OR c.kurzus_kod LIKE ?)";
    $search_like = "%$search%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

$offering_sql .= " ORDER BY s.label DESC, c.name ASC";

$offering_stmt = $conn->prepare($offering_sql);
$offering_stmt->bind_param($types, ...$params);
$offering_stmt->execute();
$offerings = $offering_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <h2>Új kurzus meghirdetése</h2>
        <form method="post" action="../enrole_post.php" class="create-form">
            <label>Tárgy:</label>
            <select name="kurzus_kod" required>
                <option value="">Válassz tárgyat...</option>
                <?php foreach ($teacher_courses as $c): ?>
                    <option value="<?= htmlspecialchars($c['kurzus_kod']) ?>"><?= htmlspecialchars($c['name']) ?></option>
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

            <label>Leírás:</label>
            <textarea name="course_description" rows="2" required></textarea>

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

            <label>Jelentkezési idő vége:</label>
            <input type="datetime-local" name="end_date">

            <label>Max létszám:</label>
            <input type="number" name="max_students" min="0" required>

            <button type="submit" class="fill-btn">Mentés</button>
        </form>
    </section>

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

        <label>Tárgy:</label>
        <select name="course_filter">
            <option value="">Összes</option>
            <?php foreach ($teacher_courses as $c): ?>
                <option value="<?= $c['kurzus_kod'] ?>" <?= ($course_filter == $c['kurzus_kod']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Keresés:</label>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tárgy neve, kód">

        <button type="submit">Szűrés</button>
    </form>

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
                <div class="course-card">
                    <div class="card-header">
                        <h2><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($kurzus_kod) ?>)</h2>
                        <span><?= htmlspecialchars($course['semester_label']) ?></span>
                    </div>

                    <div class="card-body">
                        <p><strong>Leírás:</strong> <?= htmlspecialchars($course['leiras']) ?></p>

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

                                <form method="post" action="../enrole_post.php">
                                    <input type="hidden" name="offering_id" value="<?= $off['offering_id'] ?>">

                                    <label>Leírás:</label>
                                    <textarea name="course_description" rows="2"><?= htmlspecialchars($off['leiras']) ?></textarea>

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
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>
