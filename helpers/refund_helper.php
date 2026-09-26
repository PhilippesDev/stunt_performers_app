<?php
// helpers/refund_helper.php
require_once __DIR__ . '/notification_helper.php';
require_once dirname(__DIR__) . '/env.php';

if (!function_exists('detect_provider_from_phone')) {
    /**
     * Détecte le fournisseur Mobile Money selon l'indicatif DRC conforme à l'API B2C MaishaPay.
     * Providers acceptés pour DRC : MPESA, AIRTEL, ORANGE.
     */
    function detect_provider_from_phone($phone) {
        $digits = preg_replace('/\D/', '', $phone);
        if (strpos($digits, '243') === 0) {
            $local = substr($digits, 3);
        } elseif (strpos($digits, '0') === 0) {
            $local = substr($digits, 1);
        } else {
            $local = $digits;
        }
        if (strlen($local) < 2) return 'AIRTEL';
        $prefix = substr($local, 0, 2);
        $map = [
            '81' => 'MPESA', '82' => 'MPESA', '83' => 'MPESA',
            '97' => 'AIRTEL', '98' => 'AIRTEL', '99' => 'AIRTEL', '70' => 'AIRTEL', '71' => 'AIRTEL',
            '84' => 'ORANGE', '85' => 'ORANGE', '89' => 'ORANGE', '80' => 'ORANGE'
        ];
        return $map[$prefix] ?? 'AIRTEL';
    }
}

if (!function_exists('format_wallet_id')) {
    /**
     * Formate le numéro au format international requis (+243xxxxxxxxx).
     */
    function format_wallet_id($phone) {
        $clean = preg_replace('/\D/', '', $phone);
        if (strpos($clean, '243') === 0) {
            return '+' . $clean;
        } elseif (strpos($clean, '0') === 0) {
            return '+243' . substr($clean, 1);
        } else {
            return '+243' . $clean;
        }
    }
}

