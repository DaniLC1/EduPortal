<?php
require __DIR__. '/../PHP_Header/a_register.php'
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="../CSS/site_style.css">
    <link rel="stylesheet" href="../CSS/register.css">
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
        <a href="#" id="active" ><span class="icon">📘</span> Regisztrálás </a>
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

    <h1>Új felhasználó rögzítése</h1>
    <?php include __DIR__ . '/../feedback.php'; ?>

    <!-- 🔹 Új felhasználó regisztrálása -->
    <section class="create-user">
        <div class="user-card">
            <form method="post" action="../POST/register_post.php" class="create-form">

                <label>Teljes név:</label>
                <input type="text" name="name" required>

                <label>EduPortal ID:</label>
                <input type="text" name="eduportal_id" required>

                <label>Email cím:</label>
                <input type="email" name="email" required>

                <label>Telefonszám:</label>
                <input type="tel" name="phone" required>

                <label>Jelszó:</label>
                <input type="password" name="password" required>

                <label>Irányítószám:</label>
                <input type="number" name="postal_code" required>

                <label>Város:</label>
                <input type="text" name="city" required>

                <label>Cím:</label>
                <input type="text" name="address" required>

                <label>Születési dátum:</label>
                <input type="date" name="birth_date" required>

                <label>Anyja neve:</label>
                <input type="text" name="mothers_name" required>

                <!-- Role -->
                <label>Jogkör:</label>
                <select name="role" required>
                    <option value="hallgato">Hallgató</option>
                    <option value="tanar">Tanár</option>
                    <option value="admin">Admin</option>
                </select>

                <!-- Financing type -->
                <label>Finanszírozás típusa:</label>
                <select name="financing_type">
                    <option value="állami">Állami</option>
                    <option value="önköltséges">Önköltséges</option>
                </select>

                <!-- Program / szak kiválasztása -->
                <label>Szak / program:</label>
                <select name="course_code">
                    <option value="">-- nincs szak --</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?= htmlspecialchars($p['szak_szam']) ?>">
                            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['szak_szam']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="fill-btn">Felhasználó mentése</button>
            </form>
        </div>
    </section>
</main>
<script src="../Scripts/scripts.js"></script>
</body>
</html>
