<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="CSS/site_style.css">
</head>
    <body>
        <header>
            <!-- BAL MENÜ -->
            <div class="menu">
                <div class="dropdown">
                    <button id="dropdownToggleL" class="dropbtn">☰ Menü </button>
                    <div id="dropdownMenuL" class="dropdown-menu left">
                        <a href="#">Pénzügyek</a>
                        <a href="#">Felvett kurzusok</a>
                        <a href="#">Tanulmányok</a>
                    </div>
                </div>
            </div>

            <!-- NAVIGÁCIÓ -->
            <nav class="main-nav">
                <a href="#"><span class="icon">📘</span> Tárgyfelvétel</a>
                <a href="#"><span class="icon">🧑‍🏫</span> Kurzusok</a>
                <a href="#"><span class="icon">📄</span> Kérelmek</a>
            </nav>

            <!-- JOBB OLDALI MENÜ -->
            <div class="user-menu">
                <div class="dropdown">
                    <button id="dropdownToggleR" class="dropbtn">Hallgató | AZ123456 | Programtervező</button>
                    <div id="dropdownMenuR" class="dropdown-menu right">
                        <a href="#">Beállítások</a>
                        <a href="#">Kijelentkezés</a>
                    </div>
                </div>
                <!-- TÉMAVÁLTÓ GOMB -->
                <div class="theme-switcher">
                    <button id="theme-toggle" class="theme-btn">🌙</button>
                </div>
            </div>
        </header>

        <!-- IDE JÖN A FŐ TARTALOM -->
        <main>
            <h1>Kezdőlap</h1>
            <p>Itt jelenik meg a tartalom.</p>
        </main>

        <script src="Scripts/scripts.js"></script>
    </body>
</html>