if (!function_exists('process_b2c_refund')) {
    /**
     * Exécute un remboursement B2C intégral via l'API officielle MaishaPay Mobile Money.
     * Documentation officielle : https://documenter.getpostman.com/view/22376672/2sAYQXnCU4#8b19e241-ec11-4302-bfd1-cae935e8d21d
     * Endpoint : POST https://marchand.maishapay.online/api/b2c/store/transfert/mobilemoney
     *
     * @param int $order_id ID de la commande
     * @param mysqli $conn Connexion DB
     * @param string $reason Motif de l'annulation
     * @param bool|int $penalize_seller Indique si la crédibilité du vendeur doit être décrémentée
     * @return array Résultat détaillé avec statut B2C exact
     */
    function process_b2c_refund($order_id, $conn, $reason = '', $penalize_seller = true) {
        $order_id = intval($order_id);
        if (!$order_id) {
            return [
                'success' => false,
                'b2c_success' => false,
                'status_code' => 400,
                'transaction_status' => 'INVALID_ORDER_ID',
                'message' => "ID de commande invalide."
            ];
        }

        // 1. Récupérer les données de la commande, client et vendeur
        $stmt = $conn->prepare("
            SELECT o.id, o.user_id, o.seller_id, o.product_id, o.customer_name, 
                   o.customer_phone, o.total_amount, o.currency, o.status, 
                   o.refund_status, o.transaction_id, p.name AS product_name,
                   u_seller.username AS seller_name, u_seller.credibility_score
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN users u_seller ON o.seller_id = u_seller.id
            WHERE o.id = ?
        ");
        if (!$stmt) {
            return [
                'success' => false,
                'b2c_success' => false,
                'status_code' => 500,
                'transaction_status' => 'DB_ERROR',
                'message' => "Erreur de requête base de données : " . $conn->error
            ];
        }
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return [
                'success' => false,
                'b2c_success' => false,
                'status_code' => 404,
                'transaction_status' => 'ORDER_NOT_FOUND',
                'message' => "Commande introuvable."
            ];
        }

        $phone = $order['customer_phone'];
        $amount = floatval($order['total_amount']);
        $currency = $order['currency'] ?: 'USD';
        $customer_name = $order['customer_name'] ?: 'Client';
        $provider = detect_provider_from_phone($phone);
        $walletID = format_wallet_id($phone);
        $seller_id = intval($order['seller_id']);
        $buyer_id = intval($order['user_id']);
        $product_name = $order['product_name'] ?: 'Produit';

        if ($order['refund_status'] === 'refunded') {
            return [
                'success' => true,
                'b2c_success' => true,
                'status_code' => 200,
                'transaction_status' => 'ALREADY_REFUNDED',
                'transaction_id' => $order['transaction_id'] ?: $order_id,
                'amount' => $amount,
                'currency' => $currency,
                'provider' => $provider,
                'phone' => $phone,
                'message' => "Cette commande a déjà été remboursée intégralement."
            ];
        }

        // 2. Préparation du payload B2C MaishaPay conforme à la doc Postman
        $transaction_ref = "REFUND-" . ($order['transaction_id'] ?: $order_id) . "-" . time();
        $payload = [
            "transactionReference" => $transaction_ref,
            "gatewayMode" => "0", // 0: sandbox, 1: live
            "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
            "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
            "order" => [
                "motif" => "Remboursement Commande #" . $order_id . ($reason ? " ($reason)" : ""),
                "amount" => (string)$amount, // Remboursement 100% intégral
                "currency" => $currency,
                "customerFullName" => $customer_name,
                "customerEmailAdress" => ""
            ],
            "paymentChannel" => [
                "provider" => $provider,
                "walletID" => $walletID,
                "callbackUrl" => "https://omp.alwaysdata.net/main/php/dashboard.php"
            ]
        ];

        // 3. Appel synchrone cURL vers l'API B2C MaishaPay
        $ch = curl_init("https://marchand.maishapay.online/api/b2c/store/transfert/mobilemoney");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response_data = json_decode($response, true);
        $api_status_code = $response_data['status_code'] ?? ($response_data['status'] ?? ($response_data['data']['statusCode'] ?? $http_code));
        $transaction_status = $response_data['transactionStatus'] ?? ($response_data['data']['status'] ?? ($response_data['status'] ?? 'UNKNOWN'));
        
        $is_success = ($api_status_code == 200 || $api_status_code == 202 || strtoupper($transaction_status) === 'SUCCESS' || strtoupper($transaction_status) === 'APPROVED');

        $transaction_id_returned = $response_data['transactionId'] ?? ($response_data['data']['transactionId'] ?? ($response_data['originatingTransactionId'] ?? $transaction_ref));

        // 4. Mettre à jour la commande : statut 'cancelled', et statut de remboursement exact
        $new_refund_status = $is_success ? 'refunded' : 'refund_failed';
        $update_status = $conn->prepare("UPDATE orders SET status = 'cancelled', refund_status = ? WHERE id = ?");
        if ($update_status) {
            $update_status->bind_param("si", $new_refund_status, $order_id);
            $update_status->execute();
            $update_status->close();
        }

        // 5. Pénaliser la réputation du vendeur si nécessaire
        if ($penalize_seller && $seller_id) {
            $penalty_points = is_numeric($penalize_seller) && $penalize_seller > 0 ? intval($penalize_seller) : 10;
            $conn->query("UPDATE users SET credibility_score = GREATEST(0, credibility_score - $penalty_points) WHERE id = $seller_id");
            
            $seller_msg = "Votre commande #$order_id a été annulée ($reason). Votre score de crédibilité a été réduit de $penalty_points%. Veillez à honorer vos livraisons avec des produits conformes et dans les délais impartis.";
            send_notification($conn, $seller_id, "Impact sur votre crédibilité (-$penalty_points%)", $seller_msg, "credibility_alert", "notifications.php");
        }

        // 6. Envoyer notification à l'acheteur
        if ($is_success) {
            $buyer_msg = "Votre commande #$order_id pour \"$product_name\" a été annulée. Un remboursement intégral de $amount $currency a été envoyé avec succès vers votre numéro $phone ($provider). Réf: $transaction_id_returned.";
            send_notification($conn, $buyer_id, "Remboursement validé ($amount $currency)", $buyer_msg, "refund_success", "notifications.php");
        } else {
            $buyer_msg = "Votre commande #$order_id pour \"$product_name\" a été annulée. La demande de remboursement de $amount $currency est en attente opérateur (Statut B2C: $transaction_status).";
            send_notification($conn, $buyer_id, "Commande annulée (Remboursement en cours)", $buyer_msg, "order_refunded", "notifications.php");
        }

        $api_desc = $response_data['transactionDescription'] ?? ($response_data['motif'] ?? ($response_data['message'] ?? ''));

        return [
            'success' => true,
            'b2c_success' => $is_success,
            'status_code' => $api_status_code,
            'transaction_status' => $transaction_status,
            'transaction_id' => $transaction_id_returned,
            'amount' => $amount,
            'currency' => $currency,
            'provider' => $provider,
            'phone' => $phone,
            'message' => $is_success
                ? "Remboursement de $amount $currency effectué avec succès vers votre numéro $phone ($provider)."
                : "La commande a été annulée. Réponse de l'API B2C : " . ($api_desc ?: "Statut $transaction_status (Code: $api_status_code)"),
            'api_response' => $response_data
        ];
    }
}
