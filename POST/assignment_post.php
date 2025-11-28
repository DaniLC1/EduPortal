<?php
require_once __DIR__ . '/../connection.php';
session_start();
global $conn;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

/* ===========================
   🧑‍🎓 DIÁK DOLGOZAT BEKÜLDÉS
=========================== */
if (isset($_POST['answers'])) {
    $assignment_id = $_POST['assignment_id'] ?? null;
    $answers = $_POST['answers'] ?? [];
    $eduportal_id = $_SESSION['eduportal_id'] ?? null;

    if (!$assignment_id || !$eduportal_id || empty($answers)) {
        die("Hiányzó adatok a beküldéshez.");
    }

    $conn->begin_transaction();

    try {
        // 1. Beadás beszúrása
        $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, users_eduportal_ID, submitted_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $assignment_id, $eduportal_id);
        $stmt->execute();
        $submission_id = $stmt->insert_id;
        $stmt->close();

        $total_score = 0;
        $question_type = '';
        $question_score = 0;
        $is_correct = 0;

        foreach ($answers as $question_id => $answer_value) {
            $question_id = (int)$question_id;

            // Kérdés típusának lekérdezése
            $stmt = $conn->prepare("SELECT question_type, score FROM assignment_questions WHERE id = ?");
            $stmt->bind_param("i", $question_id);
            $stmt->execute();
            $stmt->bind_result($question_type, $question_score);
            $stmt->fetch();
            $stmt->close();

            if ($question_type === 'multiple_choice') {
                $selected_answer_id = (int)$answer_value;

                // Válasz mentése
                $stmt = $conn->prepare("INSERT INTO submission_answers (submission_id, question_id, selected_answer_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $submission_id, $question_id, $selected_answer_id);
                $stmt->execute();
                $stmt->close();

                // Pontszám ellenőrzés
                $stmt = $conn->prepare("SELECT is_correct FROM question_answers WHERE id = ?");
                $stmt->bind_param("i", $selected_answer_id);
                $stmt->execute();
                $stmt->bind_result($is_correct);
                if ($stmt->fetch() && $is_correct) {
                    $total_score += (float)$question_score;
                }
                $stmt->close();

            } elseif ($question_type === 'true_false') {
                // Több kijelölt válasz kezelése
                $selected_ids = array_map('intval', array_keys($answer_value));

                foreach ($selected_ids as $aid) {
                    $stmt = $conn->prepare("INSERT INTO submission_answers (submission_id, question_id, selected_answer_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $submission_id, $question_id, $aid);
                    $stmt->execute();
                    $stmt->close();
                }

                // Ellenőrzés
                $correct_ids = [];
                $stmt = $conn->prepare("SELECT id FROM question_answers WHERE question_id = ? AND is_correct = 1");
                $stmt->bind_param("i", $question_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $correct_ids[] = (int)$row['id'];
                }
                $stmt->close();

                sort($selected_ids);
                sort($correct_ids);

                if ($selected_ids === $correct_ids) {
                    $total_score += (float)$question_score;
                }
            }
        }

        // 4. Pontszám mentése
        $stmt = $conn->prepare("UPDATE assignment_submissions SET score = ?, graded_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $total_score, $submission_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        header("Location: /EduPortal/student/courses.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Hiba történt: " . $e->getMessage());
    }
}

/* ===========================
   👩‍🏫 TANÁR DOLGOZAT LÉTREHOZÁS / SZERKESZTÉS
=========================== */
elseif (isset($_POST['title']) && isset($_POST['questions'])) {

    $assignment_id = $_POST['assignment_id'] ?? null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $available_from = $_POST['available_from'] ?? null;
    $due_date = $_POST['due_date'] ?? null;
    $max_attempts = (int)($_POST['max_attempts'] ?? 1);
    $offering_id = $_POST['offering_id'] ?? null;
    $questions = $_POST['questions'] ?? [];

    if (empty($title) || empty($offering_id)) {
        die("Hiányzó adatok a dolgozat mentéséhez.");
    }

    $conn->begin_transaction();

    try {
        if ($assignment_id) {
            // 🔄 Szerkesztés
            $stmt = $conn->prepare("
                UPDATE assignments 
                SET title = ?, description = ?, available_from = ?, due_date = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssi", $title, $description, $available_from, $due_date, $assignment_id);
            $stmt->execute();
            $stmt->close();

            // Régi kérdések és válaszok törlése
            $stmt = $conn->prepare("SELECT id FROM assignment_questions WHERE assignment_id = ?");
            $stmt->bind_param("i", $assignment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $old_q_ids = [];
            while ($r = $result->fetch_assoc()) {
                $old_q_ids[] = $r['id'];
            }
            $stmt->close();

            if (!empty($old_q_ids)) {
                $in = implode(',', array_fill(0, count($old_q_ids), '?'));
                $types = str_repeat('i', count($old_q_ids));

                $stmt = $conn->prepare("DELETE FROM question_answers WHERE question_id IN ($in)");
                $stmt->bind_param($types, ...$old_q_ids);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM assignment_questions WHERE assignment_id = ?");
                $stmt->bind_param("i", $assignment_id);
                $stmt->execute();
                $stmt->close();
            }

        } else {
            // 🆕 Új dolgozat létrehozása
            $stmt = $conn->prepare("
                INSERT INTO assignments (offering_id, title, description, available_from, due_date, max_attempts)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("issssi", $offering_id, $title, $description, $available_from, $due_date, $max_attempts);
            $stmt->execute();
            $assignment_id = $stmt->insert_id;
            $stmt->close();
        }

        // 💾 Kérdések és válaszok mentése
        foreach ($questions as $question_index => $q) {
            $question_text = trim($q['text'] ?? '');
            $question_type = $q['type'] ?? 'multiple_choice';
            $score = isset($q['score']) ? (float)$q['score'] : 1.0;
            $answers = $q['answers'] ?? [];

            if (empty($question_text)) continue;

            // 🔹 Kérdés beszúrása
            $stmt = $conn->prepare("
        INSERT INTO assignment_questions (assignment_id, question_text, question_type, score)
        VALUES (?, ?, ?, ?)
    ");
            $stmt->bind_param("issd", $assignment_id, $question_text, $question_type, $score);
            $stmt->execute();
            $question_id = $stmt->insert_id;
            $stmt->close();

            // 🔹 Válaszok beszúrása is_correct kezeléssel
            foreach ($answers as $aid => $ans) {
                $answer_text = trim($ans['text'] ?? '');
                if ($answer_text === '') continue;

                $is_correct = isset($ans['is_correct']) ? 1 : 0;

                $stmt = $conn->prepare("
            INSERT INTO question_answers (question_id, answer_text, is_correct)
            VALUES (?, ?, ?)
        ");
                $stmt->bind_param("isi", $question_id, $answer_text, $is_correct);
                $stmt->execute();
                $stmt->close();
            }
        }


        $conn->commit();

        header("Location: /EduPortal/teacher/courses.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Hiba történt: " . $e->getMessage());
    }
}

/* ===========================
   ⚠️ ISMERETLEN POST
=========================== */
else {
    die("Ismeretlen POST kérés.");
}

