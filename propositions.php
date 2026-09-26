<?php
// propositions.php - Propositions alternatives suite au refus d'un fournisseur
session_start();
require_once 'db.php';
require_once 'helpers/notification_helper.php';
require_once 'helpers/refund_helper.php';

$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$message = '';
$error = '';

if (!$order_id) {
    header("Location: index.php");
    exit();
}

// 1. Récupérer les informations de la commande refusée
$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.customer_name, o.customer_phone, o.total_amount, 
           o.currency, o.status, o.refund_status, o.product_id, 
           p.name AS product_name, p.category
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: index.php");
    exit();
}

$product_name = $order['product_name'] ?: 'votre article commandé';
$buyer_name = $order['customer_name'] ?: 'Cher client';
$phone = $order['customer_phone'];
$total_amount = number_format(floatval($order['total_amount']), 2);
$currency = $order['currency'] ?: 'USD';

// 2. Traitement de la demande de remboursement par le client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_refund'])) {
    if ($order['refund_status'] === 'refunded') {
        $message = "Cette commande a déjà été remboursée intégralement sur votre numéro $phone.";
    } else {
        $refund_result = process_b2c_refund($order_id, $conn, "Remboursement demandé par le client suite à refus fournisseur", false);
        if ($refund_result['success']) {
            $message = "Votre remboursement intégral de $total_amount $currency a été envoyé avec succès vers votre numéro $phone !";
            $order['refund_status'] = 'refunded';
        } else {
            $error = "Une erreur est survenue lors de l'initiation du remboursement : " . ($refund_result['message'] ?? 'Erreur inconnue');
        }
    }
}

// 3. Récupérer la catégorie du produit refusé
$category_name = '';
if (!empty($order['category'])) {
    $decoded_cat = json_decode($order['category'], true);
    if (is_array($decoded_cat) && !empty($decoded_cat)) {
        $category_name = $decoded_cat[0];
    } else {
        $category_name = $order['category'];
    }
}

