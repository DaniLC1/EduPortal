<?php if (isset($_GET['error'])): ?>
    <p class="error">
        <?php
        switch ($_GET['error']) {
            case 'invalid_credentials':
                echo "Hibás jelszó!";
                break;
            case 'user_not_found':
                echo "Felhasználó nem található!";
                break;
        }
        ?>
    </p>
<?php endif; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="CSS/index_style.css">
</head>
<body>
    <header>
        <h1 id="welcome-message">Üdvözöljük az EduPortálon!</h1>
        <div class="theme-switcher">
            <button id="theme-toggle" class="theme-btn">🌙</button>
        </div>
    </header>
    <main>
        <div class="main-container">
            <!-- BAL OLDAL: Képek -->
            <div class="image-slider">
                <img id="slider-image" src="slider_pictures/no_copyright1.png" alt="Slider kép">
            </div>
            <!-- KÖZÉP: Bejelentkezés -->
            <section class="login-box">
                <h2>Bejelentkezés</h2>
                <form action="login.php" method="POST">
                    <label for="eduportal_id">EduPortál azonosító:</label>
                    <input type="text" id="eduportal_id" name="eduportal_id" required>

                    <label for="password">Jelszó:</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit">Belépés</button>
                </form>
            </section>
            <!-- JOBB OLDAL: Hírek -->
            <div class="news-box">
                <h2>Hírek</h2>
                <p>
                    A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!A jövő héten indul a Neptun rendszer karbantartása, így előfordulhatnak átmeneti leállások.
                    Kérjük, mindenki mentse el időben az adatokat és figyelje a további közleményeket!
                </p>
            </div>
        </div>
    </main>
    <script src="Scripts/scripts.js"></script>
</body>
</html>