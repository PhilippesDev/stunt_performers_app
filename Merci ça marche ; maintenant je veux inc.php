Merci ça marche ; maintenant je veux inclure la gestion et la pris en compte des couleurs dans la soumission du formulaire et enregistrer tous les données de la commadans les orders si les champs sont insuffisantes tu me donne les code pour en ajouter , et après payement on redirige directement sur la page de paiement :

donc assure toi que la commande s'enregistre correctement dans la base de données avec toutes ces données et qu'on renvoie un popup de réussite pour une commande réussie et d'echec dans le cas contraire et si réussite on rédirige vers le payment.php ; code actuelle :<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    die('ID de produit invalide.');
}

// Récupération complète du produit selon la nouvelle structure
$stmt = $conn->prepare("
    SELECT p.*, 
           pm.file_path as main_image, 
           pm.file_type as main_file_type,
           c.name as category_name,
           u.username as seller_name
    FROM products p
    LEFT JOIN product_media pm ON p.id = pm.product_id AND pm.sort_order = 0 AND pm.is_color_image = 0
    LEFT JOIN categories c ON JSON_EXTRACT(p.category, '$[0].name') = c.name
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die('Produit non trouvé.');
}

// Récupérer tous les médias du produit
$media_stmt = $conn->prepare("
    SELECT file_path, file_type, is_color_image, sort_order 
    FROM product_media 
    WHERE product_id = ? 
    ORDER BY is_color_image, sort_order
");
$media_stmt->bind_param("i", $product_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
$all_media = [];
$color_images = [];
$main_media = [];

while ($media = $media_result->fetch_assoc()) {
    if ($media['is_color_image'] == 1) {
        $color_images[] = $media['file_path'];
    } else {
        $main_media[] = $media;
    }
}
$media_stmt->close();

// Récupérer les spécifications
$specifications = json_decode($product['specifications'] ?? '[]', true);
$colors_data = json_decode($product['colors'] ?? '[]', true);
$colors_with_images = [];

foreach ($colors_data as $index => $color) {
    $color_entry = [
        'index' => $index,
        'hex' => $color['hex'] ?? '#000000',
        'quantity' => 0 // Initialiser la quantité à 0
    ];
    
    // Si on a du base64 dans 'preview'
    if (isset($color['preview']) && strpos($color['preview'], 'data:image') === 0) {
        $color_entry['image_data'] = $color['preview'];
    }
    // Si on a déjà un chemin d'image (nouveau format)
    elseif (isset($color['image']) && is_string($color['image'])) {
        $color_entry['image_path'] = $color['image'];
    }
    
    $colors_with_images[] = $color_entry;
}
$regions = json_decode($product['regions'] ?? '[]', true);
$defects = json_decode($product['defects'] ?? '[]', true);
$shoe_sizes = json_decode($product['shoe_sizes'] ?? '[]', true);
$child_sizes = json_decode($product['child_sizes'] ?? '[]', true);
$adult_sizes = json_decode($product['adult_sizes'] ?? '[]', true);

// Calculer le prix avec et sans réduction
$price = floatval($product['price']);
$discount_percent = intval($product['discount_percent'] ?? 0);
$discount_threshold = floatval($product['discount_threshold'] ?? 0);
$min_order = floatval($product['min_order'] ?? 0);
$quantity = floatval($product['quantity'] ?? 0);

$has_discount = $discount_percent > 0 && $discount_threshold > 0;
$original_price = $price;
$discounted_price = $has_discount ? $price * (1 - $discount_percent / 100) : $price;

// Traitement du formulaire d'achat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $unit_value = filter_input(INPUT_POST, 'unit_value', FILTER_VALIDATE_FLOAT);
// Dans la section de traitement POST, là où vous récupérez selected_color
$selected_color = trim($_POST['selected_color'] ?? '');

// Si la couleur est un hex, c'est bon
// Si c'est du base64, vous pouvez le convertir en chemin de fichier
if (strpos($selected_color, 'data:image') === 0) {
    // Extraire et sauvegarder l'image
    $base64_data = $selected_color;
    $base64_parts = explode(',', $base64_data);
    $image_data = base64_decode($base64_parts[1]);
    
    // Déterminer l'extension
    $mime_type = explode(';', $base64_parts[0]);
    $mime_type = str_replace('data:', '', $mime_type[0]);
    $extension = '';
    
    switch($mime_type) {
        case 'image/jpeg': $extension = 'jpg'; break;
        case 'image/png': $extension = 'png'; break;
        case 'image/gif': $extension = 'gif'; break;
        case 'image/webp': $extension = 'webp'; break;
        default: $extension = 'jpg';
    }
    
    $filename = 'selected_color_' . uniqid() . '.' . $extension;
    $filepath = '../uploads/selected_colors/' . $filename;
    
    // Créer le dossier si nécessaire
    if (!file_exists('../uploads/selected_colors/')) {
        mkdir('../uploads/selected_colors/', 0777, true);
    }
    
    // Sauvegarder l'image
    file_put_contents($filepath, $image_data);
    $selected_color = $filepath;
}
        $selected_size = trim($_POST['selected_size'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $name = trim($_POST['customer_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');

        // Validation des champs obligatoires
        if (empty($phone) || empty($name) || empty($address) || empty($region) || empty($payment_method)) {
            throw new Exception('Tous les champs obligatoires doivent être remplis.');
        }

        if (!$unit_value || $unit_value <= 0) {
            throw new Exception('Veuillez spécifier une quantité valide.');
        }

        // Vérification du minimum de commande
        if ($min_order > 0 && $unit_value < $min_order) {
            throw new Exception("Le minimum de commande est de {$min_order} {$product['unit_type']}.");
        }

        // Calcul du montant
        $amount = $unit_value * $original_price;
        
        // Appliquer réduction si seuil atteint
        $is_discount_applied = false;
        if ($has_discount && $unit_value >= $discount_threshold) {
            $amount = $unit_value * $discounted_price;
            $is_discount_applied = true;
        }

        // Vérifier si la quantité est disponible
        if ($unit_value > $quantity) {
            throw new Exception("Quantité insuffisante en stock. Disponible: {$quantity} {$product['unit_type']}");
        }

        // Générer OTP
        $otp = random_int(100000, 999999);
        $buyer_id = $_SESSION['user_id'];

        // Insertion dans temp_orders
        $insert = $conn->prepare("
            INSERT INTO temp_orders 
            (product_id, user_id, seller_id, customer_name, customer_phone, customer_address, 
             region, payment_method, unit_value, unit_type, selected_color, selected_size, 
             total_amount, original_amount, discount_applied, discount_percent, otpvalidated, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $discount_applied_int = $is_discount_applied ? 1 : 0;
        $original_amount = $unit_value * $original_price;
        
        $insert->bind_param(
            "iiisssssdsssddiid",
            $product_id,
            $buyer_id,
            $product['user_id'],
            $name,
            $phone,
            $address,
            $region,
            $payment_method,
            $unit_value,
            $product['unit_type'],
            $selected_color,
            $selected_size,
            $amount,
            $original_amount,
            $discount_applied_int,
            $discount_percent,
            $otp
        );

        if (!$insert->execute()) {
            throw new Exception("Erreur lors de la création de la commande: " . $insert->error);
        }

        $order_id = $conn->insert_id;
        $insert->close();

        // Mettre à jour le stock
        $new_quantity = $quantity - $unit_value;
        $update_stmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
        $update_stmt->bind_param("di", $new_quantity, $product_id);
        $update_stmt->execute();
        $update_stmt->close();

        // Stocker les données de la commande en session
        $_SESSION['pending_order_id'] = $order_id;
        $_SESSION['pending_amount'] = $amount;
        $_SESSION['otp'] = $otp;

        // Rediriger vers la page de paiement
        header('Location: payment.php');
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Produits similaires
$similar_stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.discount_percent, pm.file_path as image
    FROM products p
    LEFT JOIN product_media pm ON p.id = pm.product_id AND pm.sort_order = 0 AND pm.is_color_image = 0
    WHERE p.category LIKE ? AND p.id != ? AND p.quantity > 0
    ORDER BY RAND()
    LIMIT 6
");
$category_like = '%"' . json_decode($product['category'])[0]->name . '"%';
$similar_stmt->bind_param("si", $category_like, $product_id);
$similar_stmt->execute();
$similar_products = $similar_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$similar_stmt->close();
?>
<?php
// Fonction pour convertir les balises simples en HTML
function parseMarkdown($text) {
    // 1. Convertir les titres : # Titre -> <h3 class="text-xl font-bold mt-4 mb-2">Titre</h3>
    $text = preg_replace('/^#\s+(.+)$/m', '<h3 class="text-xl font-bold text-gray-900 mt-4 mb-2 border-b pb-1">$1</h3>', $text);
    
    // 2. Convertir le gras : **texte** -> <strong>texte</strong>
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold text-gray-900">$1</strong>', $text);
    
    // 3. Préserver les retours à la ligne restants
    return nl2br($text);
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .swiper-slide { height: 500px; }
        .swiper-slide img, .swiper-slide video { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            background: #f9fafb;
        }
        .thumb-slide { height: 80px; cursor: pointer; opacity: 0.6; transition: opacity 0.3s; }
        .thumb-slide.active { opacity: 1; border: 2px solid #f97316; }
        .price-strike { text-decoration: line-through; opacity: 0.6; }
        .discount-badge { background: linear-gradient(135deg, #f97316, #ea580c); }
        .fullscreen-media { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100vw; 
            height: 100vh; 
            background: rgba(0,0,0,0.9); 
            z-index: 9999; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        /* Popup de couleur */
.color-popup-backdrop {
    background: linear-gradient(135deg, 
        rgba(var(--color-rgb), 0.1), 
        rgba(var(--color-rgb), 0.3)
    );
    backdrop-filter: blur(10px);
}

input[type="range"] {
    -webkit-appearance: none;
    height: 8px;
    background: linear-gradient(to right, #f97316, #ea580c);
    border-radius: 4px;
    outline: none;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    background: white;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid #f97316;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

input[type="range"]::-moz-range-thumb {
    width: 24px;
    height: 24px;
    background: white;
    border-radius: 50%;
    cursor: pointer;
    border: 3px solid #f97316;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
    </style>
</head>
<body class="min-h-screen" x-data="productPage()" x-init="init()">
    <!-- Fullscreen Media Viewer -->
    <div x-show="fullscreenOpen" x-cloak @click="fullscreenOpen = false" 
         class="fullscreen-media cursor-pointer">
        <div @click.stop class="relative max-w-4xl w-full h-full">
            <button @click="fullscreenOpen = false" 
                    class="absolute top-4 right-4 z-10 text-white bg-black/50 rounded-full p-2 hover:bg-black/80">
                ✕
            </button>
            <template x-if="fullscreenMedia.type === 'image'">
                <img :src="fullscreenMedia.src" class="w-full h-full object-contain p-4">
            </template>
            <template x-if="fullscreenMedia.type === 'video'">
                <video :src="fullscreenMedia.src" controls autoplay class="w-full h-full object-contain"></video>
            </template>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Navigation -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-orange-600 hover:text-orange-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <nav class="flex items-center gap-2 text-sm text-gray-500">
                    <a href="index.php" class="hover:text-orange-600">Accueil</a>
                    <span>/</span>
                    <a href="category.php" class="hover:text-orange-600"><?= htmlspecialchars($product['category_name'] ?? 'Catégorie') ?></a>
                    <span>/</span>
                    <span class="text-gray-700"><?= htmlspecialchars($product['name']) ?></span>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-500">Vendeur: <span class="font-semibold"><?= htmlspecialchars($product['seller_name'] ?? 'Inconnu') ?></span></span>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-10">
            <!-- Galerie média -->
            <div class="space-y-4">
                <!-- Swiper principal -->
                <div class="swiper main-swiper rounded-3xl overflow-hidden shadow-xl bg-white">
                    <div class="swiper-wrapper">
                        <?php foreach ($main_media as $media): ?>
                            <div class="swiper-slide">
                                <?php if ($media['file_type'] === 'video'): ?>
                                    <video src="<?= htmlspecialchars($media['file_path']) ?>" 
                                           class="w-full h-full" 
                                           controls 
                                           poster="<?= htmlspecialchars($media['file_path']) ?>">
                                    </video>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($media['file_path']) ?>" 
                                         alt="Produit" 
                                         class="w-full h-full cursor-zoom-in"
                                         @click="openFullscreen('<?= htmlspecialchars($media['file_path']) ?>', '<?= $media['file_type'] ?>')">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next text-white bg-black/30 rounded-full w-12 h-12 hover:bg-black/50"></div>
                    <div class="swiper-button-prev text-white bg-black/30 rounded-full w-12 h-12 hover:bg-black/50"></div>
                    <div class="swiper-pagination"></div>
                </div>

                <!-- Miniatures -->
                <div class="swiper thumb-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($main_media as $index => $media): ?>
                            <div class="swiper-slide thumb-slide rounded-xl overflow-hidden <?= $index === 0 ? 'active' : '' ?>" 
                                 data-index="<?= $index ?>">
                                <?php if ($media['file_type'] === 'video'): ?>
                                    <div class="relative w-full h-full">
                                        <img src="<?= htmlspecialchars($media['file_path']) ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="w-8 h-8 bg-white/80 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($media['file_path']) ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Images de couleurs -->
<!-- Images de couleurs -->
<?php if (!empty($colors_with_images)): ?>
    <div class="mt-6">
        <h4 class="text-sm font-semibold text-gray-700 mb-3">Couleurs disponibles</h4>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($colors_with_images as $color): ?>
                <div class="relative group">
                    <?php if (isset($color['image_data']) && $color['image_data']): ?>
                        <!-- Image depuis base64 -->
                        <img src="<?= htmlspecialchars($color['image_data']) ?>" 
                             class="w-16 h-16 rounded-lg object-cover border-2 border-gray-200 hover:border-orange-500 cursor-pointer"
                             @click="openColorPopup(<?= $color['index'] ?>)"
                             :class="{'border-orange-500': selectedColors[<?= $color['index'] ?>]?.quantity > 0}">
                    <?php elseif (isset($color['image_path']) && $color['image_path']): ?>
                        <!-- Image depuis chemin de fichier -->
                        <img src="<?= htmlspecialchars($color['image_path']) ?>" 
                             class="w-16 h-16 rounded-lg object-cover border-2 border-gray-200 hover:border-orange-500 cursor-pointer"
                             @click="openColorPopup(<?= $color['index'] ?>)"
                             :class="{'border-orange-500': selectedColors[<?= $color['index'] ?>]?.quantity > 0}">
                    <?php else: ?>
                        <!-- Seulement la couleur (cercle de couleur) -->
                        <div class="w-16 h-16 rounded-lg border-2 border-gray-200 hover:border-orange-500 cursor-pointer flex items-center justify-center"
                             style="background-color: <?= htmlspecialchars($color['hex']) ?>"
                             @click="openColorPopup(<?= $color['index'] ?>)"
                             :class="{'border-orange-500': selectedColors[<?= $color['index'] ?>]?.quantity > 0}">
                            <div class="text-xs font-bold text-white mix-blend-difference"><?= htmlspecialchars($color['hex']) ?></div>
                        </div>
                    <?php endif; ?>
                    <!-- Indicateur de quantité sélectionnée -->
                    <div x-show="selectedColors[<?= $color['index'] ?>]?.quantity > 0" 
                         class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-orange-500 text-white text-xs font-bold flex items-center justify-center border-2 border-white">
                        <span x-text="selectedColors[<?= $color['index'] ?>]?.quantity || 0"></span>
                    </div>
                    <!-- Cercle de couleur -->
                    <div class="absolute -top-2 -right-2 w-6 h-6 rounded-full border-2 border-white" 
                         style="background-color: <?= htmlspecialchars($color['hex']) ?>"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Résumé des quantités par couleur -->
        <div x-show="Object.keys(selectedColors).length > 0" class="mt-4 p-3 bg-gray-50 rounded-lg">
            <div class="text-sm text-gray-600 mb-2">Répartition des couleurs:</div>
            <div class="flex flex-wrap gap-2">
                <template x-for="(color, index) in Object.values(selectedColors)" :key="index">
                    <div class="px-3 py-1 bg-white rounded-full text-sm border border-gray-200">
                        <span class="font-medium" x-text="color.hex"></span>: 
                        <span class="text-orange-600 font-bold" x-text="color.quantity"></span>
                    </div>
                </template>
            </div>
            <div class="mt-2 text-sm text-gray-600">
                Total: <span class="font-bold" x-text="totalColorQuantity"></span> / 
                <span x-text="unitValue" class="font-bold text-gray-900"></span> <?= $product['unit_type'] ?>
                <span x-show="totalColorQuantity !== unitValue" class="text-red-500 ml-2">
                    (Reste: <span x-text="unitValue - totalColorQuantity"></span> à attribuer)
                </span>
            </div>
        </div>
    </div>
<?php endif; ?>
            </div>

            <!-- Informations produit & formulaire -->
            <div class="space-y-8">
                <!-- En-tête produit -->
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="text-sm text-gray-500">Catégorie: <?= htmlspecialchars($product['category_name'] ?? 'Non spécifiée') ?></span>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $product['product_condition'] === 'used' ? 'bg-orange-100 text-orange-700' : 'bg-emerald-100 text-emerald-700' ?>">
                            <?= $product['product_condition'] === 'used' ? 'Seconde main' : 'Neuf' ?>
                        </span>
                    </div>

                    <!-- Prix -->
                    <div class="mb-6">
                        <div class="flex items-center gap-4">
                            <div>
                                <span class="text-3xl font-bold text-gray-900">
                                    <?= number_format($discounted_price, 2) ?> 
                                    <span class="text-lg"><?= $product['currency'] === 'CDF' ? 'FC' : '$' ?></span>
                                </span>
                                <?php if ($has_discount): ?>
                                    <span class="price-strike text-lg text-gray-500 ml-2">
                                        <?= number_format($original_price, 2) ?> <?= $product['currency'] === 'CDF' ? 'FC' : '$' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($has_discount): ?>
                                <span class="discount-badge px-3 py-1 rounded-full text-white font-bold text-sm">
                                    -<?= $discount_percent ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($has_discount): ?>
                            <div class="mt-2 text-sm text-emerald-600 font-medium">
                                <span x-show="unitValue >= <?= $discount_threshold ?>">
                                    ✓ Réduction appliquée ! Économisez <?= $discount_percent ?>%
                                </span>
                                <span x-show="unitValue < <?= $discount_threshold ?> && unitValue > 0" class="text-amber-600">
                                    Ajoutez encore <?= number_format($discount_threshold - ($unit_value ?? 0), 2) ?> <?= $product['unit_type'] ?>
                                    pour bénéficier de <?= $discount_percent ?>% de réduction
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stock et unité -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-xl">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-sm text-gray-600">Disponible:</span>
                                <span class="ml-2 font-semibold"><?= number_format($quantity, 2) ?> <?= $product['unit_type'] ?></span>
                            </div>
                            <?php if ($min_order > 0): ?>
                                <div class="text-sm text-gray-600">
                                    <span>Commande minimum: </span>
                                    <span class="font-semibold"><?= number_format($min_order, 2) ?> <?= $product['unit_type'] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($discount_threshold > 0): ?>
                            <div class="mt-2 text-sm text-gray-600">
                                <span>Réduction à partir de: </span>
                                <span class="font-semibold"><?= number_format($discount_threshold, 2) ?> <?= $product['unit_type'] ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="prose prose-gray max-w-none mb-8">
                        <?= parseMarkdown(htmlspecialchars($product['description'])) ?>
                    </div>
                </div>

                <!-- Messages d'erreur -->
                <?php if (isset($error)): ?>
                    <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Formulaire d'achat -->
                <form method="POST" class="space-y-6">
                    <!-- Quantité -->
                    <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                        <label class="text-sm font-semibold text-gray-700 block mb-4">
                            Quantité souhaitée (<?= $product['unit_type'] ?>)
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="flex-1">
                                <div class="flex items-center bg-gray-50 rounded-xl border border-gray-200">
                                    <button type="button" @click="decrementUnit" class="p-3 hover:bg-gray-200 rounded-l-xl text-gray-600">-</button>
                                    <input type="number" x-model="unitValue" name="unit_value" step="0.01" min="0.01" 
                                           @input="updateTotal"
                                           class="flex-1 bg-transparent border-none text-center focus:ring-0 font-semibold text-lg py-3"
                                           required>
                                    <span class="text-gray-500 font-semibold px-3"><?= $product['unit_type'] ?></span>
                                    <button type="button" @click="incrementUnit" class="p-3 hover:bg-gray-200 rounded-r-xl text-gray-600">+</button>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Prix total</div>
                                <div class="text-2xl font-bold text-gray-900" x-text="formatPrice(totalAmount)"></div>
                                <?php if ($has_discount): ?>
                                    <div class="text-sm text-gray-500 price-strike" x-text="formatPrice(originalAmount)" x-show="originalAmount !== totalAmount"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Validation messages -->
                        <div x-show="unitValue < <?= $min_order ?> && unitValue > 0" class="mt-3 text-sm text-amber-600">
                            Minimum de commande: <?= number_format($min_order, 2) ?> <?= $product['unit_type'] ?>
                        </div>
                        <div x-show="unitValue > <?= $quantity ?>" class="mt-3 text-sm text-red-600">
                            Stock insuffisant. Maximum: <?= number_format($quantity, 2) ?> <?= $product['unit_type'] ?>
                        </div>
                    </div>

                    <!-- Tailles -->
                    <?php if (!empty($shoe_sizes) || !empty($child_sizes) || !empty($adult_sizes)): ?>
                        <div class="space-y-4">
                            <label class="text-sm font-semibold text-gray-700">Taille</label>
                            <select name="selected_size" x-model="selectedSize" class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm">
                                <option value="">Sélectionnez une taille</option>
                                <?php foreach ($shoe_sizes as $size): ?>
                                    <option value="<?= htmlspecialchars($size) ?>">Chaussure: <?= htmlspecialchars($size) ?></option>
                                <?php endforeach; ?>
                                <?php foreach ($child_sizes as $size): ?>
                                    <option value="<?= htmlspecialchars($size) ?>">Enfant: <?= htmlspecialchars($size) ?></option>
                                <?php endforeach; ?>
                                <?php foreach ($adult_sizes as $size): ?>
                                    <option value="<?= htmlspecialchars($size) ?>">Adulte: <?= htmlspecialchars($size) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Couleurs (hidden input) -->
                    <!-- Données des couleurs sélectionnées (format JSON) -->
                    <input type="hidden" name="color_quantities" :value="JSON.stringify(selectedColors)">

                    <!-- Régions de livraison -->
                    <div class="space-y-4">
                        <label class="text-sm font-semibold text-gray-700">Région de livraison *</label>
                        <select name="region" required class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm">
                            <option value="">Sélectionnez votre région</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>



                    <!-- Informations client -->
                    <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                        <h3 class="font-semibold text-gray-800">Vos coordonnées</h3>
                        <input type="text" name="customer_name" placeholder="Nom complet *" required
                               class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm" value="<?= $_SESSION['username'] ?? '' ?>">
                        <input type="tel" name="customer_phone" placeholder="Numéro de téléphone *" required
                               class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm">
                        <textarea name="address" placeholder="Adresse complète de livraison *" rows="3" required
                                  class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm"></textarea>
                    </div>

                    <!-- Bouton d'achat -->
                    <button type="submit" :disabled="isSubmitting || unitValue < <?= $min_order ?> || unitValue > <?= $quantity ?> || unitValue <= 0"
                            class="w-full py-4 bg-gray-900 text-white rounded-3xl font-semibold text-lg shadow-lg hover:bg-orange-600 transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="!isSubmitting">
                            <span>Procéder au paiement - <span x-text="formatPrice(totalAmount)"></span></span>
                        </template>
                        <template x-if="isSubmitting">
                            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                    </button>
                </form>

                <!-- Spécifications -->
                <?php if (!empty($specifications)): ?>
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Caractéristiques techniques</h3>
                        <div class="bg-white rounded-2xl p-6 border border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($specifications as $spec): ?>
                                    <?php if (!empty($spec['key']) && !empty($spec['value'])): ?>
                                        <div class="flex justify-between py-2 border-b border-gray-100">
                                            <span class="text-gray-600"><?= htmlspecialchars($spec['key']) ?></span>
                                            <span class="font-medium"><?= htmlspecialchars($spec['value']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Défauts (pour produits d'occasion) -->
                <?php if ($product['product_condition'] === 'used' && !empty($defects)): ?>
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Défauts constatés</h3>
                        <div class="bg-amber-50 rounded-2xl p-6 border border-amber-200">
                            <ul class="space-y-2">
                                <?php foreach ($defects as $defect): ?>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.282 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                        <span class="text-amber-800"><?= htmlspecialchars($defect) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Produits similaires -->
        <?php if (!empty($similar_products)): ?>
            <div class="mt-16">
                <h2 class="text-2xl font-bold text-gray-900 mb-8">Produits similaires</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
                    <?php foreach ($similar_products as $sim): ?>
                        <a href="buy_product.php?id=<?= $sim['id'] ?>" class="group block bg-white rounded-2xl shadow-sm overflow-hidden hover:shadow-xl transition-all duration-300">
                            <div class="aspect-square overflow-hidden bg-gray-100">
                                <?php if (!empty($sim['image'])): ?>
                                    <img src="<?= htmlspecialchars($sim['image']) ?>" alt="<?= htmlspecialchars($sim['name']) ?>" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h4 class="font-medium text-gray-900 truncate text-sm"><?= htmlspecialchars($sim['name']) ?></h4>
                                <div class="flex items-center gap-2 mt-2">
                                    <span class="text-orange-600 font-bold"><?= number_format($sim['price'], 2) ?> $</span>
                                    <?php if ($sim['discount_percent'] > 0): ?>
                                        <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">-<?= $sim['discount_percent'] ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<!-- Popup de sélection de couleur -->
<div x-show="colorPopupOpen" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden" @click.stop>
        <!-- En-tête avec couleur de fond -->
        <div class="h-20" :style="{backgroundColor: currentColor?.hex || '#ffffff'}"></div>
        
        <div class="relative -top-10 flex flex-col items-center">
            <!-- Image de la couleur -->
            <div class="w-32 h-32 rounded-full border-4 border-white shadow-lg overflow-hidden bg-white">
                <template x-if="currentColor?.image_data">
                    <img :src="currentColor?.image_data" class="w-full h-full object-cover">
                </template>
                <template x-if="!currentColor?.image_data && currentColor?.image_path">
                    <img :src="currentColor?.image_path" class="w-full h-full object-cover">
                </template>
                <template x-if="!currentColor?.image_data && !currentColor?.image_path">
                    <div class="w-full h-full" :style="{backgroundColor: currentColor?.hex || '#000000'}"></div>
                </template>
            </div>
            
            <!-- Code couleur -->
            <div class="mt-4 text-center">
                <div class="text-2xl font-bold" x-text="currentColor?.hex"></div>
                <div class="text-sm text-gray-500 mt-1">Quantité disponible: <span x-text="maxQuantity - totalColorQuantity + getColorQuantity(currentColorIndex)"></span> <?= $product['unit_type'] ?></div>
            </div>
        </div>
        
        <div class="px-6 pb-6">
            <!-- Sélecteur de quantité -->
            <div class="mt-8">
                <label class="block text-sm font-semibold text-gray-700 mb-4 text-center">
                    Quantité pour cette couleur
                </label>
                <div class="flex items-center justify-center gap-4">
                    <button type="button" @click="decrementColorQuantity" 
                            :disabled="getColorQuantity(currentColorIndex) <= 0"
                            class="w-12 h-12 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                        </svg>
                    </button>
                    
                    <div class="w-32">
                        <input type="number" x-model="tempColorQuantity" min="0" 
                               :max="maxQuantity - totalColorQuantity + getColorQuantity(currentColorIndex)"
                               @input="validateColorQuantity"
                               class="w-full text-center text-3xl font-bold border-none focus:ring-0">
                        <div class="text-sm text-gray-500 text-center mt-1"><?= $product['unit_type'] ?></div>
                    </div>
                    
                    <button type="button" @click="incrementColorQuantity" 
                            :disabled="totalColorQuantity >= maxQuantity"
                            class="w-12 h-12 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Slider -->
                <div class="mt-6">
                    <input type="range" x-model.number="tempColorQuantity" 
                        :min="0" 
                        :max="maxAvailable"
                        step="1"
                        class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                        @input="tempColorQuantity = parseInt($event.target.value)">
                </div>
                
                <!-- Informations -->
                <div class="mt-6 text-sm text-gray-600 space-y-2">
                    <div class="flex justify-between">
                        <span>Quantité attribuée:</span>
                        <span class="font-bold" x-text="getColorQuantity(currentColorIndex)"></span>
                    </div>
                    <div class="mt-6 text-sm text-gray-600 space-y-2">
                        <div class="flex justify-between">
                            <span>Reste à attribuer:</span>
                            <span class="font-bold" x-text="unitValue - totalColorQuantity"></span>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span>Total attribué:</span>
                        <span class="font-bold text-orange-600" x-text="totalColorQuantity"></span>
                    </div>
                </div>
            </div>
            
            <!-- Boutons d'action -->
            <div class="mt-8 flex gap-3">
                <button type="button" @click="colorPopupOpen = false" 
                        class="flex-1 py-3 font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition">
                    Annuler
                </button>
                <button type="button" @click="saveColorQuantity" 
                        :disabled="totalColorQuantity > unitValue"
                        class="flex-1 py-3 bg-orange-500 text-white font-bold rounded-xl hover:bg-orange-600 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Valider
                </button>
            </div>
            
            <!-- Option "Tout attribuer" -->
            <button type="button" @click="assignAllToColor" 
                    :disabled="unitValue - totalColorQuantity + getColorQuantity(currentColorIndex) <= 0"
                    class="w-full mt-4 py-2 text-sm text-orange-600 hover:text-orange-700 font-medium">
                Attribuer tout le reste à cette couleur
            </button>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
function productPage() {
    return {
        unitValue: <?= $min_order > 0 ? number_format($min_order, 2) : 1 ?>,
        selectedSize: '',
        isSubmitting: false,
        fullscreenOpen: false,
        fullscreenMedia: { src: '', type: 'image' },
        originalPrice: <?= $original_price ?>,
        discountedPrice: <?= $discounted_price ?>,
        discountPercent: <?= $discount_percent ?>,
        discountThreshold: <?= $discount_threshold ?>,
        minOrder: <?= $min_order ?>,
        maxQuantity: <?= $quantity ?>,
        currency: '<?= $product['currency'] === 'CDF' ? 'FC' : '$' ?>',
        unitType: '<?= $product['unit_type'] ?>',
        
        // Nouvelles propriétés pour la gestion des couleurs
        colorPopupOpen: false,
        currentColorIndex: null,
        currentColor: null,
        tempColorQuantity: 0,
        selectedColors: {}, // Format: {index: {hex: '#ff0000', quantity: 10, image_data: '...'}}
        
        // Données des couleurs depuis PHP
        colorsData: <?= json_encode($colors_with_images) ?>,
        
        // Getters calculés
        get totalAmount() {
            const amount = this.unitValue >= this.discountThreshold ? 
                this.unitValue * this.discountedPrice : 
                this.unitValue * this.originalPrice;
            return Math.max(0, amount);
        },
        
        get originalAmount() {
            return this.unitValue * this.originalPrice;
        },
        
        get totalColorQuantity() {
            return Object.values(this.selectedColors).reduce((total, color) => {
                return total + (color.quantity || 0);
            }, 0);
        },

        get maxAvailable() {
            return Math.min(
                this.maxQuantity,
                this.unitValue - this.totalColorQuantity + this.getColorQuantity(this.currentColorIndex)
            );
        },
        
        // Méthodes
        init() {
            this.initSwiper();
            ScrollReveal().reveal('.grid > div, .space-y-8 > div', { 
                distance: '30px', 
                origin: 'bottom', 
                duration: 800, 
                interval: 100 
            });
            
            // Initialiser selectedColors avec les données des couleurs
            this.colorsData.forEach((color, index) => {
                this.selectedColors[index] = {
                    hex: color.hex,
                    quantity: 0,
                    image_data: color.image_data || null,
                    image_path: color.image_path || null
                };
            });
        },
        
        initSwiper() {
            const thumbSwiper = new Swiper('.thumb-swiper', {
                spaceBetween: 10,
                slidesPerView: 4,
                freeMode: true,
                watchSlidesProgress: true,
            });
            
            const mainSwiper = new Swiper('.main-swiper', {
                spaceBetween: 10,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                thumbs: {
                    swiper: thumbSwiper,
                },
            });
            
            mainSwiper.on('slideChange', () => {
                document.querySelectorAll('.thumb-slide').forEach((thumb, index) => {
                    thumb.classList.toggle('active', index === mainSwiper.activeIndex);
                });
            });
            
            document.querySelectorAll('.thumb-slide').forEach((thumb, index) => {
                thumb.addEventListener('click', () => {
                    mainSwiper.slideTo(index);
                });
            });
        },
        
        // Méthodes pour la quantité totale
        incrementUnit() {
            if (this.unitValue < this.maxQuantity) {
                this.unitValue = parseFloat((this.unitValue + 1).toFixed(2));
                this.updateTotal();
            }
        },
        
        decrementUnit() {
            const newValue = this.unitValue - 1;
            if (newValue >= this.minOrder || this.minOrder === 0) {
                this.unitValue = parseFloat(Math.max(0.01, newValue).toFixed(2));
                this.updateTotal();
                // Ajuster les quantités de couleur si nécessaire
                if (this.totalColorQuantity > this.unitValue) {
                    this.adjustColorQuantities();
                }
            }
        },
        
        updateTotal() {
            // La logique est gérée par les getters
        },
        
        // Méthodes pour le popup de couleur
        openColorPopup(colorIndex) {
            this.currentColorIndex = colorIndex;
            this.currentColor = this.colorsData[colorIndex];
            this.tempColorQuantity = this.getColorQuantity(colorIndex);
            this.colorPopupOpen = true;
        },
        
        getColorQuantity(colorIndex) {
            return this.selectedColors[colorIndex]?.quantity || 0;
        },
        
        incrementColorQuantity() {
            if (this.tempColorQuantity < this.maxAvailable) {
                this.tempColorQuantity++;
            }
        },

        decrementColorQuantity() {
            if (this.tempColorQuantity > 0) {
                this.tempColorQuantity--;
            }
        },
        
        validateColorQuantity() {
            // Convertir en entier
            this.tempColorQuantity = parseInt(this.tempColorQuantity) || 0;
            
            // Limiter à la quantité maximum disponible
            if (this.tempColorQuantity > this.maxAvailable) {
                this.tempColorQuantity = this.maxAvailable;
            }
            
            // Minimum 0
            if (this.tempColorQuantity < 0) {
                this.tempColorQuantity = 0;
            }
        },
        
        saveColorQuantity() {
            // Mettre à jour la quantité pour cette couleur
            this.selectedColors[this.currentColorIndex].quantity = this.tempColorQuantity;
            this.colorPopupOpen = false;
        },
        
        assignAllToColor() {
            // Attribuer tout le reste disponible à cette couleur
            const available = this.unitValue - this.totalColorQuantity + this.getColorQuantity(this.currentColorIndex);
            this.tempColorQuantity = available;
            this.saveColorQuantity();
        },
        
        adjustColorQuantities() {
            // Ajuster les quantités de couleur si la quantité totale a diminué
            let total = this.totalColorQuantity;
            if (total > this.unitValue) {
                // Réduire proportionnellement
                Object.keys(this.selectedColors).forEach(index => {
                    const color = this.selectedColors[index];
                    if (color.quantity > 0) {
                        const proportion = color.quantity / total;
                        color.quantity = Math.max(0, Math.floor(this.unitValue * proportion));
                    }
                });
                
                // Ajuster les arrondis
                let newTotal = this.totalColorQuantity;
                while (newTotal > this.unitValue) {
                    Object.keys(this.selectedColors).forEach(index => {
                        if (this.selectedColors[index].quantity > 0 && newTotal > this.unitValue) {
                            this.selectedColors[index].quantity--;
                            newTotal--;
                        }
                    });
                }
            }
        },
        
        formatPrice(amount) {
            return `${amount.toFixed(2)} ${this.currency}`;
        },
        
        openFullscreen(src, type) {
            this.fullscreenMedia = { src, type };
            this.fullscreenOpen = true;
        },
        
        submitOrder() {
            // Validation : si des couleurs sont présentes, au moins une doit avoir une quantité > 0
            if (this.colorsData.length > 0) {
                const hasColorSelection = Object.values(this.selectedColors).some(color => color.quantity > 0);
                if (!hasColorSelection) {
                    alert('Veuillez sélectionner au moins une couleur avec une quantité.');
                    return false;
                }
            }
            
            // Validation : la somme des quantités de couleur doit égaler la quantité totale
            if (this.totalColorQuantity !== this.unitValue) {
                alert(`La somme des quantités par couleur (${this.totalColorQuantity}) doit égaler la quantité totale (${this.unitValue}).`);
                return false;
            }
            
            this.isSubmitting = true;
            return true;
        }
    }
}
    </script>
</body>
</html> , strucure bd actuelle : CREATE TABLE `temp_orders` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `customer_address` varchar(255) NOT NULL,
  `customer_phone` varchar(15) NOT NULL,
  `size_number` int(11) DEFAULT NULL,
  `size_letter` varchar(5) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','delivered') DEFAULT 'pending',
  `qr_code` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `kilogrammes` decimal(10,2) DEFAULT NULL,
  `metres` decimal(10,2) DEFAULT NULL,
  `litres` decimal(10,2) DEFAULT NULL,
  `unit_type` varchar(50) DEFAULT NULL,
  `ordered_quantity` int(11) DEFAULT NULL,
  `ordered_kilogrammes` decimal(10,2) DEFAULT NULL,
  `ordered_metres` decimal(10,2) DEFAULT NULL,
  `ordered_litres` decimal(10,2) DEFAULT NULL,
  `applied_discount_percentage` decimal(5,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `otpvalidated` int(6) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_german2_ci;