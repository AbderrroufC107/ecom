<?php
require_once 'inc/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$p_id = isset($_POST['p_id']) ? (int)$_POST['p_id'] : 0;
if (!$p_id) {
    echo json_encode(['status' => 'error', 'message' => 'معرف المنتج مفقود']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Update tbl_ai_product
    $stmt = $dbRepo->prepare("SELECT id FROM tbl_ai_product WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $exists = $stmt->fetch();

    if ($exists) {
        $stmt = $dbRepo->prepare("UPDATE tbl_ai_product SET 
            ai_version = ai_version + 1,
            selling_title = ?, short_pitch = ?, long_pitch = ?, cta = ?, 
            negotiable = ?, lowest_price = ?, max_discount_pct = ?, discount_conditions = ? 
            WHERE p_id = ?");
        $stmt->execute([
            $_POST['selling_title'] ?? '',
            $_POST['short_pitch'] ?? '',
            $_POST['long_pitch'] ?? '',
            $_POST['cta'] ?? '',
            isset($_POST['negotiable']) ? (int)$_POST['negotiable'] : 0,
            isset($_POST['lowest_price']) ? (float)$_POST['lowest_price'] : 0,
            isset($_POST['max_discount_pct']) ? (float)$_POST['max_discount_pct'] : 0,
            $_POST['discount_conditions'] ?? '',
            $p_id
        ]);
    } else {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_product 
            (p_id, ai_version, selling_title, short_pitch, long_pitch, cta, negotiable, lowest_price, max_discount_pct, discount_conditions) 
            VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $p_id,
            $_POST['selling_title'] ?? '',
            $_POST['short_pitch'] ?? '',
            $_POST['long_pitch'] ?? '',
            $_POST['cta'] ?? '',
            isset($_POST['negotiable']) ? (int)$_POST['negotiable'] : 0,
            isset($_POST['lowest_price']) ? (float)$_POST['lowest_price'] : 0,
            isset($_POST['max_discount_pct']) ? (float)$_POST['max_discount_pct'] : 0,
            $_POST['discount_conditions'] ?? ''
        ]);
    }

    // 2. Update tbl_ai_keyword
    $dbRepo->prepare("DELETE FROM tbl_ai_keyword WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['keywords'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_keyword (p_id, keyword, is_synonym) VALUES (?, ?, ?)");
        foreach ($_POST['keywords'] as $idx => $kw) {
            $kw = trim($kw);
            if ($kw !== '') {
                $is_synonym = isset($_POST['is_synonym'][$idx]) ? (int)$_POST['is_synonym'][$idx] : 0;
                $stmt->execute([$p_id, $kw, $is_synonym]);
            }
        }
    }

    // 3. Update tbl_ai_faq
    $dbRepo->prepare("DELETE FROM tbl_ai_faq WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['faq_questions'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_faq (p_id, question, answer) VALUES (?, ?, ?)");
        foreach ($_POST['faq_questions'] as $idx => $q) {
            $q = trim($q);
            $a = trim($_POST['faq_answers'][$idx] ?? '');
            if ($q !== '' || $a !== '') {
                $stmt->execute([$p_id, $q, $a]);
            }
        }
    }

    // 4. Update tbl_ai_objection
    $dbRepo->prepare("DELETE FROM tbl_ai_objection WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['obj_objections'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_objection (p_id, objection, best_reply, priority) VALUES (?, ?, ?, ?)");
        foreach ($_POST['obj_objections'] as $idx => $obj) {
            $obj = trim($obj);
            $rep = trim($_POST['obj_replies'][$idx] ?? '');
            $pri = isset($_POST['obj_priorities'][$idx]) ? (int)$_POST['obj_priorities'][$idx] : 0;
            if ($obj !== '' || $rep !== '') {
                $stmt->execute([$p_id, $obj, $rep, $pri]);
            }
        }
    }

    // 5. Update tbl_ai_training
    $dbRepo->prepare("DELETE FROM tbl_ai_training WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['tr_topics'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_training (p_id, topic, training_reply) VALUES (?, ?, ?)");
        foreach ($_POST['tr_topics'] as $idx => $topic) {
            $topic = trim($topic);
            $rep = trim($_POST['tr_replies'][$idx] ?? '');
            if ($rep !== '') {
                $stmt->execute([$p_id, $topic, $rep]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
