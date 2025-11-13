<?php
session_start();
require_once __DIR__. '/../connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php"); // vagy login.php
    exit;
}

// 🧑‍🏫 Csak tanárok léphetnek be
if ($_SESSION['role'] !== 'hallgato') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn; // Globális változó használata

// Felhasználó adatainak lekérdezése (név és szak)
$user_sql = "
SELECT u.name, 
       p.name AS szak_nev 
FROM users u
JOIN programs p ON p.szak_szam = u.course_code 
WHERE eduportal_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();

if ($user_result->num_rows === 1) {
    $user = $user_result->fetch_assoc();
    $user_name = $user['name'];
    $user_course = $user['szak_nev'];
} else {
    $user_name = "Ismeretlen";
    $user_course = "N/A";
}

// 🔹 Félévek lekérdezése és aktuális kiválasztása
$today = date('Y-m-d');

$semesters_sql = "SELECT id, label, start_date, end_date FROM semesters ORDER BY start_date DESC";
$semesters_result = $conn->query($semesters_sql);
$semesters = [];
while ($row = $semesters_result->fetch_assoc()) {
    $semesters[] = $row;
}

$selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;

if ($selected_semester_id === 0) {
    foreach ($semesters as $s) {
        if ($today >= $s['start_date'] && $today <= $s['end_date']) {
            $selected_semester_id = $s['id'];
            break;
        }
    }
}

if ($selected_semester_id === 0 && !empty($semesters)) {
    $selected_semester_id = $semesters[0]['id'];
}

$notif_sql = "
SELECT nr.read_at,
       nr.notification_id, 
       n.noti_type, 
       n.created_at,
       c.name AS course_name
FROM notification_reads nr
JOIN notifications n ON nr.notification_id = n.id
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
WHERE nr.users_eduportal_id = ? AND nr.read_at IS NULL
ORDER BY n.created_at DESC
";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("s", $eduportal_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();

// Összes kurzus
$courses_sql = "
SELECT  co.id AS offering_id,
        c.name AS course_name,
        c.kurzus_kod,
        c.leiras AS description
FROM enrollments e
JOIN course_offerings co ON e.offering_id = co.id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
WHERE e.users_eduportal_ID = ? 
  AND co.semester_id = ?
ORDER BY c.name ASC 
";

$courses_stmt = $conn->prepare($courses_sql);
$courses_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

//kurzusokhoz tartozó hirdetmények
$hirdetmeny_sql = "
SELECT n.message, 
       n.noti_type,
       n.created_at,
       c.name AS course_name,
       u.name AS user_name
FROM notifications n
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u on n.users_eduportal_id = u.eduportal_id
WHERE e.users_eduportal_ID = ? AND noti_type = 'hirdetmeny' AND co.semester_id = ?
ORDER BY created_at DESC 
";

$hirdetmeny_stmt = $conn->prepare($hirdetmeny_sql);
$hirdetmeny_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$hirdetmeny_stmt->execute();
$hirdetmeny_result = $hirdetmeny_stmt->get_result();

//kurzusokhoz tartozó fórum hozzászólások
$forum_sql = "
SELECT n.message,
       n.noti_type,
       n.created_at,
       c.name AS course_name,
       n.updated_at,
       u.name AS user_name,
       n.users_eduportal_id,
       n.course_offering_id,
       n.id
FROM notifications n
JOIN course_offerings co ON n.course_offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
JOIN users u on n.users_eduportal_id = u.eduportal_id
WHERE e.users_eduportal_ID = ? AND noti_type = 'forum' AND co.semester_id = ?
ORDER BY created_at DESC 
";

$forum_stmt = $conn->prepare($forum_sql);
$forum_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$forum_stmt->execute();
$forum_result = $forum_stmt->get_result();

//kurzusokhoz tartozó dolgozatok
$assignment_sql = "
SELECT 
    a.title,
    a.due_date,
    a.description,
    c.name AS course_name,
    a.id,
    a.max_attempts,
    COALESCE(SUM(aq.score), 0) AS max_score
