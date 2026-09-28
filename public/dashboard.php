<?php
session_start();
require_once __DIR__ . '/../libs/db.php';
require_once __DIR__ . '/../libs/helpers/notification_helper.php';
require_once __DIR__ . '/../libs/helpers/refund_helper.php';

// Expiration automatique des commandes en attente > 2h
check_and_expire_pending_orders($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$banned = false;

// Récupération des informations utilisateur
$user_stmt = $conn->prepare("SELECT username, phone, email, address, role, status, created_at FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

if($user['status'] === 'inactive'){
    $banned = true;
    session_destroy();
}

// Extraire le prénom
$firstname = explode(' ', $user['username'])[0];
$phone = $user['phone'];
$email = $user['email'];
$address = $user['address'];
$role = $user['role'];
$joined_date = date('d/m/Y', strtotime($user['created_at']));

// Récupération photo de profil
require_once __DIR__ . '/../libs/user_helper.php';
$profilePic = getUserProfilePic($conn);

// Statistiques rapides
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE user_id = $user_id")->fetch_row()[0];
$total_orders_received = $conn->query("SELECT COUNT(*) FROM orders WHERE seller_id = $user_id")->fetch_row()[0];
$total_orders_delivered = $conn->query("SELECT COUNT(*) FROM orders WHERE seller_id = $user_id AND status = 'delivered'")->fetch_row()[0];
$total_orders_ordered = $conn->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id AND status = 'delivered'")->fetch_row()[0];
$total_orders_pending = $conn->query("SELECT COUNT(*) FROM temp_orders t JOIN products p ON t.product_id = p.id WHERE t.user_id = $user_id AND p.quantity > 0 AND t.status = 'unpaid'")->fetch_row()[0];
$total_sales = $conn->query("SELECT SUM(total_amount) FROM orders WHERE seller_id = $user_id AND status = 'delivered'")->fetch_row()[0] ?? 0;
$total_purchases = $conn->query("SELECT SUM(total_amount) FROM orders WHERE user_id = $user_id AND status = 'delivered'")->fetch_row()[0] ?? 0;
$unread_notifs_count = get_unread_notifications_count($conn, $user_id);
$b2c_alert = $b2c_alert ?? null;

// Récupérer les images des produits (première image pour chaque produit)
function getProductImage($product_id, $conn) {
    $stmt = $conn->prepare("SELECT file_path FROM product_media WHERE product_id = ? AND file_type = 'image' ORDER BY sort_order LIMIT 1");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $image = $result->fetch_assoc();
    $stmt->close();
    return $image ? $image['file_path'] : null;
}

// ==============================================
// FONCTION DE CONVERSION HEX -> NOM DE COULEUR VIA API
// (identique à buy_product.php)
// ==============================================
function getColorNameFromHex($hex) {
    $hex = strtoupper(ltrim($hex, '#'));
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return 'Couleur inconnue';
    }

    if (!isset($_SESSION['color_name_cache'])) {
        $_SESSION['color_name_cache'] = [];
    }
    if (isset($_SESSION['color_name_cache'][$hex])) {
        return $_SESSION['color_name_cache'][$hex];
    }

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
            if ($max === $r) $hue = 60 * fmod((($g - $b) / $delta), 6);
            elseif ($max === $g) $hue = 60 * ((($b - $r) / $delta) + 2);
            else $hue = 60 * ((($r - $g) / $delta) + 4);
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

    $_SESSION['color_name_cache'][$hex] = $colorName;
    return $colorName;
}

function getColorName($hex) {
    return getColorNameFromHex($hex);
}

// ==============================================
// Récupération des variantes d'une commande
// Gère le nouveau format {variants: [...]} et l'ancien
// ==============================================
function getOrderVariants($order_id, $conn, $table = 'orders') {
    $variants = [];

    $check_stmt = $conn->prepare("SHOW COLUMNS FROM $table LIKE 'variant_details'");
    $check_stmt->execute();
    $has_variant_details = $check_stmt->get_result()->num_rows > 0;
    $check_stmt->close();

    if ($has_variant_details) {
        $stmt = $conn->prepare("SELECT variant_details, selected_color, selected_size FROM $table WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row['variant_details'])) {
            $details = json_decode($row['variant_details'], true);

            if (is_array($details)) {
                if (isset($details['variants']) && is_array($details['variants'])) {
                    foreach ($details['variants'] as $v) {
                        $variants[] = [
                            'key' => $v['key'] ?? null,
                            'color' => $v['color'] ?? null,
                            'size' => $v['size'] ?? null,
                            'quantity' => $v['quantity'] ?? 1,
                            'price' => $v['price'] ?? 0,
                            'original_price' => $v['original_price'] ?? ($v['price'] ?? 0),
                            'variant_type' => $v['variant_type'] ?? 'default',
                            'is_default' => $v['is_default'] ?? false,
                        ];
                    }
                    return $variants;
                }

                if (isset($details[0]) && is_array($details[0])) {
                    return $details;
                }

                if (isset($details['color']) || isset($details['size']) || isset($details['quantity'])) {
                    return [$details];
                }
            }
        }

        if (!empty($row['selected_color']) || !empty($row['selected_size'])) {
            $variants[] = [
                'color' => $row['selected_color'] ?: null,
                'size' => $row['selected_size'] ?: null,
                'quantity' => 1,
                'price' => 0,
                'variant_type' => 'color_size',
            ];
        }
    }

    if (empty($variants)) {
        $stmt = $conn->prepare("
            SELECT pv.color, pv.size, pv.quantity, pv.price, pv.variant_key
            FROM product_variants pv
            JOIN orders o ON o.product_id = pv.product_id
            WHERE o.id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $variants[] = [
                'color' => $r['color'],
                'size' => $r['size'],
                'quantity' => $r['quantity'],
                'price' => $r['price'],
                'key' => $r['variant_key']
            ];
        }
        $stmt->close();
    }

    return $variants;
}

// ==============================================
// Affichage des variantes dans un tableau compact
// ==============================================
function renderOrderVariants($variants, $unit_type = 'pcs') {
    if (empty($variants)) {
        return '<span class="text-xs text-gray-400 italic">Aucune variante</span>';
    }

    $html = '<div class="space-y-1.5">';
    foreach ($variants as $variant) {
        $color_hex = $variant['color'] ?? null;
        $color_name = $color_hex ? getColorNameFromHex($color_hex) : null;
        $size = $variant['size'] ?? null;
        $quantity = isset($variant['quantity']) ? floatval($variant['quantity']) : 1;

        $html .= '<div class="flex items-center justify-between gap-2 text-xs bg-white px-2 py-1.5 rounded-lg border border-gray-100">';
        $html .= '<div class="flex items-center gap-2 flex-1 min-w-0">';

        if ($color_hex) {
            $html .= '<span class="w-3.5 h-3.5 rounded-full border border-gray-300 flex-shrink-0" '
                   . 'style="background:' . htmlspecialchars($color_hex) . '"></span>';
        }

        $parts = [];
        if ($color_name) $parts[] = '<span class="font-medium text-gray-800">' . htmlspecialchars($color_name) . '</span>';
        if ($size) $parts[] = '<span class="text-gray-600">Taille <strong>' . htmlspecialchars($size) . '</strong></span>';

        $html .= '<span class="truncate">' . implode(' · ', $parts) . '</span>';
        $html .= '</div>';
        $html .= '<span class="font-semibold text-fuchsia-700 flex-shrink-0">× ' . number_format($quantity, 0) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

// Onglet actif par défaut
$active_tab = $_GET['tab'] ?? 'dashboard';

// Récupération des données selon l'onglet actif
$products = [];
$pending_orders = [];
$completed_orders = [];
$completed_orders_delivered = [];
$purchases = [];
$cancelled_orders = [];
$cancelled_purchases = [];

if ($active_tab === 'products') {
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.price, p.currency, p.quantity, p.unit_type, p.created_at 
        FROM products p 
        WHERE p.user_id = ? 
        ORDER BY p.created_at DESC 
        LIMIT 20 
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $products_result = $stmt->get_result();
    $products = [];
    while ($row = $products_result->fetch_assoc()) {
        $row['image_url'] = getProductImage($row['id'], $conn);
        $products[] = $row;
    }
    $stmt->close();
} 
elseif ($active_tab === 'pending' || $active_tab === 'dashboard') {
    $stmt = $conn->prepare("
        SELECT t.id, t.customer_name, t.unit_value, t.unit_type, t.total_amount, 
               t.created_at, p.name as product_name, p.id as product_id
        FROM temp_orders t 
        JOIN products p ON t.product_id = p.id
        WHERE t.user_id = ? AND p.quantity >= t.unit_value AND status = 'unpaid'
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($pending_orders as &$order) {
        $order['variants'] = getOrderVariants($order['id'], $conn, 'temp_orders');
    }
    unset($order);
} 
elseif ($active_tab === 'completed' || $active_tab === 'dashboard') {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.temp_order_id AS tmp_id,
            o.customer_name,
            o.quantity,
            o.total_amount,
            o.created_at,
            o.status,
            p.name AS product_name,
            p.id AS product_id
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.seller_id = ?
          AND o.status IN ('pending', 'delivered')
        ORDER BY o.created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $completed_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($completed_orders as &$order) {
        $order['variants'] = getOrderVariants($order['id'], $conn, 'orders');
    }
    unset($order);
}

// ACHATS
if ($active_tab === 'purchases' || $active_tab === 'dashboard') {
    $stmt = $conn->prepare("
        SELECT 
            o.id,
            o.temp_order_id AS tmp_id,
            o.customer_name,
            o.quantity,
            o.total_amount,
            o.created_at,
            o.status,
            p.name AS product_name,
            p.id AS product_id,
            u.username AS seller_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.seller_id = u.id
        WHERE o.user_id = ?
          AND o.status IN ('pending', 'delivered')
        ORDER BY o.created_at DESC
        LIMIT 30
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($purchases as &$purchase) {
        $purchase['variants'] = getOrderVariants($purchase['id'], $conn, 'orders');
    }
    unset($purchase);

    // Récupérer les avis déjà déposés par cet acheteur
    $existing_reviews = [];
    $rev_stmt = $conn->prepare("SELECT order_id, rating, comment FROM seller_reviews WHERE buyer_id = ?");
    if ($rev_stmt) {
        $rev_stmt->bind_param("i", $user_id);
        $rev_stmt->execute();
        $rev_res = $rev_stmt->get_result();
        while ($r = $rev_res->fetch_assoc()) {
            $existing_reviews[$r['order_id']] = $r;
        }
        $rev_stmt->close();
    }
}

// Commandes livrées (pour la section des ventes finalisées)
$stmt = $conn->prepare("
    SELECT o.id, o.customer_name, o.quantity, o.total_amount, o.created_at, 
           o.status,
           p.name as product_name, p.id as product_id
    FROM orders o
    JOIN products p ON o.product_id = p.id
    WHERE o.seller_id = ? AND o.status = 'delivered'
    ORDER BY o.created_at DESC 
    LIMIT 30
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$completed_orders_delivered = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

foreach ($completed_orders_delivered as &$order) {
    $order['variants'] = getOrderVariants($order['id'], $conn, 'orders');
}
unset($order);

// Récupérer les commandes annulées (depuis order_cancellations)
$stmt = $conn->prepare("
    SELECT oc.id, oc.cancel_reason, oc.created_at, 
           u.username as cancelled_by, o.customer_name,
           p.name as product_name, p.id as product_id,
           o.id as order_id, o.unit_type
    FROM order_cancellations oc
    LEFT JOIN orders o ON oc.order_id = o.id
    LEFT JOIN users u ON oc.user_id = u.id
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.seller_id = ? 
    ORDER BY oc.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cancelled_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Récupérer les annulations d'achats
$stmt = $conn->prepare("
        SELECT ocr.id, ocr.cancel_reason AS reason, ocr.created_at, 
           u.username as seller_name, o.customer_name,
           p.name as product_name, p.id as product_id,
           o.id as order_id, o.unit_type
        FROM order_cancellations ocr
    LEFT JOIN orders o ON ocr.order_id = o.id
    LEFT JOIN users u ON o.seller_id = u.id
    LEFT JOIN products p ON o.product_id = p.id
        WHERE ocr.user_id = ? AND o.user_id = ocr.user_id
    ORDER BY ocr.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cancelled_purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Gestion des annulations POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_cancel_order'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $cancel_reason = trim($_POST['cancel_reason'] ?? '');

    if ($order_id && $cancel_reason) {
        $stmt = $conn->prepare("SELECT id, seller_id FROM orders WHERE id = ? AND seller_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($order) {
            $stmt = $conn->prepare("INSERT INTO order_cancellations (order_id, user_id, cancel_reason) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $order_id, $user_id, $cancel_reason);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();

            $success_message = "Commande annulée avec succès.";
        } else {
            $error_message = "Commande introuvable ou non autorisée.";
        }
    } else {
        $error_message = "Veuillez indiquer une raison d'annulation.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_cancel_purchase'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $cancel_assertion = trim($_POST['cancel_assertion'] ?? '');
    $cancel_reason_text = trim($_POST['cancel_reason'] ?? '');

    $assertion_map = [
        'je_n_en_ai_plus_besoin' => "Je n'en ai plus besoin",
        'produit_mauvais_etat' => "Le produit est en très mauvais état",
        'pas_livre_dans_le_temps' => "Je n'ai pas été livré dans les temps",
        'autre' => $cancel_reason_text ?: "Autre motif"
    ];

    if (empty($cancel_assertion)) {
        $error_message = "Veuillez obligatoirement sélectionner une raison avant de confirmer l'annulation.";
    } elseif ($cancel_assertion === 'autre' && empty($cancel_reason_text)) {
        $error_message = "Veuillez préciser la raison de votre annulation.";
    } else {
        $final_reason = $assertion_map[$cancel_assertion] ?? "Annulation par le client";

        if ($order_id) {
            $stmt = $conn->prepare("SELECT id, user_id, seller_id, customer_name, customer_phone, total_amount, currency FROM orders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $order_id, $user_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($order) {
                // Enregistrer le motif d'annulation
                $stmt = $conn->prepare("INSERT INTO order_cancellations (order_id, user_id, cancel_reason) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $order_id, $user_id, $final_reason);
                $stmt->execute();
                $stmt->close();

                // Impact sur la réputation / crédibilité du vendeur
                if ($cancel_assertion === 'produit_mauvais_etat' || $cancel_assertion === 'pas_livre_dans_le_temps') {
                    $penalty_points = 15;
                } else {
                    $penalty_points = 5;
                }

                // Exécuter directement et de manière synchrone le remboursement B2C intégral pour toutes les raisons
                $refund_res = process_b2c_refund($order_id, $conn, $final_reason, $penalty_points);

                // Notifier le vendeur
                $seller_id = intval($order['seller_id']);
                if ($seller_id) {
                    $seller_notif_title = "Commande #$order_id annulée par le client";
                    $seller_notif_msg = "Le client a annulé sa commande #$order_id. Motif : $final_reason. Votre score de crédibilité a été ajusté (-$penalty_points%).";
                    send_notification($conn, $seller_id, $seller_notif_title, $seller_notif_msg, "order_cancelled", "notifications.php");
                }

                $b2c_alert = $refund_res;
                if (!empty($refund_res['b2c_success'])) {
                    $success_message = "Remboursement direct effectué avec succès ! " . $refund_res['message'];
                } else {
                    $error_message = $refund_res['message'];
                }
            } else {
                $error_message = "Commande introuvable ou non autorisée.";
            }
        } else {
            $error_message = "Identifiant de commande non valide.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_cancellations'])) {
    $stmt = $conn->prepare("DELETE FROM order_cancellations WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    $success_message = "Boîte d'annulations vidée.";
}

// Préparer la map des noms de couleurs pour le JS
$color_names_map = $_SESSION['color_name_cache'] ?? [];
$all_orders_for_map = array_merge($pending_orders, $completed_orders, $purchases, $completed_orders_delivered);
foreach ($all_orders_for_map as $o) {
    if (!empty($o['variants'])) {
        foreach ($o['variants'] as $v) {
            if (!empty($v['color'])) {
                $hex = strtoupper(ltrim($v['color'], '#'));
                if (!isset($color_names_map[$hex])) {
                    $color_names_map[$hex] = getColorNameFromHex($v['color']);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #FDF2F0;
        }
        [x-cloak] { display: none !important; }
        .glass-header {
            background: linear-gradient(110deg, #E0C3FC 0%, #8EC5FC 100%);
            filter: blur(80px);
            opacity: 0.4;
        }
        .bg-custom-fuchsia { background-color: #FF5C01; }
        .text-custom-fuchsia { color: #FF5C01; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .line-clamp-1 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-animation {
            animation: fadeIn 0.2s ease-out;
        }
        
        dialog {
            border: none;
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 0;
            background: white;
            max-width: 90%;
            width: 500px;
        }
        
        dialog::backdrop {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            max-height: 80vh;
            overflow-y: auto;
        }

        .dashboard-loading {
            position: fixed;
            inset: 0 0 auto;
            height: 3px;
            z-index: 60;
            overflow: hidden;
            background: #fae8ff;
        }

        .dashboard-loading::after {
            content: '';
            display: block;
            width: 40%;
            height: 100%;
            background: #a21caf;
            animation: dashboard-progress 1s ease-in-out infinite;
        }

        @keyframes dashboard-progress {
            from { transform: translateX(-100%); }
            to { transform: translateX(350%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .dashboard-loading::after { animation: none; width: 100%; }
        }
        
        @media (max-width: 768px) {
            dialog {
                width: 100%;
                height: 100%;
                max-width: 100%;
                border-radius: 0;
                margin: 0;
            }
            
            .modal-content {
                max-height: 100vh;
            }
        }
        
        .status-badge-pending {
            background-color: #fef3c7;
            color: #d97706;
        }
        .status-badge-delivered {
            background-color: #d1fae5;
            color: #059669;
        }
        .status-badge-cancelled {
            background-color: #fee2e2;
            color: #dc2626;
        }
    </style>
</head>
<body class="min-h-screen" x-data="dashboardApp()">

    <!-- Map des noms de couleurs pour Alpine -->
    <script>
        window.COLOR_NAMES_MAP = <?= json_encode($color_names_map) ?>;
    </script>

    <!-- Navigation principale (desktop) -->
    <nav class="hidden md:flex items-center justify-between px-8 py-4 bg-white border-b sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <a href="index.php" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                <span>ecascadeur<span class="text-fuchsia-700">.com</span></span>
            </a>
        </div>
        <nav class="flex items-center gap-10 text-slate-500 font-semibold tracking-tight">
            <a href="index.php" class="transition-colors duration-300 hover:text-black">Accueil</a>
            <a href="catalog.php" class="transition-colors duration-300 hover:text-black">Catégories</a>
            <div class="relative flex flex-col items-center">
                <a href="dashboard.php" class="text-fuchsia-900">Dashboard</a>
                <span class="absolute -bottom-2 w-1.5 h-1.5 bg-fuchsia-900 rounded-full"></span>
            </div>
            <a href="notifications.php" class="relative transition-colors duration-300 hover:text-black flex items-center gap-1.5">
                <span>Notifications</span>
                <?php if ($unread_notifs_count > 0): ?>
                    <span class="px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-black rounded-full animate-pulse">
                        <?= $unread_notifs_count > 99 ? '99+' : $unread_notifs_count ?>
                    </span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="flex items-center gap-4">
            <a href="notifications.php" class="relative p-2 text-gray-600 hover:text-fuchsia-900 hover:bg-gray-100 rounded-xl transition" title="Vos notifications">
                <i class="bi bi-bell text-xl"></i>
                <?php if ($unread_notifs_count > 0): ?>
                    <span class="absolute 1 top-1 right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[10px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow-sm animate-pulse">
                        <?= $unread_notifs_count > 99 ? '99+' : $unread_notifs_count ?>
                    </span>
                <?php endif; ?>
            </a>
            <button @click="openSettingsModal()" class="bg-fuchsia-700 text-white px-6 py-2 rounded-xl font-medium hover:bg-fuchsia-900 transition">
                Paramètres
            </button>
            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profil" class="w-10 h-10 rounded-full border border-gray-200">
        </div>
    </nav>

    <!-- Notification Toast Banner dynamique en haut d'écran -->
    <div 
        x-cloak
        x-show="toast.show" 
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 -translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-4 scale-95"
        class="fixed top-4 right-4 z-50 max-w-sm w-full bg-white border border-gray-200 rounded-2xl shadow-2xl p-4 flex items-start gap-3"
    >
        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
             :class="toast.isWarning ? 'bg-amber-100 text-amber-600 animate-bounce' : 'bg-fuchsia-100 text-fuchsia-700'">
            <i class="bi text-lg" :class="toast.isWarning ? 'bi-exclamation-triangle-fill' : 'bi-bell-fill'"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-bold text-gray-900 line-clamp-1" x-text="toast.title"></h4>
            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2" x-text="toast.message"></p>
            <div class="mt-2 flex items-center gap-2">
                <a :href="toast.link || 'notifications.php'" class="text-[11px] font-bold text-fuchsia-700 hover:underline">
                    Ouvrir &rarr;
                </a>
                <button @click="toast.show = false" class="text-[11px] text-gray-400 hover:text-gray-600 ml-auto">
                    Fermer
                </button>
            </div>
        </div>
        <button @click="toast.show = false" class="text-gray-400 hover:text-gray-600 text-sm">
            <i class="bi bi-x"></i>
        </button>
    </div>

    <section>
    <?php if($banned): ?>
        <div class="max-w-3xl mx-auto mt-20 p-6 bg-red-50 border border-red-200 rounded-2xl text-center">
            <i class="bi bi-exclamation-triangle-fill text-red-500 text-4xl mb-4"></i>
            <h2 class="text-2xl font-bold text-red-700 mb-2">Compte Banni</h2>
            <p class="text-red-600 mb-4">Votre compte a été banni en raison de violations de nos conditions d'utilisation. Si vous pensez qu'il s'agit d'une erreur, veuillez contacter le support client.</p>
            <a href="mailto:support@ecascadeur.com" class="inline-block bg-red-600 text-white px-6 py-3 rounded-xl font-medium hover:bg-red-800 transition">
                Contacter le Support
            </a>
        </div>
    <?php else: ?>
    </section>

    <main class="max-w-6xl mx-auto px-4 py-6 md:py-8">

        <!-- Bannière B2C Refund Feedback Immédiate -->
        <?php if (!empty($b2c_alert)): ?>
            <div class="mb-6 p-4 sm:p-5 rounded-2xl border <?= !empty($b2c_alert['b2c_success']) ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-amber-50 border-amber-300 text-amber-900' ?> shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl <?= !empty($b2c_alert['b2c_success']) ? 'bg-emerald-600 text-white' : 'bg-amber-500 text-white' ?> flex items-center justify-center shrink-0 shadow-xs">
                        <i class="bi <?= !empty($b2c_alert['b2c_success']) ? 'bi-check2-circle' : 'bi-exclamation-diamond-fill' ?> text-2xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-extrabold text-sm sm:text-base <?= !empty($b2c_alert['b2c_success']) ? 'text-emerald-900' : 'text-amber-900' ?>">
                            <?= !empty($b2c_alert['b2c_success']) ? '🎉 Remboursement B2C Confirmé !' : '⚠️ Statut Remboursement B2C : ' . htmlspecialchars($b2c_alert['transaction_status'] ?? 'EN ATTENTE') ?>
                        </h4>
                        <p class="text-xs sm:text-sm mt-1 leading-relaxed">
                            <?= htmlspecialchars($b2c_alert['message'] ?? '') ?>
                        </p>
                        <div class="mt-2 text-[11px] font-mono <?= !empty($b2c_alert['b2c_success']) ? 'text-emerald-800' : 'text-amber-800' ?> flex flex-wrap gap-4 bg-white/60 p-2 rounded-lg border border-black/5">
                            <span><strong>Réf Transaction :</strong> <?= htmlspecialchars($b2c_alert['transaction_id'] ?? 'N/A') ?></span>
                            <span><strong>Statut B2C :</strong> <?= htmlspecialchars($b2c_alert['transaction_status'] ?? 'N/A') ?></span>
                            <span><strong>Code HTTP :</strong> <?= htmlspecialchars($b2c_alert['status_code'] ?? 'N/A') ?></span>
                            <span><strong>Opérateur :</strong> <?= htmlspecialchars($b2c_alert['provider'] ?? 'Mobile Money') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($success_message)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-base text-emerald-600"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
        <?php elseif (!empty($error_message)): ?>
            <div class="mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-xs font-bold flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-base text-red-600"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- En-tête profil mobile -->
        <div class="md:hidden mb-6 bg-gradient-to-r from-fuchsia-50 to-pink-50 rounded-3xl p-6 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-64 glass-header -z-10"></div>
            <div class="flex items-center justify-between mb-4">
                <button onclick="window.location.href = 'index.php'" class="bg-white/90 p-2 rounded-xl shadow-sm">
                    <i class="bi bi-arrow-left"> </i> Accueil
                </button>
                <div class="flex items-center gap-2">
                    <a href="notifications.php" class="bg-white/90 p-2 rounded-xl shadow-sm relative text-gray-700">
                        <i class="bi bi-bell text-base"></i>
                        <?php if ($unread_notifs_count > 0): ?>
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                                <?= $unread_notifs_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <button @click="openSettingsModal()" class="bg-white/90 p-2 rounded-xl shadow-sm">
                        <i class="bi bi-gear"></i>
                    </button>
                </div>
            </div>
            <div class="flex items-end gap-4">
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($firstname) ?>" 
                     class="w-20 h-20 rounded-full border-4 border-white object-cover shadow-md">
                <div class="flex-1 pb-2">
                    <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($firstname) ?></h1>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($phone) ?></p>
                </div>
                <button @click="openSettingsModal()" class="border border-fuchsia-700 text-fuchsia-700 px-4 py-1.5 rounded-xl text-sm font-medium hover:bg-fuchsia-50">
                    Modifier
                </button>
            </div>
            
            <div class="flex gap-8 mt-6">
                <div class="text-center">
                    <span class="block font-bold"><?= $total_products ?></span>
                    <span class="text-xs text-gray-500">Produits</span>
                </div>
                <div class="text-center">
                    <span class="block font-bold"><?= $total_orders_delivered ?></span>
                    <span class="text-xs text-gray-500">Ventes</span>
                </div>
                <div class="text-center">
                    <span class="block font-bold text-fuchsia-900"><?= $total_orders_pending ?></span>
                    <span class="text-xs text-gray-500">En attente</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 md:hidden">
            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Fidélité Cascade</p>
                        <h3 class="text-xl font-bold text-gray-800">Programme Achat</h3>
                    </div>
                    <div class="bg-fuchsia-100 p-3 rounded-2xl text-fuchsia-900">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="text-4xl font-black text-gray-900"><?php echo number_format($total_orders_ordered * 3.7, 1, '.', '') ?></span>
                    <span class="text-gray-400 font-medium">/ 100 pts</span>
                </div>
                <div class="space-y-3">
                    <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                        <div class="bg-fuchsia-700 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_ordered, 100) * 3.7 ?>%"></div>
                    </div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        Plus que <span class="font-bold text-fuchsia-900"><?php echo number_format(100 - ($total_orders_ordered * 3.7), 1, '.', '') ?> points</span> pour obtenir un <span class="font-bold">achat gratuit (20%)</span> sur vos prochaines ventes.
                    </p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Fidélité Cascade</p>
                        <h3 class="text-xl font-bold text-gray-800">Programme Vente</h3>
                    </div>
                    <div class="bg-teal-100 p-3 rounded-2xl text-teal-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 mb-4">
                    <span class="text-4xl font-black text-gray-900"><?php echo number_format($total_orders_delivered * 3.7, 1, '.', '')?></span>
                    <span class="text-gray-400 font-medium">/ 100 pts</span>
                </div>
                <div class="space-y-3">
                    <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                        <div class="bg-teal-500 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_delivered * 3.7, 100) ?>%"></div>
                    </div>
                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        Objectif 100 : Soyez rémunéré à hauteur de <span class="font-bold text-teal-600">20% du montant total</span> des produits vendus.
                    </p>
                </div>
            </div>
        </div>

        <!-- En-tête profil desktop -->
        <div class="hidden md:block bg-white rounded-[40px] shadow-sm overflow-hidden relative mb-8">
            <div class="absolute top-0 left-0 right-0 h-64 glass-header -z-10"></div>
            <div class="relative">
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($firstname) ?>" 
                     class="w-40 h-40 rounded-full m-auto object-cover border-8 border-white shadow-lg">
            </div>
            <div class="px-12 pt-12 pb-8 flex items-end gap-8">
                <div class="flex-1 pb-4">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($firstname) ?></h1>
                        <span class="bg-fuchsia-100 text-fuchsia-900 px-3 py-1 rounded-lg text-sm font-bold"><?= ucfirst($role) ?></span>
                    </div>
                    <p class="text-gray-500 mt-2 text-sm"><?= htmlspecialchars($phone) ?></p>
                    <p class="text-gray-500 mt-2 text-sm"><?= htmlspecialchars($email) ?></p>
                    <div class="flex gap-3 mt-6">
                        <button @click="openSettingsModal()" class="bg-black text-white px-8 py-3 rounded-2xl font-semibold hover:bg-gray-800 transition">Paramètres</button>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4">
                    <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Fidélité Cascade</p>
                                <h3 class="text-xl font-bold text-gray-800">Programme Achat</h3>
                            </div>
                            <div class="bg-fuchsia-100 p-3 rounded-2xl text-fuchsia-900">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-4">
                            <span class="text-4xl font-black text-gray-900"><?= $total_orders_ordered * 3.7 ?></span>
                            <span class="text-gray-400 font-medium">/ 100 pts</span>
                        </div>
                        <div class="space-y-3">
                            <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                                <div class="bg-fuchsia-700 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_ordered, 100) * 3.7 ?>%"></div>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed">
                                Plus que <span class="font-bold text-fuchsia-900"><?php echo 100 - min($total_orders_ordered, 100) * 3.7 ?> points</span> pour obtenir un <span class="font-bold">achat gratuit (20%)</span> sur vos prochaines ventes.
                            </p>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <p class="text-gray-500 text-xs font-bold uppercase tracking-wider mb-1">Fidélité Cascade</p>
                                <h3 class="text-xl font-bold text-gray-800">Programme Vente</h3>
                            </div>
                            <div class="bg-teal-100 p-3 rounded-2xl text-teal-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2 mb-4">
                            <span class="text-4xl font-black text-gray-900"><?= $total_orders_delivered * 3.7 ?></span>
                            <span class="text-gray-400 font-medium">/ 100 pts</span>
                        </div>
                        <div class="space-y-3">
                            <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                                <div class="bg-teal-500 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_delivered, 100) * 3.7 ?>%"></div>
                            </div>
                            <p class="text-[11px] text-gray-500 leading-relaxed">
                                Objectif 100 : Soyez rémunéré à hauteur de <span class="font-bold text-teal-600">20% du montant total</span> des produits vendus.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-4 rounded-3xl shadow-sm relative">
                <div class="bg-yellow-100 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-box-seam text-yellow-600"></i>
                </div>
                <div class="text-2xl font-bold"><?= $total_products ?></div>
                <div class="text-xs text-gray-400">Produits</div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm relative">
                <div class="bg-fuchsia-100 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-clock-history text-fuchsia-900"></i>
                </div>
                <div class="text-2xl font-bold text-fuchsia-900"><?= $total_orders_pending ?></div>
                <div class="text-xs text-gray-400">Mes achats en attente</div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm">
                <div class="bg-emerald-50 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-bag-check text-emerald-600"></i>
                </div>
                <div class="text-2xl font-bold text-emerald-600"><?= $total_orders_ordered ?> / <?= number_format($total_purchases, 0) ?> $</div>
                <div class="text-xs text-gray-400">Total Achats</div>
            </div>
            <div class="bg-white p-4 rounded-3xl shadow-sm">
                <div class="bg-blue-50 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-cash-stack text-blue-600"></i>
                </div>
                <div class="text-2xl font-bold text-emerald-600"><?= $total_orders_delivered ?> / <?= number_format($total_sales, 0) ?> $</div>
                <div class="text-xs text-gray-400">Total ventes</div>
            </div>
        </div>

        <!-- Navigation par onglets -->
        <div class="flex overflow-x-auto gap-2 pb-3 mb-6 border-b border-gray-200 hide-scrollbar">
            <a href="?tab=dashboard" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'dashboard' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-speedometer2 mr-2"></i>Dashboard
            </a>
            <a href="?tab=products" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'products' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-box-seam mr-2"></i>Mes produits
            </a>
            <a href="?tab=pending" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'pending' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-clock-history mr-2"></i>En attente
            </a>
            <a href="?tab=completed" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'completed' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-bag-check mr-2"></i>Ventes
            </a>
            <a href="?tab=purchases" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'purchases' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-cart-check mr-2"></i>Achats
            </a>
            <a href="?tab=cancellations" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors
                <?= $active_tab === 'cancellations' ? 'bg-fuchsia-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="bi bi-bag-x mr-2"></i>Annulations
            </a>
            <a href="notifications.php" class="px-5 py-2.5 rounded-lg text-sm font-medium whitespace-nowrap transition-colors bg-gray-100 text-gray-700 hover:bg-gray-200 flex items-center gap-1.5">
                <i class="bi bi-bell mr-1"></i>Notifications
                <?php if ($unread_notifs_count > 0): ?>
                    <span class="px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-black rounded-full"><?= $unread_notifs_count ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Contenu selon onglet -->

        <!-- Dashboard principal -->
        <div x-show="'dashboard' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Commandes en attente -->
                <div class="bg-white rounded-3xl p-6 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                     @click="openPendingModal()">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg mb-1">Commandes en attente</h3>
                            <p class="text-gray-500 text-sm">Paiements à finaliser</p>
                        </div>
                        <span class="bg-fuchsia-100 text-fuchsia-900 px-3 py-1 rounded-lg text-sm font-medium">
                            <?= count($pending_orders) ?>
                        </span>
                    </div>
                    <?php if (empty($pending_orders)): ?>
                        <div class="text-center py-8">
                            <i class="bi bi-clock-history text-5xl text-gray-300 mb-4 block"></i>
                            <p class="text-gray-600">Aucune commande en attente</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($pending_orders, 0, 3) as $order): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($order['customer_name']) ?></p>
                                    <?php if (!empty($order['variants'])): ?>
                                        <div class="mt-1">
                                            <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="font-bold text-fuchsia-900 ml-2"><?= number_format($order['total_amount'], 0) ?> $</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pending_orders)): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="?tab=pending" class="text-fuchsia-900 text-sm font-medium cursor-pointer flex items-center gap-1">
                            Voir tout <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Ventes finalisées -->
                <div class="bg-white rounded-3xl p-6 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                       @click="openCompletedModal()">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg mb-1">Ventes finalisées</h3>
                            <p class="text-gray-500 text-sm">Historique récent</p>
                        </div>
                        <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-sm font-medium">
                            <?= count($completed_orders_delivered) ?>
                        </span>
                    </div>
                    <?php if (empty($completed_orders_delivered)): ?>
                        <div class="text-center py-8">
                            <i class="bi bi-bag-check text-5xl text-gray-300 mb-4 block"></i>
                            <p class="text-gray-600">Aucune vente finalisée</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($completed_orders_delivered, 0, 3) as $order): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></p>
                                    <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                    <?php if (!empty($order['variants'])): ?>
                                        <div class="mt-1">
                                            <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="font-bold text-emerald-600 ml-2"><?= number_format($order['total_amount'], 0) ?> $</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($completed_orders_delivered)): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <a href="?tab=completed" class="text-fuchsia-900 text-sm font-medium cursor-pointer flex items-center gap-1">
                            Voir tout <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Annulations -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                     @click="openCancelledModal()">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg mb-1">Commandes annulées</h3>
                            <p class="text-gray-500 text-sm">Toutes les annulations</p>
                        </div>
                        <?php if (!empty($cancelled_orders)): ?>
                        <span class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-sm font-medium">
                            <?= count($cancelled_orders) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($cancelled_orders)): ?>
                        <div class="text-center py-8">
                            <i class="bi bi-bag-x text-5xl text-gray-300 mb-4 block"></i>
                            <p class="text-gray-600">Aucune commande annulée</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($cancelled_orders, 0, 2) as $c): ?>
                            <div class="p-3 bg-gray-50 rounded-xl">
                                <p class="font-medium text-sm line-clamp-1"><?= htmlspecialchars($c['product_name'] ?? 'Produit') ?></p>
                                <p class="text-xs text-gray-500 line-clamp-1 mt-1">
                                    <?= htmlspecialchars($c['cancelled_by']) ?> - <?= htmlspecialchars($c['cancel_reason']) ?>
                                </p>
                                <?php 
                                $cancelled_variants = getOrderVariants($c['order_id'] ?? 0, $conn, 'orders');
                                if (!empty($cancelled_variants)): 
                                ?>
                                    <div class="mt-2">
                                        <?= renderOrderVariants($cancelled_variants, $c['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ajouter un produit -->
                <div class="bg-white rounded-3xl p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-fuchsia-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="bi bi-plus-lg text-fuchsia-900 text-2xl"></i>
                        </div>
                        <h3 class="font-bold text-lg mb-2">Ajouter un produit</h3>
                        <p class="text-gray-500 text-sm mb-6">Commencez à vendre vos produits en ligne</p>
                        <a href="add_product.php" class="inline-block bg-fuchsia-700 text-white px-6 py-3 rounded-xl text-sm font-medium hover:bg-fuchsia-900 transition">
                            + Nouveau produit
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Mes produits -->
        <div x-show="'products' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Mes produits (<?= count($products) ?>)</h2>
                <a href="add_product.php" class="bg-fuchsia-700 text-white px-6 py-3 rounded-xl text-sm font-medium hover:bg-fuchsia-900 transition flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Ajouter un produit
                </a>
            </div>

            <?php if (empty($products)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-box-seam text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Vous n'avez pas encore ajouté de produit</p>
                    <a href="add_product.php" class="text-fuchsia-900 font-medium hover:underline flex items-center justify-center gap-2 text-lg">
                        Commencer maintenant <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($products as $p): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:border-fuchsia-200 transition-all group shadow-sm hover:shadow-md">
                            <div class="aspect-square bg-gray-50 relative overflow-hidden">
                                <?php if (!empty($p['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($p['name']) ?>"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <?php else: ?>
                                    <div class="absolute inset-0 flex items-center justify-center text-gray-300">
                                        <i class="bi bi-image text-5xl"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <h3 class="font-semibold text-gray-900 mb-2 line-clamp-1"><?= htmlspecialchars($p['name']) ?></h3>
                                <div class="flex items-baseline gap-1.5 mb-3">
                                    <span class="text-xl font-bold text-fuchsia-900"><?= number_format($p['price'], 0) ?></span>
                                    <span class="text-sm text-fuchsia-900"><?= $p['currency'] ?></span>
                                </div>
                                <div class="text-xs text-gray-500 mb-4">
                                    <span class="inline-block px-2 py-1 bg-gray-100 rounded-md"><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit_type']) ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="modify_product.php?id=<?= $p['id'] ?>" 
                                       class="flex-1 text-center py-2.5 border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition">
                                        <i class="bi bi-pencil mr-1"></i> Modifier
                                    </a>
                                    <button @click="openDeleteDialog(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')" 
                                            class="flex-1 text-center py-2.5 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50 transition">
                                        <i class="bi bi-trash mr-1"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Commandes en attente -->
        <div x-show="'pending' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <h2 class="text-xl font-bold text-gray-900">Commandes en attente (<?= count($pending_orders) ?>)</h2>
            <h3 class="text-xs font-bold text-orange-600">Ce produit n'est pas réservé. En cas d'achat du produit par un autre client avant la validation de votre commande, celui-ci sera automatiquement retiré de vos commandes en attente.</h3>
            
            <?php if (empty($pending_orders)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-clock-history text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Aucune commande en attente de paiement</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="bg-fuchsia-100 text-fuchsia-900 px-3 py-1 rounded-lg text-sm font-medium">
                                    En attente
                                </span>
                            </div>
                            
                            <?php if (!empty($order['variants'])): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                                    <p class="text-xs font-semibold text-gray-600 mb-2">Détails de la commande :</p>
                                    <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <p class="text-xs text-gray-500">Quantité</p>
                                    <p class="font-medium"><?= $order['unit_value'] ?> <?= htmlspecialchars($order['unit_type']) ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Montant</p>
                                    <p class="font-bold text-fuchsia-900"><?= number_format($order['total_amount'], 0) ?> $</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <a href="payment.php?order_id=<?= $order['id'] ?>" 
                                   class="flex-1 bg-gray-800 text-white py-3 rounded-xl text-center text-sm font-medium hover:bg-fuchsia-900 transition">
                                    Finaliser le paiement
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Ventes finalisées -->
        <div x-show="'completed' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <h2 class="text-xl font-bold text-gray-900">Ventes en attente de livraison (<?= count(array_filter($completed_orders, function($o) { return $o['status'] === 'pending'; })) ?>)</h2>
            
            <?php 
            $pending_sales = array_filter($completed_orders, function($o) { return $o['status'] === 'pending'; });
            if (empty($pending_sales)): 
            ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-truck text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Aucune vente en attente de livraison pour le moment</p>
                </div>
            <?php else: ?>
                <h3 class="text-xs font-bold text-orange-600">Veuillez procéder à la livraison</h3>
                <?php if(isset($success_message)): ?>
                    <div class="bg-green-100 text-green-700 p-3 rounded-lg mb-4">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php elseif(isset($error_message)): ?>
                    <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($pending_sales as $order): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="status-badge-pending px-3 py-1 rounded-lg text-sm font-medium">
                                    En attente de livraison
                                </span>
                            </div>
                            
                            <?php if (!empty($order['variants'])): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                        <i class="bi bi-grid-3x3-gap-fill"></i> Détail des articles commandés :
                                    </p>
                                    <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Montant</p>
                                    <p class="font-bold text-emerald-600"><?= number_format($order['total_amount'], 0) ?> $</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="flex gap-3 mt-4">
                                <button
                                    @click="openModal = 'cancelOrder' ; deleteProductId=<?= $order['id'] ?>"
                                    class="flex-1 bg-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-red-300 transition">
                                    Annuler <span class="hidden sm:inline"> la commande </span>
                                </button>
                                <a href="seller_qr.php?id=<?= $order['id'] ?>" class="flex-1 bg-fuchsia-800 text-center text-white py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition"> valider <span class="hidden sm:inline"> la commande </span> </a>
                            </div>
                            <button @click="loadOrderDetails(<?= $order['tmp_id'] ?>)" 
                                    class="mt-4 w-full bg-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition">
                                <i class="bi bi-info-circle mr-1"></i> Détail de la commande
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 class="text-xl font-bold text-gray-900">Ventes finalisées et livrées (<?= count($completed_orders_delivered) ?>)</h2>

            <?php if (empty($completed_orders_delivered)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-bag-check text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Vous n'avez effectué aucune livraison pour le moment</p>
                </div>
            <?php else: ?>
                <h3 class="text-sm font-bold text-green-600">Cascade vous félicite</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($completed_orders_delivered as $order): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="status-badge-delivered px-3 py-1 rounded-lg text-sm font-medium">
                                    Finalisée et livrée
                                </span>
                            </div>
                            
                            <?php if (!empty($order['variants'])): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-green-500"></i> Articles livrés :
                                    </p>
                                    <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Montant</p>
                                    <p class="font-bold text-emerald-600"><?= number_format($order['total_amount'], 0) ?> $</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 4. Achats finalisés -->
        <div x-show="'purchases' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <h2 class="text-xl font-bold text-gray-900">Mes achats en attente (<?= count(array_filter($purchases, function($o) { return $o['status'] === 'pending'; })) ?>)</h2>

            <?php 
            $pending_purchases = array_filter($purchases, function($o) { return $o['status'] === 'pending'; });
            if (empty($pending_purchases)): 
            ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-cart-check text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Vous n'avez effectué aucun achat en attente</p>
                    <a href="catalog.php" class="text-fuchsia-900 font-medium hover:underline flex items-center justify-center gap-2 text-lg">
                        Parcourir les produits <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($pending_purchases as $purchase): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($purchase['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($purchase['seller_name']) ?></p>
                                </div>
                                <span class="status-badge-pending px-3 py-1 rounded-lg text-sm font-medium">
                                    En attente de livraison
                                </span>
                            </div>
                            
                            <?php if (!empty($purchase['variants'])): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                        <i class="bi bi-cart-check"></i> Détail de votre commande :
                                    </p>
                                    <?= renderOrderVariants($purchase['variants'], $purchase['unit_type'] ?? 'pcs') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <p class="text-xs text-gray-500">Montant</p>
                                    <p class="font-bold text-blue-600"><?= number_format($purchase['total_amount'], 0) ?> $</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Quantité</p>
                                    <p class="font-medium"><?= $purchase['quantity'] ?></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?></p>
                                </div>
                            </div>
                            
                            <div class="flex gap-3">
                                <button 
                                    @click="openCancelPurchaseModal(<?= $purchase['id'] ?>)"
                                    class="mt-4 w-full bg-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-red-300 transition">
                                    Annuler <span class="hidden sm:inline"> la commande </span> 
                                </button>
                                <a href="scan_qr.php?id=<?= $purchase['id'] ?>" class="mt-4 w-full bg-fuchsia-800 text-center text-white py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition"> valider <span class="hidden sm:inline"> la commande </span> </a>
                            </div>
                            <button @click="loadOrderDetails(<?= $purchase['tmp_id'] ?>)" 
                                    class="mt-4 w-full bg-gray-200 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition">
                                <i class="bi bi-info-circle mr-1"></i> Détail de la commande
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h2 class="text-xl font-bold text-gray-900">Mes achats livrés (<?= count(array_filter($purchases, function($o) { return $o['status'] === 'delivered'; })) ?>)</h2>
            
            <?php 
            $delivered_purchases = array_filter($purchases, function($o) { return $o['status'] === 'delivered'; });
            if (empty($delivered_purchases)): 
            ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-box-seam text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Aucun achat livré pour le moment</p>
                </div>
            <?php else: ?>
                <h3 class="text-sm font-bold text-green-600">Cascade vous félicite</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($delivered_purchases as $purchase): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($purchase['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($purchase['seller_name']) ?></p>
                                </div>
                                <span class="status-badge-delivered px-3 py-1 rounded-lg text-sm font-medium">
                                    Livrée
                                </span>
                            </div>
                            
                            <?php if (!empty($purchase['variants'])): ?>
                                <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                    <p class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-green-500"></i> Articles reçus :
                                    </p>
                                    <?= renderOrderVariants($purchase['variants'], $purchase['unit_type'] ?? 'pcs') ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs text-gray-500">Montant</p>
                                    <p class="font-bold text-emerald-600"><?= number_format($purchase['total_amount'], 0) ?> $</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y', strtotime($purchase['created_at'])) ?></p>
                                </div>
                            </div>

                            <?php 
                                $order_rev = $existing_reviews[$purchase['id']] ?? null;
                            ?>
                            <?php if ($order_rev): ?>
                                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between">
                                    <div>
                                        <div class="text-xs text-amber-500 font-bold tracking-wider">
                                            <?= str_repeat('★', $order_rev['rating']) . str_repeat('☆', 5 - $order_rev['rating']) ?>
                                        </div>
                                        <p class="text-xs text-gray-600 italic mt-0.5 max-w-[200px] truncate">« <?= htmlspecialchars($order_rev['comment']) ?> »</p>
                                    </div>
                                    <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                        Avis déposé
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="mt-4 pt-3 border-t border-gray-100">
                                    <button 
                                        type="button"
                                        @click="openReviewModal(<?= $purchase['id'] ?>, '<?= addslashes(htmlspecialchars($purchase['product_name'] ?? 'Produit')) ?>', '<?= addslashes(htmlspecialchars($purchase['seller_name'])) ?>')"
                                        class="w-full flex items-center justify-center gap-1.5 py-2.5 px-3 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-bold transition shadow-xs active:scale-95"
                                    >
                                        <i class="bi bi-star-fill text-amber-500"></i> Noter le vendeur et laisser un avis
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 5. Annulations -->
        <div x-show="'cancellations' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Mes annulations</h2>
                <?php if (!empty($cancelled_orders) || !empty($cancelled_purchases)): ?>
                <form method="POST" style="display:inline;">
                    <button type="submit" 
                            name="clear_cancellations"
                            onclick="return confirm('Êtes-vous sûr de vouloir vider la boîte des annulations ? Cette action est irréversible.')"
                            class="bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition">
                        <i class="bi bi-trash mr-1"></i> Vider la boîte
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Annulations de vente -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-900">Commandes annulées (Ventes)</h3>
                
                <?php if (empty($cancelled_orders)): ?>
                    <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                        <i class="bi bi-bag-x text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune commande annulée</p>
                        <p class="text-gray-500 text-sm">Les commandes que vous annulez apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($cancelled_orders as $c): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($c['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($c['customer_name']) ?></p>
                                    </div>
                                    <span class="status-badge-cancelled px-3 py-1 rounded-lg text-sm font-medium">
                                        Annulée
                                    </span>
                                </div>
                                
                                <?php 
                                $cancelled_variants = getOrderVariants($c['order_id'] ?? 0, $conn, 'orders');
                                if (!empty($cancelled_variants)): 
                                ?>
                                    <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                                        <p class="font-medium text-xs text-gray-600 mb-1.5">Articles concernés :</p>
                                        <?= renderOrderVariants($cancelled_variants, $c['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-4">
                                    <p class="text-xs text-gray-500 mb-1">Motif</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($c['cancel_reason']) ?></p>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-medium"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></p>
                                    </div>
                                    <button @click="loadCancellationDetails(<?= $c['id'] ?>)" class="text-sm text-fuchsia-900 font-medium bg-fuchsia-50 px-3 py-2 rounded-lg">Voir détails</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Annulations d'achats -->
            <div class="space-y-4">
                <h3 class="text-lg font-bold text-gray-900">Commandes annulées (Achats)</h3>
                
                <?php if (empty($cancelled_purchases)): ?>
                    <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                        <i class="bi bi-bag-x text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune commande annulée</p>
                        <p class="text-gray-500 text-sm">Les commandes que vous annulez apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($cancelled_purchases as $c): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($c['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($c['seller_name']) ?></p>
                                    </div>
                                    <span class="status-badge-cancelled px-3 py-1 rounded-lg text-sm font-medium">
                                        Annulée
                                    </span>
                                </div>
                                
                                <?php 
                                $cancelled_variants = getOrderVariants($c['order_id'] ?? 0, $conn, 'orders');
                                if (!empty($cancelled_variants)): 
                                ?>
                                    <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                                        <p class="font-medium text-xs text-gray-600 mb-1.5">Articles concernés :</p>
                                        <?= renderOrderVariants($cancelled_variants, $c['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-4">
                                    <p class="text-xs text-gray-500 mb-1">Motif</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($c['reason']) ?></p>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-medium"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></p>
                                    </div>
                                    <button @click="loadCancellationDetails(<?= $c['id'] ?>)" class="text-sm text-fuchsia-900 font-medium bg-fuchsia-50 px-3 py-2 rounded-lg">Voir détails</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- MODALES POPUP -->
    
    <!-- Modal Paramètres -->
    <dialog
        x-ref="settingsModal"
        x-effect="
            openModal === 'settings'
                ? $refs.settingsModal.showModal()
                : $refs.settingsModal.close()
        "
        @click.self="closeModal()"
        class="modal-animation"
    >
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Paramètres</h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="font-bold text-lg mb-4">Profil</h3>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-xl">
                            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profil" class="w-16 h-16 rounded-full border-2 border-white">
                            <div class="flex-1">
                                <p class="font-medium"><?= htmlspecialchars($firstname) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($phone) ?></p>
                            </div>
                            <a href="edit_profile.php" class="text-fuchsia-900 hover:text-fuchsia-900">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl">
                                <p class="text-sm text-gray-500">Email</p>
                                <p class="font-medium"><?= htmlspecialchars($email ?: 'Non renseigné') ?></p>
                            </div>
                            <div class="p-4 bg-white border border-gray-200 rounded-xl">
                                <p class="text-sm text-gray-500">Rôle</p>
                                <p class="font-medium"><?= ucfirst($role) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-bold text-lg mb-4">Informations de compte</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="bi bi-person text-blue-600"></i>
                                </div>
                                <a href="edit_profile.php">
                                    <p class="font-medium">Profil complet</p>
                                    <p class="text-sm text-gray-500">Modifier toutes vos informations</p>
                                </a>
                            </div>
                            <i class="bi bi-chevron-right text-gray-400"></i>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 cursor-pointer"
                            @click="openPasswordModal()">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="bi bi-shield-lock text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Sécurité</p>
                                    <p class="text-sm text-gray-500">Changer le mot de passe</p>
                                </div>
                            </div>
                            <i class="bi bi-chevron-right text-gray-400"></i>
                        </div>
                                                
                        <div class="p-4 bg-blue-50 rounded-xl">
                            <p class="font-bold text-gray-800 mb-2">Membre depuis</p>
                            <p class="text-gray-600"><?= $joined_date ?></p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-bold text-lg mb-4">Support</h3>
                    <div class="space-y-4">
                        <a href="term_condition.html" target="_blank" 
                           class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                                    <i class="bi bi-file-text text-gray-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Conditions d'utilisation</p>
                                </div>
                            </div>
                            <i class="bi bi-box-arrow-up-right text-gray-400"></i>
                        </a>
                        
                        <div class="p-4 bg-blue-50 rounded-xl">
                            <h5 class="font-bold text-gray-800 mb-3">Service client</h5>
                            <p class="text-sm text-gray-600 mb-3">Nous contacter via :</p>
                            <div class="flex gap-3">
                                <a href="mailto:support@ecascadeur.com" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm hover:shadow-md transition">
                                    <i class="bi bi-envelope text-blue-600"></i>
                                </a>
                                <a href="https://wa.me/+243992210266" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm hover:shadow-md transition">
                                    <i class="bi bi-whatsapp text-green-600"></i>
                                </a>
                                <a href="https://t.me/Philippe mir" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center shadow-sm hover:shadow-md transition">
                                    <i class="bi bi-telegram text-blue-500"></i>
                                </a>
                            </div>
                        </div>
                        
                        <form method="POST" action="settings.php" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.');">
                            <div class="p-4 bg-red-50 rounded-xl">
                                <h5 class="font-bold text-red-700 mb-2">Zone de danger</h5>
                                <p class="text-sm text-red-600 mb-4">Cette action supprimera définitivement votre compte et toutes les données associées.</p>
                                <button type="submit" name="delete_account" 
                                        class="w-full bg-red-600 text-white py-3 rounded-xl font-medium hover:bg-red-700 transition flex items-center justify-center gap-2">
                                    <i class="bi bi-trash"></i> Supprimer mon compte
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4">
                <a href="logout.php" 
                   class="w-full flex items-center justify-center gap-2 bg-gray-100 text-gray-700 py-3 rounded-xl font-medium hover:bg-gray-200 transition">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </dialog>

    <!-- Modal Commandes en attente -->
    <dialog x-show="openModal === 'pending'" @click.self="closeModal()" class="modal-animation">
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Commandes en attente</h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <?php if (empty($pending_orders)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-clock-history text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune commande en attente</p>
                        <p class="text-gray-500 text-sm">Les commandes en attente de paiement apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                    </div>
                                    <span class="bg-fuchsia-100 text-fuchsia-900 px-3 py-1 rounded-lg text-sm font-medium">
                                        En attente
                                    </span>
                                </div>
                                
                                <?php if (!empty($order['variants'])): ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-semibold text-gray-600 mb-2">Détails de la commande :</p>
                                        <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-2 gap-4 mb-5">
                                    <div>
                                        <p class="text-xs text-gray-500">Quantité</p>
                                        <p class="font-medium"><?= $order['unit_value'] ?> <?= htmlspecialchars($order['unit_type']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Montant</p>
                                        <p class="font-bold text-fuchsia-900"><?= number_format($order['total_amount'], 0) ?> $</p>
                                    </div>
                                    <div class="col-span-2">
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-medium"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <a href="payment.php?order_id=<?= $order['id'] ?>" 
                                       class="flex-1 bg-fuchsia-700 text-white py-3 rounded-xl text-center text-sm font-medium hover:bg-fuchsia-900 transition">
                                        Finaliser le paiement
                                    </a>
                                    <button class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                                        Contacter client
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </dialog>

    <!-- Modal Ventes finalisées -->
    <dialog x-show="openModal === 'completed'" @click.self="closeModal()" class="modal-animation">
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Ventes finalisées</h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <?php 
                $pending_sales = array_filter($completed_orders, function($o) { return $o['status'] === 'pending'; });
                if (empty($pending_sales)): 
                ?>
                    <div class="text-center py-12">
                        <i class="bi bi-truck text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune vente en attente de livraison</p>
                        <p class="text-gray-500 text-sm">Les ventes en attente de livraison apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_sales as $order): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                    </div>
                                    <span class="status-badge-pending px-3 py-1 rounded-lg text-sm font-medium">
                                        En attente de livraison
                                    </span>
                                </div>
                                
                                <?php if (!empty($order['variants'])): ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-semibold text-gray-600 mb-2">Détail des articles :</p>
                                        <?= renderOrderVariants($order['variants'], $order['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500">Montant</p>
                                        <p class="font-bold text-emerald-600"><?= number_format($order['total_amount'], 0) ?> $</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-medium"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </dialog>

    <!-- Modal Achats finalisés -->
    <dialog x-show="openModal === 'purchases'" @click.self="closeModal()" class="modal-animation">
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Mes achats</h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <?php 
                $pending_purchases = array_filter($purchases, function($o) { return $o['status'] === 'pending'; });
                if (empty($pending_purchases)): 
                ?>
                    <div class="text-center py-12">
                        <i class="bi bi-cart-check text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucun achat en attente de livraison</p>
                        <p class="text-gray-500 text-sm">Vos achats en attente de livraison apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($pending_purchases as $purchase): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($purchase['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($purchase['seller_name']) ?></p>
                                    </div>
                                    <span class="status-badge-pending px-3 py-1 rounded-lg text-sm font-medium">
                                        En attente de livraison
                                    </span>
                                </div>
                                
                                <?php if (!empty($purchase['variants'])): ?>
                                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-semibold text-gray-600 mb-2">Votre commande :</p>
                                        <?= renderOrderVariants($purchase['variants'], $purchase['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-2 gap-4 mb-5">
                                    <div>
                                        <p class="text-xs text-gray-500">Montant</p>
                                        <p class="font-bold text-blue-600"><?= number_format($purchase['total_amount'], 0) ?> $</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500">Quantité</p>
                                        <p class="font-medium"><?= $purchase['quantity'] ?></p>
                                    </div>
                                    <div class="col-span-2">
                                        <p class="text-xs text-gray-500">Date</p>
                                        <p class="font-medium"><?= date('d/m/Y H:i', strtotime($purchase['created_at'])) ?></p>
                                    </div>
                                </div>
                                
                                <div class="flex gap-3">
                                    <button class="flex-1 border border-gray-300 text-gray-700 py-3 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                                        Contacter vendeur
                                    </button>
                                    <button class="flex-1 bg-blue-500 text-white py-3 rounded-xl text-sm font-medium hover:bg-blue-600 transition">
                                        Évaluer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </dialog>

    <!-- Modal Commandes annulées -->
    <dialog x-show="openModal === 'cancelled'" @click.self="closeModal()" class="modal-animation">
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Commandes annulées</h2>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6">
                <?php if (empty($cancelled_orders)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-bag-x text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune commande annulée</p>
                        <p class="text-gray-500 text-sm">Les commandes annulées apparaîtront ici</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($cancelled_orders as $c): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($c['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Annulée par : <?= htmlspecialchars($c['cancelled_by']) ?></p>
                                    </div>
                                    <span class="status-badge-cancelled px-3 py-1 rounded-lg text-sm font-medium">
                                        Annulée
                                    </span>
                                </div>
                                
                                <?php 
                                $cancelled_variants = getOrderVariants($c['order_id'] ?? 0, $conn, 'orders');
                                if (!empty($cancelled_variants)): 
                                ?>
                                    <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                                        <p class="font-medium text-xs text-gray-600 mb-1.5">Articles concernés :</p>
                                        <?= renderOrderVariants($cancelled_variants, $c['unit_type'] ?? 'pcs') ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500 mb-1">Raison</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($c['cancel_reason']) ?></p>
                                </div>
                                
                                <div>
                                    <p class="text-xs text-gray-500">Date</p>
                                    <p class="font-medium"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </dialog>

    <!-- Modal Confirmation suppression -->
    <div
        x-show="openModal === 'delete'"
        x-transition.opacity
        @click.self="closeModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
        <div
            x-transition.scale
            class="bg-white rounded-xl shadow-2xl max-w-md w-full p-8"
        >
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2"
                    x-text="'Supprimer \"' + deleteProductName + '\" ?'">
                </h3>
                <p class="text-gray-600">
                    Cette action est irréversible. Le produit sera définitivement supprimé.
                </p>
            </div>

            <div class="flex gap-3">
                <button @click="closeModal()"
                    class="flex-1 py-3 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Annuler
                </button>
                <a :href="'api/delete_product.php?id=' + deleteProductId"
                    class="flex-1 py-3 bg-red-600 text-white rounded-lg text-center hover:bg-red-700 transition">
                    Supprimer
                </a>
            </div>
        </div>
    </div>

    <div
        x-show="openModal === 'cancelOrder'"
        x-transition.opacity
        @click.self="closeModal()"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
        <div
            x-transition.scale
            class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6"
        >
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <div class="flex gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-red-600 text-xl flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-sm font-semibold text-red-700 mb-1">Attention</p>
                        <p class="text-xs text-red-600">Cette action est irréversible. Une fois annulée, la commande ne peut pas être restaurée.</p>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                Annuler la commande
            </h3>

            <p class="text-sm text-gray-600 mb-4">
                Veuillez indiquer la raison de l'annulation. Cette information nous aide à améliorer notre service.
            </p>

            <form method="POST">
                <input type="hidden" name="order_id" :value="deleteProductId">

                <textarea
                    name="cancel_reason"
                    x-model="cancelReason"
                    placeholder="Ex : délai de livraison trop long, erreur de commande…"
                    class="w-full h-24 border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-red-500 focus:outline-none"
                ></textarea>

                <div class="mt-6 flex gap-3">
                    <button type="button"
                            @click="closeModal()"
                            class="flex-1 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Retour
                    </button>

                    <button type="submit"
                            name="submit_cancel_order"
                            class="flex-1 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                            onclick="return confirm('Êtes-vous certain de vouloir annuler cette commande ? Cette action est irréversible.')">
                        Confirmer l'annulation
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        x-cloak
        x-show="openModal === 'cancelPurchase'"
        x-transition.opacity
        @click.self="closeModal()"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    >
        <div
            x-transition.scale
            class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6"
        >
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <div class="flex gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-red-600 text-xl flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-sm font-semibold text-red-700 mb-1">Attention</p>
                        <p class="text-xs text-red-600">Cette action est irréversible. Une fois annulée, la commande ne peut pas être restaurée.</p>
                    </div>
                </div>
            </div>

            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                Annuler mon achat
            </h3>

            <p class="text-xs text-gray-500 mb-4">
                Veuillez sélectionner le motif qui correspond à votre situation. Votre demande sera traitée immédiatement.
            </p>

            <form method="POST" class="space-y-3">
                <input type="hidden" name="order_id" :value="cancelPurchaseId">

                <div class="space-y-2">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition"
                           :class="cancelAssertion === 'je_n_en_ai_plus_besoin' ? 'border-fuchsia-500 bg-fuchsia-50/20' : ''">
                        <input type="radio" name="cancel_assertion" value="je_n_en_ai_plus_besoin" x-model="cancelAssertion" class="mt-0.5 text-fuchsia-600 focus:ring-fuchsia-500">
                        <div>
                            <span class="text-xs font-bold text-gray-900 block">Je n'en ai plus besoin</span>
                            <span class="text-[11px] text-gray-500">Vous avez changé d'avis ou trouvé une autre solution.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition"
                           :class="cancelAssertion === 'produit_mauvais_etat' ? 'border-red-500 bg-red-50/20' : ''">
                        <input type="radio" name="cancel_assertion" value="produit_mauvais_etat" x-model="cancelAssertion" class="mt-0.5 text-red-600 focus:ring-red-500">
                        <div>
                            <span class="text-xs font-bold text-red-700 flex items-center gap-1.5">
                                <i class="bi bi-shield-x"></i> Le produit est en très mauvais état
                            </span>
                            <span class="text-[11px] text-gray-500">Produit non conforme ou détérioré. Déclenche le remboursement B2C intégral immédiat.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition"
                           :class="cancelAssertion === 'pas_livre_dans_le_temps' ? 'border-amber-500 bg-amber-50/20' : ''">
                        <input type="radio" name="cancel_assertion" value="pas_livre_dans_le_temps" x-model="cancelAssertion" class="mt-0.5 text-amber-600 focus:ring-amber-500">
                        <div>
                            <span class="text-xs font-bold text-amber-800 flex items-center gap-1.5">
                                <i class="bi bi-alarm"></i> Je n'ai pas été livré dans les temps
                            </span>
                            <span class="text-[11px] text-gray-500">Délai d'attente dépassé sans nouvelle. Déclenche le remboursement B2C intégral immédiat.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:bg-gray-50 transition"
                           :class="cancelAssertion === 'autre' ? 'border-gray-400 bg-gray-50' : ''">
                        <input type="radio" name="cancel_assertion" value="autre" x-model="cancelAssertion" class="mt-0.5 text-gray-700">
                        <div>
                            <span class="text-xs font-bold text-gray-900 block">Autre motif</span>
                            <span class="text-[11px] text-gray-500">Précisez la raison de votre annulation ci-dessous.</span>
                        </div>
                    </label>
                </div>

                <div x-show="cancelAssertion === 'autre'" class="pt-1">
                    <textarea
                        name="cancel_reason"
                        x-model="cancelReason"
                        placeholder="Précisez votre motif ici..."
                        class="w-full h-20 border border-gray-300 rounded-xl p-3 text-xs focus:ring-2 focus:ring-red-500 focus:outline-none"
                    ></textarea>
                </div>

                <!-- Info B2C pour toutes les raisons d'annulation -->
                <div x-show="cancelAssertion" class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-center gap-2">
                    <i class="bi bi-cash-coin text-emerald-600 text-lg shrink-0"></i>
                    <span><strong>Garantie Remboursement Immédiat :</strong> Cette annulation déclenche directement le remboursement B2C intégral vers votre numéro Mobile Money.</span>
                </div>

                <div x-show="!cancelAssertion" class="p-2.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 font-medium flex items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill text-amber-600 shrink-0"></i>
                    <span>Veuillez sélectionner un motif ci-dessus pour pouvoir confirmer l'annulation.</span>
                </div>

                <div class="mt-5 flex gap-3">
                    <button type="button"
                            @click="closeModal()"
                            :disabled="isSubmittingCancel"
                            class="flex-1 py-2.5 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 transition text-xs font-semibold">
                        Retour
                    </button>

                    <button type="submit"
                            name="submit_cancel_purchase"
                            :disabled="!cancelAssertion || (cancelAssertion === 'autre' && !cancelReason.trim()) || isSubmittingCancel"
                            :class="(!cancelAssertion || (cancelAssertion === 'autre' && !cancelReason.trim()) || isSubmittingCancel) ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-red-600 hover:bg-red-700 active:scale-95 cursor-pointer shadow-md'"
                            @click="if (confirm('Confirmez-vous l\'annulation et le remboursement intégral immédiat de cette commande ?')) { isSubmittingCancel = true; return true; } else { return false; }"
                            class="flex-1 py-2.5 text-white rounded-xl transition text-xs font-bold flex items-center justify-center gap-2">
                        <span x-show="!isSubmittingCancel">Confirmer l'annulation</span>
                        <span x-show="isSubmittingCancel" class="flex items-center gap-2">
                            <i class="bi bi-arrow-repeat animate-spin"></i> Remboursement B2C en cours...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE NOTATION ET AVIS VENDEUR -->
    <div
        x-cloak
        x-show="showReviewModal"
        x-transition.opacity
        @click.self="closeReviewModal()"
        @keydown.escape.window="closeReviewModal()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
        <div
            x-transition.scale
            class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 sm:p-8"
        >
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="size-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600">
                        <i class="bi bi-star-fill text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Donner votre avis</h3>
                        <p class="text-xs text-gray-500" x-text="reviewProductName"></p>
                    </div>
                </div>
                <button type="button" @click="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            <form method="POST" action="api/submit_review.php" class="space-y-4">
                <input type="hidden" name="order_id" :value="reviewOrderId">
                <input type="hidden" name="rating" :value="reviewRating">

                <!-- Étoiles interactives -->
                <div class="text-center py-3 bg-gray-50 rounded-2xl border border-gray-100">
                    <span class="text-xs font-semibold text-gray-600 block mb-2">Notez la prestation du vendeur</span>
                    <div class="flex items-center justify-center gap-2">
                        <template x-for="star in [1, 2, 3, 4, 5]" :key="star">
                            <button 
                                type="button" 
                                @click="reviewRating = star"
                                class="text-3xl transition transform hover:scale-125 focus:outline-none"
                                :class="star <= reviewRating ? 'text-amber-400' : 'text-gray-300'"
                            >
                                ★
                            </button>
                        </template>
                    </div>
                    <span class="text-xs font-bold text-amber-700 mt-1 block" x-text="reviewRating + '/5 étoiles'"></span>
                </div>

                <!-- Commentaire -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Votre commentaire sur la livraison et le produit</label>
                    <textarea 
                        name="comment" 
                        x-model="reviewComment" 
                        rows="3" 
                        required
                        placeholder="Ex : Produit conforme, livraison ultra rapide et soignée. Vendeur recommandé !"
                        class="w-full border border-gray-200 rounded-xl p-3 text-xs text-gray-900 focus:ring-2 focus:ring-amber-500 focus:outline-none resize-none"
                    ></textarea>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" @click="closeReviewModal()" class="flex-1 py-2.5 border border-gray-200 rounded-xl text-gray-600 hover:bg-gray-50 text-xs font-semibold">
                        Annuler
                    </button>
                    <button type="submit" class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold transition shadow-md active:scale-95">
                        Publier mon avis
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div 
        x-show="showOrderDetailModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/30 backdrop-blur-xs"
        @click.self="showOrderDetailModal = false"
        @keydown.escape.window="showOrderDetailModal = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
    >
        <div class="bg-white rounded-xl w-full max-w-md max-h-[85vh] overflow-y-auto border border-gray-100 shadow-xl flex flex-col">
            
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-sm font-semibold text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="bi bi-receipt text-gray-400"></i>
                    Détails de la commande
                </h2>
                <button @click="showOrderDetailModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-md transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div x-show="orderLoading" class="p-12 text-center flex-1 flex flex-col items-center justify-center">
                <div class="animate-spin w-6 h-6 border-2 border-gray-200 border-t-gray-800 rounded-full mb-3"></div>
                <p class="text-xs text-gray-500 font-medium">Chargement des données...</p>
            </div>
            
            <div x-show="!orderLoading && orderDetails && orderDetails.error" class="p-12 text-center flex-1">
                <div class="w-10 h-10 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="bi bi-exclamation-triangle text-sm"></i>
                </div>
                <p class="text-xs font-medium text-gray-700" x-text="orderDetails.error"></p>
            </div>
            
            <div x-show="!orderLoading && orderDetails && !orderDetails.error" class="p-6 space-y-6 flex-1">
                
                <div class="flex items-start gap-4 pb-5 border-b border-gray-100">
                    <img x-show="orderDetails.product_image" 
                         :src="orderDetails.product_image" 
                         class="w-14 h-14 rounded-lg object-cover bg-gray-50 border border-gray-100 flex-shrink-0"
                         onerror="this.style.display='none'">
                    <div class="space-y-0.5">
                        <h3 class="font-semibold text-sm text-gray-900" x-text="orderDetails.product_name"></h3>
                        <p class="text-xs text-gray-500">
                            Réf. #<span x-text="orderDetails.id" class="font-mono"></span>
                        </p>
                        <p class="text-[11px] text-gray-400" x-text="'Enregistrée le ' + orderDetails.created_at_formatted"></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 gap-5">
                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Acheteur</span>
                        <div class="bg-gray-50 rounded-lg p-3.5 space-y-2 text-xs border border-gray-100/50">
                            <div class="flex justify-between"><span class="text-gray-500">Nom complet</span><span class="font-medium text-gray-900" x-text="orderDetails.customer_name"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Téléphone</span><span class="font-medium text-gray-900 font-mono" x-text="orderDetails.customer_phone"></span></div>
                            <div class="flex justify-between gap-4"><span class="text-gray-500 flex-shrink-0">Adresse</span><span class="font-medium text-gray-900 text-right truncate max-w-[220px]" :title="orderDetails.customer_address" x-text="orderDetails.customer_address"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Région</span><span class="font-medium text-gray-900" x-text="orderDetails.region"></span></div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Fournisseur</span>
                        <div class="bg-gray-50 rounded-lg p-3.5 space-y-2 text-xs border border-gray-100/50">
                            <div class="flex justify-between"><span class="text-gray-500">Boutique / Nom</span><span class="font-medium text-gray-900" x-text="orderDetails.seller_name"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Téléphone</span><span class="font-medium text-gray-900 font-mono" x-text="orderDetails.seller_phone"></span></div>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Spécifications</span>
                    <div class="border border-gray-100 rounded-lg p-3.5 space-y-3 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500">Quantité demandée</span>
                            <span class="font-semibold text-gray-900" x-text="orderDetails.total_color_quantity + ' ' + orderDetails.unit_type"></span>
                        </div>
                        
                        <div x-show="orderDetails.selected_size" class="flex justify-between items-center">
                            <span class="text-gray-500">Taille sélectionnée</span>
                            <span class="font-medium text-gray-900 bg-gray-100 px-2 py-0.5 rounded text-[11px]" x-text="orderDetails.selected_size"></span>
                        </div>
                        
                        <div x-show="orderDetails.color_quantities && orderDetails.color_quantities.length > 0" class="pt-2.5 border-t border-gray-100 space-y-2">
                            <template x-for="color in orderDetails.color_quantities" :key="color.hex">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="w-3 h-3 rounded-full border border-gray-200" :style="'background-color:' + color.hex"></span>
                                        <span class="text-gray-600" x-text="getColorName(color.hex)"></span>
                                    </div>
                                    <span class="text-gray-500 font-medium" x-text="color.quantity + ' ' + orderDetails.unit_type"></span>
                                </div>
                            </template>
                        </div>
                        
                        <div class="pt-2.5 border-t border-gray-100 flex justify-between">
                            <span class="text-gray-500">Prix unitaire</span>
                            <span class="text-gray-900 font-medium" x-text="orderDetails.unit_value + ' ' + (orderDetails.currency || '$')"></span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-900 text-white rounded-lg p-4 space-y-1.5 shadow-sm">
                    <div x-show="orderDetails.discount_applied" class="flex justify-between text-[11px] text-gray-400">
                        <span>Montant brut</span>
                        <span class="line-through" x-text="orderDetails.original_amount + ' ' + (orderDetails.currency || '$')"></span>
                    </div>
                    <div x-show="orderDetails.discount_applied" class="flex justify-between text-[11px] text-emerald-400">
                        <span>Remise déduite (<span x-text="orderDetails.discount_percent"></span>%)</span>
                        <span x-text="'-' + (orderDetails.original_amount - orderDetails.total_amount).toFixed(2) + ' ' + (orderDetails.currency || '$')"></span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-xs font-medium text-gray-300">Net à payer</span>
                        <span class="text-base font-bold tracking-tight" x-text="orderDetails.total_amount + ' ' + (orderDetails.currency || '$')"></span>
                    </div>
                </div>
            </div>
            
            <div x-show="!orderLoading && orderDetails && !orderDetails.error" 
                 class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 flex gap-3">
                <a :href="'tel:' + orderDetails.customer_phone" 
                   class="flex-1 border border-gray-200 text-gray-700 py-2 rounded-lg text-xs font-medium hover:bg-gray-50 hover:text-gray-900 transition flex items-center justify-center gap-1.5">
                    <i class="bi bi-telephone text-gray-400"></i>
                    Appeler
                </a>
                <a :href="'https://wa.me/243' + orderDetails.customer_phone.replace(/[^0-9]/g, '')"
                   target="_blank"
                   class="flex-1 bg-gray-900 text-white py-2 rounded-lg text-xs font-medium hover:bg-gray-800 transition flex items-center justify-center gap-1.5">
                    <i class="bi bi-whatsapp"></i>
                    Ouvrir WhatsApp
                </a>
            </div>
        </div>
    </div>

    <!-- Modal Détails Annulation -->
    <div 
        x-show="showCancellationModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/30 backdrop-blur-xs"
        @click.self="showCancellationModal = false"
        @keydown.escape.window="showCancellationModal = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
    >
        <div class="bg-white rounded-xl w-full max-w-md max-h-[85vh] overflow-y-auto border border-gray-100 shadow-xl flex flex-col">
            
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-sm font-semibold text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="bi bi-bag-x text-gray-400"></i>
                    Détails de l'annulation
                </h2>
                <button @click="showCancellationModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-md transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <div x-show="cancellationLoading" class="p-12 text-center flex-1 flex flex-col items-center justify-center">
                <div class="animate-spin w-6 h-6 border-2 border-gray-200 border-t-gray-800 rounded-full mb-3"></div>
                <p class="text-xs text-gray-500 font-medium">Chargement des données...</p>
            </div>
            
            <div x-show="!cancellationLoading && cancellationDetails && cancellationDetails.error" class="p-12 text-center flex-1">
                <div class="w-10 h-10 bg-red-50 text-red-600 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="bi bi-exclamation-triangle text-sm"></i>
                </div>
                <p class="text-xs font-medium text-gray-700" x-text="cancellationDetails.error"></p>
            </div>
            
            <div x-show="!cancellationLoading && cancellationDetails && !cancellationDetails.error" class="p-6 space-y-6 flex-1">
                
                <div class="flex items-start gap-4 pb-5 border-b border-gray-100">
                    <div class="space-y-0.5 flex-1">
                        <h3 class="font-semibold text-sm text-gray-900" x-text="cancellationDetails.product_name"></h3>
                        <p class="text-xs text-gray-500">
                            Réf. #<span x-text="cancellationDetails.order_id" class="font-mono"></span>
                        </p>
                        <p class="text-[11px] text-gray-400" x-text="'Annulée le ' + cancellationDetails.cancellation_date_formatted"></p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 mb-3">Motif de l'annulation</h4>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-700" x-text="cancellationDetails.cancel_reason || cancellationDetails.reason">
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 mb-3">Informations de la commande</h4>
                        <div class="bg-gray-50 rounded-lg p-3.5 space-y-2 text-xs border border-gray-100/50">
                            <div class="flex justify-between"><span class="text-gray-500">Montant</span><span class="font-medium text-gray-900" x-text="cancellationDetails.total_amount + ' ' + (cancellationDetails.currency || '$')"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Quantité</span><span class="font-medium text-gray-900" x-text="cancellationDetails.quantity"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Statut original</span><span class="font-medium text-gray-900" x-text="cancellationDetails.original_status"></span></div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-700 mb-3">Client</h4>
                        <div class="bg-gray-50 rounded-lg p-3.5 space-y-2 text-xs border border-gray-100/50">
                            <div class="flex justify-between"><span class="text-gray-500">Nom</span><span class="font-medium text-gray-900" x-text="cancellationDetails.customer_name"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Téléphone</span><span class="font-medium text-gray-900 font-mono" x-text="cancellationDetails.customer_phone"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Adresse</span><span class="font-medium text-gray-900 text-right" x-text="cancellationDetails.customer_address"></span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div x-show="!cancellationLoading && cancellationDetails && !cancellationDetails.error" 
                 class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4">
                <button @click="showCancellationModal = false" class="w-full bg-gray-100 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Changement de mot de passe -->
    <dialog
        x-ref="passwordModal"
        x-effect="
            showPasswordModal
                ? $refs.passwordModal.showModal()
                : $refs.passwordModal.close()
        "
        @click.self="showPasswordModal = false"
        class="modal-animation"
    >
        <div class="modal-content">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-gray-900">Changer le mot de passe</h2>
                <button @click="showPasswordModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="bi bi-x-lg text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div id="passwordMessage" class="hidden p-4 rounded-lg"></div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe actuel</label>
                        <input type="password" id="currentPassword" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-fuchsia-700 focus:border-transparent outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                        <input type="password" id="newPassword" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-fuchsia-700 focus:border-transparent outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirmPassword" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-fuchsia-700 focus:border-transparent outline-none">
                    </div>
                </div>
                
                <button onclick="updatePassword()" 
                        class="w-full bg-fuchsia-700 text-white py-3.5 rounded-xl font-medium hover:bg-fuchsia-900 transition flex items-center justify-center gap-2">
                    <i class="bi bi-key"></i> Mettre à jour le mot de passe
                </button>
            </div>
        </div>
    </dialog>

    <?php endif; ?>
    <script>
        // Sauvegarde la position du scroll
        window.addEventListener("beforeunload", () => {
            localStorage.setItem("scrollPosition", window.scrollY);
        });

        // Restaure la position après chargement
        window.addEventListener("load", () => {
            const scrollPosition = localStorage.getItem("scrollPosition");
            if (scrollPosition !== null) {
                window.scrollTo(0, parseInt(scrollPosition));
            }
        });
    </script>

    <!-- Alpine app definition -->
    <script>
    function dashboardApp() {
        return {
            openModal: null,
            deleteProductId: null,
            deleteProductName: '',
            showPasswordModal: false,
            cancelReason: '',
            cancelAssertion: '',
            isSubmittingCancel: false,
            cancelPurchaseId: null,
            showOrderDetailModal: false,
            orderDetails: null,
            orderLoading: false,
            showCancellationModal: false,
            cancellationDetails: null,
            cancellationLoading: false,

            // Toast Notifications en temps réel
            toast: {
                show: false,
                title: '',
                message: '',
                link: '',
                isWarning: false
            },

            // Avis et notation
            showReviewModal: false,
            reviewOrderId: null,
            reviewProductName: '',
            reviewSellerName: '',
            reviewRating: 5,
            reviewComment: '',

            init() {
                // Demande d'autorisation pour les vraies notifications navigateur / OS
                if ("Notification" in window && Notification.permission === "default") {
                    Notification.requestPermission();
                }

                // Polling en arrière-plan toutes les 20 secondes pour les notifications en temps réel (alertes 2h, etc.)
                setInterval(() => {
                    this.checkForNewNotifications();
                }, 20000);

                // Ouvrir automatiquement le modal d'avis si demandé dans l'URL
                const urlParams = new URLSearchParams(window.location.search);
                const reviewId = urlParams.get('review_order');
                if (reviewId) {
                    this.openReviewModal(parseInt(reviewId), 'Commande #' + reviewId, 'le vendeur');
                }
            },

            async checkForNewNotifications() {
                try {
                    const res = await fetch('api/api_notifications_poll.php');
                    const data = await res.json();
                    if (data && data.has_new && data.latest) {
                        this.triggerNotification(data.latest);
                    }
                } catch (e) {
                    console.error("Erreur polling notifications:", e);
                }
            },

            triggerNotification(notif) {
                this.toast.title = notif.title || 'Nouvelle notification';
                this.toast.message = notif.message || '';
                this.toast.link = notif.link || 'notifications.php';
                this.toast.isWarning = (notif.type === 'order_warning');
                this.toast.show = true;

                // Vraie notification push système / navigateur
                if ("Notification" in window && Notification.permission === "granted") {
                    try {
                        const n = new Notification(notif.title, {
                            body: notif.message,
                            icon: 'assets/images/favicon.png',
                            tag: 'notif-' + notif.id
                        });
                        n.onclick = function() {
                            window.focus();
                            window.location.href = notif.link || 'notifications.php';
                        };
                    } catch (err) {
                        console.error("Notification API error:", err);
                    }
                }

                if (!this.toast.isWarning) {
                    setTimeout(() => {
                        this.toast.show = false;
                    }, 8000);
                }
            },

            openReviewModal(orderId, productName, sellerName) {
                this.reviewOrderId = orderId;
                this.reviewProductName = productName || ('Commande #' + orderId);
                this.reviewSellerName = sellerName || 'le vendeur';
                this.reviewRating = 5;
                this.reviewComment = '';
                this.showReviewModal = true;
            },

            closeReviewModal() {
                this.showReviewModal = false;
                this.reviewOrderId = null;
            },

            openSettingsModal() {
                this.openModal = 'settings';
            },

            openPasswordModal() {
                if (this.openModal === 'settings') {
                    this.openModal = null;
                }
                this.showPasswordModal = true;
            },

            openPendingModal() {
                this.openModal = 'pending';
            },

            openCompletedModal() {
                this.openModal = 'completed';
            },

            openPurchasesModal() {
                this.openModal = 'purchases';
            },

            openCancelledModal() {
                this.openModal = 'cancelled';
            },

            openDeleteDialog(id, name) {
                this.deleteProductId = id;
                this.deleteProductName = name;
                this.openModal = 'delete';
            },

            openCancelPurchaseModal(orderId) {
                this.cancelPurchaseId = orderId;
                this.cancelReason = '';
                this.cancelAssertion = '';
                this.isSubmittingCancel = false;
                this.openModal = 'cancelPurchase';
            },

            closeModal() {
                this.openModal = null;
                this.deleteProductId = null;
                this.deleteProductName = '';
                this.showPasswordModal = false;
                this.cancelReason = '';
                this.cancelAssertion = '';
                this.isSubmittingCancel = false;
                this.cancelPurchaseId = null;
            },

            async loadOrderDetails(orderId) {
                this.orderLoading = true;
                this.showOrderDetailModal = true;
                this.orderDetails = null;
                
                try {
                    const response = await fetch(`api/get_order_details.php?order_id=${orderId}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        this.orderDetails = data.order;
                    } else {
                        this.orderDetails = { error: data.message || 'Erreur lors du chargement' };
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    this.orderDetails = { error: 'Erreur de connexion' };
                } finally {
                    this.orderLoading = false;
                }
            },

            async loadCancellationDetails(cancellationId) {
                this.cancellationLoading = true;
                this.showCancellationModal = true;
                this.cancellationDetails = null;

                try {
                    const response = await fetch(
                        `api/get_cancellation_details.php?cancellation_id=${cancellationId}`
                    );

                    if (!response.ok) {
                        throw new Error(`HTTP error ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        this.cancellationDetails = data.cancellation;
                    } else {
                        this.cancellationDetails = {
                            error: data.message || 'Erreur lors du chargement'
                        };
                    }

                } catch (err) {
                    console.error(err);
                    this.cancellationDetails = {
                        error: err.message || 'Erreur de connexion'
                    };
                } finally {
                    this.cancellationLoading = false;
                }
            },

            getColorName(hex) {
                if (!hex) return '';
                const cleanHex = hex.replace('#', '').toUpperCase();
                if (window.COLOR_NAMES_MAP[cleanHex]) return window.COLOR_NAMES_MAP[cleanHex];
                if (window.COLOR_NAMES_MAP[hex]) return window.COLOR_NAMES_MAP[hex];
                if (window.COLOR_NAMES_MAP['#' + cleanHex]) return window.COLOR_NAMES_MAP['#' + cleanHex];
                return hex;
            },

            getStatusBadgeClass(status) {
                const classes = {
                    'pending': 'status-badge-pending',
                    'delivered': 'status-badge-delivered',
                    'cancelled': 'status-badge-cancelled'
                };
                return classes[status] || 'bg-gray-100 text-gray-700';
            },

            getStatusLabel(status) {
                const labels = {
                    'pending': 'En attente de livraison',
                    'delivered': 'Livrée',
                    'cancelled': 'Annulée'
                };
                return labels[status] || status;
            }
        }
    }
    </script>

    <!-- Script pour la mise à jour du mot de passe -->
    <script>
    function updatePassword() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const messageDiv = document.getElementById('passwordMessage');
        
        messageDiv.className = 'hidden p-4 rounded-lg';
        messageDiv.innerHTML = '';
        
        if (!currentPassword || !newPassword || !confirmPassword) {
            showMessage('Tous les champs sont requis', 'error');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showMessage('Les nouveaux mots de passe ne correspondent pas', 'error');
            return;
        }
        
        if (newPassword.length < 6) {
            showMessage('Le mot de passe doit contenir au moins 6 caractères', 'error');
            return;
        }
        
        const data = {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        };
        
        const button = event.target;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass"></i> Mise à jour en cours...';
        
        fetch('updatepassword.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showMessage(result.message, 'success');
                
                document.getElementById('currentPassword').value = '';
                document.getElementById('newPassword').value = '';
                document.getElementById('confirmPassword').value = '';
            } else {
                showMessage(result.message, 'error');
            }
        })
        .catch(error => {
            showMessage('Erreur réseau. Veuillez réessayer.', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-key"></i> Mettre à jour le mot de passe';
        });
    }

    function showMessage(text, type) {
        const messageDiv = document.getElementById('passwordMessage');
        messageDiv.className = `p-4 rounded-lg ${type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'}`;
        messageDiv.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'}"></i>
                <span>${text}</span>
            </div>
        `;
        messageDiv.classList.remove('hidden');
    }
    </script>

    <script>
        let dashboardRequest = null;

        function setDashboardLoading(isLoading) {
            const main = document.querySelector('main');
            const existingLoader = document.querySelector('.dashboard-loading');

            if (isLoading) {
                if (!existingLoader) {
                    document.body.insertAdjacentHTML('afterbegin', '<div class="dashboard-loading" role="progressbar" aria-label="Chargement de la section"></div>');
                }
                main?.setAttribute('aria-busy', 'true');
            } else {
                existingLoader?.remove();
                main?.removeAttribute('aria-busy');
            }
        }

        async function loadDashboardTab(url, updateHistory = true) {
            if (dashboardRequest) {
                dashboardRequest.abort();
            }

            dashboardRequest = new AbortController();
            setDashboardLoading(true);

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: dashboardRequest.signal
                });

                if (!response.ok) {
                    throw new Error('Impossible de charger cette section.');
                }

                const html = await response.text();
                const nextDocument = new DOMParser().parseFromString(html, 'text/html');
                const nextMain = nextDocument.querySelector('main');
                const currentMain = document.querySelector('main');

                if (!nextMain || !currentMain) {
                    throw new Error('Réponse du dashboard invalide.');
                }

                currentMain.replaceWith(nextMain);

                if (window.Alpine) {
                    window.Alpine.initTree(nextMain);
                }

                if (updateHistory) {
                    window.history.pushState({}, '', url);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    window.alert(error.message || 'Une erreur est survenue.');
                }
            } finally {
                dashboardRequest = null;
                setDashboardLoading(false);
            }
        }

        document.addEventListener('click', function(event) {
            const link = event.target.closest('a[href*="?tab="]');
            if (!link || link.target === '_blank' || event.defaultPrevented) {
                return;
            }

            event.preventDefault();
            loadDashboardTab(new URL(link.href, window.location.href).toString());
        });

        window.addEventListener('popstate', function() {
            loadDashboardTab(window.location.href, false);
        });
    </script>
</body>
</html>