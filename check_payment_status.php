<?php
// Connexion à la base de données
include 'db.php';

session_start();

// Récupérer l'ID de commande depuis la session
$order_id

 = $_SESSION['order_id'];

// Vérifier le statut du paiement
$stmt = $conn->prepare("SELECT status FROM payments WHERE transaction_id = ?");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

$response = [];

if ($status === 'paid') {
    $response['status'] = 'success';
} else {
    $response['status'] = 'pending';
}

echo json_encode($response);
?>