<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../libs/db.php';

// Récupération des paramètres
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?? $_REQUEST;

$limit = isset($data['limit']) ? max(1, intval($data['limit'])) : 20;
$exclude_ids = isset($data['exclude_ids']) && is_array($data['exclude_ids']) ? array_map('intval', $data['exclude_ids']) : [];

$user_preferences = [];
if (isset($data['user_preferences'])) {
    if (is_array($data['user_preferences'])) {
        $user_preferences = $data['user_preferences'];
    } else if (is_string($data['user_preferences'])) {
        $user_preferences = json_decode($data['user_preferences'], true) ?? [];
    }
} elseif (isset($_COOKIE['userPreferences'])) {
    $user_preferences = json_decode($_COOKIE['userPreferences'], true) ?? [];
}

// 1. Nombre total de produits uniques en BDD
$total_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products");
$total_row = mysqli_fetch_assoc($total_res);
$total_products_count = intval($total_row['cnt'] ?? 0);

// 2. Récupérer TOUS les produits non encore exclus
$sql_all = "SELECT p.id, p.name, p.price, p.currency, p.discount_percent, p.product_condition, p.category, 
                   pm.file_path, p.regions
            FROM products p
            LEFT JOIN product_media pm ON pm.product_id = p.id
            ORDER BY p.id DESC, pm.sort_order ASC";

$res_all = mysqli_query($conn, $sql_all);

$all_products_list = [];
if ($res_all) {
    while ($row = mysqli_fetch_assoc($res_all)) {
        $id = intval($row['id']);

        if (!isset($all_products_list[$id])) {
            $cat_arr = json_decode($row['category'], true);
            $cat_main = is_array($cat_arr) && !empty($cat_arr) ? $cat_arr[0] : "Autres";

            $all_products_list[$id] = [
                "id"        => $id,
                "name"      => $row['name'],
                "price"     => $row['price'],
                "currency"  => $row['currency'],
                "discount"  => $row['discount_percent'] ?: null,
                "condition" => ($row['product_condition'] === 'new') ? 'Neuf' : 'Occasion',
                "image"     => null,
                "category"  => $cat_main,
                "regions"   => json_decode($row['regions'], true) ?: []
            ];
        }

        if (empty($all_products_list[$id]['image']) && $row['file_path']) {
            $all_products_list[$id]['image'] = $row['file_path'];
        }
    }
}

// Filtrer pour retirer les produits déjà affichés
$available_products = [];
foreach ($all_products_list as $pid => $prod) {
    if (!in_array($pid, $exclude_ids, true)) {
        $available_products[] = $prod;
    }
}

// S'il n'y a plus de produits disponibles
if (empty($available_products)) {
    echo json_encode([
        'success'        => true,
        'products'       => [],
        'total_products' => $total_products_count,
        'has_more'       => false
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Séparation Recommandés / Aléatoires parmi les produits disponibles
$preferred_categories = [];
if (!empty($user_preferences['categories']) && is_array($user_preferences['categories'])) {
    $preferred_categories = array_keys($user_preferences['categories']);
}

$recommended_pool = [];
$other_pool = [];

foreach ($available_products as $product) {
    if (!empty($preferred_categories) && in_array($product['category'], $preferred_categories, true)) {
        $recommended_pool[] = $product;
    } else {
        $other_pool[] = $product;
    }
}

// Mélanger le pool aléatoire pour garantir la variété ("J'ai de la chance")
shuffle($other_pool);

// Mélanger également les recommandés pour varier l'ordre à chaque appel
shuffle($recommended_pool);

// Si pas ou peu de produits recommandés par catégories, utiliser d'autres produits disponibles pour remplir le pool recommandé
if (count($recommended_pool) < round($limit * 0.65)) {
    // Si la liste recommended est courte, on prend une partie d'other_pool
    $needed_for_rec = (int)round($limit * 0.65) - count($recommended_pool);
    $extra_for_rec = array_splice($other_pool, 0, $needed_for_rec);
    $recommended_pool = array_merge($recommended_pool, $extra_for_rec);
}

// Calcul du nombre exact pour le ratio 65% / 35%
$target_recommended_count = (int)round($limit * 0.65); // Ex: 13 sur 20
$target_random_count = $limit - $target_recommended_count; // Ex: 7 sur 20

// Sélectionner jusqu'à $target_recommended_count
$selected_recommended = array_splice($recommended_pool, 0, $target_recommended_count);

// Sélectionner jusqu'à $target_random_count
$selected_random = array_splice($other_pool, 0, $target_random_count);

// Si un des deux groupes a moins de produits que le target, compléter avec le reste des pools
if (count($selected_recommended) < $target_recommended_count) {
    $needed = $target_recommended_count - count($selected_recommended);
    $extra = array_splice($other_pool, 0, $needed);
    $selected_recommended = array_merge($selected_recommended, $extra);
}

if (count($selected_random) < $target_random_count) {
    $needed = $target_random_count - count($selected_random);
    $extra = array_splice($recommended_pool, 0, $needed);
    $selected_random = array_merge($selected_random, $extra);
}

// Assembler et entrelacer le lot (65% recommended, 35% random)
$batch = [];
$r_idx = 0;
$rand_idx = 0;

while ($r_idx < count($selected_recommended) || $rand_idx < count($selected_random)) {
    // Prendre jusqu'à 2 recommandés
    for ($i = 0; $i < 2 && $r_idx < count($selected_recommended); $i++) {
        $batch[] = $selected_recommended[$r_idx++];
    }
    // Prendre 1 aléatoire
    if ($rand_idx < count($selected_random)) {
        $batch[] = $selected_random[$rand_idx++];
    }
}

// Si la taille est inférieure au limit mais qu'il reste du monde dans recommended_pool ou details, compléter
$remaining_all = array_merge($recommended_pool, $other_pool);
while (count($batch) < $limit && !empty($remaining_all)) {
    $batch[] = array_shift($remaining_all);
}

// Mettre à jour la liste des IDs exclus
$new_exclude_count = count($exclude_ids) + count($batch);
$has_more = $new_exclude_count < $total_products_count && !empty($batch);

echo json_encode([
    'success'        => true,
    'products'       => $batch,
    'total_products' => $total_products_count,
    'loaded_count'   => $new_exclude_count,
    'has_more'       => $has_more
], JSON_UNESCAPED_UNICODE);
