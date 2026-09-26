<?php
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

// ==============================================
// FONCTION DE CONVERSION HEX -> NOM DE COULEUR VIA API
// ==============================================

/**
 * Récupère le nom d'une couleur à partir de son code hex
 * Utilise The Color API (https://www.thecolorapi.com) - gratuite, sans clé
 * Avec cache en session et fallback local
 */
function getColorNameFromHex($hex) {
    // Normaliser le hex (enlever #, mettre en majuscules, compléter à 6 caractères)
    $hex = strtoupper(ltrim($hex, '#'));
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return 'Couleur inconnue';
    }

    // Initialiser le cache en session
    if (!isset($_SESSION['color_name_cache'])) {
        $_SESSION['color_name_cache'] = [];
    }

    // Vérifier le cache
    if (isset($_SESSION['color_name_cache'][$hex])) {
        return $_SESSION['color_name_cache'][$hex];
    }

    // Fallback local immédiat pour les couleurs les plus courantes
    $localFallback = [
        '000000' => 'Black', 'FFFFFF' => 'White', 'FF0000' => 'Red',
        '00FF00' => 'Lime', '0000FF' => 'Blue', 'FFFF00' => 'Yellow',
        'FF00FF' => 'Magenta', '00FFFF' => 'Cyan', 'FFA500' => 'Orange',
        '800080' => 'Purple', 'A52A2A' => 'Brown', '808080' => 'Gray',
        'C0C0C0' => 'Silver', 'FFC0CB' => 'Pink', '000080' => 'Navy',
        '008000' => 'Green', '800000' => 'Maroon', '808000' => 'Olive',
        '008080' => 'Teal', '4B0082' => 'Indigo', 'EE82EE' => 'Violet',
        'F5F5DC' => 'Beige', 'FFD700' => 'Gold', 'D2691E' => 'Chocolate',
        'DC143C' => 'Crimson', 'B22222' => 'Firebrick', '228B22' => 'ForestGreen',
        '2E8B57' => 'SeaGreen', '6A5ACD' => 'SlateBlue', '7B68EE' => 'MediumSlateBlue',
        '9370DB' => 'MediumPurple', '8B4513' => 'SaddleBrown', 'DAA520' => 'Goldenrod',
        'CD853F' => 'Peru', 'D2B48C' => 'Tan', 'F4A460' => 'SandyBrown',
        'A0522D' => 'Sienna', '8B0000' => 'DarkRed', 'FF4500' => 'OrangeRed',
        'FF6347' => 'Tomato', 'FF7F50' => 'Coral', 'FF8C00' => 'DarkOrange',
        'FFA07A' => 'LightSalmon', 'F08080' => 'LightCoral', 'FA8072' => 'Salmon',
        'E9967A' => 'DarkSalmon', 'CD5C5C' => 'IndianRed', 'B0C4DE' => 'LightSteelBlue',
        'ADD8E6' => 'LightBlue', '87CEEB' => 'SkyBlue', '87CEFA' => 'LightSkyBlue',
        '4682B4' => 'SteelBlue', '5F9EA0' => 'CadetBlue', '6495ED' => 'CornflowerBlue',
        '483D8B' => 'DarkSlateBlue', '191970' => 'MidnightBlue', '00008B' => 'DarkBlue',
        '0000CD' => 'MediumBlue', '4169E1' => 'RoyalBlue', '1E90FF' => 'DodgerBlue',
        '00BFFF' => 'DeepSkyBlue', '00CED1' => 'DarkTurquoise', '20B2AA' => 'LightSeaGreen',
        '48D1CC' => 'MediumTurquoise', '40E0D0' => 'Turquoise', '00FA9A' => 'MediumSpringGreen',
        '00FF7F' => 'SpringGreen', '3CB371' => 'MediumSeaGreen', '90EE90' => 'LightGreen',
        '98FB98' => 'PaleGreen', '8FBC8F' => 'DarkSeaGreen', '556B2F' => 'DarkOliveGreen',
        '6B8E23' => 'OliveDrab', '7CFC00' => 'LawnGreen', '7FFF00' => 'Chartreuse',
        'ADFF2F' => 'GreenYellow', '32CD32' => 'LimeGreen', '9ACD32' => 'YellowGreen',
        'FF1493' => 'DeepPink', 'FF69B4' => 'HotPink', 'FFB6C1' => 'LightPink',
        'DB7093' => 'PaleVioletRed', 'C71585' => 'MediumVioletRed', 'DA70D6' => 'Orchid',
        'DDA0DD' => 'Plum', 'D8BFD8' => 'Thistle', 'E6E6FA' => 'Lavender',
        'F0F8FF' => 'AliceBlue', 'F5F5F5' => 'WhiteSmoke', 'FAEBD7' => 'AntiqueWhite',
        'FDF5E6' => 'OldLace', 'FFFAF0' => 'FloralWhite', 'FFFFF0' => 'Ivory',
        'FFFACD' => 'LemonChiffon', 'FFF8DC' => 'Cornsilk', 'FFF5EE' => 'Seashell',
        'F0FFF0' => 'Honeydew', 'F5FFFA' => 'MintCream', 'F0FFFF' => 'Azure',
        'E0FFFF' => 'LightCyan', 'FFF0F5' => 'LavenderBlush', 'FFE4E1' => 'MistyRose',
        'FFE4B5' => 'Moccasin', 'FFDEAD' => 'NavajoWhite', 'FFEBCD' => 'BlanchedAlmond',
        'FFE4C4' => 'Bisque', 'FFDAB9' => 'PeachPuff', 'F5DEB3' => 'Wheat',
        'DEB887' => 'BurlyWood', 'BC8F8F' => 'RosyBrown', 'B8860B' => 'DarkGoldenrod',
    ];

    if (isset($localFallback[$hex])) {
        $_SESSION['color_name_cache'][$hex] = $localFallback[$hex];
        return $localFallback[$hex];
    }

    // Appel à The Color API
    $apiUrl = "https://www.thecolorapi.com/id?hex=" . $hex;
    $colorName = null;

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Cascade-Ecommerce/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['name']['value'])) {
                $colorName = $data['name']['value'];
            }
        }
    } else {
        // Fallback si cURL n'est pas disponible
        $context = stream_context_create([
            'http' => [
                'timeout' => 3,
                'method' => 'GET',
                'header' => "User-Agent: Cascade-Ecommerce/1.0\r\n"
            ]
        ]);
        $response = @file_get_contents($apiUrl, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['name']['value'])) {
                $colorName = $data['name']['value'];
            }
        }
    }

    // Si l'API a échoué, utiliser un fallback par calcul de teinte
    if ($colorName === null) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        if (abs($r - $g) < 20 && abs($g - $b) < 20 && abs($r - $b) < 20) {
            if ($r < 50) $colorName = 'Black';
            elseif ($r < 120) $colorName = 'Dark Gray';
            elseif ($r < 180) $colorName = 'Gray';
            elseif ($r < 230) $colorName = 'Light Gray';
            else $colorName = 'White';
        } else {
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $delta = $max - $min;
            
            if ($max === $r) {
                $hue = 60 * fmod((($g - $b) / $delta), 6);
            } elseif ($max === $g) {
                $hue = 60 * ((($b - $r) / $delta) + 2);
            } else {
                $hue = 60 * ((($r - $g) / $delta) + 4);
            }
            if ($hue < 0) $hue += 360;
            
            if ($hue < 15 || $hue >= 345) $colorName = 'Red';
            elseif ($hue < 45) $colorName = 'Orange';
            elseif ($hue < 70) $colorName = 'Yellow';
            elseif ($hue < 150) $colorName = 'Green';
            elseif ($hue < 200) $colorName = 'Cyan';
            elseif ($hue < 260) $colorName = 'Blue';
            elseif ($hue < 290) $colorName = 'Purple';
            else $colorName = 'Pink';
        }
    }

    // Mettre en cache
    $_SESSION['color_name_cache'][$hex] = $colorName;
    return $colorName;
}

