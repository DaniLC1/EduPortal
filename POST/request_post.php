<?php
session_start();
require_once __DIR__ . '/../connection.php';
global $conn;

if (!isset($_SESSION['eduportal_id'])) {
    header('Location: index.php');
    exit;
}

$eduportal_id = $_SESSION['eduportal_id'];
$role = $_SESSION['role'];
$source = $_POST['source'] ?? ''; // 🔹 Ebből tudjuk, honnan jött a POST (pl. "admin_request", "admin_submitted", "user_request")

try {
    $conn->begin_transaction();

    /* ================================================================
       🟢 1. HALLGATÓ / TANÁR kérelmek beküldése
       ================================================================= */
    if (in_array($role, ['hallgato', 'tanar'])) {

        $template_id = $_POST['template_id'] ?? null;
        if (!$template_id) {
            throw new Exception('Nem lett kiválasztva kérelemsablon.');
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
            throw new Exception('Nincsenek kitöltött mezők.');
        }

        // 🔹 Kérelem beszúrása
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

        // 🔁 Visszairányítás
        if ($role === 'tanar') {
            header('Location: teacher/request.php?success=1');
        } else {
            header('Location: student/request.php?success=1');
        }
        exit;
    }

    /* ================================================================
   🟡 ADMIN - request.php (kérelemsablon létrehozása / szerkesztése / törlése)
   ================================================================ */
    elseif ($role === 'admin' && ($_POST['source'] ?? '') === 'admin_request') {

        $template_id = $_POST['template_id'] ?? null;

        $conn->begin_transaction();

        try {
            // 🗑️ TÖRLÉS kezelése
            if (isset($_POST['delete'])) {
                if ($template_id) {
                    // 🔹 Először a mezők törlése
                    $stmt_del = $conn->prepare("DELETE FROM request_template_fields WHERE template_id = ?");
                    $stmt_del->bind_param("i", $template_id);
                    $stmt_del->execute();
                    $stmt_del->close();

                    // 🔹 Majd a sablon törlése
                    $stmt = $conn->prepare("DELETE FROM request_templates WHERE id = ?");
                    $stmt->bind_param("i", $template_id);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    header("Location: admin/request.php?success=deleted");
                    exit();
                } else {
                    $conn->rollback();
                    header("Location: admin/request.php?error=" . urlencode("Hibás sablon azonosító"));
                    exit();
                }
            }

            // 💾 MENTÉS / ÚJ sablon létrehozása
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $to_who = $_POST['to_who'] ?? 'hallgato';
            $fields = $_POST['fields'] ?? [];

            // opcionális validáció: ha üres a title, visszairányítás hibaüzenettel
            if (empty($title)) {
                $conn->rollback();
                header("Location: admin/request.php?error=" . urlencode("A kérelem címe kötelező"));
                exit();
            }

            // 🆕 Új sablon létrehozása
            if (empty($template_id)) {
                $stmt = $conn->prepare("
                INSERT INTO request_templates (title, description, to_who)
                VALUES (?, ?, ?)
            ");
                $stmt->bind_param("sss", $title, $description, $to_who);
                $stmt->execute();
                $template_id = $stmt->insert_id;
                $stmt->close();
            }
            // 🔄 Meglévő sablon frissítése
            else {
                $stmt = $conn->prepare("
                UPDATE request_templates 
                SET title = ?, description = ?, to_who = ?
                WHERE id = ?
            ");
                $stmt->bind_param("sssi", $title, $description, $to_who, $template_id);
                $stmt->execute();
                $stmt->close();

                // Régi mezők törlése a tiszta újrainsert miatt
                $stmt_del = $conn->prepare("DELETE FROM request_template_fields WHERE template_id = ?");
                $stmt_del->bind_param("i", $template_id);
                $stmt_del->execute();
                $stmt_del->close();
            }

            // 💾 Új mezők beszúrása
            if (!empty($fields)) {
                $stmt_field = $conn->prepare("
                INSERT INTO request_template_fields (template_id, label, field_type, is_required)
                VALUES (?, ?, ?, ?)
            ");
                foreach ($fields as $fid => $f) {
                    $label = trim($f['label'] ?? '');
                    $type = $f['field_type'] ?? 'text';
                    $req = isset($f['is_required']) ? 1 : 0;
                    if ($label !== '') {
                        $stmt_field->bind_param("issi", $template_id, $label, $type, $req);
                        $stmt_field->execute();
                    }
                }
                $stmt_field->close();
            }

            $conn->commit();
            header("Location: admin/request.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = urlencode("Hiba történt: " . $e->getMessage());
            header("Location: admin/request.php?error={$error_message}");
            exit();
        }
    }


    /* ================================================================
   🟣 ADMIN - submitted_request.php (kérelmek elbírálása)
   ================================================================= */
    elseif ($role === 'admin' && $source === 'admin_submitted') {

        $conn->begin_transaction();

        try {
            $request_id = intval($_POST['request_id'] ?? 0);
            if ($request_id <= 0) {
                throw new Exception("Érvénytelen kérelem azonosító.");
            }

            $status = trim($_POST['status'] ?? '');
            $admin_comment = trim($_POST['admin_comment'] ?? '');
            $admin_suggestions = $_POST['admin_suggestion'] ?? [];

            // 🔹 Ha nincs admin komment, állítsuk vissza "beküldve" státuszra
            if ($admin_comment === '') {
                $status = 'beküldve';
            }

            // 🔹 student_requests frissítése
            $stmt_req = $conn->prepare("
            UPDATE student_requests
            SET status = ?, 
                admin_comment = NULLIF(?, ''), 
                reviewed_at = NOW()
            WHERE id = ?
        ");
            $stmt_req->bind_param("ssi", $status, $admin_comment, $request_id);
            $stmt_req->execute();
            $stmt_req->close();

            // 🔹 student_request_field_values frissítése (admin javaslatok)
            if (!empty($admin_suggestions) && is_array($admin_suggestions)) {
                $stmt_field = $conn->prepare("
                UPDATE student_request_field_values
                SET admin_suggestion = NULLIF(?, '')
                WHERE request_id = ? AND field_id = ?
            ");

                foreach ($admin_suggestions as $field_id => $suggestion) {
                    $clean_suggestion = trim($suggestion);
                    $stmt_field->bind_param("sii", $clean_suggestion, $request_id, $field_id);
                    $stmt_field->execute();
                }

                $stmt_field->close();
            }

            $conn->commit();
            header("Location: admin/submitted_request.php?success=1");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = urlencode("Hiba történt a kérelem frissítése közben: " . $e->getMessage());
            header("Location: admin/submitted_request.php?error={$error_message}");
            exit();
        }
    }

    /* ================================================================
       🔴 Hibás / ismeretlen forrás
       ================================================================= */
    else {
        throw new Exception('Ismeretlen kérésforrás vagy jogosulatlan szerep.');
    }

} catch (Exception $e) {
    $conn->rollback();
    die('Hiba történt: ' . $e->getMessage());
}
?>
