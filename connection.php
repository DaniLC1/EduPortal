<?php
// --- Adatbáziskapcsolat ---
$servername = "localhost"; // szerver név
$username = "root";        // adatbázis felhasználó
$password = "";            // jelszó (ha van)
$dbname = "eduportal_db"; // adatbázis neve

// Kapcsolódás
$conn = new mysqli($servername, $username, $password, $dbname);

// Ellenőrzés
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
