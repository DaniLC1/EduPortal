<?php
session_start();
require_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php");
    exit;
}

// 🧑‍🏫 Csak adminok léphetnek be
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
global $conn;

// 🔹 Admin adatainak lekérése
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
$user_course = "Admin";

$profile_sql = "
SELECT u.name,
       u.birth_date,
       u.mothers_name,
       u.email,
       u.phone,
       u.postal_code,
       u.city,
       u.address
FROM users u
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

// Beadott kérelmek lekérdezése
$submitted_sql = "
    SELECT sr.id AS request_id,
           sr.submitted_at,
           sr.status,
           sr.admin_comment,
           rt.title,
           rt.description
    FROM student_requests sr
    JOIN request_templates rt ON rt.id = sr.template_id
    WHERE sr.users_eduportal_ID = ?
    ORDER BY sr.submitted_at DESC
";

$submitted_stmt = $conn->prepare($submitted_sql);
$submitted_stmt->bind_param("s", $eduportal_id);
$submitted_stmt->execute();
$submitted_result = $submitted_stmt->get_result();

$submitted_requests = [];
while ($row = $submitted_result->fetch_assoc()) {
    $submitted_requests[$row['request_id']] = $row;
}

// Mezők és értékek lekérdezése (minden beküldött kéréshez)
$field_values_sql = "
    SELECT fv.request_id,
           tf.label,
           fv.field_value,
           fv.admin_suggestion
    FROM student_request_field_values fv
    JOIN request_template_fields tf ON tf.id = fv.field_id
    WHERE fv.request_id IN (" . implode(',', array_keys($submitted_requests) ?: [0]) . ")
    ORDER BY fv.request_id, tf.id
";

$field_values_result = $conn->query($field_values_sql);
while ($row = $field_values_result->fetch_assoc()) {
    $submitted_requests[$row['request_id']]['fields'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/profile.css">
</head>
<body>
    <header>
        <!-- BAL MENÜ -->
        <div class="menu">
            <div class="dropdown">
                <div id="dropdownMenuL" class="dropdown-menu left" hidden="hidden">
                </div>
            </div>
        </div>

        <!-- NAVIGÁCIÓ -->
        <nav class="main-nav">
            <a href="database.php" ><span class="icon">📘</span> Adatbázis</a>
            <a href="submitted_request.php" ><span class="icon">🧑‍🏫</span> Beadott kérelmek</a>
            <a href="request.php"><span class="icon">📄</span> Kérelmek szerkesztése</a>
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

            <form id="profile-form" class="profile-details" method="post" action="../profile_post.php">
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
                <?php // TODO: Egységes (hiba) üzenetek  ?>
                <?php
                if (!empty($_SESSION['success'])) {
                    echo "<p class='success-message'>{$_SESSION['success']}</p>";
                    unset($_SESSION['success']);
                }
                if (!empty($_SESSION['error'])) {
                    echo "<p class='error-message'>{$_SESSION['error']}</p>";
                    unset($_SESSION['error']);
                }
                ?>
            </form>
        </div>
    </main>
    <script src="../Scripts/scripts.js"></script>
</body>
</html>
