<?php
global $conn;
require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = $_POST['notification_id'];
    $eduportal_id = $_POST['eduportal_id'];

    $sql = "UPDATE notification_reads SET read_at = NOW() WHERE users_eduportal_ID = ? AND notification_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $eduportal_id, $notification_id);
    $stmt->execute();
}

header("Location: courses.php");
exit;

