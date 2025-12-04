<?php
require_once __DIR__ . '/../PHP_Header/a_profile.php';
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
                <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                <div id="dropdownMenuL" class="dropdown-menu left">
                    <a href="message.php" >Üzenetek</a>
                </div>
            </div>
        </div>

        <!-- NAVIGÁCIÓ -->
        <nav class="main-nav">
            <a href="register.php" ><span class="icon">📘</span> Regisztrálás</a>
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

            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
                <div class="success-message">
                    ✅ A(z) adat(ok) sikeresen mentve/módosítva!
                </div>
                <hr>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    ⚠️ <?= htmlspecialchars($_GET['error']) ?>
                </div>
                <hr>
            <?php endif; ?>

            <h3 class="section-title">Adatok</h3>

            <form id="profile-form" class="profile-details" method="post" action="../POST/profile_post.php">
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
            </form>
        </div>
    </main>
    <script src="../Scripts/scripts.js"></script>
</body>
</html>