// 4. Récupérer des produits alternatifs du même genre avec la crédibilité vendeur
$suggested_products = [];
if (!empty($category_name)) {
    $search_cat = "%$category_name%";
    $stmt_sug = $conn->prepare("
        SELECT p.id, p.name, p.price, p.currency, p.product_condition, p.discount_percent,
               COALESCE(u.credibility_score, 100) AS credibility_score,
               pm.file_path
        FROM products p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN product_media pm ON pm.product_id = p.id
        WHERE p.id != ? AND p.category LIKE ?
        GROUP BY p.id
        ORDER BY u.credibility_score DESC, p.created_at DESC
        LIMIT 12
    ");
    $stmt_sug->bind_param("is", $order['product_id'], $search_cat);
    $stmt_sug->execute();
    $suggested_products = $stmt_sug->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_sug->close();
}

// Si pas assez de produits dans la même catégorie, compléter avec les plus récents des vendeurs fiables
if (count($suggested_products) < 4) {
    $exclude_id = intval($order['product_id']);
    $stmt_all = $conn->prepare("
        SELECT p.id, p.name, p.price, p.currency, p.product_condition, p.discount_percent,
               COALESCE(u.credibility_score, 100) AS credibility_score,
               pm.file_path
        FROM products p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN product_media pm ON pm.product_id = p.id
        WHERE p.id != ?
        GROUP BY p.id
        ORDER BY u.credibility_score DESC, p.created_at DESC
        LIMIT 8
    ");
    $stmt_all->bind_param("i", $exclude_id);
    $stmt_all->execute();
    $suggested_products = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_all->close();
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propositions alternatives | Cascade</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-gray-50 text-gray-900">

    <!-- En-tête -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-2">
                <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-xl font-bold tracking-tight text-gray-900">
                    ecascadeur<span class="text-fuchsia-500 font-medium">.com</span>
                </span>
            </a>
            <a href="index.php" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition">
                <i class="bi bi-arrow-left"></i> Retour à l'accueil
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Notification Message Banner -->
        <?php if ($message): ?>
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3">
                <i class="bi bi-check-circle-fill text-emerald-600 text-xl shrink-0"></i>
                <div class="text-sm font-medium text-emerald-900"><?= htmlspecialchars($message) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="rounded-2xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
                <i class="bi bi-exclamation-triangle-fill text-red-600 text-xl shrink-0"></i>
                <div class="text-sm font-medium text-red-900"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Carte d'information sur la commande refusée -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 sm:p-8 overflow-hidden relative">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <i class="bi bi-info-circle-fill"></i> Commande #<?= $order_id ?> refusée
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                        Désolé <?= htmlspecialchars($buyer_name) ?>,
                    </h1>
                    <p class="text-sm text-gray-600 max-w-2xl leading-relaxed">
                        Le fournisseur a décliné votre commande pour « <span class="font-semibold text-gray-900"><?= htmlspecialchars($product_name) ?></span> ». Ne vous inquiétez pas : votre argent est en totale sécurité. Vous pouvez choisir un autre produit équivalent ci-dessous ou demander un remboursement immédiat.
                    </p>
                </div>

                <!-- Action Remboursement -->
                <div class="shrink-0 bg-gray-50 border border-gray-200/80 rounded-2xl p-5 text-center sm:text-right space-y-3 min-w-[280px]">
                    <div class="text-xs text-gray-500 font-medium">Montant sécurisé en séquestre</div>
                    <div class="text-2xl font-mono font-black text-gray-900">
                        <?= $total_amount ?> <span class="text-sm font-sans font-bold text-gray-500"><?= htmlspecialchars($currency) ?></span>
                    </div>

                    <?php if ($order['refund_status'] === 'refunded'): ?>
                        <div class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-100 text-emerald-800 text-xs font-bold w-full justify-center">
                            <i class="bi bi-check-all text-base"></i> Remboursé sur <?= htmlspecialchars($phone) ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Confirmez-vous la demande de remboursement intégral de <?= $total_amount ?> <?= $currency ?> vers le numéro <?= htmlspecialchars($phone) ?> ?');">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <button type="submit" name="request_refund" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition shadow-md active:scale-95">
                                <i class="bi bi-cash-stack text-sm"></i>
                                Demander un remboursement intégral
                            </button>
                        </form>
                        <p class="text-[10px] text-gray-400">Remboursement direct par Mobile Money sur <?= htmlspecialchars($phone) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section des produits du même genre -->
        <section class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Autres produits dans ce genre</h2>
                    <p class="text-xs text-gray-500 mt-1">Articles équivalents proposés par des vendeurs de haute réputation.</p>
                </div>
                <span class="text-xs font-semibold text-fuchsia-600 bg-fuchsia-50 px-3 py-1 rounded-full border border-fuchsia-100">
                    <?= count($suggested_products) ?> suggestions
                </span>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-6">
                <?php foreach ($suggested_products as $item): ?>
                    <?php 
                        $img = $item['file_path'] ?: 'https://via.placeholder.com/300x300?text=Produit';
                        $cred = intval($item['credibility_score'] ?? 100);
                    ?>
                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 flex flex-col group">
                        <div class="relative aspect-square overflow-hidden bg-gray-100">
                            <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                            
                            <!-- Badge Crédibilité Vendeur -->
                            <div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-0.5 rounded-full border border-gray-100 text-[10px] font-bold flex items-center gap-1 shadow-sm">
                                <i class="bi bi-patch-check-fill text-fuchsia-600"></i>
                                <span><?= $cred ?>% fiabilité</span>
                            </div>

                            <?php if (!empty($item['discount_percent'])): ?>
                                <div class="absolute bottom-2 left-2 bg-red-600 text-white text-[10px] font-black px-2 py-0.5 rounded-md">
                                    -<?= intval($item['discount_percent']) ?>%
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                            <div>
                                <h3 class="font-semibold text-xs text-gray-900 line-clamp-2 leading-tight">
                                    <?= htmlspecialchars($item['name']) ?>
                                </h3>
                                <div class="mt-2 text-sm font-mono font-bold text-gray-900">
                                    <?= number_format(floatval($item['price']), 2) ?> <?= htmlspecialchars($item['currency']) ?>
                                </div>
                            </div>

                            <a href="buy_product.php?id=<?= $item['id'] ?>" class="w-full flex items-center justify-center gap-1.5 py-2.5 bg-gray-900 hover:bg-fuchsia-600 text-white text-xs font-bold rounded-xl transition duration-200 active:scale-95">
                                <i class="bi bi-cart-plus"></i> Voir le produit
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <footer class="bg-white border-t border-gray-100 py-6 text-center text-xs text-gray-400 mt-12">
        <p>&copy; 2026 Cascade e-commerce. Sécurité fiduciaire garantie.</p>
    </footer>

</body>
</html>
