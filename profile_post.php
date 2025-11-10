<?php
require_once "connection.php";
session_start();
global $conn;

$eduportal_id = $_SESSION['eduportal_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_save_data'])) {

    // Tiltott mezők
    $excluded_fields = ['eduportal_id', 'id', 'password', 'created_at', 'profile_save_data'];

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
            $_SESSION['success'] = "✅ Adatok sikeresen mentve!";
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
