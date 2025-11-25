<?php
session_start();
require_once __DIR__ . '/../connection.php';
global $conn;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = $_POST['notification_id'] ?? null;
    $eduportal_id = $_POST['eduportal_id'] ?? null;

    if ($notification_id && $eduportal_id) {
        $sql = "UPDATE notification_reads SET read_at = NOW() WHERE users_eduportal_ID = ? AND notification_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $eduportal_id, $notification_id);
        $stmt->execute();
    }
}

// 🔁 Visszairányítás a szerepkör alapján
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'tanar') {
        header("Location: teacher/courses.php");
    } else {
        header("Location: student/courses.php");
    }
} else {
    // Ha valamiért nincs session (biztonsági fallback)
    header("Location: index.php");
}
exit;



