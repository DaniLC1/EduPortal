<?php
session_start();
require_once 'connection.php'; // adatbázis kapcsolat

if (!isset($_SESSION['eduportal_id'])) {
    // Nincs bejelentkezve, vissza a login oldalra
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Érvénytelen kérés.');
}

$eduportal_id = $_SESSION['eduportal_id'];
$template_id = $_POST['template_id'] ?? null;

if (!$template_id) {
    die('Nem lett kiválasztva kérelemsablon.');
}

// A POST adatból kiszedjük a kitöltött mezőket (melyek neve field_<id>)
$field_values = [];
foreach ($_POST as $key => $value) {
    if (strpos($key, 'field_') === 0) {
        $field_id = intval(substr($key, 6));
        $field_values[$field_id] = trim($value);
    }
}

if (empty($field_values)) {
    die('Nincsenek kitöltött mezők.');
}

global $conn;

// Beszúrjuk az új kérelmet (student_requests)
$stmt = $conn->prepare("INSERT INTO student_requests (users_eduportal_ID, template_id, status) VALUES (?, ?, 'beküldve')");
$stmt->bind_param("si", $eduportal_id, $template_id);

if (!$stmt->execute()) {
    die('Hiba a kérelem mentése során: ' . $stmt->error);
}

$request_id = $stmt->insert_id;
$stmt->close();

// Most beillesztjük a mezőértékeket a student_request_field_values-be
$stmt2 = $conn->prepare("INSERT INTO student_request_field_values (request_id, field_id, field_value) VALUES (?, ?, ?)");

foreach ($field_values as $field_id => $field_value) {
    $stmt2->bind_param("iis", $request_id, $field_id, $field_value);
    if (!$stmt2->execute()) {
        die('Hiba a mező érték mentése során: ' . $stmt2->error);
    }
}

$stmt2->close();

// Sikeres mentés, visszairányítás vagy üzenet megjelenítése
?>

<script>
    alert('A kérelem sikeresen beadva!');
    window.location.href = 'request.php';
</script>
