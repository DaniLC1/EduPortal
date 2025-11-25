<?php
require_once __DIR__ . '/../PHP_Header/s_request.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/request.css">
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
        <a href="courses.php"><span class="icon">🧑‍🏫</span> Kurzusok</a>
        <a id="active" href="#"><span class="icon">📄</span> Kérelmek</a>
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
    <h1>📄 Kérelmek</h1>
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="success-message">
            ✅ A kérelem sikeresen beadva!
        </div>
    <?php endif; ?>

    <!-- Kereső -->
    <div class="search-form">
        <input type="text" id="sr_searchInput" placeholder="Keresés címre vagy leírásra...">
    </div>

    <!-- Kérelmek listája -->
    <div class="requests-container">
        <?php if (empty($templates)): ?>
            <p>Nincs találat a megadott keresésre.</p>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>

                <details class="sr_request-card"
                          data-title="<?= strtolower(htmlspecialchars($template['title'])) ?>"
                          data-description="<?= strtolower(htmlspecialchars($template['description'])) ?>">

                    <summary class="card-summary">
                        <span class="title"><?= htmlspecialchars($template['title']) ?></span>
                    </summary>

                    <div class="card-content">
                        <p class="description"><?= nl2br(htmlspecialchars($template['description'])) ?></p>

                        <form method="post" action="../POST/request_post.php" class="request-form">
                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">

                            <?php if (!empty($template_fields[$template['id']])): ?>
                                <?php foreach ($template_fields[$template['id']] as $field): ?>
                                    <label class="field-label">
                                        <?= htmlspecialchars($field['label']) ?>
                                        <?= $field['is_required'] ? ' *' : '' ?>
                                    </label>

                                    <?php if ($field['field_type'] === 'textarea'): ?>
                                        <textarea
                                                class="auto-resize-textarea"
                                                name="field_<?= $field['id'] ?>"
                                                rows="3"
                                        <?= $field['is_required'] ? 'required' : '' ?>
                                    ></textarea>
                                    <?php else: ?>
                                        <input
                                                type="<?= htmlspecialchars($field['field_type']) ?>"
                                                name="field_<?= $field['id'] ?>"
                                                <?= $field['is_required'] ? 'required' : '' ?>
                                        >
                                    <?php endif; ?>
                                    <br><br>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>Nincsenek kitölthető mezők ehhez a kérelemhez.</p>
                            <?php endif; ?>

                            <button type="submit" class="submit-btn">Kérelem beküldése</button>
                        </form>
                    </div>
                </details>

            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script src="../Scripts/scripts.js"></script>
</body>
</html>