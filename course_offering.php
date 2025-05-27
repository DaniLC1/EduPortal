<?php
session_start();
require_once 'connection.php'; // Adatbáziskapcsolat betöltése

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php"); // vagy login.php
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

$today = date('Y-m-d');

// Aktuális félév lekérdezése
$semester_sql = "SELECT * FROM semesters WHERE start_date <= ? AND end_date >= ? LIMIT 1";
$semester_stmt = $conn->prepare($semester_sql);
$semester_stmt->bind_param("ss", $today, $today);
$semester_stmt->execute();
$semester_result = $semester_stmt->get_result();
$current_semester = $semester_result->fetch_assoc();
$current_semester_id = $current_semester['id'] ?? null;

// Lekérdezés GET-ből vagy fallback az aktuális szemeszterre
$selected_semester_id = $_GET['semester_id'] ?? $current_semester_id;

$search = $_GET['search'] ?? '';
$type_filter = $_GET['type_filter'] ?? '';
$show_completed = $_GET['show_completed'] ?? '1';

//Összes félév lekérdezés
$semesters_sql = "SELECT * FROM semesters ORDER BY start_date DESC";
$semesters_result = $conn->query($semesters_sql);
$semesters = $semesters_result->fetch_all(MYSQLI_ASSOC);

$course_offering_sql = "
SELECT 
    co.id AS offering_id,
    c.kurzus_kod,
    c.name AS course_name,
    c.leiras,
    c.credit,
    co.course_type,
    co.day_of_week,
    co.start_time,
    co.room,
    co.max_students,
    COUNT(DISTINCT e.users_eduportal_ID) AS enrolled_count,
    GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS teachers,
    COALESCE(pc.tipus, 'szv') AS course_required_type,
    EXISTS (
        SELECT 1 FROM enrollments e2 
        WHERE e2.users_eduportal_ID = ? AND e2.offering_id = co.id
    ) AS already_enrolled,
    EXISTS (
        SELECT 1 FROM enrollments e3 
        JOIN course_offerings co3 ON e3.offering_id = co3.id
        WHERE e3.users_eduportal_ID = ? 
        AND co3.kurzus_kod = c.kurzus_kod
        AND e3.status = 'completed'
    ) AS already_completed

FROM course_offerings co
JOIN courses c ON c.kurzus_kod = co.kurzus_kod
LEFT JOIN teacher_courses tc ON tc.kurzus_kod = c.kurzus_kod
LEFT JOIN users t ON t.eduportal_id = tc.teacher_id
LEFT JOIN enrollments e ON e.offering_id = co.id
JOIN users u ON u.eduportal_id = ?
JOIN programs p ON p.szak_szam = u.course_code
LEFT JOIN program_courses pc ON pc.kurzus_kod = c.kurzus_kod AND pc.szak_szam = p.szak_szam
WHERE co.semester_id = ?
";

// Szűrés név, kód, tanár alapján
$course_offering_sql .= " AND (
    c.name LIKE CONCAT('%', ?, '%') OR 
    c.kurzus_kod LIKE CONCAT('%', ?, '%') OR 
    t.name LIKE CONCAT('%', ?, '%')
)";

// Kurzus típus szűrő
if ($type_filter) {
    $course_offering_sql .= " AND COALESCE(pc.tipus, 'szv') = ?";
}

// Teljesített kurzusok szűrése
if ($show_completed === '0') {
    $course_offering_sql .= " AND NOT (
        EXISTS (
            SELECT 1 FROM enrollments e3 
            JOIN course_offerings co3 ON e3.offering_id = co3.id
            WHERE e3.users_eduportal_ID = ?
            AND co3.kurzus_kod = c.kurzus_kod
            AND e3.status = 'completed'
        )
    )";
}

$course_offering_sql .= "
GROUP BY co.id
ORDER BY c.name ASC, co.course_type ASC
";

// Paraméterek hozzárendelése
$params = [
    $eduportal_id,  // e2 ellenőrzés
    $eduportal_id,  // completed ellenőrzés
    $eduportal_id,  // csatlakozás userhez
    $selected_semester_id,
    $search, $search, $search
];
$types = "sssssss";

if ($type_filter) {
    $params[] = $type_filter;
    $types .= "s";
}
if ($show_completed === '0') {
    $params[] = $eduportal_id;
    $types .= "s";
}

// Lekérdezés futtatása
$course_stmt = $conn->prepare($course_offering_sql);
$course_stmt->bind_param($types, ...$params);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$raw_courses = $course_result->fetch_all(MYSQLI_ASSOC);

$courses = [];

