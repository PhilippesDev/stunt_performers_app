<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Supprimer les annulations liées à l'utilisateur
$stmt = $conn->prepare("
    DELETE oc FROM order_cancellations oc
    JOIN orders o ON oc.order_id = o.id
    WHERE o.seller_id = ? OR o.user_id = ?
");
$stmt->bind_param("ii", $user_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Historique effacé']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
}

$stmt->close();
?>