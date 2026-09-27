<?php
// api_notifications_poll.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['has_new' => false, 'unread_count' => 0]);
    exit();
}

require_once __DIR__ . '/../../libs/db.php';
require_once __DIR__ . '/../../libs/helpers/notification_helper.php';

$user_id = intval($_SESSION['user_id']);

// Vérifier les 2h et envoyer les alertes d'approche (30 min restantes)
check_and_expire_pending_orders($conn);

// Nombre total de non lues
$unread_count = get_unread_notifications_count($conn, $user_id);

// Récupérer la dernière notification non lue
$stmt = $conn->prepare("
    SELECT id, title, message, type, link, action_data, created_at 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY id DESC 
    LIMIT 1
");

$latest = null;
$has_new = false;

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $last_alerted_id = $_SESSION['last_alerted_notif_id'] ?? 0;
        if ($row['id'] > $last_alerted_id) {
            $has_new = true;
            $latest = $row;
            $_SESSION['last_alerted_notif_id'] = $row['id'];
        }
    }
    $stmt->close();
}

echo json_encode([
    'unread_count' => $unread_count,
    'has_new' => $has_new,
    'latest' => $latest
]);
