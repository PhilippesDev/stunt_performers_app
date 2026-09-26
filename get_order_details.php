<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID commande invalide']);
    exit();
}

try {
    // Récupérer les détails de la commande (sans l'OTP)
    $stmt = $conn->prepare("
        SELECT 
            t.id,
            t.product_id,
            t.user_id,
            t.seller_id,
            t.customer_name,
            t.customer_phone,
            t.customer_address,
            t.region,
            t.payment_method,
            t.unit_value,
            t.unit_type,
            t.selected_size,
            t.total_amount,
            t.original_amount,
            t.discount_applied,
            t.discount_percent,
            t.color_quantities,
            t.created_at,
            t.currency,
            p.name as product_name,
            p.description as product_description,
            u.username as seller_name,
            u.phone as seller_phone,
            buyer.username as buyer_name,
            buyer.phone as buyer_phone,
            buyer.email as buyer_email,
            o.quantity
        FROM temp_orders t
        JOIN products p ON t.product_id = p.id
        JOIN users u ON t.seller_id = u.id
        JOIN users buyer ON t.user_id = buyer.id
        JOIN orders o ON t.id = o.temp_order_id
        WHERE t.id = ? AND (t.user_id = ? OR t.seller_id = ?)
    ");
    
    $stmt->bind_param("iii", $order_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée ' . $order_id . ' for user ' . $user_id]);
        exit();
    }
    
    // Décoder les couleurs
    $order['color_quantities'] = json_decode($order['color_quantities'], true);
    
    // Récupérer l'image du produit
    $stmt = $conn->prepare("SELECT file_path FROM product_media WHERE product_id = ? AND file_type = 'image' ORDER BY sort_order LIMIT 1");
    $stmt->bind_param("i", $order['product_id']);
    $stmt->execute();
    $img_result = $stmt->get_result();
    $image = $img_result->fetch_assoc();
    $stmt->close();
    
    $order['product_image'] = $image ? $image['file_path'] : null;
    
    // Formater les dates
    $order['created_at_formatted'] = date('d/m/Y à H:i', strtotime($order['created_at']));
    
    // Calculer le total des quantités par couleur
    $total_color_qty = 0;
    if (is_array($order['color_quantities'])) {
        foreach ($order['color_quantities'] as $color) {
            $total_color_qty += intval($color['quantity'] ?? 0);
        }
    }
    $order['total_color_quantity'] = $total_color_qty != 0 ? $total_color_qty : $order['quantity'];
    
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>