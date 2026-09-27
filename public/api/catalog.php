<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'newest';

$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($min_price > 0) {
    $sql .= " AND price >= ?";
    $params[] = $min_price;
    $types .= "d";
}

if ($max_price > 0) {
    $sql .= " AND price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

if ($sort === 'price_asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort === 'price_desc') {
    $sql .= " ORDER BY price DESC";
} else {
    $sql .= " ORDER BY id DESC";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $row['formatted_price'] = number_format($row['price'], 2);
    $products[] = $row;
}

echo json_encode([
    'success' => true,
    'count' => count($products),
    'products' => $products
]);
