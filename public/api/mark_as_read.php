<?php
// mark_as_read.php - Marquer une ou toutes les notifications comme lues
session_start();
require_once __DIR__ . '/../../libs/db.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!$user_id) {
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = intval($_POST['notification_id'] ?? 0);
    $mark_all = isset($_POST['mark_all']) && $_POST['mark_all'] == '1';

    if ($mark_all) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    if ($is_ajax) {
        echo json_encode(['success' => true]);
        exit;
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: $redirect");
    exit;
}