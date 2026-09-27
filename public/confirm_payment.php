<?php
include __DIR__ . '/../libs/db.php';
session_start();

$order_id = $_SESSION['order_id'] ?? null;

if (!$order_id) {
    header("Location: dashboard.php");
    exit();
}

// Récupérer les informations de la commande
$query = $conn->prepare("SELECT qr_code, total_amount, customer_name FROM orders WHERE id = ?");
$query->bind_param("i", $order_id);
$query->execute();
$query->bind_result($validation_id, $total_amount, $customer_name);
$query->fetch();
$query->close();

$filename = "qrcodes/$validation_id.png";
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-100">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation Commande | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .ticket-cut {
            position: relative;
            background-image: radial-gradient(circle at 0 50%, transparent 15px, white 16px),
                              radial-gradient(circle at 100% 50%, transparent 15px, white 16px);
            background-position: left, right;
            background-size: 51% 100%;
            background-repeat: no-repeat;
        }
        .dotted-line {
            border-top: 2px dashed #f3f4f6;
            width: 80%;
            margin: 0 auto;
        }
    </style>
</head>
<body class="h-full flex flex-col items-center justify-center p-6">

    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-flex items-center justify-center gap-2">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                <span class="text-2xl font-black text-gray-900">ecascadeur<span class="text-orange-500">.com</span></span>
            </a>
            <p class="text-gray-500 text-sm mt-2">Validation de votre achat</p>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-2xl overflow-hidden ticket-cut">
            
            <div class="p-8 text-center">
                <div class="inline-block px-4 py-1 bg-orange-100 text-orange-600 rounded-full text-[10px] font-bold uppercase tracking-widest mb-4">
                    Prêt pour validation
                </div>
                <h2 class="text-gray-400 text-xs font-bold uppercase tracking-tighter">Montant à régler</h2>
                <div class="text-4xl font-black text-gray-900 mt-1">
                    <?= number_format($total_amount, 2); ?> <span class="text-lg text-orange-500">$</span>
                </div>
                <p class="text-gray-500 text-sm mt-4 italic">"Merci pour votre confiance, <?= htmlspecialchars($customer_name); ?> !"</p>
            </div>

            <div class="dotted-line"></div>

            <div class="p-8 bg-white flex flex-col items-center">
                <div class="p-4 bg-gray-50 rounded-[2rem] border-2 border-gray-100 mb-6">
                    <?php if(file_exists($filename)): ?>
                        <img src="<?= $filename; ?>" alt="QR Code" class="w-48 h-48 mix-blend-multiply">
                    <?php else: ?>
                        <div class="w-48 h-48 flex items-center justify-center text-gray-400 border-4 border-dashed rounded-xl">
                            QR Code non généré
                        </div>
                    <?php endif; ?>
                </div>

                <p class="text-center text-[11px] text-gray-400 leading-relaxed mb-6 px-4">
                    Présentez ce code lors de la livraison ou scannez-le pour confirmer la réception de votre colis.
                </p>

                <a href="scan_qr.php?order_id=<?= $order_id; ?>" 
                   class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold text-center flex items-center justify-center gap-2 hover:bg-orange-600 transition-all shadow-lg active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Scanner maintenant
                </a>
            </div>
        </div>

        <div class="mt-8 text-center">
            <a href="dashboard.php" class="text-sm font-bold text-gray-400 hover:text-orange-500 transition-colors">
                Retour au tableau de bord
            </a>
        </div>
    </div>

</body>
</html>