<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    echo json_encode(['error' => 'Product ID required', 'regions' => []]);
    exit;
}

$product_id = intval($_GET['product_id']);

// Récupérer la colonne regions de votre table products
$stmt = mysqli_prepare($conn, "SELECT regions FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($stmt);

if ($row && $row['regions']) {
    // Décoder le JSON stocké dans votre base
    $regions = json_decode($row['regions'], true);
    echo json_encode(['regions' => $regions ?: []]);
} else {
    echo json_encode(['regions' => []]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>