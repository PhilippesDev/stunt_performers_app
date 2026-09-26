<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $scanned_qr = $_POST['scanned_qr'];
    parse_str($scanned_qr, $params);

    $order_id = $params['order_id'];
    $validation_id = $params['validation_id'];

    // Vérifier l'identifiant de validation
    $query = $conn->prepare("SELECT qr_code, transaction_id FROM orders WHERE id = ? AND status = 'pending'");
    $query->bind_param("i", $order_id);
    $query->execute();
    $query->bind_result($stored_validation_id, $transaction_id);
    $query->fetch();
    $query->close();

    if ($stored_validation_id === $validation_id) {
        // Débloquer les fonds vers le vendeur
        $api_url = 'https://marchand.maishapay.online/api/payment/rest/vers1.0/merchant/unlock';
        $api_data = array(
            "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
            "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
            "transactionId" => $transaction_id
        );

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if ($response_data['original']['status'] == 200) {
            // Mettre à jour le statut de la commande
            $query = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
            $query->bind_param("i", $order_id);
            $query->execute();
            echo "Commande validée avec succès et fonds débloqués.";
        } else {
            echo "Erreur lors du déblocage des fonds.";
        }
    } else {
        echo "Échec de la validation. Le code QR ne correspond pas.";
    }
}
?>