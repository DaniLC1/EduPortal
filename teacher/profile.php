<?php
require_once __DIR__ . '/../PHP_Header/t_profile.php';
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
                        <a href="assignment_result.php">Eredmények</a>
                        <a href="student_complete.php">Lezárások</a>
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
            <div class="profile-card">
                <h3>Beadott kérelmeim</h3>
                <?php if (empty($submitted_requests)): ?>
                    <p>Nincs beadott kérelmed.</p>
                <?php else: ?>
                    <div class="submitted-requests">
                        <?php foreach ($submitted_requests as $req): ?>
                            <div class="submitted-request-card">
                                <div class="submitted-header">
                                    <h4><?= htmlspecialchars($req['title']) ?></h4>
                                    <p class="submission-date">📅 <?= date("Y.m.d H:i", strtotime($req['submitted_at'])) ?></p>
                                    <p class="status">Állapot: <strong><?= htmlspecialchars($req['status']) ?></strong></p>
                                </div>

                                <!-- PHP/HTML alapú toggle -->
                                <details class="submitted-details">
                                    <summary>Részletek</summary>

                                    <p><?= nl2br(htmlspecialchars($req['description'])) ?></p>

                                    <div class="submitted-fields">
                                        <?php if (!empty($req['fields'])): ?>
                                            <?php foreach ($req['fields'] as $field): ?>
                                                <div class="field-group">
                                                    <label><?= htmlspecialchars($field['label']) ?>:</label>
                                                    <div class="value-box"><?= nl2br(htmlspecialchars($field['field_value'])) ?></div>

                                                    <?php if (!empty($field['admin_suggestion'])): ?>
                                                        <div class="admin-suggestion">
                                                            <span>📝 Admin javaslat:</span>
                                                            <?= nl2br(htmlspecialchars($field['admin_suggestion'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p>Nincs kitöltött adat ehhez a kéréshez.</p>
                                        <?php endif; ?>

                                        <?php if (!empty($req['admin_comment'])): ?>
                                            <div class="admin-comment-box">
                                                <strong>💬 Admin megjegyzés:</strong><br>
                                                <?= nl2br(htmlspecialchars($req['admin_comment'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        <script src="../Scripts/scripts.js"></script>
    </body>
</html>