// Récupération complète du produit
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
    $hex = $color['hex'] ?? '#000000';
    
    // Déterminer le nom : soit celui défini en base, soit récupéré via l'API
    $colorName = !empty($color['name']) 
        ? $color['name'] 
        : getColorNameFromHex($hex);
    
    $color_entry = [
        'index' => $index,
        'hex' => $hex,
        'name' => $colorName
    ];
    
    if (isset($color['preview']) && strpos($color['preview'], 'data:image') === 0) {
        $color_entry['image_data'] = $color['preview'];
    }
    elseif (isset($color['image']) && is_string($color['image'])) {
        $color_entry['image_path'] = $color['image'];
    }
    
    $colors_with_images[] = $color_entry;
}

// Récupérer les variantes du produit
$variant_stmt = $conn->prepare("
    SELECT id, variant_key, variant_type, color, size, price, quantity 
    FROM product_variants 
    WHERE product_id = ?
    ORDER BY variant_type, color, size
");
$variant_stmt->bind_param("i", $product_id);
$variant_stmt->execute();
$variants_result = $variant_stmt->get_result();
$variants = [];
while ($variant = $variants_result->fetch_assoc()) {
    $variants[] = $variant;
}
$variant_stmt->close();

// === Gestion du fallback si product_variants est vide ===
$useMainTable = empty($variants);
$fallbackColors = [];
$fallbackSizes = [];
$fallbackPrice = 0;
$fallbackStock = 0;

if ($useMainTable) {
    $fallbackColors = json_decode($product['colors'] ?? '[]', true);
    $fallbackSizes = array_unique(array_merge(
        json_decode($product['shoe_sizes'] ?? '[]', true),
        json_decode($product['adult_sizes'] ?? '[]', true),
        json_decode($product['child_sizes'] ?? '[]', true)
    ));
    $fallbackPrice = floatval($product['price'] ?? 0);
    $fallbackStock = floatval($product['quantity'] ?? 0);
}

// Structurer les données pour l'affichage
$price_mode = $product['price_mode'] ?? 'uniform';
$base_price = null;
$base_stock = null;
$variant_prices = [];
$variant_stocks = [];
$color_prices = [];
$color_stocks = [];
$size_prices = [];
$size_stocks = [];
$variant_details = [];
$total_stock = 0;
$default_variant_key = null;

foreach ($variants as $v) {
    $qty = floatval($v['quantity']);
    $price_val = floatval($v['price']);
    
    if ($v['variant_type'] === 'default') {
        $base_price = $price_val;
        $base_stock = $qty;
        $total_stock += $qty;
        $default_variant_key = $v['variant_key'];
    } elseif ($v['variant_type'] === 'color') {
        $color_prices[$v['color']] = $price_val;
        $color_stocks[$v['color']] = $qty;
        $total_stock += $qty;
        $variant_details[$v['color']] = [
            'price' => $price_val,
            'stock' => $qty,
            'type' => 'color'
        ];
    } elseif ($v['variant_type'] === 'size') {
        $size_prices[$v['size']] = $price_val;
        $size_stocks[$v['size']] = $qty;
        $total_stock += $qty;
        $variant_details[$v['size']] = [
            'price' => $price_val,
            'stock' => $qty,
            'type' => 'size'
        ];
    } elseif ($v['variant_type'] === 'color_size') {
        $variant_prices[$v['variant_key']] = $price_val;
        $variant_stocks[$v['variant_key']] = $qty;
        $total_stock += $qty;
        
        $color_hex = $v['color'];
        $size_label = $v['size'];
        
        $color_stocks[$color_hex] = ($color_stocks[$color_hex] ?? 0) + $qty;
        $size_stocks[$size_label] = ($size_stocks[$size_label] ?? 0) + $qty;
        
        if (!isset($color_prices[$color_hex])) {
            $color_prices[$color_hex] = $price_val;
        }
        if (!isset($size_prices[$size_label])) {
            $size_prices[$size_label] = $price_val;
        }

        $variant_details[$v['variant_key']] = [
            'price' => $price_val,
            'stock' => $qty,
            'color' => $color_hex,
            'size' => $size_label,
            'type' => 'color_size'
        ];
    }
}

// Si le produit a des variantes, utiliser la première variante valide comme prix par défaut
if (!empty($variants)) {
    $display_price = floatval($variants[0]['price']);
    $display_quantity = $total_stock;
} else {
    $display_price = floatval($product['price']);
    $display_quantity = floatval($product['quantity']);
}

$regions = json_decode($product['regions'] ?? '[]', true);
$defects = json_decode($product['defects'] ?? '[]', true);
$shoe_sizes = json_decode($product['shoe_sizes'] ?? '[]', true);
$child_sizes = json_decode($product['child_sizes'] ?? '[]', true);
$adult_sizes = json_decode($product['adult_sizes'] ?? '[]', true);

$all_sizes = array_unique(array_merge($shoe_sizes, $child_sizes, $adult_sizes));

$price = $display_price;
$discount_percent = intval($product['discount_percent'] ?? 0);
$discount_threshold = floatval($product['discount_threshold'] ?? 0);
$min_order = floatval($product['min_order'] ?? 0);
$quantity = $display_quantity;

$has_discount = $discount_percent > 0 && $discount_threshold > 0;
$original_price = $price;
$discounted_price = $has_discount ? $price * (1 - $discount_percent / 100) : $price;

// Construire une map des noms de couleurs pour le JavaScript
$color_names_map = [];
foreach ($colors_with_images as $c) {
    $color_names_map[$c['hex']] = $c['name'];
}
// Ajouter aussi les couleurs des variantes qui ne sont pas dans colors_with_images
foreach ($color_stocks as $hex => $stock) {
    if (!isset($color_names_map[$hex])) {
        $color_names_map[$hex] = getColorNameFromHex($hex);
    }
}

// ==============================================
// TRAITEMENT DU FORMULAIRE
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selected_variants_json = $_POST['selected_variants'] ?? '[]';
        $selected_variants = json_decode($selected_variants_json, true);
        $total_quantity = 0;
        $total_amount = 0;
        $total_original_amount = 0;
        $variant_details_array = [];
        $variant_keys_array = [];
        $has_discount_applied = false;
        $selected_variant_keys = [];
        $color_quantities = [];
        
        if (empty($selected_variants)) {
            throw new Exception('Veuillez sélectionner au moins une variante.');
        }
        
        foreach ($selected_variants as $variant) {
            $qty = floatval($variant['quantity'] ?? 0);
            if ($qty > 0) {
                $total_quantity += $qty;
                $selected_variant_keys[] = $variant['key'] ?? '';
            }
        }
        
        if ($total_quantity <= 0) {
            throw new Exception('Veuillez sélectionner une quantité valide.');
        }
        
        if ($min_order > 0 && $total_quantity < $min_order) {
            throw new Exception("Le minimum de commande est de {$min_order} {$product['unit_type']}.");
        }
        
        if ($total_quantity > $display_quantity) {
            throw new Exception("Quantité totale insuffisante en stock. Disponible: {$display_quantity} {$product['unit_type']}");
        }
        
        $discount_applies = $has_discount && $total_quantity >= $discount_threshold;
        
        foreach ($selected_variants as $variant) {
            $qty = floatval($variant['quantity'] ?? 0);
            $key = $variant['key'] ?? '';
            $color_hex = $variant['color'] ?? null;
            $size_label = $variant['size'] ?? null;
            
            if ($qty <= 0) continue;
            
            $stock = 0;
            $price_variant = 0;
            $variant_type = 'default';
            $found_variant = false;
            
            foreach ($variants as $v) {
                if ($v['variant_key'] === $key) {
                    $found_variant = true;
                    $stock = floatval($v['quantity']);
                    $price_variant = floatval($v['price']);
                    $variant_type = $v['variant_type'];
                    break;
                }
            }
            
            if (!$found_variant) {
                if ($useMainTable || $price_mode === 'uniform') {
                    $stock = $display_quantity;
                    $price_variant = $display_price;
                } elseif ($price_mode === 'by_color' && $color_hex) {
                    $stock = $color_stocks[$color_hex] ?? 0;
                    $price_variant = $color_prices[$color_hex] ?? $display_price;
                } elseif ($price_mode === 'by_size' && $size_label) {
                    $stock = $size_stocks[$size_label] ?? 0;
                    $price_variant = $size_prices[$size_label] ?? $display_price;
                } elseif ($price_mode === 'by_color_size' && $key) {
                    $stock = $variant_stocks[$key] ?? 0;
                    $price_variant = $variant_prices[$key] ?? $display_price;
                } else {
                    $stock = $display_quantity;
                    $price_variant = $display_price;
                }
            }
            
            if ($qty > $stock) {
                throw new Exception("Stock insuffisant pour la variante sélectionnée. Disponible: {$stock} unités.");
            }
            
            if ($discount_applies) {
                $unit_price = $price_variant * (1 - $discount_percent / 100);
                $has_discount_applied = true;
            } else {
                $unit_price = $price_variant;
            }
            
            $total_original_amount += $qty * $price_variant;
            $total_amount += $qty * $unit_price;
            
            $variant_details_array[] = [
                'key' => $key,
                'quantity' => $qty,
                'price' => $unit_price,
                'original_price' => $price_variant,
                'color' => $color_hex,
                'size' => $size_label,
                'variant_type' => $variant_type,
                'is_default' => ($variant_type === 'default')
            ];
            
            if ($color_hex) {
                $color_quantities[] = [
                    'hex' => $color_hex,
                    'size' => $size_label,
                    'quantity' => $qty
                ];
            }
        }
        
        $savings = $total_original_amount - $total_amount;
        
        $otp = random_int(100000, 999999);
        $buyer_id = $_SESSION['user_id'];
        $phone = trim($_POST['customer_phone'] ?? '');
        $name = trim($_POST['customer_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $region = trim($_POST['region'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');
        
        $color_quantities_json = json_encode($color_quantities);
        $variant_details_json = json_encode($variant_details_array);
        $variant_keys_json = json_encode($selected_variant_keys);
        $currency = $product['currency'] ?? 'USD';
        $discount_applied_int = $has_discount_applied ? 1 : 0;
        
        $main_variant_key = !empty($selected_variant_keys) ? $selected_variant_keys[0] : null;
        $selected_color = !empty($variant_details_array) && isset($variant_details_array[0]['color']) ? $variant_details_array[0]['color'] : null;
        $selected_size = !empty($variant_details_array) && isset($variant_details_array[0]['size']) ? $variant_details_array[0]['size'] : null;
        
        $variant_details_complete = [
            'variants' => $variant_details_array,
            'variant_keys' => $selected_variant_keys,
            'total_quantity' => $total_quantity,
            'total_amount' => $total_amount,
            'original_amount' => $total_original_amount,
            'discount_applied' => $has_discount_applied,
            'discount_percent' => $discount_percent,
            'savings' => $savings,
            'main_variant_key' => $main_variant_key,
            'selected_color' => $selected_color,
            'selected_size' => $selected_size
        ];
        
        $variant_details_json_complete = json_encode($variant_details_complete);
        
        $insert_sql = "
            INSERT INTO temp_orders (
                product_id, user_id, seller_id, customer_name, customer_phone,
                customer_address, region, payment_method, unit_value, unit_type,
                selected_size, selected_color,
                total_amount, original_amount, discount_applied, discount_percent,
                color_quantities, variant_details,
                otpvalidated, created_at, currency, status
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, NOW(), ?, 'unpaid'
            )
        ";
        
        $insert = $conn->prepare($insert_sql);
        
        if (!$insert) {
            throw new Exception("Erreur de préparation: " . $conn->error);
        }
        
        $insert->bind_param(
            "iiisssssdsssddidssis",
            $product_id,           
            $buyer_id,             
            $product['user_id'],   
            $name,                 
            $phone,                
            $address,              
            $region,               
            $payment_method,       
            $total_quantity,       
            $product['unit_type'], 
            $selected_size,        
            $selected_color,      
            $total_amount,         
            $total_original_amount,
            $discount_applied_int, 
            $discount_percent,     
            $color_quantities_json,
            $variant_details_json_complete,
            $otp,                  
            $currency              
        );
        
        if (!$insert->execute()) {
            throw new Exception("Erreur lors de l'insertion: " . $insert->error);
        }
        
        $order_id = $conn->insert_id;
        $insert->close();
        
        // Mise à jour du stock
        foreach ($selected_variants as $variant) {
            $qty = floatval($variant['quantity'] ?? 0);
            $key = $variant['key'] ?? '';
            if ($qty <= 0 || empty($key)) continue;
            
            $found_variant = false;
            $variant_type = 'default';
            foreach ($variants as $v) {
                if ($v['variant_key'] === $key) {
                    $found_variant = true;
                    $variant_type = $v['variant_type'];
                    break;
                }
            }
            
            if ($found_variant) {
                $update_stmt = $conn->prepare("UPDATE product_variants SET quantity = quantity - ? WHERE product_id = ? AND variant_key = ?");
                $update_stmt->bind_param("dis", $qty, $product_id, $key);
                $update_stmt->execute();
                $update_stmt->close();
                
                if ($variant_type === 'default') {
                    $update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $update_stmt->bind_param("di", $qty, $product_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
            } else {
                $update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $update_stmt->bind_param("di", $qty, $product_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
        
        $_SESSION['pending_order_id'] = $order_id;
        $_SESSION['pending_amount'] = $total_amount;
        $_SESSION['order_id'] = $order_id;
        $_SESSION['seller_id'] = $product['user_id'];
        $_SESSION['otp'] = $otp;
        $_SESSION['order_success'] = "Commande créée avec succès !";
        $_SESSION['savings'] = $savings;
        $_SESSION['total_original_amount'] = $total_original_amount;
        $_SESSION['variant_details'] = $variant_details_array;
        $_SESSION['selected_variant_keys'] = $selected_variant_keys;
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
    <link rel="icon" type="image/png" href="favicon.png">
            <title>Redirection vers paiement...</title>
        </head>
        <body onload="document.getElementById('payment-form').submit();">
            <form id="payment-form" method="POST" action="payment.php" style="display: none;">
                <input type="hidden" name="id" value="<?= htmlspecialchars($order_id) ?>">
            </form>
            <p>Redirection en cours vers la page de paiement...</p>
        </body>
        </html>
        <?php
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function parseMarkdown($text) {
    $text = preg_replace('/^#\s+(.+)$/m', '<h3 class="text-xl font-bold text-gray-900 mt-4 mb-2 border-b pb-1">$1</h3>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold text-gray-900">$1</strong>', $text);
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
        .swiper-slide { height: 300px; }
        @media (min-width: 768px) {
            .swiper-slide { height: 500px; }
        }
        .swiper-slide img, .swiper-slide video { 
            width: 100%; 
            height: 100%; 
            object-fit: contain; 
            background: #f9fafb;
        }
        .thumb-slide { height: 60px; cursor: pointer; opacity: 0.6; transition: opacity 0.3s; }
        @media (min-width: 768px) {
            .thumb-slide { height: 80px; }
        }
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
        .variant-checkbox {
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .variant-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .variant-checkbox .checkmark {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            background: white;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 2;
        }
        .variant-checkbox input:checked + .checkmark {
            background: #f97316;
            border-color: #f97316;
        }
        .variant-checkbox input:checked + .checkmark svg {
            display: block;
        }
        .variant-checkbox .checkmark svg {
            display: none;
            width: 14px;
            height: 14px;
            color: white;
        }
        .variant-item {
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }
        .variant-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .variant-item.selected {
            border-color: #f97316;
            box-shadow: 0 8px 25px rgba(249,115,22,0.2);
        }
        .variant-item.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .variant-item .stock-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }
        .variant-item .price-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(255,255,255,0.95);
            color: #1f2937;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .variant-item .color-name-badge {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            color: white;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            backdrop-filter: blur(4px);
            border-bottom-left-radius: 10px;
            border-bottom-right-radius: 10px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f9fafb;
            border-radius: 12px;
            padding: 4px;
            border: 1px solid #e5e7eb;
        }
        .quantity-control button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: white;
            color: #374151;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .quantity-control button:hover {
            background: #f97316;
            color: white;
        }
        .quantity-control button:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: #f3f4f6;
            color: #9ca3af;
        }
        .quantity-control input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 700;
            font-size: 16px;
            padding: 4px 0;
        }
        .quantity-control input:focus {
            outline: none;
        }
        .variant-grid {
            display: grid;
            gap: 12px;
        }
        .variant-grid.colors {
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        }
        .variant-grid.sizes {
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        }
        .variant-color-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 12px;
        }
        .variant-size-item {
            padding: 12px 16px;
            text-align: center;
            font-weight: 600;
            border-radius: 12px;
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .variant-size-item:hover {
            border-color: #f97316;
        }
        .variant-size-item.selected {
            border-color: #f97316;
            background: #fff7ed;
        }
        .variant-size-item.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        .selected-variants-summary {
            background: #f9fafb;
            border-radius: 16px;
            padding: 16px;
            border: 1px solid #e5e7eb;
        }
        .selected-variant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .selected-variant-item:last-child {
            border-bottom: none;
        }
        .discount-progress {
            height: 6px;
            border-radius: 3px;
            background: #e5e7eb;
            overflow: hidden;
            margin-top: 8px;
        }
        .discount-progress .progress-bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #f97316, #ea580c);
        }
        .size-section {
            background: white;
            border-radius: 12px;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
        }
        .size-section:last-child {
            margin-bottom: 0;
        }
        .size-section h4 {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .savings-box {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #6ee7b7;
            border-radius: 12px;
            padding: 10px 16px;
            margin-top: 8px;
        }
        .savings-text {
            color: #065f46;
            font-weight: 600;
        }
        .savings-amount {
            color: #047857;
            font-weight: 700;
        }
        .default-variant-section {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        .default-variant-section .default-price {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }
        .default-variant-section .default-stock {
            font-size: 14px;
            color: #64748b;
        }
    </style>
</head>
<body class="min-h-screen" x-data="productPage()" x-init="init()">
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

    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8 py-4 sm:py-6 md:py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 sm:mb-8 gap-3">
            <div class="flex items-center gap-2 sm:gap-4 overflow-x-auto">
                <a href="index.php" class="text-fuchsia-600 hover:text-fuchsia-500 flex-shrink-0">
                    <svg class="w-6 sm:w-8 h-6 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <nav class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                    <a href="index.php" class="hover:text-fuchsia-600">Accueil</a>
                    <span>/</span>
                    <a href="category.php" class="hover:text-fuchsia-600 truncate"><?= htmlspecialchars($product['category_name'] ?? 'Catégorie') ?></a>
                    <span>/</span>
                    <span class="text-gray-700 truncate"><?= htmlspecialchars(substr($product['name'], 0, 30)) ?></span>
                </nav>
            </div>
            <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500 flex-shrink-0">
                <span>Vendeur: <span class="font-semibold"><?= htmlspecialchars($product['seller_name'] ?? 'Inconnu') ?></span></span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 md:gap-10">
            <div class="space-y-3 sm:space-y-4">
                <div class="swiper main-swiper rounded-2xl sm:rounded-3xl overflow-hidden shadow-sm bg-gray-50 group">
                    <div class="swiper-wrapper">
                        <?php foreach ($main_media as $media): ?>
                            <div class="swiper-slide custom-video-container">
                                <?php if ($media['file_type'] === 'video'): ?>
                                    <div class="relative w-full h-[300px] sm:h-[400px] md:h-[500px] bg-black flex items-center justify-center">
                                        <video src="<?= htmlspecialchars($media['file_path']) ?>" 
                                            class="w-full h-full object-contain cursor-pointer main-video-player"
                                            playsinline
                                            onclick="this.paused ? this.play() : this.pause()">
                                        </video>
                                    </div>
                                <?php else: ?>
                                    <div class="w-full h-[300px] sm:h-[400px] md:h-[500px]">
                                        <img src="<?= htmlspecialchars($media['file_path']) ?>" 
                                            alt="Produit" 
                                            class="w-full h-full object-cover cursor-zoom-in"
                                            @click="openFullscreen('<?= htmlspecialchars($media['file_path']) ?>', '<?= $media['file_type'] ?>')">
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>

                <div class="swiper thumb-swiper px-1 sm:px-2">
                    <div class="swiper-wrapper">
                        <?php foreach ($main_media as $index => $media): ?>
                            <div class="swiper-slide thumb-slide rounded-xl overflow-hidden aspect-square bg-gray-100" 
                                 data-index="<?= $index ?>">
                                <?php if ($media['file_type'] === 'video'): ?>
                                    <div class="relative w-full h-full">
                                        <video src="<?= htmlspecialchars($media['file_path']) ?>#t=0.1" 
                                               class="w-full h-full object-cover"></video>
                                        <div class="absolute inset-0 bg-black/20 flex items-center justify-center">
                                            <div class="w-6 h-6 bg-white/90 rounded-full flex items-center justify-center shadow-sm">
                                                <svg class="w-3 h-3 text-gray-900 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
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
            </div>

            <div class="space-y-6 sm:space-y-8">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 mb-4">
                        <span class="text-xs sm:text-sm text-gray-500">Catégorie: <?= htmlspecialchars($product['category_name'] ?? 'Non spécifiée') ?></span>
                        <span class="px-2 sm:px-3 py-0.5 sm:py-1 rounded-full text-xs font-semibold w-fit <?= $product['product_condition'] === 'used' ? 'bg-fuchsia-100 text-fuchsia-700' : 'bg-emerald-100 text-emerald-700' ?>">
                            <?= $product['product_condition'] === 'used' ? 'Seconde main' : 'Neuf' ?>
                        </span>
                    </div>

                    <div class="mb-4 sm:mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                            <div>
                                <span class="text-2xl sm:text-3xl font-bold text-gray-900" x-text="formatPrice(unitPrice)">
                                    <?= number_format($discounted_price, 2) ?> 
                                    <span class="text-base sm:text-md"><?= $product['currency'] === 'CDF' ? 'FC' : '$' ?></span>
                                </span>
                                <span class="price-strike text-base sm:text-md text-gray-500 ml-2" x-show="hasDiscount && discountApplied" 
                                      x-text="formatPrice(originalPrice)">
                                    <?= number_format($original_price, 2) ?> <?= $product['currency'] === 'CDF' ? 'FC' : '$' ?>
                                </span>
                            </div>
                            <?php if ($has_discount): ?>
                                <span class="discount-badge px-2 sm:px-3 py-0.5 sm:py-1 rounded-full text-white font-bold text-xs sm:text-sm w-fit">
                                    -<?= $discount_percent ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3" x-show="hasDiscount">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Progression vers la réduction</span>
                                <span x-text="Math.min(100, (totalQuantity / discountThreshold * 100)).toFixed(0) + '%'"></span>
                            </div>
                            <div class="discount-progress">
                                <div class="progress-bar" :style="'width: ' + Math.min(100, (totalQuantity / discountThreshold * 100)) + '%'"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>0 <?= $product['unit_type'] ?></span>
                                <span x-text="discountThreshold + ' ' + unitType"></span>
                            </div>
                        </div>
                        
                        <div class="mt-2 text-sm text-emerald-600 font-medium" x-show="hasDiscount && discountApplied">
                            ✓ Réduction appliquée ! Économisez <?= $discount_percent ?>%
                        </div>
                        <div class="mt-2 text-sm text-fuchsia-600 font-medium" x-show="hasDiscount && !discountApplied && totalQuantity > 0">
                            Ajoutez encore <span x-text="(discountThreshold - totalQuantity).toFixed(2)"></span> <?= $product['unit_type'] ?>
                            pour bénéficier de <?= $discount_percent ?>% de réduction
                        </div>
                    </div>

                    <div class="mb-4 sm:mb-6 p-3 sm:p-4 bg-gray-50 rounded-lg sm:rounded-xl">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                            <div>
                                <span class="text-xs sm:text-sm text-gray-600">Total en stock:</span>
                                <span class="ml-2 font-semibold text-sm">
                                    <span><?= $display_quantity ?></span> <?= $product['unit_type'] ?>
                                </span>
                            </div>
                            <?php if ($min_order > 0): ?>
                                <div class="text-xs sm:text-sm text-gray-600">
                                    <span>Commande minimum: </span>
                                    <span class="font-semibold"><?= number_format($min_order, 2) ?> <?= $product['unit_type'] ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            <span>Total sélectionné: </span>
                            <span class="font-semibold text-gray-700" x-text="totalQuantity"></span> <?= $product['unit_type'] ?>
                        </div>
                    </div>

                    <div class="prose prose-gray max-w-none mb-6 sm:mb-8 text-xs sm:text-sm">
                        <?= parseMarkdown(htmlspecialchars($product['description'])) ?>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="p-3 sm:p-4 bg-red-50 border border-red-200 rounded-lg sm:rounded-xl text-red-700 text-xs sm:text-sm">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-4 sm:space-y-6" @submit.prevent="submitForm">
                    
                    <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Sélectionnez vos variantes</h3>
                        
                        <!-- SECTION POUR LES VARIANTES 'default' -->
                        <?php if ($default_variant_key !== null): ?>
                            <div class="default-variant-section mb-4">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="text-sm font-medium text-gray-500">Produit sans attributs (couleur/taille)</span>
                                    <div class="flex items-center gap-4">
                                        <span class="default-price"><?= number_format($base_price, 2) ?> <?= $product['currency'] === 'CDF' ? 'FC' : '$' ?></span>
                                        <span class="default-stock">Stock: <?= $base_stock ?> <?= $product['unit_type'] ?></span>
                                    </div>
                                    <button type="button" 
                                            @click="addDefaultVariant()"
                                            :disabled="defaultVariantAdded || <?= $base_stock <= 0 ? 'true' : 'false' ?>"
                                            class="mt-2 px-6 py-2 bg-fuchsia-600 text-white rounded-full text-sm font-semibold hover:bg-fuchsia-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        <span x-show="!defaultVariantAdded">Ajouter ce produit</span>
                                        <span x-show="defaultVariantAdded" class="text-emerald-400">✓ Ajouté</span>
                                    </button>
                                    <div class="text-xs text-gray-500 mt-1" x-show="defaultVariantAdded">
                                        Quantité: 
                                        <div class="quantity-control inline-flex ml-2">
                                            <button type="button" @click="decreaseDefaultVariant()" :disabled="defaultVariantQuantity <= 1">−</button>
                                            <span class="w-8 text-center font-bold" x-text="defaultVariantQuantity"></span>
                                            <button type="button" @click="increaseDefaultVariant()" :disabled="defaultVariantQuantity >= defaultVariantMaxStock">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($colors_with_images) && ($price_mode === 'by_color' || $price_mode === 'by_color_size' || $useMainTable)): ?>
                            <div class="mb-4">
                                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wider block mb-3">
                                    Couleurs disponibles
                                </label>
                                <div class="variant-grid colors">
                                    <?php if ($useMainTable): ?>
                                        <?php foreach ($colors_with_images as $color): ?>
                                            <label class="variant-checkbox variant-item rounded-xl overflow-hidden bg-gray-50"
                                                   :class="{'selected': isColorSelected('<?= $color['hex'] ?>'), 'disabled': <?= $fallbackStock > 0 ? 'false' : 'true' ?>}">
                                                <input type="checkbox" 
                                                       value="<?= $color['hex'] ?>"
                                                       :checked="isColorSelected('<?= $color['hex'] ?>')"
                                                       @change="toggleColor('<?= $color['hex'] ?>')"
                                                       <?= $fallbackStock > 0 ? '' : 'disabled' ?>>
                                                <div class="checkmark">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                                <div class="relative">
                                                    <?php if (isset($color['image_data']) && strpos($color['image_data'], 'data:image') === 0): ?>
                                                        <img src="<?= $color['image_data'] ?>" class="variant-color-image" alt="<?= htmlspecialchars($color['name']) ?>">
                                                    <?php elseif (isset($color['image_path']) && is_string($color['image_path'])): ?>
                                                        <img src="<?= htmlspecialchars($color['image_path']) ?>" class="variant-color-image" alt="<?= htmlspecialchars($color['name']) ?>">
                                                    <?php else: ?>
                                                        <div class="variant-color-image" style="background: <?= $color['hex'] ?>"></div>
                                                    <?php endif; ?>
                                                    <div class="stock-badge"><?= $fallbackStock ?> dispo</div>
                                                    <div class="price-badge" x-text="formatPrice(<?= $fallbackPrice ?>)"></div>
                                                    <div class="color-name-badge"><?= htmlspecialchars($color['name']) ?></div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <template x-for="color in colorsData" :key="color.hex">
                                            <label class="variant-checkbox variant-item rounded-xl overflow-hidden bg-gray-50"
                                                   :class="{'selected': isColorSelected(color.hex), 'disabled': getColorStock(color.hex) <= 0}">
                                                <input type="checkbox" 
                                                       :value="color.hex"
                                                       :checked="isColorSelected(color.hex)"
                                                       @change="toggleColor(color.hex)"
                                                       :disabled="getColorStock(color.hex) <= 0">
                                                <div class="checkmark">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>
                                                <div class="relative">
                                                    <template x-if="color.image_data">
                                                        <img :src="color.image_data" class="variant-color-image" :alt="color.name">
                                                    </template>
                                                    <template x-if="!color.image_data && color.image_path">
                                                        <img :src="color.image_path" class="variant-color-image" :alt="color.name">
                                                    </template>
                                                    <template x-if="!color.image_data && !color.image_path">
                                                        <div class="variant-color-image" :style="'background:' + color.hex"></div>
                                                    </template>
                                                    <div class="stock-badge" x-text="getColorStock(color.hex) + ' dispo'"></div>
                                                    <div class="price-badge" x-text="formatPrice(getColorPrice(color.hex))"></div>
                                                    <div class="color-name-badge" x-text="color.name"></div>
                                                </div>
                                            </label>
                                        </template>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div id="sizes-sections-container" x-show="selectedColors.length > 0 && (priceMode === 'by_color' || priceMode === 'by_color_size' || useMainTable)">
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wider block mb-3">
                                Tailles disponibles par couleur
                            </label>
                            <template x-for="color in selectedColors" :key="color">
                                <div class="size-section bg-gray-50/50 rounded-2xl p-4 border border-gray-200 mb-4">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full inline-block shadow-inner border border-gray-300" :style="'background: ' + color"></span>
                                        <span>Couleur : <span x-text="getColorName(color)" class="text-gray-800"></span></span>
                                    </h4>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                        <template x-for="size in getSizesForColor(color)" :key="size">
                                            <label class="variant-checkbox variant-item rounded-2xl p-3 bg-white border-2 border-gray-200 flex flex-col justify-between items-center cursor-pointer transition-all duration-300"
                                                   :class="{'selected border-fuchsia-500 bg-fuchsia-50/30 shadow-md': isSizeSelectedForColor(color, size), 'opacity-40 cursor-not-allowed': !isSizeAvailable(color, size)}">
                                                <input type="checkbox" 
                                                       class="sr-only"
                                                       :value="size"
                                                       @change="toggleSizeForColor(color, size)"
                                                       :checked="isSizeSelectedForColor(color, size)"
                                                       :disabled="!isSizeAvailable(color, size)">
                                                
                                                <div class="checkmark">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </div>

                                                <div class="text-base font-bold text-gray-900 mt-1" x-text="size"></div>

                                                <div class="text-xs font-semibold text-fuchsia-600 mt-2 bg-fuchsia-50 px-2 py-0.5 rounded-full" 
                                                     x-text="formatPrice(getSizePriceForColor(color, size))"></div>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <?php if (!empty($all_sizes) && $price_mode === 'by_size'): ?>
                            <div>
                                <label class="text-xs font-semibold text-gray-600 uppercase tracking-wider block mb-3">
                                    Tailles disponibles
                                </label>
                                <div class="variant-grid sizes">
                                    <template x-for="size in allSizes" :key="size">
                                        <div class="variant-size-item"
                                             :class="{'selected': isSizeSelected(size), 'disabled': getSizeStock(size) <= 0}"
                                             @click="toggleSize(size)"
                                             x-show="canSelectSize(size)">
                                            <div x-text="size"></div>
                                            <div class="text-xs text-gray-400 mt-1" x-text="getSizeStock(size) + ' dispo'"></div>
                                            <div class="text-xs font-bold text-fuchsia-600 mt-1" x-text="formatPrice(getSizePrice(size))"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($price_mode === 'by_color_size'): ?>
                            <div class="mt-4 p-3 bg-amber-50 rounded-xl border border-amber-200">
                                <p class="text-xs text-amber-700">
                                    💡 Sélectionnez d'abord une couleur, puis une taille pour chaque combinaison souhaitée.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 border border-gray-200 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-800 mb-3">Vos sélections</h3>
                        
                        <div class="selected-variants-summary" x-show="selectedVariants.length > 0">
                            <template x-for="(variant, index) in selectedVariants" :key="index">
                                <div class="selected-variant-item">
                                    <div>
                                        <span class="font-medium text-sm" x-text="variant.label"></span>
                                        <span class="text-xs text-gray-500 ml-2" x-text="formatPrice(variant.price) + '/unité'"></span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="quantity-control">
                                            <button type="button" @click="decreaseVariantQuantity(index)" :disabled="variant.quantity <= 1">−</button>
                                            <input type="number" x-model.number="variant.quantity" min="1" :max="variant.maxStock" 
                                                   @input="updateVariantQuantity(index, $event.target.value)">
                                            <button type="button" @click="increaseVariantQuantity(index)" :disabled="variant.quantity >= variant.maxStock">+</button>
                                        </div>
                                        <button type="button" @click="removeVariant(index)" class="text-red-400 hover:text-red-600 p-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex justify-between font-bold">
                                    <span>Total</span>
                                    <span x-text="totalQuantity + ' ' + unitType + ' = ' + formatPrice(totalAmount)"></span>
                                </div>
                                
                                <div x-show="hasDiscount && discountApplied" 
                                     class="mt-3 savings-box flex justify-between items-center">
                                    <span class="savings-text">
                                        <svg class="inline w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Économies réalisées
                                    </span>
                                    <span class="savings-amount text-lg">
                                        - <span x-text="formatPrice(savingsAmount)"></span>
                                    </span>
                                </div>
                                
                                <div x-show="hasDiscount && !discountApplied && totalQuantity > 0" 
                                     class="mt-2 pt-2 border-t border-amber-200 text-xs text-amber-600 flex items-center gap-2">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>
                                        Ajoutez encore <strong x-text="(discountThreshold - totalQuantity).toFixed(2)"></strong> 
                                        <span x-text="unitType"></span> pour économiser <strong x-text="discountPercent"></strong>%
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div x-show="selectedVariants.length === 0" class="text-center py-6 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <p class="text-sm">Aucune variante sélectionnée</p>
                            <p class="text-xs">Choisissez une couleur et/ou une taille ci-dessus</p>
                        </div>
                    </div>

                    <input type="hidden" name="selected_variants" :value="JSON.stringify(selectedVariants)">

                    <div class="space-y-4">
                        <label class="text-sm font-semibold text-gray-700">Région de livraison *</label>
                        <select name="region" required class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm">
                            <option value="">Sélectionnez votre région</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?= htmlspecialchars($region) ?>"><?= htmlspecialchars($region) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                        <h3 class="font-semibold text-gray-800">Vos coordonnées</h3>
                        <input type="text" name="customer_name" placeholder="Nom complet *" required
                               class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm" value="<?= $_SESSION['username'] ?? '' ?>">
                        <input type="tel" name="customer_phone" placeholder="Numéro de téléphone *" required
                               class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm">
                        <textarea name="address" placeholder="Adresse complète de livraison *" rows="3" required
                                  class="w-full rounded-xl border-gray-300 px-4 py-3 text-sm"></textarea>
                    </div>

                    <div class="flex items-center space-x-4 p-4 bg-white border border-gray-200 rounded-xl mb-6 select-none shadow-sm">
                        <div id="captcha-container" class="relative flex items-center justify-center w-7 h-7">
                            <div id="check-circle" class="w-full h-full border-2 border-gray-300 rounded-full cursor-pointer transition-all duration-300 hover:border-fuchsia-500"></div>
                            <svg id="captcha-spinner" class="hidden animate-spin absolute w-5 h-5 text-fuchsia-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <svg id="check-icon" class="hidden absolute w-5 h-5 text-white opacity-0 transition-opacity duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <label class="text-sm font-semibold text-gray-700 cursor-pointer">Je ne suis pas un robot</label>
                        <input type="hidden" name="human_token" id="human_token" value="">
                    </div>

                    <button type="submit" 
                            :disabled="isSubmitting || selectedVariants.length === 0 || totalQuantity < <?= $min_order ?> || totalQuantity > <?= $display_quantity ?> || totalQuantity <= 0"
                            :aria-busy="isSubmitting" aria-live="polite"
                            class="w-full py-4 bg-gray-900 text-white rounded-3xl font-semibold text-md hover:bg-fuchsia-600 transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed">
                        <template x-if="!isSubmitting">
                            <span>Procéder au paiement - <span x-text="formatPrice(totalAmount)"></span></span>
                        </template>
                        <template x-if="isSubmitting">
                            <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Validation en cours...</span>
                        </template>
                    </button>
                    
                    <div class="text-xs text-gray-500 text-center" x-show="selectedVariants.length > 0 && totalQuantity < <?= $min_order ?>">
                        ⚠️ Minimum de commande: <?= number_format($min_order, 2) ?> <?= $product['unit_type'] ?>
                    </div>
                </form>

                <?php if (!empty($specifications)): ?>
                    <div class="mt-8">
                        <h3 class="text-md font-semibold text-gray-900 mb-4">Caractéristiques techniques</h3>
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

                <?php if ($product['product_condition'] === 'used' && !empty($defects)): ?>
                    <div class="mt-8">
                        <h3 class="text-md font-semibold text-gray-900 mb-4">Défauts constatés</h3>
                        <div class="bg-fuchsia-50 rounded-2xl p-6 border border-fuchsia-200">
                            <ul class="space-y-2">
                                <?php foreach ($defects as $defect): ?>
                                    <li class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-fuchsia-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.282 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                        <span class="text-fuchsia-800"><?= htmlspecialchars($defect) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                                    <span class="text-fuchsia-600 font-bold"><?= number_format($sim['price'], 2) ?> $</span>
                                    <?php if ($sim['discount_percent'] > 0): ?>
                                        <span class="text-xs bg-fuchsia-100 text-fuchsia-700 px-2 py-0.5 rounded-full">-<?= $sim['discount_percent'] ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    const captchaContainer = document.getElementById('captcha-container');
    const checkCircle = document.getElementById('check-circle');
    const captchaSpinner = document.getElementById('captcha-spinner');
    const checkIcon = document.getElementById('check-icon');
    const humanToken = document.getElementById('human_token');

    let isVerified = false;

    if (captchaContainer) {
        captchaContainer.addEventListener('mousedown', function() {
            if (isVerified) return;

            checkCircle.classList.add('scale-0', 'opacity-0');
            captchaSpinner.classList.remove('hidden');

            setTimeout(() => {
                captchaSpinner.classList.add('hidden');
                checkCircle.classList.remove('scale-0', 'opacity-0', 'border-gray-300', 'hover:border-fuchsia-500');
                checkCircle.classList.add('bg-green-500', 'border-green-500', 'scale-110');
                checkIcon.classList.remove('hidden');
                setTimeout(() => {
                    checkIcon.classList.remove('opacity-0');
                    checkIcon.classList.add('opacity-100');
                }, 50);
                humanToken.value = 'mouse_verified';
                isVerified = true;
            }, 1200);
        });
    }

    // Map des noms de couleurs (résolus côté serveur, pas d'appel API côté client)
    const COLOR_NAMES_MAP = <?= json_encode($color_names_map) ?>;

    function productPage() {
        return {
            priceMode: '<?= $price_mode ?>',
            unitType: '<?= $product['unit_type'] ?>',
            currency: '<?= $product['currency'] === 'CDF' ? 'FC' : '$' ?>',
            originalPrice: <?= $original_price ?>,
            discountedPrice: <?= $discounted_price ?>,
            discountPercent: <?= $discount_percent ?>,
            discountThreshold: <?= $discount_threshold ?>,
            minOrder: <?= $min_order ?>,
            hasDiscount: <?= $has_discount ? 'true' : 'false' ?>,
            totalStockGlobal: <?= $display_quantity ?>,
            useMainTable: <?= $useMainTable ? 'true' : 'false' ?>,
            fallbackSizes: <?= json_encode($fallbackSizes) ?>,
            fallbackPrice: <?= $fallbackPrice ?>,
            fallbackStock: <?= $fallbackStock ?>,
            
            defaultVariantKey: '<?= $default_variant_key ?>',
            defaultVariantPrice: <?= $base_price ?? 0 ?>,
            defaultVariantStock: <?= $base_stock ?? 0 ?>,
            defaultVariantAdded: false,
            defaultVariantQuantity: 1,
            
            colorsData: <?= json_encode($colors_with_images) ?>,
            allSizes: <?= json_encode($all_sizes) ?>,
            colorPrices: <?= json_encode($color_prices) ?>,
            colorStocks: <?= json_encode($color_stocks) ?>,
            sizePrices: <?= json_encode($size_prices) ?>,
            sizeStocks: <?= json_encode($size_stocks) ?>,
            variantPrices: <?= json_encode($variant_prices) ?>,
            variantStocks: <?= json_encode($variant_stocks) ?>,
            
            selectedColors: [],
            selectedSizes: [],
            selectedVariants: [],
            selectedColorSizes: [],
            
            isSubmitting: false,
            fullscreenOpen: false,
            fullscreenMedia: { src: '', type: 'image' },
            
            get defaultVariantMaxStock() {
                return this.defaultVariantStock;
            },
            
            get totalQuantity() {
                return this.selectedVariants.reduce((sum, v) => sum + v.quantity, 0);
            },
            
            get totalAmount() {
                let total = 0;
                const totalQty = this.totalQuantity;
                const discountApplies = this.hasDiscount && totalQty >= this.discountThreshold;
                
                this.selectedVariants.forEach(v => {
                    let price = v.price;
                    if (discountApplies) {
                        price = v.price * (1 - this.discountPercent / 100);
                    }
                    total += v.quantity * price;
                });
                return total;
            },
            
            get savingsAmount() {
                if (!this.hasDiscount || !this.discountApplied) return 0;
                
                let originalTotal = 0;
                this.selectedVariants.forEach(v => {
                    if (v.originalPrice) {
                        originalTotal += v.quantity * v.originalPrice;
                    } else {
                        const originalPrice = v.price / (1 - this.discountPercent / 100);
                        originalTotal += v.quantity * originalPrice;
                    }
                });
                
                return originalTotal - this.totalAmount;
            },
            
            get unitPrice() {
                if (this.selectedVariants.length > 0) {
                    let price = this.selectedVariants[0].price;
                    if (this.hasDiscount && this.totalQuantity >= this.discountThreshold) {
                        price = price * (1 - this.discountPercent / 100);
                    }
                    return price;
                }
                return this.discountedPrice;
            },
            
            get discountApplied() {
                return this.hasDiscount && this.totalQuantity >= this.discountThreshold;
            },
            
            // Récupère le nom de la couleur depuis la map (résolue côté serveur)
            getColorName(hex) {
                if (!hex) return '';
                const cleanHex = hex.replace('#', '').toUpperCase();
                if (COLOR_NAMES_MAP[cleanHex]) return COLOR_NAMES_MAP[cleanHex];
                if (COLOR_NAMES_MAP[hex]) return COLOR_NAMES_MAP[hex];
                // Fallback : essayer avec # préfixé
                if (COLOR_NAMES_MAP['#' + cleanHex]) return COLOR_NAMES_MAP['#' + cleanHex];
                return hex;
            },
            
            addDefaultVariant() {
                if (this.defaultVariantAdded) return;
                if (this.defaultVariantStock <= 0) return;
                
                const exists = this.selectedVariants.some(v => v.key === this.defaultVariantKey);
                if (exists) return;
                
                this.selectedVariants.push({
                    key: this.defaultVariantKey,
                    label: 'Produit standard',
                    color: null,
                    size: null,
                    price: this.defaultVariantPrice,
                    originalPrice: this.defaultVariantPrice,
                    maxStock: this.defaultVariantStock,
                    quantity: 1,
                    isDefault: true
                });
                this.defaultVariantAdded = true;
                this.defaultVariantQuantity = 1;
            },
            
            increaseDefaultVariant() {
                if (this.defaultVariantQuantity < this.defaultVariantStock) {
                    this.defaultVariantQuantity++;
                    const variant = this.selectedVariants.find(v => v.key === this.defaultVariantKey);
                    if (variant) {
                        variant.quantity = this.defaultVariantQuantity;
                    }
                }
            },
            
            decreaseDefaultVariant() {
                if (this.defaultVariantQuantity > 1) {
                    this.defaultVariantQuantity--;
                    const variant = this.selectedVariants.find(v => v.key === this.defaultVariantKey);
                    if (variant) {
                        variant.quantity = this.defaultVariantQuantity;
                    }
                }
            },
            
            getSizesForColor(color) {
                if (this.useMainTable) {
                    return this.fallbackSizes;
                }
                return this.allSizes.filter(size => {
                    const key = color + '_' + size;
                    return (this.variantStocks[key] || 0) > 0;
                });
            },

            isSizeAvailable(color, size) {
                if (this.useMainTable) return true;
                const key = color + '_' + size;
                return (this.variantStocks[key] || 0) > 0;
            },

            getSizePriceForColor(color, size) {
                if (this.useMainTable) return this.fallbackPrice;
                const key = color + '_' + size;
                return this.variantPrices[key] || this.originalPrice;
            },
            
            isSizeSelectedForColor(color, size) {
                return this.selectedColorSizes.some(item => item.color === color && item.size === size);
            },
            
            toggleSizeForColor(color, size) {
                const index = this.selectedColorSizes.findIndex(item => item.color === color && item.size === size);
                if (index > -1) {
                    this.selectedColorSizes.splice(index, 1);
                    const key = color + '_' + size;
                    this.selectedVariants = this.selectedVariants.filter(v => v.key !== key);
                } else {
                    this.selectedColorSizes.push({ color, size });
                    const key = color + '_' + size;
                    const stock = this.variantStocks[key] || this.fallbackStock || 0;
                    if (stock > 0 || this.useMainTable) {
                        const price = this.variantPrices[key] || this.fallbackPrice || this.originalPrice;
                        this.selectedVariants.push({
                            key: key,
                            label: 'Couleur ' + this.getColorName(color) + ' - Taille ' + size,
                            color: color,
                            size: size,
                            price: price,
                            originalPrice: price,
                            maxStock: stock > 0 ? stock : this.fallbackStock,
                            quantity: 1
                        });
                    }
                }
                this.updateVariantQuantities();
            },
            
            isColorSelected(hex) {
                return this.selectedColors.includes(hex);
            },
            
            getColorPrice(hex) {
                if (this.useMainTable) return this.fallbackPrice;
                return this.colorPrices[hex] || this.originalPrice;
            },
            
            getColorStock(hex) {
                if (this.useMainTable) return this.fallbackStock;
                return this.colorStocks[hex] || 0;
            },
            
            toggleColor(hex) {
                if (this.getColorStock(hex) <= 0 && !this.useMainTable) return;
                
                const index = this.selectedColors.indexOf(hex);
                if (index > -1) {
                    this.selectedColors.splice(index, 1);
                    this.selectedVariants = this.selectedVariants.filter(v => v.color !== hex);
                    this.selectedColorSizes = this.selectedColorSizes.filter(item => item.color !== hex);
                } else {
                    this.selectedColors.push(hex);
                    if (this.useMainTable) {
                        const price = this.fallbackPrice;
                        this.selectedVariants.push({
                            key: hex,
                            label: 'Couleur ' + this.getColorName(hex),
                            color: hex,
                            size: null,
                            price: price,
                            originalPrice: price,
                            maxStock: this.fallbackStock,
                            quantity: 1
                        });
                    } else if (this.priceMode === 'by_color') {
                        this.addVariantsForSelection();
                    }
                }
                this.updateVariantQuantities();
            },
            
            isSizeSelected(size) {
                return this.selectedSizes.includes(size);
            },
            
            getSizePrice(size) {
                if (this.useMainTable) return this.fallbackPrice;
                return this.sizePrices[size] || this.originalPrice;
            },
            
            getSizeStock(size) {
                if (this.useMainTable) return this.fallbackStock;
                return this.sizeStocks[size] || 0;
            },
            
            canSelectSize(size) {
                if (this.useMainTable) return true;
                if (this.priceMode === 'by_size') {
                    return this.getSizeStock(size) > 0;
                }
                if (this.priceMode === 'by_color_size') {
                    return this.selectedColors.length > 0 && this.getSizeStock(size) > 0;
                }
                return true;
            },
            
            toggleSize(size) {
                if (!this.canSelectSize(size)) return;
                
                const index = this.selectedSizes.indexOf(size);
                if (index > -1) {
                    this.selectedSizes.splice(index, 1);
                    this.selectedVariants = this.selectedVariants.filter(v => v.size !== size);
                } else {
                    this.selectedSizes.push(size);
                    if (this.useMainTable) {
                        const price = this.fallbackPrice;
                        this.selectedVariants.push({
                            key: size,
                            label: 'Taille ' + size,
                            color: null,
                            size: size,
                            price: price,
                            originalPrice: price,
                            maxStock: this.fallbackStock,
                            quantity: 1
                        });
                    } else {
                        this.addVariantsForSelection();
                    }
                }
                this.updateVariantQuantities();
            },
            
            addVariantsForSelection() {
                if (this.priceMode === 'by_color') {
                    this.selectedColors.forEach(color => {
                        if (!this.selectedVariants.some(v => v.key === color)) {
                            const stock = this.getColorStock(color);
                            const price = this.getColorPrice(color);
                            this.selectedVariants.push({
                                key: color,
                                label: 'Couleur ' + this.getColorName(color),
                                color: color,
                                size: null,
                                price: price,
                                originalPrice: price,
                                maxStock: stock,
                                quantity: Math.min(1, stock)
                            });
                        }
                    });
                } else if (this.priceMode === 'by_size') {
                    this.selectedSizes.forEach(size => {
                        if (!this.selectedVariants.some(v => v.key === size)) {
                            const stock = this.getSizeStock(size);
                            const price = this.getSizePrice(size);
                            this.selectedVariants.push({
                                key: size,
                                label: 'Taille ' + size,
                                color: null,
                                size: size,
                                price: price,
                                originalPrice: price,
                                maxStock: stock,
                                quantity: Math.min(1, stock)
                            });
                        }
                    });
                } else if (this.priceMode === 'by_color_size') {
                    this.selectedColors.forEach(color => {
                        this.selectedSizes.forEach(size => {
                            const key = color + '_' + size;
                            if (!this.selectedVariants.some(v => v.key === key)) {
                                const stock = this.variantStocks[key] || 0;
                                if (stock > 0) {
                                    const price = this.variantPrices[key] || this.originalPrice;
                                    this.selectedVariants.push({
                                        key: key,
                                        label: 'Couleur ' + this.getColorName(color) + ' - Taille ' + size,
                                        color: color,
                                        size: size,
                                        price: price,
                                        originalPrice: price,
                                        maxStock: stock,
                                        quantity: Math.min(1, stock)
                                    });
                                }
                            }
                        });
                    });
                }
                this.updateVariantQuantities();
            },
            
            updateVariantQuantities() {
                this.selectedVariants.forEach(v => {
                    if (v.quantity > v.maxStock) {
                        v.quantity = v.maxStock;
                    }
                    if (v.quantity < 1) {
                        v.quantity = 1;
                    }
                });
            },
            
            increaseVariantQuantity(index) {
                const v = this.selectedVariants[index];
                if (v.quantity < v.maxStock) {
                    v.quantity++;
                    if (v.isDefault) {
                        this.defaultVariantQuantity = v.quantity;
                    }
                }
            },
            
            decreaseVariantQuantity(index) {
                const v = this.selectedVariants[index];
                if (v.quantity > 1) {
                    v.quantity--;
                    if (v.isDefault) {
                        this.defaultVariantQuantity = v.quantity;
                    }
                }
            },
            
            updateVariantQuantity(index, value) {
                const v = this.selectedVariants[index];
                let qty = parseInt(value) || 1;
                qty = Math.max(1, Math.min(qty, v.maxStock));
                v.quantity = qty;
                if (v.isDefault) {
                    this.defaultVariantQuantity = qty;
                }
            },
            
            removeVariant(index) {
                const removed = this.selectedVariants[index];
                this.selectedVariants.splice(index, 1);
                if (removed && removed.isDefault) {
                    this.defaultVariantAdded = false;
                    this.defaultVariantQuantity = 1;
                }
                if (this.selectedVariants.length === 0) {
                    this.selectedColors = [];
                    this.selectedSizes = [];
                    this.selectedColorSizes = [];
                } else if (removed && removed.color) {
                    const colorStillUsed = this.selectedVariants.some(v => v.color === removed.color);
                    if (!colorStillUsed) {
                        const colorIndex = this.selectedColors.indexOf(removed.color);
                        if (colorIndex > -1) {
                            this.selectedColors.splice(colorIndex, 1);
                        }
                        this.selectedColorSizes = this.selectedColorSizes.filter(item => item.color !== removed.color);
                    }
                }
            },
            
            formatPrice(amount) {
                return amount.toFixed(2) + ' ' + this.currency;
            },
            
            openFullscreen(src, type) {
                this.fullscreenMedia = { src, type };
                this.fullscreenOpen = true;
            },
            
            init() {
                this.initSwiper();
                ScrollReveal().reveal('.grid > div, .space-y-8 > div', { 
                    distance: '30px', 
                    origin: 'bottom', 
                    duration: 800, 
                    interval: 100 
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
            
            submitOrder() {
                if (this.selectedVariants.length === 0) {
                    showErrorMessage('Veuillez sélectionner au moins une variante.');
                    return false;
                }
                if (this.totalQuantity < this.minOrder) {
                    showErrorMessage('Minimum de commande: ' + this.minOrder + ' ' + this.unitType);
                    return false;
                }
                if (this.totalQuantity > this.totalStockGlobal) {
                    showErrorMessage('Quantité totale supérieure au stock disponible.');
                    return false;
                }
                this.isSubmitting = true;
                return true;
            },
            
            async submitForm() {
                const isValid = this.submitOrder();
                if (!isValid) {
                    this.isSubmitting = false;
                    return false;
                }
                
                const form = document.querySelector('form[method="POST"]');
                if (!form) {
                    showErrorMessage('Formulaire introuvable');
                    this.isSubmitting = false;
                    return;
                }
                
                try {
                    const formData = new FormData(form);
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.redirected) {
                        window.location.href = 'payment.php';
                    } else {
                        const text = await response.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(text, 'text/html');
                        const errorDiv = doc.querySelector('.bg-red-50');
                        if (errorDiv) {
                            throw new Error(errorDiv.textContent.trim());
                        }
                        showSuccessMessage('Commande créée avec succès !');
                        setTimeout(() => {
                            window.location.href = 'payment.php';
                        }, 2000);
                    }
                } catch (error) {
                    showErrorMessage(error.message || 'Une erreur est survenue');
                    this.isSubmitting = false;
                }
            }
        }
    }

    function showSuccessMessage(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'fixed top-4 right-4 z-50 bg-green-50 border border-green-200 rounded-xl p-4 text-green-700 text-sm shadow-md max-w-sm';
        alertDiv.innerHTML = `
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }

    function showErrorMessage(message) {
        const oldErrors = document.querySelectorAll('.error-alert');
        oldErrors.forEach(el => el.remove());
        const alertDiv = document.createElement('div');
        alertDiv.className = 'error-alert fixed top-4 right-4 z-50 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm shadow-md max-w-sm';
        alertDiv.innerHTML = `
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 5000);
    }
    </script>
</body>
</html>