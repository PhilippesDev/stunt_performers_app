<?php
require_once __DIR__ . '/db.php';

// Lire la réponse JSON de Maishapay
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data["transactionReference"]) || !isset($data["status"])) {
    die("Données invalides.");
}

$transactionRef = $data["transactionReference"];
$status = $data["status"]; // "success" ou "failed"

// Mettre à jour la table "orders" en fonction du paiement
if ($status === "success") {
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE transaction_ref = ?");
    $stmt->bind_param("s", $transactionRef);
    $stmt->execute();
    $stmt->close();
    echo "Paiement validé.";
} else {
    echo "Échec du paiement.";
}

$conn->close();
?>