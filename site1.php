<?php
require_once 'connection.php'; // Adatbáziskapcsolat betöltése

global $conn; // Globális változó használata
// Lekérdezés
$sql = "SELECT * FROM teszt WHERE ID BETWEEN 501 AND 599";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPortál</title>
    <link rel="stylesheet" href="CSS/site_style.css">
    <link rel="stylesheet" href="CSS/site1.css">
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
                        <a href="./logout.php">Kijelentkezés</a>
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
            <!-- BAL OLDALI SÁV -->
            <aside class="sidebar">
                <div class="card calendar">
                    <h3>📅 Naptár</h3>
                    <p>[Naptár ide]</p>
                </div>

                <div class="card notifications">
                    <h3>🔔 Értesítések</h3>
                    <ul>
                        <li>Új kurzusfórum hozzászólás: Matematika 2</li>
                        <li>Új teszt eredmény: Webfejlesztés</li>
                        <li>Új hirdetmény: Angol</li>
                    </ul>
                </div>
            </aside>

            <!-- FŐ TARTALOM -->
            <section class="main-content">
                <h1>Kezdőlap</h1>
                <p>Itt jelenik meg a tartalom.</p>

                <?php
                // Ha van találat
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<div class="course-card">';
                        echo '<h3>' . htmlspecialchars($row['Teszt2']) . '</h3>'; // kurzus neve (teszt2)

                        // Hirdetmények
                        echo '<div class="section">';
                        echo '<h4>📢 Hirdetmények</h4>';
                        echo '<ul>';
                        foreach (explode('|', $row['Teszt4']) as $hirdetes) {
                            echo '<li>' . htmlspecialchars($hirdetes) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';

                        // Fórum
                        echo '<div class="section">';
                        echo '<h4>💬 Kurzus fórum</h4>';
                        echo '<ul>';
                        foreach (explode('|', $row['Teszt3']) as $forum) {
                            echo '<li>' . htmlspecialchars($forum) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';

                        // Tesztek
                        echo '<div class="section">';
                        echo '<h4>📝 Tesztek</h4>';
                        echo '<ul>';
                        echo '<li>Legutolsó teszt: ' . htmlspecialchars($row['Teszt1']) . '</li>';
                        echo '</ul>';
                        echo '</div>';

                        echo '</div>';
                    }
                } else {
                    echo "<p>Nincs elérhető kurzus.</p>";
                }

                $conn->close(); // Kapcsolat lezárása
                ?>
            </section>
        </main>

        <script src="Scripts/scripts.js"></script>
    </body>
</html>