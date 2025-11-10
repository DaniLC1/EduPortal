<?php
session_start();
require_once 'connection.php'; // adatbázis kapcsolat

if (!isset($_SESSION['eduportal_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Érvénytelen kérés.');
}

$eduportal_id = $_SESSION['eduportal_id'];
$role = $_SESSION['role'] ?? 'diak'; // alapértelmezett szerep
$template_id = $_POST['template_id'] ?? null;

if (!$template_id) {
    die('Nem lett kiválasztva kérelemsablon.');
}

// 🟢 Kitöltött mezők összegyűjtése
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

try {
    $conn->begin_transaction();

    // 🔹 Kérelem mentése
    $stmt = $conn->prepare("
        INSERT INTO student_requests (users_eduportal_ID, template_id, status)
        VALUES (?, ?, 'beküldve')
    ");
    $stmt->bind_param("si", $eduportal_id, $template_id);
    $stmt->execute();
    $request_id = $stmt->insert_id;
    $stmt->close();

    // 🔹 Mezőértékek mentése
    $stmt2 = $conn->prepare("
        INSERT INTO student_request_field_values (request_id, field_id, field_value)
        VALUES (?, ?, ?)
    ");

    foreach ($field_values as $field_id => $field_value) {
        $stmt2->bind_param("iis", $request_id, $field_id, $field_value);
        $stmt2->execute();
    }

    $stmt2->close();
    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    die('Hiba történt: ' . $e->getMessage());
}

// ✅ Sikeres mentés után visszairányítás a szerepnek megfelelő oldalra
if ($role === 'tanar') {
    header('Location: teacher/request.php?success=1');
} else {
    header('Location: student/request.php?success=1');
}
exit;
