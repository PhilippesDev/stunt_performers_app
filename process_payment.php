<?php
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $order_id = $_POST['order_id'];
    $phone = $_POST['phone'];
    $full_name = $_POST['full_name'];
    $provider = $_POST['provider'];

    // Récupérer les informations de la commande
    $query = $conn->prepare("SELECT total_amount, seller_id FROM orders WHERE id = ?");
    $query->bind_param("i", $order_id);
    $query->execute();
    $query->bind_result($total_amount, $seller_id);
    $query->fetch();
    $query->close();

    // Récupérer le numéro de téléphone du vendeur
    $query = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    $query->bind_param("i", $seller_id);
    $query->execute();
    $query->bind_result($seller_phone);
    $query->fetch();
    $query->close();

    // Calculer la commission et le montant net
    $commission = $total_amount * 0.05;
    $net_amount = $total_amount - $commission;

    // Appel à l'API MaishaPay
    $api_url = 'https://marchand.maishapay.online/api/payment/rest/vers1.0/merchant';
    $transaction_ref = uniqid('TRX_');
    $api_data = array(
        "gatewayMode" => 0, // 0 pour SandBox, 1 pour Live
        "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
        "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
        "transactionReference" => $transaction_ref,
        "amount" => $net_amount,
        "currency" => "USD",
        "customerFullName" => $full_name,
        "customerPhoneNumber" => $phone,
        "channel" => "MOBILEMONEY",
        "provider" => $provider,
        "walletID" => $seller_phone
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
        // Stocker l'ID de la transaction et générer le QR code
        $transaction_id = $response_data['original']['transactionId'];
        $validation_id = uniqid('QR_');

        $query = $conn->prepare("UPDATE orders SET transaction_id = ?, qr_code = ? WHERE id = ?");
        $query->bind_param("ssi", $transaction_id, $validation_id, $order_id);
        $query->execute();

        include 'phpqrcode/qrlib.php';
        $qr_content = "order_id=$order_id&validation_id=$validation_id";
        $filename = "qrcodes/$validation_id.png";
        QRcode::png($qr_content, $filename, QR_ECLEVEL_L, 10);

        header("Location: confirm_payment.php?order_id=$order_id");
        exit;
    } else {
        echo "Le paiement a échoué. Veuillez réessayer.";
    }
}
?>
