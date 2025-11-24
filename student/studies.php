<?php
// Globális session és connection
session_start();
require_once __DIR__ . '/../connection.php';

// Jogosultság ellenőrzés
if (!isset($_SESSION['eduportal_id']) || $_SESSION['role'] !== 'hallgato') {
    header("Location: ../index.php");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

/* ============================================================
   🔹 Felhasználó alapadatok lekérése
============================================================ */
$user_sql = "
SELECT
    u.name,
    p.name AS szak_nev
FROM users u
LEFT JOIN programs p ON p.szak_szam = u.course_code
WHERE u.eduportal_id = ?
";

$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $eduportal_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$user_name = $user['name'] ?? "Ismeretlen";
$user_course = $user['szak_nev'] ?? "N/A";

$all_courses_sql = <<<SQL
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'kötelező' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc ON pc.szak_szam = p.szak_szam AND pc.tipus = 'kotelezo'
    JOIN courses c ON c.kurzus_kod = pc.kurzus_kod
    WHERE u.eduportal_id = ?
)
UNION
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'kv' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )
            THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc ON pc.szak_szam = p.szak_szam AND pc.tipus = 'valaszthato'
    JOIN courses c ON c.kurzus_kod = pc.kurzus_kod
    WHERE u.eduportal_id = ?
)
UNION
(
    SELECT 
        c.kurzus_kod,
        c.name AS subject_name,
        c.credit,
        'szv' AS subject_type,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM course_offerings co
                JOIN enrollments e ON e.offering_id = co.id
                WHERE co.kurzus_kod = c.kurzus_kod
                  AND e.users_eduportal_id = u.eduportal_id
                  AND e.status = 'completed'
            )
            THEN 1 ELSE 0
        END AS completed,
        (
            SELECT s.label
            FROM enrollments e
            JOIN course_offerings co ON co.id = e.offering_id
            JOIN semesters s ON s.id = co.semester_id
            WHERE co.kurzus_kod = c.kurzus_kod
              AND e.users_eduportal_id = u.eduportal_id
              AND e.status = 'completed'
            ORDER BY e.completed_at DESC
            LIMIT 1
        ) AS completed_semester
    FROM users u
    JOIN programs p ON p.szak_szam = u.course_code
    JOIN program_courses pc_other ON pc_other.tipus = 'valaszthato'
    JOIN courses c ON c.kurzus_kod = pc_other.kurzus_kod
    LEFT JOIN program_courses pc_this 
        ON pc_this.kurzus_kod = c.kurzus_kod AND pc_this.szak_szam = p.szak_szam
    WHERE u.eduportal_id = ?
      AND pc_this.kurzus_kod IS NULL
)
ORDER BY subject_name
SQL;

$all_courses_stmt = $conn->prepare($all_courses_sql);
$all_courses_stmt->bind_param("sss", $eduportal_id, $eduportal_id, $eduportal_id);
$all_courses_stmt->execute();
$all_courses_result = $all_courses_stmt->get_result();
$subjects = $all_courses_result->fetch_all(MYSQLI_ASSOC);

$total_credits = 0;
$completed_credits = 0;

foreach ($subjects as $subject) {
    $total_credits += $subject['credit'];
    if ($subject['completed']) {
        $completed_credits += $subject['credit'];
    }
}
$missing_credits = $total_credits - $completed_credits;
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
                        <a href="finances.php">Pénzügyek</a>
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
                    <input type="text" id="searchInput" placeholder="🔎 Keresés tárgy névre...">

                    <select id="statusFilter">
                        <option value="all">Összes</option>
                        <option value="completed">Elvégzett</option>
                        <option value="not-completed">Még nem elvégzett</option>
                    </select>

                    <select id="typeFilter">
                        <option value="all">Mindegyik típus</option>
                        <option value="kot">Kötelező</option>
                        <option value="kotval">Kötelezően választható</option>
                        <option value="szabval">Szabadon választható</option>
                    </select>
                </section>

                <section class="subject-list">
                    <?php foreach ($subjects as $subject): ?>
                        <div class="subject-card"
                             data-name="<?= strtolower($subject['subject_name']) ?>"
                             data-status="<?= $subject['completed'] ? 'completed' : 'not-completed' ?>"
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