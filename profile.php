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

$profile_sql = "
SELECT u.name,
       u.birth_date,
       u.mothers_name,
       u.email,
       u.phone,
       u.postal_code,
       u.city,
       u.address,
       u.financing_type
FROM users u
JOIN programs p ON p.szak_szam = u.course_code 
WHERE eduportal_id = ?";

$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("s", $eduportal_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();

$field_types = [];
$type_query = $conn->query("SHOW COLUMNS FROM users");
while ($col = $type_query->fetch_assoc()) {
    $field_types[$col['Field']] = $col['Type'];
}
?>
<!DOCTYPE html>
<html lang="hu">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>EduPortál</title>
        <link rel="stylesheet" href="CSS/site_style.css">
        <link rel="stylesheet" href="CSS/profile.css">
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
                        <a id="active" href="#">Beállítások</a>
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
            <div class="profile-card">
                <div class="profile-header">
                    <div class="icon">👤</div>
                    <div class="user-info">
                        <h2><?= htmlspecialchars($user_name) ?></h2>
                        <p class="edu-id"><?= htmlspecialchars($eduportal_id) ?></p>
                        <p class="edu-id"><?= htmlspecialchars($user_course) ?></p>
                    </div>
                </div>

                <hr class="double-line">

                <h3 class="section-title">Adatok</h3>

                <form id="profile-form" class="profile-details" method="post" action="profile_post.php">
                    <?php
                    // Feldolgozás megjelenítéshez
                    $label_map = [
                        'name' => 'Név',
                        'birth_date' => 'Születési dátum',
                        'mothers_name' => 'Anyja neve',
                        'email' => 'E-mail',
                        'phone' => 'Telefonszám',
                        'eduportal_id' => 'EduID',
                        'szak_nev' => 'Szak',
                        'postal_code' => 'Irányítószám',
                        'city' =>'Város',
                        'address' => 'Cím',
                        'financing_type' => 'Képzés típúsa'
                    ];

                    $user_data = $profile_result->fetch_assoc();
                    foreach ($user_data as $key => $value) {
                        $label = $label_map[$key] ?? ucfirst(str_replace('_', ' ', $key));
                        $readonly = $key === 'eduportal_id' ? 'readonly' : '';

                        $input_type = 'text'; // alapértelmezett
                        if (isset($field_types[$key])) {
                            if (str_contains($field_types[$key], 'date')) {
                                $input_type = 'date';
                            } elseif (str_contains($field_types[$key], 'int')) {
                                $input_type = 'number';
                            } elseif (str_contains($field_types[$key], 'email')) {
                                $input_type = 'email';
                            } elseif (str_contains($field_types[$key], 'varchar')) {
                                $input_type = 'text';
                            }
                            // stb., bővíthető más típusokkal is
                        }

                        echo "
                            <div class='detail-row'>
                                <label for='{$key}' class='label'>{$label}:</label>
                                <input type='{$input_type}' id='{$key}' name='{$key}' class='value' value='" . htmlspecialchars($value) . "' {$readonly}>
                            </div>
                            ";
                    }
                    ?>
                    <button type="submit" name="profile_save_data" class="save-btn">Mentés</button>
                    <?php
                    if (!empty($_SESSION['success'])) {
                        echo "<p class='success-message'>{$_SESSION['success']} ✅ Mentve!</p>";
                        unset($_SESSION['success']);
                    }
                    if (!empty($_SESSION['error'])) {
                        echo "<p class='error-message'>{$_SESSION['error']} Hiba!!</p>";
                        unset($_SESSION['error']);
                    }
                    ?>
                </form>
            </div>
        </main>
        <script src="Scripts/scripts.js"></script>
    </body>
</html>
