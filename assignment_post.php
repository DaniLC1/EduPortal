<?php
global $conn;
include 'connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// POST változók beolvasása
$assignment_id = $_POST['assignment_id'] ?? null;
$answers = $_POST['answers'] ?? [];
$eduportal_id = $_SESSION['eduportal_id'] ?? null;

if (!$assignment_id || !$eduportal_id || empty($answers)) {
    die("Hiányzó adatok a beküldéshez.");
}

// Tranzakció indítása
$conn->begin_transaction();

try {
    // 1. Beadás beszúrása
    $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, users_eduportal_ID, submitted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $assignment_id, $eduportal_id);
    $stmt->execute();
    $submission_id = $stmt->insert_id;
    $stmt->close();

    $total_score = 0;

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
            // Ez egy tömb: [answer_id => 1, ...]
            $selected_ids = array_map('intval', array_keys($answer_value));

            // Mentés minden kijelölt válaszra
            foreach ($selected_ids as $aid) {
                $stmt = $conn->prepare("INSERT INTO submission_answers (submission_id, question_id, selected_answer_id) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $submission_id, $question_id, $aid);
                $stmt->execute();
                $stmt->close();
            }

            // Ellenőrzés: pontos egyezés kell a helyes válaszokkal
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

    // 4. Pontszám frissítése
    $stmt = $conn->prepare("UPDATE assignment_submissions SET score = ?, graded_at = NOW() WHERE id = ?");
    $stmt->bind_param("di", $total_score, $submission_id);
    $stmt->execute();
    $stmt->close();

    // Tranzakció mentése
    $conn->commit();

    // Visszairányítás
    header("Location: courses.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Hiba történt: " . $e->getMessage());
}