foreach ($raw_courses as $row) {
    $kod = $row['kurzus_kod'];
    $type = $row['course_type'];

    if (!isset($courses[$kod])) {
        $courses[$kod] = [
            'kurzus_kod' => $kod,
            'course_name' => $row['course_name'],
            'credit' => $row['credit'],
            'leiras' => $row['leiras'],
            'teachers' => $row['teachers'],
            'course_required_type' => $row['course_required_type'],
            'already_completed' => $row['already_completed'],
            'offerings' => [],
        ];
    }

    if (!isset($courses[$kod]['offerings'][$type])) {
        $courses[$kod]['offerings'][$type] = [];
    }

    $courses[$kod]['offerings'][$type][] = [
        'offering_id' => $row['offering_id'],
        'day_of_week' => $row['day_of_week'],
        'start_time' => $row['start_time'],
        'room' => $row['room'],
        'max_students' => $row['max_students'],
        'enrolled_count' => $row['enrolled_count'],
        'already_enrolled' => $row['already_enrolled']
    ];
}

?>
<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EduPortál</title>
        <link rel="stylesheet" href="CSS/site_style.css">
        <link rel="stylesheet" href="CSS/course_offering.css">
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
                        <a href="./logout.php">Kijelentkezés</a>
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
            <form method="get" class="filters">
                <label for="semester_id">Félév:</label>
                <select name="semester_id" id="semester_id">
                    <?php foreach ($semesters as $semester): ?>
                        <option value="<?= $semester['id'] ?>" <?= $semester['id'] == $selected_semester_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($semester['label']) ?> (<?= $semester['start_date'] ?> - <?= $semester['end_date'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="search">Keresés:</label>
                <input type="text" name="search" id="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Tárgy neve, kód, tanár">

                <label for="type_filter">Kurzustípus:</label>
                <select name="type_filter" id="type_filter">
                    <option value="">Összes</option>
                    <option value="kotelezo" <?= ($_GET['type_filter'] ?? '') === 'kotelezo' ? 'selected' : '' ?>>Kötelező</option>
                    <option value="valaszthato" <?= ($_GET['type_filter'] ?? '') === 'valaszthato' ? 'selected' : '' ?>>Kötelezően választható</option>
                    <option value="szv" <?= ($_GET['type_filter'] ?? '') === 'szv' ? 'selected' : '' ?>>Szabadon választható</option>
                </select>

                <label for="show_completed">Teljesített kurzusok:</label>
                <select name="show_completed" id="show_completed">
                    <option value="1" <?= ($_GET['show_completed'] ?? '') === '1' ? 'selected' : '' ?>>Mutassa</option>
                    <option value="0" <?= ($_GET['show_completed'] ?? '') === '0' ? 'selected' : '' ?>>Ne mutassa</option>
                </select>

                <button type="submit">Szűrés</button>
            </form>
            <section class="course-list">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card<?= array_reduce($course['offerings'], fn($c, $v) => $c || array_filter($v, fn($o) => $o['already_enrolled']), false) ? ' enrolled' : '' ?>">
                        <div class="card-header">
                            <h2><?= htmlspecialchars($course['course_name']) ?> (<?= htmlspecialchars($course['kurzus_kod']) ?>)</h2>
                            <span class="credit"><?= $course['credit'] ?> kredit</span>
                        </div>
                        <div class="card-body">
                            <p><strong>Leírás:</strong> <?= nl2br(htmlspecialchars($course['leiras'])) ?></p>
                            <p><strong>Tanárok:</strong> <?= htmlspecialchars($course['teachers']) ?: 'Nincs megadva' ?></p>
                            <p><strong>Típus:</strong>
                                <?php
                                if ($course['course_required_type'] === 'valaszthato') {
                                    echo 'Kötelezően választható';
                                } elseif ($course['course_required_type'] === 'kotelezo') {
                                    echo 'Kötelező';
                                } else {
                                    echo 'Szabadon választható';
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
                                                Jelentkezettek: <?= $offering['enrolled_count'] ?>

                                                <?php if (!$offering['already_enrolled'] && !$course['already_completed']): ?>
                                                    <form method="post" action="enrole_post.php" style="display:inline">
                                                        <input type="hidden" name="offering_id" value="<?= $offering['offering_id'] ?>">
                                                        <input type="hidden" name="action" value="enroll">
                                                        <button type="submit" class="send-btn">Jelentkezés</button>
                                                    </form>
                                                <?php elseif ($offering['already_enrolled']): ?>
                                                    <form method="post" action="enrole_post.php" style="display:inline">
                                                        <input type="hidden" name="offering_id" value="<?= $offering['offering_id'] ?>">
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
                <?php endforeach; ?>
            </section>
        </main>
        <script src="Scripts/scripts.js"></script>
        <?php if (isset($_SESSION['message'])): ?>
            <script>
                alert("<?= addslashes($_SESSION['message']) ?>");
            </script>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    </body>
</html>

