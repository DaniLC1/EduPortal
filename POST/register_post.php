<?php
// Globális session és connection
require_once __DIR__ . '/../connection.php';
session_start();
global $conn;

/* ============================================================
   🔹 Jogosultság ellenőrzés
============================================================ */
if (!isset($_SESSION['eduportal_id'])) {
    header("Location: ../index.php?error=Nincs jogosultságod az oldal megtekintéséhez.");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];

/* ============================================================
   🔹 Csak POST esetén fusson
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'admin') {

    try {
        $conn->begin_transaction();

        /* ============================================================
        🔹 Adatok inicializálása
        ============================================================ */
        $name = trim($_POST['name'] ?? '');
        $eduportal_id = trim($_POST['eduportal_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $mothers_name = trim($_POST['mothers_name'] ?? '');
        $role = $_POST['role'] ?? '';
        $course_code = $_POST['course_code'] ?: null;
        $financing_type = $_POST['financing_type'] ?: null;
        $password = $_POST['password'] ?? '';

        /* ============================================================
           🔹 Kötelező mezők ellenőrzése
        ============================================================= */
        if (empty($name) || empty($eduportal_id) || empty($email) || empty($role) || empty($password)) {
            throw new Exception("Minden kötelező mezőt ki kell tölteni!");
        }

        if ($role == ('admin' || 'tanar') && $financing_type == 'önköltséges' ) {
            throw new Exception('Kolléga finanszírozási típusa nem lehet önköltséges!');
        }

        if ($role == 'hallgato' && $course_code == '' ) {
            throw new Exception('Hallgatóhoz kötelező rendelni kurzust!');
        }

        if (!preg_match('/^EDU[0-9]{6}$/', $eduportal_id)) {
            throw new Exception("Az eduportal ID formátuma hibás! (Helyes: EDU123456)");
        }

        /* ============================================================
           🔹 EDUPORTAL ID egyediség ellenőrzése
        ============================================================= */
        $check_sql = "SELECT id FROM users WHERE eduportal_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $eduportal_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();

        if ($exists) {
            throw new Exception("Már létezik felhasználó ezzel az EDUPORTAL ID-val!");
        }

        /* ============================================================
           🔹 Jelszó hash-elése
        ============================================================= */
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        /* ============================================================
          🔹 Felhasználó mentése
       ============================================================= */

        $insert_sql = "
        INSERT INTO users 
        (name, eduportal_id, email, phone, password_hash, postal_code, city, address, birth_date, mothers_name, role, course_code, financing_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param(
            "sssssssssssss",
            $name,
            $eduportal_id,
            $email,
            $phone,
            $password_hash,
            $postal_code,
            $city,
            $address,
            $birth_date,
            $mothers_name,
            $role,
            $course_code,
            $financing_type
        );
        if (!$stmt->execute()) {
            throw new Exception("Mentési hiba: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit();

        header("Location: ../admin/register.php?success=12");
        exit;

    }catch (Exception $e) {
        $conn->rollback();

        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        header("Location: ../admin/register.php?error={$error_message}");
        exit;
    }
}else {
    /* ============================================================
       🔹 Fallback (ha nem POST) -> kell ha valaki az direktbe akarja megnyitni a _post.php fájlt
    ============================================================ */
    header("Location: ../index.php?error=Fallback");
    exit;
}
