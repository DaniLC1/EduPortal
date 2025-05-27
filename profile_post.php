<?php
require_once "connection.php";
session_start();
global $conn;

$eduportal_id = $_SESSION['eduportal_id'];
if (!$eduportal_id) {
    http_response_code(403);
    echo "Hozzáférés megtagadva.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_save_data'])) {

    // Tiltott mezők
    $excluded_fields = ['eduportal_id', 'id', 'password', 'created_at', 'profile_save_data'];

    // --- 1. Külön a szak_nev (vagy course) kezelése ---
    if (isset($_POST['szak_nev'])) {
        $szak_nev = trim($_POST['szak_nev']);
        // A programs tábla frissítése, ehhez kell a szak_szam, amit valahogy meg kell kapnod,
        // pl lekérdezed az eduportal_id-hoz tartozó user course_code-ját:
        $sql = "SELECT course_code FROM users WHERE eduportal_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $eduportal_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $course_code = $user['course_code'] ?? null;

        if ($course_code) {
            // programs tábla frissítése a course_code alapján
            $update_programs_sql = "UPDATE programs SET name = ? WHERE szak_szam = ?";
            $stmt2 = $conn->prepare($update_programs_sql);
            $stmt2->bind_param("ss", $szak_nev, $course_code);
            $stmt2->execute();
        }
        unset($_POST['szak_nev']); // Eltávolítjuk, hogy ne frissüljön users táblában
    }

    // --- 2. A többi mező frissítése a users táblában ---
    $updates = [];
    $values = [];
    $types = "";

    foreach ($_POST as $field => $value) {
        if (in_array($field, $excluded_fields)) continue;

        $updates[] = "`$field` = ?";
        $values[] = trim($value);
        $types .= "s";
    }

    if (!empty($updates)) {
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE eduportal_id = ?";
        $values[] = $eduportal_id;
        $types .= "s";

        $stmt3 = $conn->prepare($sql);
        if (!$stmt3) {
            $_SESSION['error'] = "Hiba történt: " . $conn->error;
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit;
        }

        $stmt3->bind_param($types, ...$values);
        if ($stmt3->execute()) {
            $_SESSION['success'] = "Adatok sikeresen mentve!";
        } else {
            $_SESSION['error'] = "Mentési hiba: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Nincs frissítendő adat.";
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

http_response_code(400);
echo "Ismeretlen művelet.";
exit;
