<?php
session_start();
require_once __DIR__ . '/../libs/db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// If using mysqli, report errors as exceptions
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// --- FONCTION DE COMPRESSION ET CONVERSION WEBP ---
function optimiserEtSauvegarderImage($sourcePath, &$destinationPath, $maxWidth = 1200, $quality = 80) {
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) return false;

    $mime = $imageInfo['mime'];
    list($width, $height) = $imageInfo;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':  $sourceImage = imagecreatefromjpeg($sourcePath); break;
        case 'image/png':  $sourceImage = imagecreatefrompng($sourcePath); break;
        case 'image/webp': $sourceImage = imagecreatefromwebp($sourcePath); break;
        default: return false; 
    }

    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = floor($height * ($maxWidth / $width));
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }

    $targetImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime == 'image/png' || $mime == 'image/webp') {
        imagealphablending($targetImage, false);
        imagesavealpha($targetImage, true);
        $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
        imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Force l'extension finale en .webp
    $destinationPath = preg_replace('/\.[^.]+$/', '', $destinationPath) . '.webp';
    $success = imagewebp($targetImage, $destinationPath, $quality);

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    return $success;
}

// ==================== TRAITEMENT DU FORMULAIRE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxRequest = !empty($_POST['ajax']) && $_POST['ajax'] === '1';

    if (!isset($_POST['human_token']) || $_POST['human_token'] !== 'mouse_verified') {
        $error_message = "Veuillez prouver que vous êtes un humain en cochant la case.";

        if ($isAjaxRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit();
        }

        $_SESSION['error_message'] = $error_message;
        header('Location: add_product.php?error=1');
        exit();
    } else {
        try {
            // Validation des données requises
            $required_fields = ['name', 'description', 'base_price', 'quantity', 'unit_type', 'condition'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Le champ '$field' est requis.");
                }
            }
            
            // Validation des catégories
            if (empty($_POST['category']) || empty(json_decode($_POST['category'], true))) {
                throw new Exception("Une catégorie doit être sélectionnée.");
            }
            
            // Validation des régions
            if (empty($_POST['regions']) || empty(json_decode($_POST['regions'], true))) {
                throw new Exception("Au moins une région doit être sélectionnée.");
            }
            
            // Validation des médias (minimum 5 fichiers valides)
            if (!isset($_FILES['media']) || !is_array($_FILES['media']['name'])) {
                throw new Exception("Aucun fichier média reçu.");
            }

            $mediaNames = array_filter($_FILES['media']['name'], function ($name) {
                return trim($name) !== '';
            });

            if (count($mediaNames) < 5) {
                throw new Exception("Minimum 5 fichiers média requis.");
            }

            // Vérification de la taille et du type des fichiers
            $allowed_types = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'video/mp4',
                'video/quicktime'
            ];

            $max_size = 50 * 1024 * 1024; // 50MB
            
            foreach ($_FILES['media']['tmp_name'] as $index => $tmp_name) {
                if ($_FILES['media']['size'][$index] > $max_size) {
                    throw new Exception("Le fichier " . $_FILES['media']['name'][$index] . " dépasse 50MB.");
                }
                
                $file_type = mime_content_type($tmp_name);
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Type de fichier non supporté: " . $_FILES['media']['name'][$index]);
                }
            }
            
            // Démarrer la transaction
            $conn->begin_transaction();
            
            // Récupérer les données
            $user_id = $_SESSION['user_id'];
            $name = htmlspecialchars($_POST['name']);
            $description = htmlspecialchars($_POST['description']);
            
            // Prix de base (prix de référence)
            $base_price = floatval($_POST['base_price']);
            $currency = in_array($_POST['currency'], ['USD', 'CDF']) ? $_POST['currency'] : 'USD';
            $condition = $_POST['condition'];
            $unit_type = $_POST['unit_type'];
            $quantity = floatval($_POST['quantity']);
            
            // Appliquer la commission de 12% sur le prix de base
            $final_price = round($base_price * 1.12, 2);
            
            // Mode de gestion des prix
            $price_mode = $_POST['price_mode'] ?? 'uniform';
            
            // Valeurs optionnelles
            $min_order = !empty($_POST['min_order']) ? floatval($_POST['min_order']) : null;
            $discount_threshold = !empty($_POST['discount_threshold']) ? floatval($_POST['discount_threshold']) : null;
            $discount_percent = !empty($_POST['discount_percent']) ? intval($_POST['discount_percent']) : 0;
            
            // Données JSON
            $category = $_POST['category'];
            $regions = $_POST['regions'];
            $defects = !empty($_POST['defects']) ? $_POST['defects'] : '[]';
            $colors_json = !empty($_POST['colors']) ? $_POST['colors'] : '[]';
            $shoe_sizes = !empty($_POST['shoe_sizes']) ? $_POST['shoe_sizes'] : '[]';
            $child_sizes = !empty($_POST['child_sizes']) ? $_POST['child_sizes'] : '[]';
            $adult_sizes = !empty($_POST['adult_sizes']) ? $_POST['adult_sizes'] : '[]';
            $specifications = !empty($_POST['specifications']) ? $_POST['specifications'] : '[]';
            
            // 1. Insérer le produit principal
            $stmt = $conn->prepare("
                INSERT INTO products (
                    user_id, name, description, price, currency, product_condition,
                    unit_type, quantity, min_order, discount_threshold, discount_percent,
                    category, regions, defects, colors, shoe_sizes, child_sizes,
                    adult_sizes, specifications, price_mode
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt === false) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param(
                "issdsssdiddsssssssss",
                $user_id, 
                $name, 
                $description, 
                $final_price, 
                $currency, 
                $condition,
                $unit_type, 
                $quantity, 
                $min_order, 
                $discount_threshold, 
                $discount_percent,
                $category, 
                $regions, 
                $defects, 
                $colors_json, 
                $shoe_sizes, 
                $child_sizes,
                $adult_sizes, 
                $specifications,
                $price_mode
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de l'insertion du produit: " . $stmt->error);
            }
            
            $product_id = $conn->insert_id;
            $stmt->close();
            
            // 2. Gérer les variantes (prix et stock par couleur/taille)
            $variants_data = [];
            
            if ($price_mode === 'uniform') {
                // Prix unique : une seule variante avec le prix de base
                $variants_data[] = [
                    'variant_key' => 'default',
                    'variant_type' => 'default',
                    'color' => null,
                    'size' => null,
                    'price' => $final_price,
                    'quantity' => $quantity
                ];
            } 
            elseif ($price_mode === 'by_color') {
                // Par couleur
                $colors_data = json_decode($colors_json, true);
                $color_prices = isset($_POST['color_prices']) ? json_decode($_POST['color_prices'], true) : [];
                $color_stocks = isset($_POST['color_stocks']) ? json_decode($_POST['color_stocks'], true) : [];
                
                foreach ($colors_data as $index => $color) {
                    $color_price = isset($color_prices[$index]) ? floatval($color_prices[$index]) : $base_price;
                    $color_stock = isset($color_stocks[$index]) ? intval($color_stocks[$index]) : 0;
                    
                    $variants_data[] = [
                        'variant_key' => $color['hex'],
                        'variant_type' => 'color',
                        'color' => $color['hex'],
                        'size' => null,
                        'price' => round($color_price * 1.12, 2),
                        'quantity' => $color_stock
                    ];
                }
            }
            elseif ($price_mode === 'by_size') {
                // Par taille
                $sizes = [];
                $sizes = array_merge(
                    json_decode($shoe_sizes, true) ?? [],
                    json_decode($child_sizes, true) ?? [],
                    json_decode($adult_sizes, true) ?? []
                );
                
                $size_prices = isset($_POST['size_prices']) ? json_decode($_POST['size_prices'], true) : [];
                $size_stocks = isset($_POST['size_stocks']) ? json_decode($_POST['size_stocks'], true) : [];
                
                foreach ($sizes as $index => $size) {
                    $size_price = isset($size_prices[$index]) ? floatval($size_prices[$index]) : $base_price;
                    $size_stock = isset($size_stocks[$index]) ? intval($size_stocks[$index]) : 0;
                    
                    $variants_data[] = [
                        'variant_key' => $size,
                        'variant_type' => 'size',
                        'color' => null,
                        'size' => $size,
                        'price' => round($size_price * 1.12, 2),
                        'quantity' => $size_stock
                    ];
                }
            }
            elseif ($price_mode === 'by_color_size') {
                // Par couleur + taille
                $colors_data = json_decode($colors_json, true);
                $sizes = array_merge(
                    json_decode($shoe_sizes, true) ?? [],
                    json_decode($child_sizes, true) ?? [],
                    json_decode($adult_sizes, true) ?? []
                );
                
                $variant_prices = isset($_POST['variant_prices']) ? json_decode($_POST['variant_prices'], true) : [];
                $variant_stocks = isset($_POST['variant_stocks']) ? json_decode($_POST['variant_stocks'], true) : [];
                
                foreach ($colors_data as $color_index => $color) {
                    foreach ($sizes as $size_index => $size) {
                        $key = $color['hex'] . '_' . $size;
                        $var_price = isset($variant_prices[$key]) ? floatval($variant_prices[$key]) : $base_price;
                        $var_stock = isset($variant_stocks[$key]) ? intval($variant_stocks[$key]) : 0;
                        
                        $variants_data[] = [
                            'variant_key' => $key,
                            'variant_type' => 'color_size',
                            'color' => $color['hex'],
                            'size' => $size,
                            'price' => round($var_price * 1.12, 2),
                            'quantity' => $var_stock
                        ];
                    }
                }
            }
            
            // Insertion des variantes
            if (!empty($variants_data)) {
                $variant_stmt = $conn->prepare("
                    INSERT INTO product_variants 
                    (product_id, variant_key, variant_type, color, size, price, quantity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($variants_data as $variant) {
                    $variant_stmt->bind_param(
                        "issssdi",
                        $product_id,
                        $variant['variant_key'],
                        $variant['variant_type'],
                        $variant['color'],
                        $variant['size'],
                        $variant['price'],
                        $variant['quantity']
                    );
                    
                    if (!$variant_stmt->execute()) {
                        throw new Exception("Erreur lors de l'insertion de la variante: " . $variant_stmt->error);
                    }
                }
                $variant_stmt->close();
            }
            
            // 3. Gérer l'upload des médias
            $upload_dir = '../uploads/products/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Créer un sous-dossier pour le produit
            $product_dir = $upload_dir . $product_id . '/';
            if (!file_exists($product_dir)) {
                mkdir($product_dir, 0777, true);
            }
            
            $media_stmt = $conn->prepare("
                INSERT INTO product_media (product_id, file_path, file_type, is_color_image, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_FILES['media']['tmp_name'] as $index => $tmp_name) {
                if ($_FILES['media']['error'][$index] !== UPLOAD_ERR_OK) {
                    throw new Exception("Erreur d'upload pour: " . $_FILES['media']['name'][$index]);
                }
                
                // Déterminer le type MIME réel du fichier temporaire
                $mime_type = mime_content_type($tmp_name);
                $file_type = strpos($mime_type, 'video') !== false ? 'video' : 'image';
                
                // Générer un nom unique de base
                $file_extension = pathinfo($_FILES['media']['name'][$index], PATHINFO_EXTENSION);
                $file_name = uniqid('media_', true) . '.' . $file_extension;
                $file_path = $product_dir . $file_name;
                
                // --- STRATÉGIE D'UPLOAD INTELLIGENTE ---
                if ($file_type === 'image') {
                    // La fonction va compresser et modifier la variable $file_path pour finir en .webp
                    if (!optimiserEtSauvegarderImage($tmp_name, $file_path, 1200, 80)) {
                        throw new Exception("Impossible de compresser l'image: " . $_FILES['media']['name'][$index]);
                    }
                } else {
                    // Si c'est une vidéo, on la déplace normalement sans y toucher
                    if (!move_uploaded_file($tmp_name, $file_path)) {
                        throw new Exception("Impossible de sauvegarder la vidéo: " . $_FILES['media']['name'][$index]);
                    }
                }
                
                $is_color_image = 0;
                $sort_order = $index;
                $media_stmt->bind_param("issii", $product_id, $file_path, $file_type, $is_color_image, $sort_order);
                if (!$media_stmt->execute()) {
                    throw new Exception("Erreur lors de l'insertion du média: " . $media_stmt->error);
                }
            }
            $media_stmt->close();
            
            // 4. Gérer les images des couleurs si présentes
            $colors_data = json_decode($colors_json, true);

            if (!empty($colors_data) && isset($_FILES['color_images'])) {
                $color_media_stmt = $conn->prepare("
                    INSERT INTO product_media (product_id, file_path, file_type, is_color_image, sort_order)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $colors_final = [];
                
                foreach ($colors_data as $color_index => $color) {
                    $color_entry = ['hex' => $color['hex']];
                    
                    if (isset($_FILES['color_images']['tmp_name'][$color_index]) 
                        && $_FILES['color_images']['tmp_name'][$color_index] != '') {
                        
                        $color_tmp_name = $_FILES['color_images']['tmp_name'][$color_index];
                        $color_name = $_FILES['color_images']['name'][$color_index];
                        $color_error = $_FILES['color_images']['error'][$color_index];
                        
                        if ($color_error === UPLOAD_ERR_OK) {
                            $color_extension = pathinfo($color_name, PATHINFO_EXTENSION);
                            $color_file_name = uniqid('color_', true) . '.' . $color_extension;
                            $color_file_path = $product_dir . $color_file_name;
                            
                            if (optimiserEtSauvegarderImage($color_tmp_name, $color_file_path, 800, 80)) { 
                                $color_entry['image'] = $color_file_path;
                                
                                $c_type = 'image';
                                $c_is_color = 1;
                                $c_sort = 100 + $color_index;

                                $color_media_stmt->bind_param("issii", 
                                    $product_id, 
                                    $color_file_path, 
                                    $c_type, 
                                    $c_is_color, 
                                    $c_sort
                                );
                                
                                $color_media_stmt->execute();
                            }
                        }
                    } 
                    
                    $colors_final[] = $color_entry;
                }
                
                $color_media_stmt->close();
                
                $colors_updated = json_encode($colors_final);
                
                $update_stmt = $conn->prepare("UPDATE products SET colors = ? WHERE id = ?");
                $update_stmt->bind_param("si", $colors_updated, $product_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            // Valider la transaction
            $conn->commit();

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => 'Produit ajouté avec succès!']);
                exit();
            }
            // Succès - rediriger avec message
            $_SESSION['success_message'] = "Produit ajouté avec succès!";
            header('Location: add_product.php?success=1');
            exit();
            
        } catch (Exception $e) {
            if (isset($conn) && $conn) {
                $conn->rollback();
            }
            
            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit();
            }
            
            $_SESSION['error_message'] = $e->getMessage();
            header('Location: add_product.php?error=1');
            exit();
        }
    }
}

