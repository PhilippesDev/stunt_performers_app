<?php
include 'db.php';
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations de la commande
$order_id = $_SESSION['order_id'];

// Vérifier que la commande a été payée
$stmt = $conn->prepare("SELECT status FROM orders WHERE id = ? AND status = 'paid'");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

if (!$status) {
    die('Commande non payée ou déjà confirmée.');
}

// Générer un code QR unique
$qr_code_content = "ORDER_CONFIRM_" . $order_id . "_" . uniqid();
$qr_code = generateQRCode($qr_code_content);

// Mettre à jour la commande avec le code QR
$stmt = $conn->prepare("UPDATE orders SET qr_code = ? WHERE id = ?");
$stmt->bind_param("si", $qr_code, $order_id);
$stmt->execute();
$stmt->close();

// Fonction pour générer le code QR
function generateQRCode($content) {
    include('phpqrcode/qrlib.php'); // Assurez-vous d'avoir la librairie PHP QR Code
    $tempDir = 'qrcodes/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $fileName = uniqid() . '.png';
    $filePath = $tempDir . $fileName;
    QRcode::png($content, $filePath, QR_ECLEVEL_L, 10);
    return $filePath;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Livraison</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Confirmation de Livraison</h1>
        <p>Scannez ce code QR pour confirmer la livraison :</p>
        <img src="<?php echo htmlspecialchars($qr_code); ?>" alt="QR Code" style="max-width: 300px;">
        <a href="dashboard.php">Retour au tableau de bord</a>
    </div>
</body>
</html>