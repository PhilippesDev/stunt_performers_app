<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$cancellation_id = intval($_GET['cancellation_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$cancellation_id) {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
    exit();
}

// Récupération des détails complets depuis order_cancellations
$stmt = $conn->prepare("
    SELECT 
        oc.id,
        oc.cancel_reason,
        oc.created_at AS cancellation_date,
        o.id AS order_id,
        o.customer_name,
        o.customer_phone,
        o.customer_address,
        o.quantity,
        o.total_amount,
        o.currency,
        o.status AS original_status,
        o.created_at AS order_date,
        p.name AS product_name,
        p.id AS product_id,
        u_seller.username AS seller_name,
        u_seller.phone AS seller_phone,
        u_buyer.username AS buyer_name,
        u_buyer.phone AS buyer_phone,
        u_canceller.username AS cancelled_by_name
    FROM order_cancellations oc
    INNER JOIN orders o ON oc.order_id = o.id
    INNER JOIN products p ON o.product_id = p.id
    INNER JOIN users u_seller ON o.seller_id = u_seller.id
    INNER JOIN users u_buyer ON o.user_id = u_buyer.id
    INNER JOIN users u_canceller ON oc.user_id = u_canceller.id
    WHERE oc.id = ?
      AND (o.seller_id = ? OR o.user_id = ?)
");

$stmt->bind_param("iii", $cancellation_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // L’annulation provient de la table des vendeurs → c’est une vente annulée
    $row['cancellation_type'] = 'Vente annulée';
    $row['cancellation_date_formatted'] = date('d/m/Y à H:i', strtotime($row['cancellation_date']));
    $row['order_date_formatted'] = date('d/m/Y à H:i', strtotime($row['order_date']));
    
    echo json_encode(['success' => true, 'cancellation' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Annulation introuvable']);
}

$stmt->close();