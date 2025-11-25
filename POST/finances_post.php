<?php
session_start();
require_once __DIR__ . '/../connection.php';

$eduportal_id = $_SESSION['eduportal_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['financing_id']) || !isset($_POST['amount'])) {
    $_SESSION['message'] = "Hibás kérés.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$financing_id = intval($_POST['financing_id']);
$amount = floatval($_POST['amount']);

// Ellenőrzés – minimum 1000 Ft
if ($amount < 1000) {
    $_SESSION['message'] = "A minimum befizethető összeg 1.000 Ft.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

global $conn;

$insert_sql = "INSERT INTO payment_installments (financing_id, amount_paid) VALUES (?, ?)";
$stmt = $conn->prepare($insert_sql);

if (!$stmt) {
    $_SESSION['message'] = "Adatbázis hiba: " . $conn->error;
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$stmt->bind_param("id", $financing_id, $amount);

if ($stmt->execute()) {
    $_SESSION['message'] = "Sikeres befizetés: " . number_format($amount, 0, ',', ' ') . " Ft.";
} else {
    $_SESSION['message'] = "Hiba történt a befizetés során.";
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