FROM assignments a
JOIN course_offerings co ON a.offering_id = co.id
JOIN enrollments e ON co.id = e.offering_id
JOIN courses c ON co.kurzus_kod = c.kurzus_kod
LEFT JOIN assignment_questions aq ON aq.assignment_id = a.id
WHERE e.users_eduportal_ID = ? 
  AND co.semester_id = ?
GROUP BY a.id, a.title, a.due_date, a.description, c.name, a.max_attempts
ORDER BY a.due_date ASC
";

$assignment_stmt = $conn->prepare($assignment_sql);
$assignment_stmt->bind_param("si", $eduportal_id, $selected_semester_id);
$assignment_stmt->execute();
$assignment_result = $assignment_stmt->get_result();

// Diák korábbi beadásai és próbálkozások száma
$submission_sql = "
SELECT 
    s.assignment_id,
    s.id AS submission_id,
    s.submitted_at,
    s.score,
    a.max_attempts
FROM assignment_submissions s
JOIN assignments a ON a.id = s.assignment_id
WHERE s.users_eduportal_ID = ?
ORDER BY s.assignment_id, s.submitted_at ASC
";
$submission_stmt = $conn->prepare($submission_sql);
$submission_stmt->bind_param("s", $eduportal_id);
$submission_stmt->execute();
$submission_result = $submission_stmt->get_result();

