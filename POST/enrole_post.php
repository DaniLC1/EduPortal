<?php
session_start();
require_once __DIR__ . '/../connection.php';
global $conn;

if (!isset($_SESSION['eduportal_id'])) {
    header("Location: index.php?error=unauthorized");
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
$role = $_SESSION['role'];

/* ============================================
   🧑‍🏫 TANÁR: Új kurzusóra (offering) létrehozása + kurzusleírás frissítés
============================================ */
if ($role === 'tanar' && isset($_POST['kurzus_kod'])) {
    $kurzus_kod = trim($_POST['kurzus_kod']);
    $semester_id = intval($_POST['semester_id'] ?? 0);
    $course_type = trim($_POST['course_type'] ?? 'Gyakorlat');
    $day_of_week = trim($_POST['day_of_week'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $room = trim($_POST['room'] ?? '');
    $max_students = intval($_POST['max_students'] ?? 0);
    $end_date = $_POST['end_date'] ?? null;
    $course_description = trim($_POST['course_description'] ?? '');

    if (empty($kurzus_kod) || empty($semester_id) || empty($day_of_week) || empty($start_time)) {
        $error_message = urlencode("Hiányzó adatok az új kurzusóra létrehozásához.");
        header("Location: /EduPortal/teacher/courses.php?error={$error_message}");
        exit;
    }

    try {
        $conn->begin_transaction();

        // 1️⃣ Kurzusleírás frissítése a 'courses' táblában (ha meg van adva)
        if (!empty($course_description)) {
            $update_course_sql = "UPDATE courses SET leiras = ? WHERE kurzus_kod = ?";
            $stmt = $conn->prepare($update_course_sql);
            $stmt->bind_param("ss", $course_description, $kurzus_kod);
            if (!$stmt->execute()) {
                throw new Exception("A kurzus leírásának frissítése sikertelen: " . $stmt->error);
            }
            $stmt->close();
        }

        // 2️⃣ Új kurzusóra létrehozása
        $insert_sql = "
            INSERT INTO course_offerings 
            (kurzus_kod, semester_id, teacher_id, course_type, day_of_week, start_time, room, end_date, max_students)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sissssssi",
            $kurzus_kod,
            $semester_id,
            $eduportal_id,
            $course_type,
            $day_of_week,
            $start_time,
            $room,
            $end_date,
            $max_students
        );

        if (!$stmt->execute()) {
            throw new Exception("Sikertelen kurzusóra beszúrás: " . $stmt->error);
        }

        $conn->commit();
        $stmt->close();

        header("Location: teacher/course_offering.php?success=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        header("Location: /EduPortal/teacher/courses.php?error={$error_message}");
        exit;
    }
}

/* ============================================
   🧑‍🏫 TANÁR: Meglévő kurzusóra (offering) szerkesztése
============================================ */
if ($role === 'tanar' && isset($_POST['offering_id'])) {
    $offering_id = intval($_POST['offering_id']);
    $day_of_week = trim($_POST['day_of_week'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $room = trim($_POST['room'] ?? '');
    $end_date = $_POST['end_date'] ?? null;
    $max_students = intval($_POST['max_students'] ?? 0);
    $course_description = trim($_POST['course_description'] ?? '');

    try {
        $conn->begin_transaction();

        // 1️⃣ Kurzusóra adatok frissítése
        $update_sql = "
            UPDATE course_offerings
            SET day_of_week = ?, start_time = ?, room = ?, end_date = ?, max_students = ?
            WHERE id = ?
        ";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssii", $day_of_week, $start_time, $room, $end_date, $max_students, $offering_id);
        $stmt->execute();
        $stmt->close();

        // 2️⃣ Kurzus leírás frissítése (a kapcsolt kurzusban)
        if (!empty($course_description)) {
            $get_course_sql = "SELECT kurzus_kod FROM course_offerings WHERE id = ?";
            $stmt = $conn->prepare($get_course_sql);
            $stmt->bind_param("i", $offering_id);
            $stmt->execute();
            $stmt->bind_result($kurzus_kod);
            $stmt->fetch();
            $stmt->close();

            if (!empty($kurzus_kod)) {
                $update_course_sql = "UPDATE courses SET leiras = ? WHERE kurzus_kod = ?";
                $stmt = $conn->prepare($update_course_sql);
                $stmt->bind_param("ss", $course_description, $kurzus_kod);
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        header("Location: /EduPortal/teacher/course_offering.php?success=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        header("Location: /EduPortal/teacher/course_offering.php?error={$error_message}");
        exit;
    }
}

/* ============================================
   🧑‍🎓 DIÁK: Jelentkezés / lejelentkezés
============================================ */
if ($role === 'hallgato' && isset($_POST['offering_id'])) {
    $offering_id = intval($_POST['offering_id']);
    $action = $_POST['action'] ?? 'enroll';

    try {
        $conn->begin_transaction();

        // Kurzusrészletek lekérdezése
        $sql = "
            SELECT co.kurzus_kod, co.semester_id, co.end_date, c.name AS course_name
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
            throw new Exception("A megadott kurzus nem található.");
        }

        $offering = $result->fetch_assoc();
        $course_code = $offering['kurzus_kod'];
        $semester_id = $offering['semester_id'];
        $end_date = $offering['end_date'];

        // ⚠️ Jelentkezési határidő ellenőrzése
        $now = date('Y-m-d H:i:s');
        if ($now > $end_date) {
            throw new Exception("Lejárt a jelentkezési határidő.");
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
            $delete_stmt->execute();
            $delete_stmt->close();

            $conn->commit();
            header("Location: /EduPortal/student/course_offering.php?success=1");
            exit;

        } else {
            // Jelentkezés ellenőrzése
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
            if ($check_result->num_rows > 0) throw new Exception("Már jelentkeztél erre a kurzusra ebben a félévben.");

            // Jelentkezés beszúrása
            $status = 'enrolled';
            $insert_sql = "INSERT INTO enrollments (users_eduportal_ID, offering_id, status) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("sis", $eduportal_id, $offering_id, $status);
            $insert_stmt->execute();
            $insert_stmt->close();

            $conn->commit();
            header("Location: /EduPortal/student/course_offering.php?success=2");
            exit;
        }

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = urlencode("Hiba történt: " . $e->getMessage());
        header("Location: /EduPortal/student/course_offering.php?error={$error_message}");
        exit;
    }
}

// Fallback: ha nem teljesül semmi
header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
?>