// ==================== AFFICHAGE DES MESSAGES ====================
$success_message = '';
$error_message = '';

if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_GET['error']) && $_GET['error'] == 1 && isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Récupérer toutes les régions
$regions_query = "SELECT id, name, province FROM regions ORDER BY province, name";
$regions_result = $conn->query($regions_query);
$all_regions = [];
while ($row = $regions_result->fetch_assoc()) {
    $all_regions[] = $row['name'];
}

// Récupérer les catégories et sous-catégories
$categories_query = "SELECT c.id as cat_id, c.name as cat_name,
                    sc.id as subcat_id, sc.name as subcat_name
                    FROM categories c
                    LEFT JOIN subcategories sc ON c.id = sc.category_id
                    ORDER BY c.name, sc.name";
$categories_result = $conn->query($categories_query);
$categories = [];
$subcategories_by_cat = [];
while ($row = $categories_result->fetch_assoc()) {
    if (!isset($categories[$row['cat_id']])) {
        $categories[$row['cat_id']] = $row['cat_name'];
    }
    if ($row['subcat_id']) {
        $subcategories_by_cat[$row['cat_id']][] = [
            'id' => $row['subcat_id'],
            'name' => $row['subcat_name']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/preline@2.0.3/dist/preline.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .age-range-slider {
            -webkit-appearance: none;
            height: 5px;
            background: linear-gradient(to right, #60a5fa, #34d399, #fbbf24);
            border-radius: 3px;
            outline: none;
        }
        .age-range-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: #8b5cf6;
            border-radius: 50%;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 1px 4px rgba(0,0,0,0.15);
        }
        .variant-input {
            transition: all 0.2s ease;
        }
        .variant-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
        }
        .discount-card {
            transition: all 0.3s ease;
        }
        .discount-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        @media (max-width: 640px) {
            .variant-grid {
                grid-template-columns: 1fr !important;
            }
            .variant-table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        .variant-cell-input {
            transition: all 0.2s ease;
        }
        .variant-cell-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.15);
        }
    </style>
</head>
<body class="h-full pb-20 relative" x-data="productForm()">
    <!-- Image incrustée -->
    <img
        src="assets/images/img/background4.jpg"
        class="pointer-events-none fixed inset-0 m-auto w-full opacity-[0.04]"
        alt=""
    >
    
<div class="max-w-5xl mx-auto py-6 sm:py-10 px-3 sm:px-6 lg:px-8">
    <!-- Header avec score -->
    <div class="block sm:flex items-center justify-between mb-6 sm:mb-8 reveal">
        <div class="flex justify-left items-center gap-2 mb-4 sm:mb-0 reveal-top">
            <a href="index.php" class="flex items-center gap-2">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>
        </div>
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Nouveau Produit</h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">Optimisez votre annonce pour vendre plus vite.</p>
            <a href="dashboard.php" class="text-xs sm:text-sm font-medium text-fuchsia-600 hover:text-fuchsia-500 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Retour au tableau de bord
            </a>
        </div>
        <div class="md:block mt-4 sm:mt-0">
            <div class="flex items-center gap-4 bg-white p-3 sm:p-4 rounded-2xl border border-gray-100 shadow-sm">
                <div class="relative size-12 sm:size-14">
                    <svg class="rotate-[135deg] size-full" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-current text-gray-100" stroke-width="2" stroke-dasharray="75 100"></circle>
                        <circle cx="18" cy="18" r="16" fill="none" class="stroke-current transition-all duration-1000"
                                :class="scoreColor" stroke-width="2.5" :stroke-dasharray="`${(score * 0.75)} 100`" stroke-linecap="round"></circle>
                    </svg>
                    <div class="absolute top-1/2 start-1/2 transform -translate-x-1/2 -translate-y-1/2 text-center">
                        <span class="text-sm sm:text-base font-bold text-gray-800" x-text="score"></span>
                    </div>
                </div>
                <div>
                    <span class="text-[10px] sm:text-xs font-medium text-gray-500 uppercase tracking-wider">Score d'efficacité</span>
                    <p class="text-xs sm:text-sm font-bold mt-0.5" :class="scoreTextColor" x-text="scoreText"></p>
                </div>
            </div>
        </div>
    </div>

<form id="productForm" method="POST" enctype="multipart/form-data" @submit.prevent="submitForm" class="space-y-6 sm:space-y-8">

    <!-- Hidden fields pour les données JSON -->
    <input type="hidden" name="category" :value="JSON.stringify(selectedCategories)">
    <input type="hidden" name="regions" :value="JSON.stringify(selectedRegions)">
    <input type="hidden" name="unit_type" :value="selectedUnit.id">
    <input type="hidden" name="price_mode" :value="priceMode">
    
    <!-- Hidden fields pour les données de variantes -->
    <input type="hidden" name="color_prices" :value="JSON.stringify(colorPrices)">
    <input type="hidden" name="color_stocks" :value="JSON.stringify(colorStocks)">
    <input type="hidden" name="size_prices" :value="JSON.stringify(sizePrices)">
    <input type="hidden" name="size_stocks" :value="JSON.stringify(sizeStocks)">
    <input type="hidden" name="variant_prices" :value="JSON.stringify(variantPrices)">
    <input type="hidden" name="variant_stocks" :value="JSON.stringify(variantStocks)">
   
    <!-- Section 1: Détails du produit -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-fuchsia-100 text-fuchsia-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">1</span>
            Détails du produit
        </h3>
       
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Nom du produit</label>
                <input type="text" x-model="formData.name" name="name" placeholder="Ex: iPhone 15 Pro Max" required
                       class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400 outline-none border transition-all">
            </div>
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Catégorie & Sous-catégorie</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-for="(cat, index) in selectedCategories" :key="index">
                        <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-fuchsia-100 text-fuchsia-700">
                            <span x-text="cat.name + (cat.sub ? ' > ' + cat.sub : '')"></span>
                            <button type="button" @click="removeCategory(index)" class="hover:bg-fuchsia-200 rounded-full p-0.5">&times;</button>
                        </span>
                    </template>
                </div>
                <button type="button" @click="openCatModal = true"
                        class="w-full py-2.5 px-4 border-2 border-dashed border-gray-200 rounded-xl text-gray-600 text-sm hover:bg-gray-50 transition-all font-medium">
                    + Sélectionner une catégorie
                </button>
            </div>
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Régions de vente (appuyez sur ↵ après)</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <template x-for="r in selectedRegions" :key="r">
                        <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                            <span x-text="r"></span>
                            <button type="button" @click="removeRegion(r)" class="hover:bg-blue-200 rounded-full p-0.5">
                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" stroke-width="2"/></svg>
                            </button>
                        </span>
                    </template>
                </div>
                <input type="text" enterkeyhint="done" list="regions-list" @keydown.enter="addRegion($event.target.value)" placeholder="Ajouter les zones de livraison"
                       class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-blue-400 outline-none border transition-all">
                <datalist id="regions-list">
                    <template x-for="region in allRegions" :key="region">
                        <option :value="region"></option>
                    </template>
                </datalist>
            </div>
            <div class="space-y-1.5">
                <label class="text-sm font-semibold text-gray-700">Prix de référence *</label>
                <div class="flex-col sm:flex items-center gap-2">
                    <select x-model="formData.currency" name="currency" class="w-full sm:w-24 rounded-xl border-gray-200 bg-white px-3 py-3 text-sm border focus:ring-2 focus:ring-fuchsia-400 outline-none">
                        <option value="USD">$ USD</option>
                        <option value="CDF">FC CDF</option>
                    </select>
                    <div class="flex-1 flex items-center bg-gray-50 rounded-xl border border-gray-200 mt-2 sm:mt-0">
                        <button type="button" @click="formData.base_price > 0 ? formData.base_price-- : 0" class="p-3 hover:bg-gray-200 rounded-l-xl text-gray-600">-</button>
                        <input type="number" x-model="formData.base_price" name="base_price" step="0.01" min="0" required
                               class="flex-1 bg-transparent border-none w-20 sm:w-auto text-center focus:ring-0 font-semibold text-base">
                        <span class="text-gray-500 font-semibold px-3" x-text="formData.currency === 'USD' ? '$' : 'Fc'"></span>
                        <button type="button" @click="formData.base_price++" class="p-3 hover:bg-gray-200 rounded-r-xl text-gray-600">+</button>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">* Prix de base avant commission. Une commission de 12% sera appliquée automatiquement.</p>
            </div>
        </div>
        <div class="mt-4 sm:mt-6 space-y-2">
            <label class="text-sm font-semibold text-gray-700">Description (Format: # Titre, **Gras**) *</label>
            <div class="flex items-center gap-2 mb-3">
                <button type="button" @click="formatText('title')" class="px-4 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Titre (#)</button>
                <button type="button" @click="formatText('bold')" class="px-4 py-1.5 text-xs bg-gray-100 hover:bg-gray-200 rounded-lg font-medium">Gras (**)</button>
            </div>
            <textarea x-model="formData.description" maxlength="2000" name="description" rows="5" required @input="updatePreview"
                      class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400 outline-none border transition-all"
                      placeholder="Décrivez votre produit..."></textarea>
           
            <div class="mt-3 p-4 border border-gray-200 rounded-xl bg-gray-50">
                <p class="text-xs font-semibold text-gray-600 mb-2">Aperçu :</p>
                <div class="text-gray-700 text-sm leading-relaxed prose prose-sm" x-html="descriptionPreview"></div>
            </div>
        </div>
    </div>

    <!-- Section 1.5: Gestion des prix par variante -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">1.5</span>
            Gestion des prix et stocks par variante
        </h3>
       
        <div class="space-y-4">
            <!-- Mode de gestion -->
            <div>
                <label class="text-sm font-semibold text-gray-700 block mb-3">Mode de gestion des prix</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button type="button" @click="priceMode = 'uniform'" 
                            :class="priceMode === 'uniform' ? 'bg-fuchsia-500 text-white border-fuchsia-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                            class="px-2 sm:px-3 py-2 rounded-lg border text-xs sm:text-sm font-medium transition-all">
                        Prix unique
                    </button>
                    <button type="button" @click="priceMode = 'by_color'" 
                            :class="priceMode === 'by_color' ? 'bg-fuchsia-500 text-white border-fuchsia-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                            class="px-2 sm:px-3 py-2 rounded-lg border text-xs sm:text-sm font-medium transition-all">
                        Par couleur
                    </button>
                    <button type="button" @click="priceMode = 'by_size'" 
                            :class="priceMode === 'by_size' ? 'bg-fuchsia-500 text-white border-fuchsia-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                            class="px-2 sm:px-3 py-2 rounded-lg border text-xs sm:text-sm font-medium transition-all">
                        Par taille
                    </button>
                    <button type="button" @click="priceMode = 'by_color_size'" 
                            :class="priceMode === 'by_color_size' ? 'bg-fuchsia-500 text-white border-fuchsia-500' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                            class="px-2 sm:px-3 py-2 rounded-lg border text-xs sm:text-sm font-medium transition-all">
                        Couleur + Taille
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2">
                    <span x-show="priceMode === 'uniform'">✓ Toutes les variantes auront le même prix et le même stock.</span>
                    <span x-show="priceMode === 'by_color'">✓ Définissez un prix et un stock différents pour chaque couleur.</span>
                    <span x-show="priceMode === 'by_size'">✓ Définissez un prix et un stock différents pour chaque taille.</span>
                    <span x-show="priceMode === 'by_color_size'">✓ Définissez un prix et un stock différents pour chaque combinaison couleur + taille.</span>
                </p>
            </div>

            <!-- Prix unique -->
            <div x-show="priceMode === 'uniform'" x-transition>
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <p class="text-sm text-gray-600">Le prix de référence (<span class="font-semibold" x-text="formData.base_price"></span> <span x-text="formData.currency"></span>) sera appliqué à toutes les variantes.</p>
                    <p class="text-sm text-gray-600 mt-1">Stock total : <span class="font-semibold" x-text="formData.quantity || 0"></span> unités</p>
                </div>
            </div>

            <!-- Par couleur -->
            <div x-show="priceMode === 'by_color'" x-transition>
                <div class="space-y-3">
                    <div class="grid grid-cols-12 gap-2 px-2 mb-2">
                        <div class="col-span-4 sm:col-span-5 text-xs font-semibold text-gray-500">Couleur</div>
                        <div class="col-span-3 sm:col-span-3 text-xs font-semibold text-gray-500 text-center">
                            <svg class="w-4 h-4 inline-block text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            Prix
                        </div>
                        <div class="col-span-3 sm:col-span-3 text-xs font-semibold text-gray-500 text-center">
                            <svg class="w-4 h-4 inline-block text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                            </svg>
                            Stock
                        </div>
                        <div class="col-span-2 sm:col-span-1"></div>
                    </div>
                    <template x-for="(color, index) in colors" :key="index">
                        <div class="flex flex-wrap items-center gap-2 p-3 border border-gray-200 rounded-xl bg-gray-50">
                            <div class="flex items-center gap-2 w-full sm:w-auto sm:flex-1 min-w-[100px]">
                                <div class="w-6 h-6 rounded-full border border-gray-300 flex-shrink-0" :style="'background:' + color.hex"></div>
                                <span class="font-medium text-sm truncate" x-text="'Couleur ' + (index+1)"></span>
                            </div>
                            <div class="flex items-center gap-2 flex-1 sm:flex-none">
                                <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200 px-2 py-1 w-full sm:w-auto">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                                    </svg>
                                    <input type="number" x-model="colorPrices[index]" step="0.01" min="0"
                                           class="w-16 sm:w-20 bg-transparent border-none text-sm focus:ring-0 outline-none p-0">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-1 sm:flex-none">
                                <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200 px-2 py-1 w-full sm:w-auto">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                                    </svg>
                                    <input type="number" x-model="colorStocks[index]" step="1" min="0"
                                           class="w-14 sm:w-16 bg-transparent border-none text-sm focus:ring-0 outline-none p-0">
                                </div>
                            </div>
                            <button type="button" @click="removeColor(index)" class="text-red-400 hover:text-red-600 p-1">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2"/></svg>
                            </button>
                        </div>
                    </template>
                    <p class="text-xs text-gray-400" x-show="colors.length === 0">Ajoutez d'abord des couleurs dans la section "Couleurs disponibles".</p>
                </div>
            </div>

            <!-- Par taille -->
            <div x-show="priceMode === 'by_size'" x-transition>
                <div class="space-y-3">
                    <div class="grid grid-cols-12 gap-2 px-2 mb-2">
                        <div class="col-span-4 sm:col-span-5 text-xs font-semibold text-gray-500">Taille</div>
                        <div class="col-span-3 sm:col-span-3 text-xs font-semibold text-gray-500 text-center">
                            <svg class="w-4 h-4 inline-block text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            Prix
                        </div>
                        <div class="col-span-3 sm:col-span-3 text-xs font-semibold text-gray-500 text-center">
                            <svg class="w-4 h-4 inline-block text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                            </svg>
                            Stock
                        </div>
                        <div class="col-span-2 sm:col-span-1"></div>
                    </div>
                    <template x-for="size in allSizes" :key="size">
                        <div class="flex flex-wrap items-center gap-2 p-3 border border-gray-200 rounded-xl bg-gray-50">
                            <span class="font-medium text-sm w-full sm:w-auto sm:flex-1 min-w-[60px]" x-text="size"></span>
                            <div class="flex items-center gap-2 flex-1 sm:flex-none">
                                <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200 px-2 py-1 w-full sm:w-auto">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                                    </svg>
                                    <input type="number" x-model="sizePrices[size]" step="0.01" min="0"
                                           class="w-16 sm:w-20 bg-transparent border-none text-sm focus:ring-0 outline-none p-0">
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-1 sm:flex-none">
                                <div class="flex items-center gap-1 bg-white rounded-lg border border-gray-200 px-2 py-1 w-full sm:w-auto">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                                    </svg>
                                    <input type="number" x-model="sizeStocks[size]" step="1" min="0"
                                           class="w-14 sm:w-16 bg-transparent border-none text-sm focus:ring-0 outline-none p-0">
                                </div>
                            </div>
                        </div>
                    </template>
                    <p class="text-xs text-gray-400" x-show="allSizes.length === 0">Ajoutez d'abord des tailles dans la section "Tailles".</p>
                </div>
            </div>

            <!-- Par couleur + taille - Version améliorée -->
            <div x-show="priceMode === 'by_color_size'" x-transition>
                <div class="space-y-4">
                    <!-- Légende -->
                    <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500 bg-gray-50 p-3 rounded-xl border border-gray-200">
                        <span class="font-medium text-gray-700">Légende :</span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            Prix unitaire
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                            </svg>
                            Stock disponible
                        </span>
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-400">Les champs vides utiliseront le prix de référence</span>
                    </div>

                    <!-- En-tête du tableau -->
                    <div class="overflow-x-auto -mx-2 px-2">
                        <div class="min-w-[600px]">
                            <!-- Ligne d'en-tête -->
                            <div class="grid grid-cols-[120px,repeat(auto-fit,minmax(100px,1fr))] gap-2 mb-2">
                                <div class="text-xs font-semibold text-gray-500 flex items-center">
                                    Couleur / Taille
                                </div>
                                <template x-for="size in allSizes" :key="size">
                                    <div class="text-xs font-semibold text-gray-500 text-center px-1 py-1 bg-gray-50 rounded-lg" x-text="size"></div>
                                </template>
                            </div>
                            
                            <!-- Lignes de couleurs -->
                            <template x-for="(color, colorIndex) in colors" :key="color.hex">
                                <div class="grid grid-cols-[120px,repeat(auto-fit,minmax(100px,1fr))] gap-2 p-2 mb-2 bg-gray-50 rounded-xl border border-gray-200">
                                    <!-- Nom de la couleur -->
                                    <div class="flex items-center gap-2 min-w-[100px]">
                                        <div class="w-5 h-5 rounded-full border border-gray-300 flex-shrink-0" :style="'background:' + color.hex"></div>
                                        <span class="text-xs font-medium truncate" x-text="'Couleur ' + (colorIndex + 1)"></span>
                                    </div>
                                    
                                    <!-- Cellules pour chaque taille -->
                                    <template x-for="size in allSizes" :key="size">
                                        <div class="flex flex-col gap-1 bg-white rounded-lg border border-gray-200 p-1.5 hover:border-purple-300 transition-all">
                                            <div class="flex items-center gap-1">
                                                <svg class="w-3 h-3 text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                                                </svg>
                                                <input type="number" :placeholder="formData.base_price" 
                                                       x-model="variantPrices[color.hex + '_' + size]" 
                                                       step="0.01" min="0"
                                                       class="w-full bg-transparent border-none text-xs focus:ring-0 outline-none p-0 placeholder-gray-300">
                                            </div>
                                            <div class="flex items-center gap-1 border-t border-gray-100 pt-1">
                                                <svg class="w-3 h-3 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" stroke-width="2"/>
                                                </svg>
                                                <input type="number" :placeholder="'0'" 
                                                       x-model="variantStocks[color.hex + '_' + size]" 
                                                       step="1" min="0"
                                                       class="w-full bg-transparent border-none text-xs focus:ring-0 outline-none p-0 placeholder-gray-300">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Messages d'information -->
                    <div x-show="colors.length === 0 || allSizes.length === 0" 
                         class="text-center p-6 bg-amber-50 rounded-xl border border-amber-200">
                        <svg class="w-8 h-8 mx-auto text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                        </svg>
                        <p class="text-sm text-amber-700">
                            <span x-show="colors.length === 0">Ajoutez d'abord des couleurs dans la section "Couleurs disponibles".</span>
                            <span x-show="allSizes.length === 0">Ajoutez d'abord des tailles dans la section "Tailles".</span>
                            <span x-show="colors.length === 0 && allSizes.length === 0">Ajoutez des couleurs et des tailles pour définir les prix par combinaison.</span>
                        </p>
                    </div>

                    <!-- Résumé des combinaisons -->
                    <div x-show="colors.length > 0 && allSizes.length > 0" 
                         class="text-xs text-gray-400 flex flex-wrap gap-4 p-3 bg-gray-50 rounded-xl border border-gray-200">
                        <span class="font-medium text-gray-600">Résumé :</span>
                        <span><span class="font-semibold text-gray-700" x-text="colors.length"></span> couleur(s)</span>
                        <span><span class="font-semibold text-gray-700" x-text="allSizes.length"></span> taille(s)</span>
                        <span><span class="font-semibold text-gray-700" x-text="colors.length * allSizes.length"></span> combinaison(s)</span>
                        <span class="text-gray-400">|</span>
                        <span>💰 Prix vide = prix de référence</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Quantité & Unités -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-emerald-100 text-emerald-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">2</span>
            Quantité & Unités
        </h3>
       
        <div class="mb-6">
            <label class="text-sm font-semibold text-gray-700 block mb-3">Unité de mesure *</label>
            <div class="flex bg-gray-100 p-1 rounded-xl gap-1 flex-wrap sm:flex-nowrap">
                <template x-for="unit in units" :key="unit.id">
                    <button type="button" @click="selectUnit(unit)"
                            :class="selectedUnit.id === unit.id ? 'bg-white shadow-sm text-fuchsia-600' : 'text-gray-600 hover:bg-gray-200'"
                            class="flex-1 py-2.5 rounded-lg text-xs sm:text-sm font-semibold transition-all duration-200 px-2"
                            x-text="unit.name">
                    </button>
                </template>
            </div>
        </div>
       
        <div x-show="selectedUnit" x-transition class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-gray-700" x-text="`Quantité totale (${selectedUnit.symbol}) *`"></label>
                    <input type="number" x-model="formData.quantity" name="quantity" step="0.01" min="0.01" required
                           class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-gray-700" x-text="`Min. commande (${selectedUnit.symbol})`"></label>
                    <input type="number" x-model="formData.min_order" name="min_order" step="0.01" min="0"
                           class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400">
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-gray-700" x-text="`Seuil réduction (${selectedUnit.symbol})`"></label>
                    <input type="number" x-model="formData.discount_threshold" name="discount_threshold" step="0.01" min="0"
                           class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400">
                </div>
            </div>
           
            <div class="space-y-3">
                <label class="text-sm font-semibold text-gray-700">Pourcentage de réduction (%)</label>
                <div class="flex items-center gap-4">
                    <input type="range" x-model="formData.discount_percent" name="discount_percent" min="0" max="50" step="1" class="flex-1 age-range-slider">
                    <div class="w-16 text-center">
                        <span class="text-xl font-bold text-emerald-600" x-text="formData.discount_percent"></span>
                        <span class="text-sm text-gray-500">%</span>
                    </div>
                </div>
                
                <!-- Affichage dynamique des réductions -->
                <div x-show="formData.discount_percent > 0 && formData.discount_threshold > 0" x-transition>
                    <div class="mt-4 p-4 bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl border border-emerald-200">
                        <p class="text-sm font-semibold text-emerald-800 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/>
                            </svg>
                            Offre de réduction en gros
                        </p>
                        <div class="mt-2 space-y-2">
                            <p class="text-sm text-emerald-700">
                                <span class="font-semibold">Condition :</span> 
                                Achetez au moins 
                                <span class="font-bold text-emerald-800" x-text="formData.discount_threshold"></span> 
                                <span x-text="selectedUnit.symbol"></span>
                            </p>
                            <p class="text-sm text-emerald-700">
                                <span class="font-semibold">Réduction :</span> 
                                <span class="font-bold text-emerald-800" x-text="formData.discount_percent"></span>% de réduction
                            </p>
                            <div class="mt-3 p-3 bg-white/70 rounded-lg border border-emerald-100">
                                <p class="text-xs text-gray-600">
                                    <span class="font-medium">Exemple :</span> 
                                    Pour l'achat de 
                                    <span class="font-bold" x-text="formData.discount_threshold"></span> 
                                    <span x-text="selectedUnit.symbol"></span> 
                                    à 
                                    <span class="font-bold" x-text="formData.base_price"></span> 
                                    <span x-text="formData.currency"></span> l'unité
                                </p>
                                <p class="text-xs text-emerald-700 mt-1">
                                    Prix unitaire avec réduction : 
                                    <span class="font-bold text-emerald-800">
                                        <span x-text="(formData.base_price - (formData.base_price * formData.discount_percent / 100)).toFixed(2)"></span>
                                        <span x-text="formData.currency"></span>
                                    </span>
                                    <span class="text-gray-400 text-[10px]"> (soit <span x-text="(formData.base_price * formData.discount_percent / 100).toFixed(2)"></span> <span x-text="formData.currency"></span> d'économie par unité)</span>
                                </p>
                                <p class="text-xs text-emerald-700 mt-1">
                                    Total pour <span x-text="formData.discount_threshold"></span> <span x-text="selectedUnit.symbol"></span> : 
                                    <span class="font-bold text-emerald-800">
                                        <span x-text="((formData.base_price - (formData.base_price * formData.discount_percent / 100)) * formData.discount_threshold).toFixed(2)"></span>
                                        <span x-text="formData.currency"></span>
                                    </span>
                                    <span class="text-gray-400 text-[10px]"> (économie totale de <span x-text="(formData.base_price * formData.discount_percent / 100 * formData.discount_threshold).toFixed(2)"></span> <span x-text="formData.currency"></span>)</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p x-show="formData.discount_percent === 0 || formData.discount_threshold === 0" class="text-xs text-gray-400 mt-2">
                    Définissez un seuil et un pourcentage pour proposer une réduction en gros à vos clients.
                </p>
            </div>
        </div>
    </div>

    <!-- Section 3: Galerie Médias -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">3</span>
            Galerie Médias (5 min - 30 max)
        </h3>
       
        <div class="flex items-center justify-center w-full mb-6">
            <label class="flex flex-col items-center justify-center w-full h-32 sm:h-40 border-2 border-dashed border-gray-300 rounded-3xl cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all">
                <div class="flex flex-col items-center justify-center pt-5 pb-6 px-4">
                    <svg class="w-8 sm:w-9 h-8 sm:h-9 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-width="2"/>
                    </svg>
                    <p class="text-xs sm:text-sm text-gray-600 font-medium text-center">Glissez-déposez vos médias</p>
                    <p class="text-[10px] sm:text-xs text-gray-400 mt-1">JPG, PNG, GIF, MP4, MOV - Max 50MB</p>
                </div>
                <input type="file" name="media[]" multiple class="hidden" @change="handleFiles($event)" accept="image/*,video/*" />
            </label>
        </div>
        <div class="space-y-3">
            <template x-for="(file, index) in files" :key="index">
                <div class="p-3 sm:p-4 border border-gray-100 rounded-2xl bg-white shadow-sm flex items-center gap-3 sm:gap-4">
                    <div class="size-10 sm:size-12 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                        <template x-if="file.type.startsWith('image/')">
                            <img :src="file.preview" class="w-full h-full object-cover">
                        </template>
                        <template x-if="file.type.startsWith('video/')">
                            <div class="w-full h-full flex items-center justify-center bg-purple-100">
                                <svg class="w-5 sm:w-6 h-5 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs sm:text-sm font-medium text-gray-800 truncate" x-text="file.name"></p>
                        <p class="text-[10px] sm:text-xs text-gray-400" x-text="formatFileSize(file.size)"></p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full">
                                <div class="h-full bg-emerald-500 transition-all duration-1000 rounded-full" :style="`width: ${file.progress}%`"></div>
                            </div>
                            <span class="text-[10px] sm:text-xs text-gray-500" x-text="file.progress + '%'"></span>
                        </div>
                    </div>
                    <button type="button" @click="removeFile(index)" class="text-gray-400 hover:text-red-500">
                        <svg class="size-4 sm:size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- Section 4: État du produit -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-red-100 text-red-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">4</span>
            État du produit
        </h3>
       
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
            <div>
                <label class="text-sm font-semibold text-gray-700 block mb-3">Condition *</label>
                <div class="flex gap-4">
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" x-model="formData.condition" name="condition" value="new" class="hidden peer">
                        <div class="p-4 border-2 rounded-2xl text-center peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-50 transition-all font-bold text-gray-700">
                            Neuf
                        </div>
                    </label>
                    <label class="flex-1 cursor-pointer">
                        <input type="radio" x-model="formData.condition" name="condition" value="used" class="hidden peer">
                        <div class="p-4 border-2 rounded-2xl text-center peer-checked:border-fuchsia-500 peer-checked:bg-fuchsia-50 transition-all font-bold text-gray-700">
                            Seconde main
                        </div>
                    </label>
                </div>
            </div>
           
            <div x-show="formData.condition === 'used'" x-transition>
                <label class="text-sm font-semibold text-gray-700 block mb-3">Défauts constatés (appuyez sur ↵ après)</label>
                <div class="flex flex-wrap gap-2 mb-3">
                    <template x-for="(defect, index) in formData.defects" :key="index">
                        <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            <span x-text="defect"></span>
                            <button type="button" @click="removeDefect(index)" class="hover:bg-red-200 rounded-full p-0.5">
                                <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" stroke-width="2"/></svg>
                            </button>
                        </span>
                    </template>
                </div>
                <input type="text" @keydown.enter.prevent="addDefect($event.target.value); $event.target.value = ''" placeholder="Tapez un défaut puis Entrée"
                       class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white outline-none border">
                <input type="hidden" name="defects" :value="JSON.stringify(formData.defects)">
            </div>
        </div>
    </div>

    <!-- Section 5: Couleurs disponibles -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6 flex items-center gap-2">
            <span class="size-6 sm:size-7 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center text-xs sm:text-sm font-bold">5</span>
            Couleurs disponibles
        </h3>
       
        <div class="space-y-4">
            <template x-for="(color, index) in colors" :key="index">
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50">
                    <div class="flex flex-wrap items-center gap-4">
                        <div>
                            <label class="text-xs font-bold text-gray-700 block mb-1">Couleur</label>
                            <input type="color" x-model="color.hex" @change="updateColorName(index)" class="size-10 rounded-lg border-none cursor-pointer">
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="text-xs font-bold text-gray-700 block mb-1">Image représentative</label>
                            <input type="file" @change="handleColorImage($event, index)" accept="image/*" class="hidden" :id="'colorImg'+index">
                            <label :for="'colorImg'+index" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-xl cursor-pointer hover:bg-gray-100 text-sm">
                                <template x-if="color.preview">
                                    <img :src="color.preview" class="size-8 rounded object-cover">
                                </template>
                                <span x-text="color.preview ? 'Changer' : 'Choisir image'"></span>
                            </label>
                        </div>
                        <button type="button" @click="removeColor(index)" class="text-red-500 hover:text-red-700">
                            <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2"/></svg>
                        </button>
                    </div>
                    <template x-if="color.preview">
                        <div class="mt-3">
                            <p class="text-xs font-semibold text-gray-700 mb-1">Aperçu :</p>
                            <img :src="color.preview" class="h-20 rounded-lg object-cover border border-gray-200">
                        </div>
                    </template>
                </div>
            </template>
        </div>
       
        <button type="button" @click="addColor" class="mt-4 flex items-center gap-2 text-sm font-bold text-purple-600 hover:text-purple-700">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"/></svg>
            Ajouter une couleur
        </button>
        <input type="hidden" name="colors" :value="JSON.stringify(colors)">
    </div>

    <!-- Section 6: Tailles (Collapse) -->
    <div class="reveal">
        <button type="button" class="hs-collapse-toggle w-full p-4 sm:p-6 flex justify-between items-center bg-gray-900 text-white rounded-2xl sm:rounded-3xl shadow-lg"
                data-hs-collapse="#sizes-content">
            <span class="font-bold text-sm sm:text-lg">Tailles (Chaussures & Habits)</span>
            <svg class="hs-collapse-open:rotate-180 transition-transform size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" stroke-width="2"/>
            </svg>
        </button>
        <div id="sizes-content" class="hs-collapse hidden overflow-hidden transition-all duration-300">
            <div class="mt-6 bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 border border-gray-100 space-y-6 sm:space-y-8">
                <div class="space-y-4">
                    <label class="text-sm font-bold text-gray-700 block">Tailles Chaussures (manuel) (appuyez sur ↵ après)</label>
                    <input type="text" @keydown.enter.prevent="addShoeSize($event.target.value); $event.target.value = ''"
                           placeholder="Ex: 38, 40, 42..." class="w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm">
                    <div class="flex flex-wrap gap-2 mt-3">
                        <template x-for="(size, index) in shoeSizes" :key="index">
                            <span class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <span x-text="size"></span>
                                <button type="button" @click="shoeSizes.splice(index, 1)" class="hover:bg-blue-200 rounded-full p-0.5">&times;</button>
                            </span>
                        </template>
                    </div>
                    <input type="hidden" name="shoe_sizes" :value="JSON.stringify(shoeSizes)">
                </div>
                <hr class="border-gray-100">
                <div class="space-y-4">
                    <label class="text-sm font-bold text-gray-700 block">Tailles Habits</label>
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-medium text-gray-600">Pour enfant</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="showChildSizes" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-fuchsia-500 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>
                        <div x-show="showChildSizes" x-transition>
                            <div class="grid grid-cols-3 gap-3 mb-4">
                                <template x-for="sz in childStandardSizes">
                                    <button type="button" @click="toggleChildSize(sz)"
                                            :class="childSizes.includes(sz) ? 'bg-fuchsia-500 text-white' : 'bg-gray-100 text-gray-700'"
                                            class="py-2 rounded-lg text-xs font-bold transition-all" x-text="sz"></button>
                                </template>
                            </div>
                            <input type="hidden" name="child_sizes" :value="JSON.stringify(childSizes)">
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 block mb-3">Tailles adultes</label>
                        <div class="flex flex-wrap gap-3">
                            <template x-for="sz in adultSizes">
                                <button type="button" @click="toggleAdultSize(sz)"
                                        :class="adultSizesSelected.includes(sz) ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700'"
                                        class="px-4 py-2 rounded-xl text-sm font-bold transition-all" x-text="sz"></button>
                            </template>
                        </div>
                        <input type="hidden" name="adult_sizes" :value="JSON.stringify(adultSizesSelected)">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 7: Caractéristiques -->
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-sm border border-gray-100 reveal">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 sm:mb-6">Caractéristiques techniques</h3>
        <div class="space-y-4">
            <template x-for="(spec, index) in specifications" :key="index">
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-start sm:items-center">
                    <input type="text" x-model="spec.key" placeholder="Ex: Marque" class="flex-1 w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400 outline-none border transition-all">
                    <input type="text" x-model="spec.value" placeholder="Ex: Lenovo" class="flex-1 w-full rounded-xl border-gray-200 bg-gray-50 px-4 py-3 text-sm focus:bg-white focus:ring-2 focus:ring-fuchsia-400 outline-none border transition-all">
                    <button type="button" @click="specifications.splice(index, 1)" class="text-red-500 hover:text-red-700 p-2">
                        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2"/></svg>
                    </button>
                </div>
            </template>
            <button type="button" @click="specifications.push({key:'', value:''})" class="text-sm font-bold text-fuchsia-600 hover:text-fuchsia-700 flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="2"/></svg>
                Ajouter une caractéristique
            </button>
            <input type="hidden" name="specifications" :value="JSON.stringify(specifications)">
        </div>
    </div>

    <!-- Bouton de confirmation robot -->
    <div class="flex items-center space-x-4 p-4 bg-white border border-gray-200 rounded-xl mb-6 select-none shadow-sm">
        <div id="captcha-container" class="relative flex items-center justify-center w-7 h-7">
            <div id="check-circle" class="w-full h-full border-2 border-gray-300 rounded-full cursor-pointer transition-all duration-300 hover:border-fuchsia-500"></div>
            
            <svg id="captcha-spinner" class="hidden animate-spin absolute w-5 h-5 text-fuchsia-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>

            <svg id="check-icon" class="hidden absolute w-5 h-5 text-white opacity-0 transition-opacity duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        
        <label class="text-sm font-semibold text-gray-700 cursor-pointer">Je ne suis pas un robot</label>
        <input type="hidden" name="human_token" id="human_token" value="">
    </div>

    <!-- Bouton soumission -->
    <div class="reveal">
        <button type="submit" :disabled="loading || files.length < 5"
                class="w-full py-4 bg-gray-900 text-white rounded-2xl sm:rounded-3xl font-semibold text-sm sm:text-base shadow-lg hover:bg-fuchsia-600 transition-all flex items-center justify-center gap-3 active:scale-[0.98] disabled:opacity-60">
            <template x-if="!loading">
                <span x-text="files.length < 5 ? `Ajoutez encore ${5 - files.length} image(s), videos(s) ou plus ` : 'Mettre en vente'"></span>
            </template>
            <template x-if="loading">
                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Analyse en cours...</span>
            </template>
        </button>
    </div>
</form>
</div>

<!-- Modal Catégorie -->
<div x-show="openCatModal" 
     x-cloak 
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm"
     @keydown.escape.window="openCatModal = false">
    
    <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 max-w-lg w-full shadow-2xl overflow-hidden transition-all mx-4">
        <div class="flex items-center gap-4 mb-6">
            <button x-show="tempCat.mainId" 
                    @click="tempCat.mainId = ''; tempCat.subId = ''" 
                    class="p-2 hover:bg-gray-100 rounded-full transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <h2 class="text-base sm:text-xl font-bold" x-text="!tempCat.mainId ? 'Choisir une catégorie' : 'Choisir une sous-catégorie'"></h2>
        </div>

        <div class="max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
            <div x-show="!tempCat.mainId" class="grid grid-cols-2 gap-3">
                <template x-for="(name, id) in categories" :key="id">
                    <button @click="tempCat.mainId = id" 
                            class="group p-4 rounded-2xl border-2 border-gray-50 bg-gray-50 hover:border-fuchsia-500 hover:bg-fuchsia-50 transition-all text-left">
                        <div class="w-10 h-10 mb-3 rounded-lg bg-white flex items-center justify-center shadow-sm group-hover:scale-110 transition-transform">
                            <span class="text-xl">📁</span>
                        </div>
                        <span class="font-semibold text-gray-800 block text-sm" x-text="name"></span>
                    </button>
                </template>
            </div>

            <div x-show="tempCat.mainId" class="space-y-2">
                <template x-for="sub in subcategories[tempCat.mainId]" :key="sub.id">
                    <button @click="tempCat.subId = sub.id" 
                            :class="tempCat.subId === sub.id ? 'border-fuchsia-500 bg-fuchsia-50 ring-1 ring-fuchsia-500' : 'border-gray-100 hover:border-fuchsia-200'"
                            class="w-full p-4 rounded-xl border-2 text-left transition-all flex justify-between items-center">
                        <span class="font-medium text-gray-700 text-sm" x-text="sub.name"></span>
                        <div x-show="tempCat.subId === sub.id" class="w-5 h-5 bg-fuchsia-500 rounded-full flex items-center justify-center">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </button>
                </template>
                
                <button @click="tempCat.subId = ''" 
                        class="w-full p-4 italic text-gray-500 hover:text-fuchsia-500 transition-colors text-sm">
                    Passer la sous-catégorie
                </button>
            </div>
        </div>

        <div class="flex gap-3 mt-8 pt-4 border-t border-gray-100">
            <button @click="openCatModal = false" class="flex-1 py-3 font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition">Annuler</button>
            <button @click="addCategoryFromModal" 
                    :disabled="!tempCat.mainId"
                    :class="!tempCat.mainId ? 'bg-gray-200 cursor-not-allowed' : 'bg-fuchsia-500 hover:bg-fuchsia-600'"
                    class="flex-1 py-3 text-white font-bold rounded-xl transition-all">
                Confirmer
            </button>
        </div>
    </div>
</div>

<!-- Modal Résultat -->
<div x-show="showResultModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl sm:rounded-3xl p-6 sm:p-10 max-w-sm w-full text-center shadow-2xl mx-4">
        <div :class="resultModalType === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
             class="size-16 sm:size-20 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg x-show="resultModalType === 'success'" class="size-8 sm:size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M5 13l4 4L19 7" stroke-width="3"/>
            </svg>
            <svg x-show="resultModalType === 'error'" class="size-8 sm:size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M6 18L18 6M6 6l12 12" stroke-width="3"/>
            </svg>
        </div>
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2" x-text="resultModalTitle"></h2>
        <p class="text-sm sm:text-base text-gray-500 mb-6 sm:mb-8" x-text="resultModalDesc"></p>
        <button @click="showResultModal = false" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold hover:bg-gray-800">
            Fermer
        </button>
    </div>
</div>

<div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm" 
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-90" x-transition:enter-end="opacity-100 scale-100">
    <div class="bg-white rounded-2xl sm:rounded-[3rem] p-6 sm:p-10 max-w-sm w-full text-center shadow-2xl mx-4">
        <div :class="modalType === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'" class="size-16 sm:size-20 rounded-full flex items-center justify-center mx-auto mb-6">
            <template x-if="modalType === 'success'">
                <svg class="size-8 sm:size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </template>
            <template x-if="modalType === 'error'">
                <svg class="size-8 sm:size-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </template>
        </div>
        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2" x-text="modalTitle"></h2>
        <p class="text-sm sm:text-base text-gray-500 mb-6 sm:mb-8" x-text="modalDesc"></p>
        <button @click="showModal = false" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold">Fermer</button>
    </div>
</div>

<script>
const PHP_SUCCESS = <?php echo $success_message ? json_encode($success_message) : 'null'; ?>;
const PHP_ERROR   = <?php echo $error_message ? json_encode($error_message) : 'null'; ?>;
</script>
<script>
// Captcha visual behaviour
const captchaContainer = document.getElementById('captcha-container');
const checkCircle = document.getElementById('check-circle');
const captchaSpinner = document.getElementById('captcha-spinner');
const checkIcon = document.getElementById('check-icon');
const humanTokenEl = document.getElementById('human_token');

let isVerified = false;

if (captchaContainer) {
    captchaContainer.addEventListener('mousedown', function() {
        if (isVerified) return;

        if (checkCircle) checkCircle.classList.add('scale-0', 'opacity-0');
        if (captchaSpinner) captchaSpinner.classList.remove('hidden');

        setTimeout(() => {
            if (captchaSpinner) captchaSpinner.classList.add('hidden');
            if (checkCircle) {
                checkCircle.classList.remove('scale-0', 'opacity-0', 'border-gray-300', 'hover:border-fuchsia-500');
                checkCircle.classList.add('bg-green-500', 'border-green-500', 'scale-110');
            }
            if (checkIcon) {
                checkIcon.classList.remove('hidden');
                setTimeout(() => {
                    checkIcon.classList.remove('opacity-0');
                    checkIcon.classList.add('opacity-100');
                }, 50);
            }

            if (humanTokenEl) humanTokenEl.value = 'mouse_verified';
            isVerified = true;
        }, 1200);
    });
}
</script>
<script>
function productForm() {
    return {
        loading: false,
        score: 25,
        showResultModal: false,
        resultModalType: 'success',
        resultModalTitle: '',
        resultModalDesc: '',
        openCatModal: false,
        tempCat: { mainId: '', subId: '' },
        selectedCategories: [],
        selectedRegions: [],
        allRegions: <?php echo json_encode($all_regions); ?>,
        categories: <?php echo json_encode($categories); ?>,
        subcategories: <?php echo json_encode($subcategories_by_cat); ?>,
        units: [
            { id: 'pcs', name: 'Pièces', symbol: 'pcs' },
            { id: 'kg', name: 'Kilogrammes', symbol: 'kg' },
            { id: 'meters', name: 'Mètres', symbol: 'm' },
            { id: 'liters', name: 'Litres', symbol: 'L' }
        ],
        selectedUnit: { id: 'pcs', name: 'Pièces', symbol: 'pcs' },
        files: [],
        formData: {
            name: '', 
            base_price: 0, 
            currency: 'USD', 
            description: '', 
            condition: 'new',
            quantity: '', 
            min_order: '', 
            discount_threshold: '', 
            discount_percent: 0,
            defects: []
        },
        // Gestion des prix par variante
        priceMode: 'uniform',
        colorPrices: {},
        colorStocks: {},
        sizePrices: {},
        sizeStocks: {},
        variantPrices: {},
        variantStocks: {},
        shoeSizes: [],
        showChildSizes: false,
        childStandardSizes: [
            'Naissance', '1-3 mois', '3-6 mois', '6-9 mois', '9-12 mois',
            '12-18 mois', '18-24 mois', '2-3 ans', '3-4 ans', '5-6 ans',
            '7-8 ans', '9-10 ans', '11-12 ans', '13-14 ans', '15-16 ans'
        ],
        childSizes: [],
        adultSizes: ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
        adultSizesSelected: [],
        colors: [],
        specifications: [{key: 'Marque', value: ''}],
        descriptionPreview: '',
        
        get allSizes() {
            return [...this.shoeSizes, ...this.childSizes, ...this.adultSizesSelected];
        },
        
        get scoreColor() {
            if (this.score >= 80) return 'text-green-500';
            if (this.score >= 60) return 'text-blue-500';
            if (this.score >= 40) return 'text-amber-500';
            return 'text-red-500';
        },
        get scoreTextColor() {
            if (this.score >= 80) return 'text-green-600';
            if (this.score >= 60) return 'text-blue-600';
            if (this.score >= 40) return 'text-amber-600';
            return 'text-red-600';
        },
        get scoreText() {
            if (this.score >= 80) return 'Excellent';
            if (this.score >= 60) return 'Bon';
            if (this.score >= 40) return 'Moyen';
            return 'À améliorer';
        },
        
        init() {
            this.$watch('formData', () => this.calculateScore(), { deep: true });
            this.$watch('files', () => this.calculateScore());
            this.$watch('selectedRegions', () => this.calculateScore());
            this.$watch('specifications', () => this.calculateScore(), { deep: true });
            this.$watch('colors', () => this.calculateScore(), { deep: true });
            this.$watch('priceMode', () => this.calculateScore());
            this.$watch('allSizes', () => this.calculateScore());
            
            // Initialiser les prix/stocks par défaut quand les couleurs changent
            this.$watch('colors', () => {
                this.colors.forEach((color, index) => {
                    if (!(index in this.colorPrices)) {
                        this.colorPrices[index] = this.formData.base_price;
                    }
                    if (!(index in this.colorStocks)) {
                        this.colorStocks[index] = this.formData.quantity || 0;
                    }
                });
            });
            
            // Initialiser les prix/stocks par taille quand les tailles changent
            this.$watch('allSizes', () => {
                this.allSizes.forEach(size => {
                    if (!(size in this.sizePrices)) {
                        this.sizePrices[size] = this.formData.base_price;
                    }
                    if (!(size in this.sizeStocks)) {
                        this.sizeStocks[size] = this.formData.quantity || 0;
                    }
                });
            });
            
            // Initialiser les prix/stocks par combinaison
            this.$watch('colors', () => {
                this.updateVariantCombinations();
            });
            this.$watch('allSizes', () => {
                this.updateVariantCombinations();
            });
            
            if (typeof ScrollReveal !== 'undefined') {
                ScrollReveal().reveal('.reveal', { distance: '30px', origin: 'bottom', duration: 800, interval: 100 });
            }

            if (PHP_SUCCESS) {
                this.modalTitle = "Produit Propulsé !";
                this.modalDesc = "Votre produit est maintenant en ligne et prêt à être vendu.";
                this.modalType = "success";
                this.showModal = true;
            }

            if (PHP_ERROR) {
                this.modalTitle = "Oups !";
                this.modalDesc = "Erreur : " + PHP_ERROR;
                this.modalType = "error";
                this.showModal = true;
            }
        },
        
        updateVariantCombinations() {
            this.colors.forEach(color => {
                this.allSizes.forEach(size => {
                    const key = color.hex + '_' + size;
                    if (!(key in this.variantPrices)) {
                        this.variantPrices[key] = this.formData.base_price;
                    }
                    if (!(key in this.variantStocks)) {
                        this.variantStocks[key] = 0;
                    }
                });
            });
        },
        
        calculateScore() {
            let s = 10;
            if (this.formData.name.length > 5) s += 15;
            if (this.formData.description.length > 20) s += 20;
            if (this.files.length >= 5) s += Math.min(this.files.length * 2, 30);
            if (this.specifications.length > 1 && this.specifications.some(sp => sp.key && sp.value)) s += 15;
            if (this.selectedRegions.length > 0) s += Math.min(this.selectedRegions.length * 2, 10);
            if (this.colors.length > 0) s += Math.min(this.colors.length * 3, 15);
            if (this.formData.base_price > 0) s += 10;
            if (this.formData.discount_percent > 0 && this.formData.discount_threshold > 0) s += 5;
            if (this.priceMode !== 'uniform') s += 5;
            this.score = Math.min(100, s);
        },
        
        addRegion(region) {
            if (region && !this.selectedRegions.includes(region)) {
                this.selectedRegions.push(region);
            }
        },
        removeRegion(r) { this.selectedRegions = this.selectedRegions.filter(i => i !== r); },
        
        addCategoryFromModal() {
            if (!this.tempCat.mainId) return;
            const mainName = this.categories[this.tempCat.mainId];
            const cat = { name: mainName };
            if (this.tempCat.subId) {
                const sub = this.subcategories[this.tempCat.mainId].find(s => s.id == this.tempCat.subId);
                cat.sub = sub.name;
            }
            this.selectedCategories = [cat];
            this.openCatModal = false;
            this.tempCat = { mainId: '', subId: '' };
        },
        removeCategory(index) { this.selectedCategories.splice(index, 1); },
        
        selectUnit(unit) { this.selectedUnit = unit; },
        
        handleFiles(e) {
            const dt = new DataTransfer();
            this.files.forEach(item => {
                if (item.file) dt.items.add(item.file);
            });

            Array.from(e.target.files).forEach(file => {
                if (this.files.length >= 30) return;

                if (file.size > 50 * 1024 * 1024) {
                    this.showResult('Fichier trop lourd', file.name + ' dépasse 50 Mo', 'error');
                    return;
                }

                dt.items.add(file);

                const reader = new FileReader();
                reader.onload = (ev) => {
                    this.files.push({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                        preview: ev.target.result,
                        progress: 0,
                        file: file
                    });

                    let i = this.files.length - 1;
                    let prog = 0;
                    let int = setInterval(() => {
                        prog += 25;
                        this.files[i].progress = prog;
                        if (prog >= 100) clearInterval(int);
                    }, 150);
                };
                reader.readAsDataURL(file);
            });

            e.target.files = dt.files;
        },
        removeFile(i) { this.files.splice(i, 1); },
        
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        addDefect(defect) {
            if (defect && !this.formData.defects.includes(defect)) this.formData.defects.push(defect);
        },
        removeDefect(i) { this.formData.defects.splice(i, 1); },
        
        addShoeSize(size) {
            if (size && !this.shoeSizes.includes(size)) this.shoeSizes.push(size);
        },
        
        toggleChildSize(sz) {
            const i = this.childSizes.indexOf(sz);
            i === -1 ? this.childSizes.push(sz) : this.childSizes.splice(i, 1);
        },
        toggleAdultSize(sz) {
            const i = this.adultSizesSelected.indexOf(sz);
            i === -1 ? this.adultSizesSelected.push(sz) : this.adultSizesSelected.splice(i, 1);
        },
        
        addColor() {
            this.colors.push({ hex: '#000000', preview: null, imageFile: null });
            const index = this.colors.length - 1;
            this.colorPrices[index] = this.formData.base_price;
            this.colorStocks[index] = this.formData.quantity || 0;
            this.updateVariantCombinations();
        },
        updateColorName(i) {},
        
        handleColorImage(e, i) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (ev) => { 
                    this.colors[i].preview = ev.target.result;
                    this.colors[i].imageFile = file;
                };
                reader.readAsDataURL(file);
            }
            e.target.value = '';
        },
        removeColor(i) { 
            this.colors.splice(i, 1);
            delete this.colorPrices[i];
            delete this.colorStocks[i];
            this.colorPrices = Object.values(this.colorPrices);
            this.colorStocks = Object.values(this.colorStocks);
        },
        
        formatText(type) {
            const ta = document.querySelector('textarea[x-model="formData.description"]');
            const start = ta.selectionStart;
            const end = ta.selectionEnd;
            const text = ta.value;
            let insert = '';
            if (type === 'title') insert = '# ';
            if (type === 'bold') insert = '**';
            ta.value = text.slice(0, start) + insert + text.slice(start, end) + (type === 'bold' ? '**' : '') + text.slice(end);
            this.formData.description = ta.value;
            this.updatePreview();
        },
        
        updatePreview() {
            let html = this.formData.description
                .replace(/^# (.*$)/gim, '<div class="text-lg font-bold text-gray-900 mb-2">$1</div>')
                .replace(/\*\*(.*)\*\*/g, '<strong class="font-bold">$1</strong>')
                .replace(/\n/g, '<br>');
            this.descriptionPreview = html || '<span class="text-gray-400">Aperçu vide...</span>';
        },
        
        showResult(title, desc, type = 'success') {
            this.resultModalTitle = title;
            this.resultModalDesc = desc;
            this.resultModalType = type;
            this.showResultModal = true;
        },
        
        async submitForm() {
            if (this.files.length < 5) {
                return this.showResult('Images manquantes', 'Minimum 5 images requis', 'error');
            }

            if (!this.formData.name || !this.formData.description || this.formData.base_price <= 0 || !this.formData.quantity) {
                return this.showResult('Champs obligatoires', 'Veuillez remplir tous les champs requis', 'error');
            }

            if (this.selectedRegions.length === 0) {
                return this.showResult('Région manquante', 'Choisissez au moins une région', 'error');
            }

            if (this.selectedCategories.length === 0) {
                return this.showResult('Catégorie manquante', 'Sélectionnez une catégorie', 'error');
            }

            this.loading = true;

            try {
                const formEl = document.getElementById('productForm');
                const formData = formEl ? new FormData(formEl) : new FormData();

                formData.set('ajax', '1');

                const ht = document.getElementById('human_token');
                if (ht && ht.value) {
                    formData.set('human_token', ht.value);
                }

                formData.set('category', JSON.stringify(this.selectedCategories));
                formData.set('regions', JSON.stringify(this.selectedRegions));
                formData.set('defects', JSON.stringify(this.formData.defects || []));
                formData.set('colors', JSON.stringify(this.colors.map(c => ({ hex: c.hex }))));
                formData.set('shoe_sizes', JSON.stringify(this.shoeSizes));
                formData.set('child_sizes', JSON.stringify(this.childSizes));
                formData.set('adult_sizes', JSON.stringify(this.adultSizesSelected));
                formData.set('specifications', JSON.stringify(this.specifications));
                formData.set('price_mode', this.priceMode);
                
                formData.set('color_prices', JSON.stringify(this.colorPrices));
                formData.set('color_stocks', JSON.stringify(this.colorStocks));
                formData.set('size_prices', JSON.stringify(this.sizePrices));
                formData.set('size_stocks', JSON.stringify(this.sizeStocks));
                formData.set('variant_prices', JSON.stringify(this.variantPrices));
                formData.set('variant_stocks', JSON.stringify(this.variantStocks));

                this.colors.forEach((color, idx) => {
                    if (color.imageFile) {
                        const ext = (color.imageFile.type || 'image/jpeg').split('/')[1] || 'jpg';
                        formData.append('color_images[]', color.imageFile, `color_${idx}.${ext}`);
                    } else {
                        formData.append('color_images[]', '');
                    }
                });

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    credentials: 'include',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Erreur serveur: ' + response.status);
                }

                const rawResponse = await response.text();
                let result = null;

                if (rawResponse && rawResponse.trim()) {
                    try {
                        result = JSON.parse(rawResponse);
                    } catch (parseError) {
                        throw new Error('Réponse serveur invalide. Vérifiez que le captcha est bien validé et réessayez.');
                    }
                }

                if (!result) {
                    throw new Error('Réponse vide du serveur.');
                }

                if (result.success) {
                    this.showModal = true;
                    this.modalTitle = "Produit Propulsé !";
                    this.modalDesc = result.message || "Votre produit est maintenant en ligne et prêt à être vendu.";
                    this.modalType = "success";
                    this.resetForm();
                } else {
                    this.showModal = true;
                    this.modalTitle = "Oups !";
                    this.modalDesc = "Erreur : " + (result.message || "Une erreur inconnue est survenue");
                    this.modalType = "error";
                }

            } catch (error) {
                console.error('Erreur:', error);
                this.showResult('Erreur', 'Une erreur est survenue lors de l\'envoi: ' + error.message, 'error');
            } finally {
                this.loading = false;
            }
        },

        resetForm() {
            this.formData = {
                name: '', 
                base_price: 0, 
                currency: 'USD', 
                description: '', 
                condition: 'new',
                quantity: '', 
                min_order: '', 
                discount_threshold: '', 
                discount_percent: 0,
                defects: []
            };
            this.files = [];
            this.colors = [];
            this.colorPrices = {};
            this.colorStocks = {};
            this.sizePrices = {};
            this.sizeStocks = {};
            this.variantPrices = {};
            this.variantStocks = {};
            this.shoeSizes = [];
            this.childSizes = [];
            this.adultSizesSelected = [];
            this.selectedCategories = [];
            this.selectedRegions = [];
            this.specifications = [{key: 'Marque', value: ''}];
            this.showChildSizes = false;
            this.selectedUnit = { id: 'pcs', name: 'Pièces', symbol: 'pcs' };
            this.descriptionPreview = '';
            this.priceMode = 'uniform';
            
            const checkCircle = document.getElementById('check-circle');
            const checkIcon = document.getElementById('check-icon');
            const humanTokenEl = document.getElementById('human_token');
            if (checkCircle) {
                checkCircle.classList.remove('bg-green-500', 'border-green-500', 'scale-110');
                checkCircle.classList.add('border-gray-300', 'hover:border-fuchsia-500');
            }
            if (checkIcon) {
                checkIcon.classList.add('hidden', 'opacity-0');
            }
            if (humanTokenEl) {
                humanTokenEl.value = '';
            }
            isVerified = false;
        }
    }
}
</script>
</body>
</html>