$submissions_by_assignment = [];
while ($s = $submission_result->fetch_assoc()) {
    $aid = $s['assignment_id'];
    if (!isset($submissions_by_assignment[$aid])) {
        $submissions_by_assignment[$aid] = [];
    }
    $submissions_by_assignment[$aid][] = $s;
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/courses.css">
</head>
    <body>
        <header>
            <!-- BAL MENÜ -->
            <div class="menu">
                <div class="dropdown">
                    <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                    <div id="dropdownMenuL" class="dropdown-menu left">
                        <a href="finances.php">Pénzügyek</a>
                        <a href="enrolled_courses.php">Felvett kurzusok</a>
                        <a href="studies.php">Tanulmányok</a>
                    </div>
                </div>
            </div>

            <!-- NAVIGÁCIÓ -->
            <nav class="main-nav">
                <a href="course_offering.php"><span class="icon">📘</span> Tárgyfelvétel</a>
                <a href="#" id="active"><span class="icon">🧑‍🏫</span> Kurzusok</a>
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

        <!-- IDE JÖN A FŐ TARTALOM -->
        <main class="layout">
            <!-- BAL OLDALI SÁV -->
            <aside class="sidebar">
                <div class="card calendar">
                    <h3>📅 Naptár</h3>
                    <?php // TODO: naptár  ?>
                    <p>[Naptár ide]</p>
                </div>

                <div class="card notifications">
                    <h3>🔔 Értesítések</h3>
                    <ul>
                        <?php if ($notif_result->num_rows > 0): ?>
                            <?php while ($notif = $notif_result->fetch_assoc()): ?>
                                <?php
                                $icon = '';
                                $text = '';
                                $course = htmlspecialchars($notif['course_name']);
                                $date = date('Y.m.d H:i', strtotime($notif['created_at']));

                                switch ($notif['noti_type']) {
                                    case 'forum':
                                        $icon = '💬';
                                        $text = "Új kurzusfórum hozzászólás: $course";
                                        break;
                                    case 'hirdetmeny':
                                        $icon = '📢';
                                        $text = "Új hirdetmény: $course";
                                        break;
                                    case 'szamonkeres':
                                        $icon = '📝';
                                        $text = "Számonkérés változás: $course";
                                        break;
                                    default:
                                        $icon = '❔';
                                        $text = "Ismeretlen típus: $course";
                                }
                                ?>
                                <li>
                                    <?= $icon ?> <?= $text ?> <span style="color:gray; font-size: 0.9em;">(<?= $date ?>)</span>
                                    <form method="post" action="../noti_mark_read.php" style="display:inline;">
                                        <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                        <input type="hidden" name="eduportal_id" value="<?= $eduportal_id ?>">
                                        <button type="submit" class="delete-btn" title="Megjelölés olvasottként">❌</button>
                                    </form>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li>Nincs új értesítés.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </aside>

            <!-- FŐ TARTALOM -->
            <section class="main-content">
                <h1>Kurzusok</h1>
                <!-- 🔹 Félévválasztó -->
                <form method="get" class="filters">
                    <label for="semester_id">Félév:</label>
                    <select name="semester_id" id="semester_id" onchange="this.form.submit()">
                        <?php foreach ($semesters as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_semester_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit">Szűrés</button></noscript>
                </form>
                <hr>
                <?php while ($row = $courses_result->fetch_assoc()): ?>
                    <div class="course-card">
                        <h3><?= htmlspecialchars($row['course_name']) ?></h3>

                        <?php
                        $description = htmlspecialchars($row['description']);
                        $shortDesc = mb_strimwidth($description, 0, 150, '...');
                        ?>
                        <div class="description-container">
                            <p class="short-description"><?= $shortDesc ?></p>
                            <div class="full-description">
                                <p><?= $description ?></p>
                            </div>
                            <?php if (strlen($description) > 150): ?>
                            <button class="toggle-btn" onclick="toggleContent(this)">Bővebben</button>
                            <?php endif; ?>
                        </div>

                        <!-- Hirdetmények -->
                        <div class="section">
                            <h4>📢 Hirdetmények</h4>
                            <?php
                            mysqli_data_seek($hirdetmeny_result, 0);
                            $offering_id = $row['offering_id'];
                            $hirds = [];
                            while ($h = $hirdetmeny_result->fetch_assoc()) {
                                if ($h['course_name'] === $row['course_name']) {
                                    $hirds[] = $h;
                                }
                            }
                            ?>
                            <?php if (!empty($hirds)): ?>
                                <ul class="collapsible-list">
                                    <?php foreach ($hirds as $index => $h): ?>
                                        <li class="<?= $index > 0 ? 'collapsible-item hidden' : '' ?>">
                                            <div class="forum-message">
                                                <?= $h['noti_type'] === 'forum' ? '💬' : ($h['noti_type'] === 'szamonkeres' ? '📝' : '📢') ?>
                                                <?= nl2br(htmlspecialchars($h['message'])) ?>
                                            </div>
                                            <div class="forum-meta">
                                                Közzétette: <?= htmlspecialchars($h['user_name']) ?> &middot; <?= date('Y. m. d. H:i', strtotime($h['created_at'])) ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (count($hirds) > 1): ?>
                                    <button class="toggle-btn" onclick="toggleList(this)">További hirdetmények</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>Nincsenek hirdetmények.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Fórum -->
                        <div class="section">
                            <h4>💬 Kurzus fórum</h4>
                            <?php
                            mysqli_data_seek($forum_result, 0);
                            $forums = [];
                            while ($f = $forum_result->fetch_assoc()) {
                                if ($f['course_name'] === $row['course_name']) {
                                    $forums[] = $f;
                                }
                            }
                            ?>
                            <?php if (!empty($forums)): ?>
                                <div class="forum-preview">
                                    <ul class="collapsible-list">
                                        <?php foreach ($forums as $index => $f): ?>
                                            <li class="<?= $index > 0 ? 'collapsible-item hidden' : '' ?>">
                                                <div class="forum-message"><?= nl2br(htmlspecialchars($f['message'])) ?></div>
                                                <div class="forum-meta">
                                                    Írta: <?= htmlspecialchars($f['user_name']) ?> &middot; <?= date('Y. m. d. H:i', strtotime($f['updated_at'])) ?>
                                                </div>
                                                <?php if ($f['users_eduportal_id'] === $eduportal_id): ?>
                                                    <form method="POST" action="../forum_post.php" class="edit-form hidden" onsubmit="return confirm('Biztosan menteni szeretnéd a módosítást?')">
                                                        <textarea name="edited_message" class="auto-resize-textarea"><?= htmlspecialchars($f['message']) ?></textarea>
                                                        <input type="hidden" name="edit_message_id" value="<?= $f['id'] ?>">
                                                        <button type="submit" name="submit_edit_message" class="send-btn">💾 Mentés</button>
                                                    </form>
                                                    <button class="edit-btn" onclick="toggleEditForm(this)">✏️ Szerkesztés</button>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php if (count($forums) > 1): ?>
                                    <button class="toggle-btn" onclick="toggleList(this)">További hozzászólások</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>Nincs még fórumhozzászólás.</p>
                            <?php endif; ?>
                            <!-- Új hozzászólás -->
                            <div class="forum-reply">
                                <form method="POST" action="../forum_post.php">
                                    <textarea name="new_message" placeholder="Írd be az üzeneted..." class="auto-resize-textarea" required></textarea>
                                    <input type="hidden" name="course_offering_id" value="<?= $row['offering_id'] ?>">
                                    <button type="submit" name="submit_new_message" class="send-btn">💬 Hozzászólás elküldése</button>
                                </form>
                            </div>
                        </div>

                        <!-- Dolgozatok -->
                        <div class="section">
                            <h4>📝 Dolgozatok</h4>
                            <ul>
                                <?php
                                mysqli_data_seek($assignment_result, 0);
                                $assignments = [];
                                while ($a = $assignment_result->fetch_assoc()) {
                                    if ($a['course_name'] === $row['course_name']) {
                                        $assignments[] = $a;
                                    }
                                }
                                ?>
                                <?php if (!empty($assignments)): ?>
                                    <?php foreach ($assignments as $a): ?>
                                        <?php
                                        $assignment_id = $a['id'];
                                        $student_attempts = $submissions_by_assignment[$assignment_id] ?? [];
                                        $attempt_count = count($student_attempts);
                                        $max_attempts = $student_attempts[0]['max_attempts'] ?? $a['max_attempts'] ?? '∞';
                                        $best_score = 0;
                                        foreach ($student_attempts as $sub) {
                                            if ($sub['score'] !== null && $sub['score'] > $best_score) {
                                                $best_score = $sub['score'];
                                            }
                                        }
                                        $max_score = isset($a['max_score']) ? (int)$a['max_score'] : 0;
                                        $description = htmlspecialchars($a['description']);
                                        ?>

                                        <li class="assignment-item">
                                            <details>
                                                <summary class="assignment-summary">
                                                    <div class="assignment-header">
                                                        <div class="assignment-title">
                                                            <strong><?= htmlspecialchars($a['title']) ?></strong><br>
                                                            <span class="date-range">Indítható: <?= date('Y.m.d', strtotime('-3 days', strtotime($a['due_date']))) ?> – <?= date('Y.m.d', strtotime($a['due_date'])) ?></span>
                                                        </div>
                                                        <div class="assignment-stats">
                                                            Próbálkozás: <?= $attempt_count ?> / <?= $max_attempts ?> |
                                                            Eredmény: <?= $best_score ?> / <?= $max_score ?> pont
                                                        </div>
                                                        <div class="assignment-action">
                                                            <?php if ($attempt_count < $max_attempts || $max_attempts === '∞'): ?>
                                                                <form method="GET" action="assignment.php">
                                                                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                                                                    <button type="submit" class="fill-btn">✍️ Kitöltés</button>
                                                                </form>
                                                            <?php else: ?>
                                                                <em>Maximális próbálkozások száma elérve</em>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </summary>

                                                <div class="assignment-details">
                                                    <p class="assignment-description"><?= nl2br($description) ?></p>
                                                    <?php if (!empty($student_attempts)): ?>
                                                        <ul class="attempt-list">
                                                            <?php foreach ($student_attempts as $index => $sub): ?>
                                                                <li>
                                                                    <?= $index + 1 ?>. próbálkozás – <?= date('Y.m.d H:i', strtotime($sub['submitted_at'])) ?>:
                                                                    <?= $sub['score'] ?? 0 ?> / <?= $max_score ?> pont
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <p>Még nincs próbálkozás.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li>Nincs beadandó dolgozat.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                <?php endwhile; ?>
            </section>
        </main>

        <script src="../Scripts/scripts.js"></script>
    </body>
</html>