<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer le nombre de notifications non lues
$count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$count_row = $count_result->fetch_assoc();
$notification_count = $count_row['count'];
$count_stmt->close();

// Récupérer les dernières notifications
$notifications = [];
$stmt = $conn->prepare("
    SELECT id, title, message, is_read, created_at, icon 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'is_read' => $row['is_read'],
        'time' => time_elapsed_string($row['created_at']),
        'icon' => $row['icon'] ?? 'bell'
    ];
}
$stmt->close();

// Fonction pour formater la date
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = [
        'y' => 'an',
        'm' => 'mois',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde'
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . '' : 'à l\'instant';
}

echo json_encode([
    'success' => true,
    'count' => $notification_count,
    'notifications' => $notifications
]);
?>