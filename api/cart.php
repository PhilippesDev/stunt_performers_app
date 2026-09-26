<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? ($input['action'] ?? '');

if ($action === 'add') {
    $productId = intval($input['product_id'] ?? 0);
    $quantity = max(1, intval($input['quantity'] ?? 1));

    if ($productId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Produit invalide']);
        exit;
    }

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }

    $count = array_sum($_SESSION['cart']);
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier !',
        'cart_count' => $count
    ]);
    exit;
}

if ($action === 'update') {
    $productId = intval($input['product_id'] ?? 0);
    $quantity = intval($input['quantity'] ?? 0);

    if ($quantity <= 0) {
        unset($_SESSION['cart'][$productId]);
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }

    $count = array_sum($_SESSION['cart']);
    echo json_encode([
        'success' => true,
        'cart_count' => $count
    ]);
    exit;
}

if ($action === 'remove') {
    $productId = intval($input['product_id'] ?? 0);
    unset($_SESSION['cart'][$productId]);

    $count = array_sum($_SESSION['cart']);
    echo json_encode([
        'success' => true,
        'cart_count' => $count
    ]);
    exit;
}

if ($action === 'get') {
    $items = [];
    $total = 0;

    if (!empty($_SESSION['cart'])) {
        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = $conn->prepare("SELECT id, name, price, main_image as image FROM products WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $qty = $_SESSION['cart'][$row['id']];
            $row['quantity'] = $qty;
            $row['subtotal'] = floatval($row['price']) * $qty;
            $total += $row['subtotal'];
            $items[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'total' => number_format($total, 2, '.', ''),
        'cart_count' => array_sum($_SESSION['cart'])
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
