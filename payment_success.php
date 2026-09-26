<?php
require_once 'db.php';

// Transaction de query param
$transactionRef = $_GET['ref'] ?? '';
if (!$transactionRef) die("Référence manquante");

// Vérifier temp_order & transaction
$stmt = $conn->prepare("SELECT * FROM temp_orders WHERE transaction_reference=?");
$stmt->bind_param("s", $transactionRef);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) die("Commande introuvable");

// Enregistrer dans orders_confirmed
$ins = $conn->prepare("
  INSERT INTO orders_confirmed (temp_order_id, product_id, buyer_id, seller_id, total_amount, currency, transaction_id, provider)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$currency="CDF";
$ins->bind_param("iiiidsss",
    $order['id'],
    $order['product_id'],
    $order['user_id'],
    $order['seller_id'],
    $order['total_amount'],
    $currency,
    $transactionRef,
    $order['payment_method']
);
$ins->execute();
$ins->close();

// Supprimer temp_orders
$del = $conn->prepare("DELETE FROM temp_orders WHERE id=?");
$del->bind_param("i", $order['id']);
$del->execute();
$del->close();

echo "Paiement confirmé et commande enregistrée avec succès !";
