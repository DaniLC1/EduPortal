<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['eduportal_id'])) {
    $_SESSION['message'] = "Nem vagy bejelentkezve.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['offering_id'])) {
    $_SESSION['message'] = "Hibás kérés.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$offering_id = intval($_POST['offering_id']);
$action = $_POST['action'] ?? 'enroll';
$now = date('Y-m-d H:i:s');

global $conn;

$sql = "
    SELECT 
        co.kurzus_kod,
        co.semester_id,
        co.end_date,
        c.name AS course_name
    FROM course_offerings co
    JOIN courses c ON c.kurzus_kod = co.kurzus_kod
    WHERE co.id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $offering_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['message'] = "A megadott kurzus nem található.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$offering = $result->fetch_assoc();
$course_code = $offering['kurzus_kod'];
$semester_id = $offering['semester_id'];
$end_date = $offering['end_date'];

if ($now > $end_date) {
    $_SESSION['message'] = "Lejárt a jelentkezési határidő.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

if ($action === 'unenroll') {
    // Lejelentkezés
    $delete_sql = "
        DELETE e FROM enrollments e
        JOIN course_offerings co ON co.id = e.offering_id
        WHERE e.users_eduportal_ID = ?
          AND e.offering_id = ?
          AND co.kurzus_kod = ?
          AND co.semester_id = ?
    ";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("sisi", $eduportal_id, $offering_id, $course_code, $semester_id);

    if ($delete_stmt->execute()) {
        $_SESSION['message'] = "Sikeres lejelentkezés a(z) " . htmlspecialchars($offering['course_name']) . " kurzusról.";
    } else {
        $_SESSION['message'] = "Hiba történt a lejelentkezés során.";
    }

} else {
    // Beiratkozás
    $check_sql = "
        SELECT 1
        FROM enrollments e
        JOIN course_offerings co ON co.id = e.offering_id
        WHERE e.users_eduportal_ID = ?
          AND co.kurzus_kod = ?
          AND co.semester_id = ?
        LIMIT 1
    ";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ssi", $eduportal_id, $course_code, $semester_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = "Már jelentkeztél erre a kurzusra ebben a félévben.";
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    $status = 'enrolled';
    $insert_sql = "INSERT INTO enrollments (users_eduportal_ID, offering_id, status) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sis", $eduportal_id, $offering_id, $status);

    if ($insert_stmt->execute()) {
        $_SESSION['message'] = "Sikeres jelentkezés a(z) " . htmlspecialchars($offering['course_name']) . " kurzusra.";
    } else {
        $_SESSION['message'] = "Hiba történt a jelentkezés során.";
    }
}

// Visszairányítás az előző oldalra
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
