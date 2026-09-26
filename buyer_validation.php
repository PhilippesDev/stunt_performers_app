<?php
// Inclure le fichier de connexion à la base de données
include 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scanned_order_id = $_POST['scanned_order_id'];

    // Vérifier si l'ID de commande scanné correspond à une commande valide
    $stmt = $conn->prepare("SELECT total_amount, seller_id FROM orders WHERE id = ? AND status = 'paid'");
    $stmt->bind_param("i", $scanned_order_id);
    $stmt->execute();
    $stmt->bind_result($total_amount, $seller_id);
    $stmt->fetch();
    $stmt->close();

    if ($seller_id) {
        // Calcul de la commission
        $commission = $total_amount * 0.05;
        $amount_to_seller = $total_amount - $commission;

        // Récupérer le numéro de téléphone du vendeur
        $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $stmt->bind_result($seller_phone);
        $stmt->fetch();
        $stmt->close();

        // Simuler le transfert vers le compte mobile du vendeur
        echo "Le paiement de $amount_to_seller USD a été transféré au vendeur. Commission de 5% ($commission USD) retenue.";

        // Mettre à jour le statut de la commande en 'delivered'
        $stmt = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
        $stmt->bind_param("i", $scanned_order_id);
        $stmt->execute();
        $stmt->close();
    } else {
        echo "Erreur : ID de commande invalide ou déjà livré.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation du paiement</title>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.1.0/minified/html5-qrcode.min.js"></script>
</head>
<body>
    <h1>Validation de la livraison</h1>
    <p>Scannez le QR code du vendeur pour confirmer la livraison et libérer les fonds.</p>

    <div id="reader" style="width:500px;"></div>
    <form id="qr-form" action="buyer_validation.php" method="POST">
        <input type="hidden" name="scanned_order_id" id="scanned_order_id">
    </form>

    <script>
        function onScanSuccess(decodedText) {
            // Extraire l'ID de commande du QR code
            let orderId = new URLSearchParams(decodedText).get('order_id');
            if (orderId) {
                document.getElementById('scanned_order_id').value = orderId;
                document.getElementById('qr-form').submit();
            } else {
                alert("QR code invalide !");
            }
        }

        function onScanFailure(error) {
            // Erreur lors du scan du QR code
            console.warn(`Erreur de scan: ${error}`);
        }

        let html5QrcodeScanner = new Html5QrcodeScanner(
            "reader", { fps: 10, qrbox: 250 });
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    </script>
</body>
</html>