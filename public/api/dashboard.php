<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($action === 'update_status') {
    $order_id = intval($input['order_id'] ?? 0);
    $status = trim($input['status'] ?? '');

    $allowed = ['pending', 'completed', 'cancelled'];
    if (!$order_id || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND (seller_id = ? OR buyer_id = ?)");
    $stmt->bind_param("siii", $status, $order_id, $user_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
    }
    exit;
}

if ($action === 'update_stock') {
    $product_id = intval($input['product_id'] ?? 0);
    $stock = intval($input['stock'] ?? 0);

    if ($product_id <= 0 || $stock < 0) {
        echo json_encode(['success' => false, 'error' => 'Stock ou produit invalide']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iii", $stock, $product_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Stock mis à jour']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur de mise à jour']);
    }
    exit;
}

if ($action === 'delete_product') {
    $product_id = intval($input['product_id'] ?? 0);

    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Produit invalide']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $product_id, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Produit supprimé avec succès']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
    }
    exit;
}

if ($action === 'get_order') {
    $order_id = intval($_GET['order_id'] ?? 0);

    $stmt = $conn->prepare("SELECT o.*, p.name as product_name, p.main_image as product_image 
                           FROM orders o 
                           LEFT JOIN products p ON o.product_id = p.id 
                           WHERE o.id = ? AND (o.seller_id = ? OR o.buyer_id = ?)");
    $stmt->bind_param("iii", $order_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if ($order) {
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
