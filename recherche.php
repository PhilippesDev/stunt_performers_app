<?php
require_once 'db.php';
require_once 'user_helper.php';

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (empty($search_query)) {
    header('Location: index.php');
    exit();
}

$sort_by = $_GET['sort_by'] ?? 'relevance';
$getFilterValues = static function (string $key): array {
    if (!isset($_GET[$key])) {
        return [];
    }

    $values = is_array($_GET[$key]) ? $_GET[$key] : explode(',', $_GET[$key]);
    return array_values(array_filter(array_map('trim', $values), static fn ($value) => $value !== ''));
};

$regions_filter = $getFilterValues('regions');
$categories_filter = $getFilterValues('categories');
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : PHP_FLOAT_MAX;
$condition_filter = $_GET['condition'] ?? '';
$colors_filter = $getFilterValues('colors');
$sizes_filter = $getFilterValues('sizes');
$has_discount = isset($_GET['has_discount']) && $_GET['has_discount'] == '1';

// --- CONFIGURATION DU MOTIF REGEXP TOLÉRANT ---
$sanitized_search = preg_replace('/[^a-zA-Z0-9áàâäãåçéèêëíìîïñóòôöõúùûüýÿ]/u', '', $search_query);

if (mb_strlen($sanitized_search) >= 4) {
    // FIX 1 : On utilise mb_str_split pour préserver les caractères UTF-8 accentués
    $parts = mb_str_split($sanitized_search, 2);
    $regex_pattern = implode('.*', $parts);
} else {
    $regex_pattern = $sanitized_search;
}

// FIX 2 : Requête restructurée pour la précision (LIKE gère les accents, REGEXP gère les fautes sur le nom uniquement)
$query = "SELECT p.*,
          (SELECT file_path FROM product_media WHERE product_id = p.id AND file_type = 'image' ORDER BY sort_order ASC LIMIT 1) as main_image,
          (SELECT COUNT(*) FROM product_media WHERE product_id = p.id AND file_type = 'video') as has_video,
          -- Score de pertinence optimisé
          (CASE 
            WHEN p.name LIKE ? THEN 10 
            WHEN p.name REGEXP ? THEN 5
            ELSE 1 
          END) as score
          FROM products p
          WHERE (p.name LIKE ? OR p.description LIKE ? OR p.name REGEXP ?)";

// Préparation des variables de liaison (Attention à l'ordre des placeholders '?' dans la requête)
$like_exact = "%" . $search_query . "%";
$params = [
    $like_exact,     // Pour le CASE WHEN p.name LIKE
    $regex_pattern,  // Pour le CASE WHEN p.name REGEXP
    $like_exact,     // Pour le WHERE p.name LIKE
    $like_exact,     // Pour le WHERE p.description LIKE
    $regex_pattern   // Pour le WHERE p.name REGEXP (Tolérance fautes d'orthographe)
];
$types = "sssss";

// Filtres régions (multi)
if (!empty($regions_filter)) {
    $region_conditions = [];
    foreach ($regions_filter as $region) {
        $region_conditions[] = "JSON_CONTAINS(p.regions, ?)";
        $params[] = json_encode(trim($region));
        $types .= "s";
    }
    $query .= " AND (" . implode(' OR ', $region_conditions) . ")";
}

// Filtres catégories (multi)
if (!empty($categories_filter)) {
    $cat_conditions = [];
    foreach ($categories_filter as $cat) {
        $cat_conditions[] = "JSON_CONTAINS(p.category, ?)";
        $params[] = json_encode(trim($cat));
        $types .= "s";
    }
    $query .= " AND (" . implode(' OR ', $cat_conditions) . ")";
}

// Prix range
if ($price_min > 0 || $price_max < PHP_FLOAT_MAX) {
    $query .= " AND p.price BETWEEN ? AND ?";
    $params[] = $price_min;
    $params[] = $price_max;
    $types .= "dd";
}

// Condition
if (!empty($condition_filter)) {
    $query .= " AND p.product_condition = ?";
    $params[] = $condition_filter;
    $types .= "s";
}

// Couleurs (multi)
if (!empty($colors_filter)) {
    $color_conditions = [];
    foreach ($colors_filter as $color) {
        $color_conditions[] = "JSON_SEARCH(p.colors, 'one', ?) IS NOT NULL";
        $params[] = $color;
        $types .= "s";
    }
    $query .= " AND (" . implode(' OR ', $color_conditions) . ")";
}

// Tailles (multi JSON)
if (!empty($sizes_filter)) {
    $size_conditions = [];
    foreach ($sizes_filter as $size) {
        $size_conditions[] = "JSON_CONTAINS(p.shoe_sizes, ?) OR JSON_CONTAINS(p.child_sizes, ?) OR JSON_CONTAINS(p.adult_sizes, ?)";
        $params[] = json_encode(trim($size));
        $params[] = json_encode(trim($size));
        $params[] = json_encode(trim($size));
        $types .= "sss";
    }
    $query .= " AND (" . implode(' OR ', $size_conditions) . ")";
}

// Réduction disponible
if ($has_discount) {
    $query .= " AND p.discount_percent > 0";
}

// Tri dynamique basé sur le score calculé ou le prix
switch ($sort_by) {
    case 'price_asc': $query .= " ORDER BY p.price ASC"; break;
    case 'price_desc': $query .= " ORDER BY p.price DESC"; break;
    case 'newest': $query .= " ORDER BY p.created_at DESC"; break;
    case 'relevance': default: $query .= " ORDER BY score DESC, p.created_at DESC"; break;
}

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result_products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultats pour "<?= htmlspecialchars($search_query) ?>"</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .color-swatch { width: 10px; height: 10px; border-radius: 50%; border: 1px solid #ddd; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .results-loading { position: fixed; inset: 0 0 auto; height: 3px; z-index: 60; overflow: hidden; background: #fce7f3; }
        .results-loading::after { content: ''; display: block; width: 40%; height: 100%; background: #d946ef; animation: results-progress 1s ease-in-out infinite; }
        @keyframes results-progress { from { transform: translateX(-100%); } to { transform: translateX(350%); } }
        [aria-busy="true"] { opacity: .65; pointer-events: none; transition: opacity .15s ease; }
        @media (prefers-reduced-motion: reduce) { .results-loading::after { animation: none; width: 100%; } }
    </style>
</head>
<body class="pb-24">

<header class="sticky top-0 z-50 bg-white border-b border-gray-100">
    <!-- Logo -->
    <div class="max-w-6xl mx-auto px-4 py-3 flex justify-center md:justify-start">
        <a href="index.php" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
            <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
            <span>ecascadeur<span class="text-fuchsia-500">.com</span></span>
        </a>
    </div>

    <!-- Barre recherche -->
    <div class="max-w-6xl mx-auto px-4 pb-4 flex items-center gap-3">
        <a href="index.php" class="p-2">
            <i class="bi bi-chevron-left text-lg text-gray-600"></i>
        </a>

        <form class="flex-1 relative" method="GET" action="recherche.php">
            <input
                placeholder="Cherchez un produit sur ecascadeur"
                type="text"
                name="search"
                value="<?= htmlspecialchars($search_query) ?>"
                class="w-full bg-gray-50 border border-gray-200 rounded-lg py-2 pl-10 pr-4 text-sm text-gray-800 focus:border-fuchsia-300 focus:ring-0 outline-none"
            >
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
        </form>

        <button onclick="toggleFilters()" class="p-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600">
            <i class="bi bi-sliders text-sm"></i>
        </button>
    </div>
</header>

    <main class="max-w-6xl mx-auto p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-gray-500 text-sm">
                <?= $result_products->num_rows ?> produits trouvés
            </h2>
            <div class="flex gap-2">
                <span class="px-2 py-1 bg-gray-50 border border-gray-200 rounded-md text-xs text-gray-600">
                    <?= htmlspecialchars($sort_by) ?>
                </span>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php while ($product = $result_products->fetch_assoc()) :
                $img = !empty($product['main_image']) ? $product['main_image'] : 'img/placeholder.jpg';
                $regions = json_decode($product['regions'] ?? '[]', true) ?: [];
                $colors = json_decode($product['colors'] ?? '[]', true) ?: [];
                $specs = json_decode($product['specifications'] ?? '[]', true) ?: [];
                $shoe_sizes = json_decode($product['shoe_sizes'] ?? '[]', true) ?: [];
                $child_sizes = json_decode($product['child_sizes'] ?? '[]', true) ?: [];
                $adult_sizes = json_decode($product['adult_sizes'] ?? '[]', true) ?: [];
                $all_sizes = array_merge($shoe_sizes, $child_sizes, $adult_sizes);
                $has_discount = $product['discount_percent'] > 0;
                $discounted_price = $has_discount ? $product['price'] * (1 - $product['discount_percent']/100) : $product['price'];
            ?>
                <div class="bg-white rounded-lg overflow-hidden border border-gray-100">
                    <div class="relative aspect-square overflow-hidden bg-gray-50">
                        <img src="<?= $img ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php if($product['product_condition'] == 'neuf'): ?>
                            <span class="absolute top-2 left-2 bg-green-100 text-green-600 text-xs px-2 py-0.5 rounded-md">Neuf</span>
                        <?php elseif($product['product_condition'] == 'used'): ?>
                            <span class="absolute top-2 left-2 bg-yellow-100 text-yellow-600 text-xs px-2 py-0.5 rounded-md" title="Défauts: <?= htmlspecialchars(implode(', ', json_decode($product['defects'] ?? '[]', true))) ?>">Occasion</span>
                        <?php endif; ?>
                        <?php if($product['has_video'] > 0): ?>
                            <i class="bi bi-play-circle absolute bottom-2 left-2 text-gray-800 text-sm"></i>
                        <?php endif; ?>
                        <?php if($has_discount): ?>
                            <span class="absolute top-2 right-2 bg-red-100 text-red-600 text-xs px-2 py-0.5 rounded-md">-<?= $product['discount_percent'] ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3">
                        <h3 class="text-gray-900 text-sm line-clamp-1 mb-1"><?= htmlspecialchars($product['name']) ?></h3>
                        <div class="flex items-baseline gap-1 mb-1">
                            <?php if($has_discount): ?>
                                <span class="text-gray-500 text-xs line-through"><?= number_format($product['price'], 2) ?> <?= $product['currency'] ?></span>
                                <span class="text-fuchsia-600 text-sm"><?= number_format($discounted_price, 2) ?></span>
                                <span class="text-fuchsia-600 text-xs"><?= $product['currency'] ?></span>
                            <?php else: ?>
                                <span class="text-fuchsia-600 text-sm"><?= number_format($product['price'], 2) ?></span>
                                <span class="text-fuchsia-600 text-xs"><?= $product['currency'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-gray-500 mb-1"><?= number_format($product['quantity'], 2) ?> <?= $product['unit_type'] ?> disponibles</div>
                        <div class="flex gap-1 mb-1">
                            <?php foreach(array_slice($colors, 0, 4) as $c): ?>
                                <div class="color-swatch" style="background-color: <?= $c['hex'] ?>;" title="<?= $c['hex'] ?>"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-xs text-gray-400 mb-1">Tailles: <?= implode(', ', array_slice($all_sizes, 0, 3)) ?: 'N/A' ?>...</div>
                        <div class="text-xs text-gray-500 mb-2">Specs: <?= htmlspecialchars(implode(', ', array_slice(array_map(fn($s) => $s['key'].': '.$s['value'], $specs), 0, 2))) ?: 'N/A' ?></div>
                        <div class="flex gap-1 overflow-x-auto hide-scrollbar mb-2">
                            <?php foreach(array_slice($regions, 0, 3) as $r): ?>
                                <span class="text-xs bg-gray-50 text-gray-500 px-2 py-0.5 rounded-md border border-gray-100 whitespace-nowrap">
                                    <i class="bi bi-geo-alt text-xs"></i> <?= $r ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <a href="buy_product.php?id=<?= $product['id'] ?>"
                           class="block w-full py-2 bg-gray-800 text-white text-center rounded-lg text-xs hover:bg-fuchsia-500">
                            Voir l'offre
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php if ($result_products->num_rows === 0): ?>
            <div class="text-center py-16">
                <i class="bi bi-search text-4xl text-gray-300"></i>
                <p class="mt-4 text-gray-500 text-sm">Aucun résultat pour votre recherche.</p>
                <a href="index.php" class="text-fuchsia-600 text-sm">Retourner à l'accueil</a>
            </div>
        <?php endif; ?>
    </main>
    <div id="filterMenu" class="fixed inset-0 bg-black/50 z-50 hidden">
        <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl flex flex-col max-h-[80vh]">
            <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto my-3"></div>
            <div class="px-6 pb-3 flex justify-between items-center">
                <h3 class="text-lg text-gray-900">Filtres</h3>
                <button type="button" onclick="toggleFilters()" class="text-gray-500">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-3 space-y-6">
                <form id="filterForm" method="GET" action="recherche.php">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                    <!-- Section Tri -->
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Trier par</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label>
                                <input type="radio" name="sort_by" value="relevance" <?= $sort_by === 'relevance' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 text-center cursor-pointer">
                                    Pertinence
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="sort_by" value="newest" <?= $sort_by === 'newest' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 text-center cursor-pointer">
                                    Plus récents
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="sort_by" value="price_asc" <?= $sort_by === 'price_asc' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 text-center cursor-pointer">
                                    Prix croissant
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="sort_by" value="price_desc" <?= $sort_by === 'price_desc' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 text-center cursor-pointer">
                                    Prix décroissant
                                </div>
                            </label>
                        </div>
                    </section>
                    <!-- Section Prix -->
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Budget</label>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">$</span>
                                <input type="number" name="price_min" value="<?= $price_min > 0 ? $price_min : '' ?>"
                                       placeholder="Min" min="0" step="0.01"
                                       class="w-full pl-6 pr-3 py-2 bg-gray-50 rounded-lg border border-gray-200 focus:border-fuchsia-300 focus:ring-0 text-xs text-gray-800">
                            </div>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-xs">$</span>
                                <input type="number" name="price_max" value="<?= $price_max < PHP_FLOAT_MAX ? $price_max : '' ?>"
                                       placeholder="Max" min="0" step="0.01"
                                       class="w-full pl-6 pr-3 py-2 bg-gray-50 rounded-lg border border-gray-200 focus:border-fuchsia-300 focus:ring-0 text-xs text-gray-800">
                            </div>
                        </div>
                    </section>
                    <!-- Section Condition -->
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">État</label>
                        <div class="flex gap-3">
                            <label>
                                <input type="radio" name="condition" value="new" <?= $condition_filter === 'new' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 cursor-pointer">
                                    Neuf
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="condition" value="used" <?= $condition_filter === 'used' ? 'checked' : '' ?> class="hidden peer">
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 cursor-pointer">
                                    Seconde main
                                </div>
                            </label>
                        </div>
                    </section>
                    <!-- Section Catégories -->
                    <?php
                    // Récupérer les catégories depuis la base de données
                    $cats_query = "SELECT DISTINCT JSON_EXTRACT(category, '$[0].name') as cat_name
                                  FROM products WHERE JSON_EXTRACT(category, '$[0].name') IS NOT NULL
                                  LIMIT 10";
                    $cats_result = $conn->query($cats_query);
                    if ($cats_result && $cats_result->num_rows > 0):
                    ?>
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Catégories</label>
                        <div class="flex flex-wrap gap-2">
                            <?php while($cat = $cats_result->fetch_assoc()):
                                $cat_name = trim($cat['cat_name'], '"');
                                if(empty($cat_name)) continue;
                                $is_selected = in_array($cat_name, $categories_filter);
                            ?>
                            <label>
                                <input type="checkbox" class="hidden peer" name="categories[]" value="<?= htmlspecialchars($cat_name) ?>"
                                       <?= $is_selected ? 'checked' : '' ?>>
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 cursor-pointer">
                                    <?= htmlspecialchars($cat_name) ?>
                                </div>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <!-- Section Régions -->
                    <?php
                    // Récupérer les régions depuis la base de données
                    $regions_query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(regions, '$[0]')) as region
                                     FROM products WHERE JSON_EXTRACT(regions, '$[0]') IS NOT NULL
                                     ORDER BY region LIMIT 10";
                    $regions_result = $conn->query($regions_query);
                    if ($regions_result && $regions_result->num_rows > 0):
                    ?>
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Régions</label>
                        <div class="flex flex-wrap gap-2">
                            <?php while($region = $regions_result->fetch_assoc()):
                                $region_name = trim($region['region'], '"');
                                if(empty($region_name)) continue;
                                $is_selected = in_array($region_name, $regions_filter);
                            ?>
                            <label>
                                <input type="checkbox" class="hidden peer" name="regions[]" value="<?= htmlspecialchars($region_name) ?>"
                                       <?= $is_selected ? 'checked' : '' ?>>
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 cursor-pointer flex items-center gap-1">
                                    <i class="bi bi-geo-alt text-xs"></i>
                                    <?= htmlspecialchars($region_name) ?>
                                </div>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <!-- Section Couleurs -->
                    <?php
                    // Récupérer les couleurs populaires
                    $colors_query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(colors, '$[0].hex')) as color
                                    FROM products WHERE JSON_EXTRACT(colors, '$[0].hex') IS NOT NULL
                                    AND JSON_UNQUOTE(JSON_EXTRACT(colors, '$[0].hex')) != ''
                                    LIMIT 8";
                    $colors_result = $conn->query($colors_query);
                    if ($colors_result && $colors_result->num_rows > 0):
                    ?>
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Couleurs</label>
                        <div class="flex flex-wrap gap-3">
                            <?php while($color = $colors_result->fetch_assoc()):
                                $color_hex = trim($color['color'], '"');
                                if(empty($color_hex) || !preg_match('/^#[0-9A-F]{6}$/i', $color_hex)) continue;
                                $is_selected = in_array($color_hex, $colors_filter);
                            ?>
                            <label class="relative">
                                <input type="checkbox" class="hidden peer" name="colors[]" value="<?= htmlspecialchars($color_hex) ?>"
                                       <?= $is_selected ? 'checked' : '' ?>>
                                <div class="w-8 h-8 rounded-full border border-gray-200 peer-checked:border-fuchsia-300 cursor-pointer"
                                     style="background-color: <?= $color_hex ?>">
                                    <?php if($is_selected): ?>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <i class="bi bi-check text-white text-xs"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <!-- Section Tailles -->
                    <?php
                    // Récupérer les tailles populaires
                    $sizes_query = "(
                        SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                        JSON_TABLE(shoe_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                        WHERE shoe_sizes != '[]' LIMIT 5
                    ) UNION (
                        SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                        JSON_TABLE(child_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                        WHERE child_sizes != '[]' LIMIT 5
                    ) UNION (
                        SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                        JSON_TABLE(adult_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                        WHERE adult_sizes != '[]' LIMIT 5
                    ) LIMIT 10";
                    $sizes_result = $conn->query($sizes_query);
                    if ($sizes_result && $sizes_result->num_rows > 0):
                    ?>
                    <section>
                        <label class="block text-xs text-gray-500 mb-3">Tailles</label>
                        <div class="flex flex-wrap gap-2">
                            <?php while($size = $sizes_result->fetch_assoc()):
                                $size_name = trim($size['size']);
                                if(empty($size_name)) continue;
                                $is_selected = in_array($size_name, $sizes_filter);
                            ?>
                            <label>
                                <input type="checkbox" class="hidden peer" name="sizes[]" value="<?= htmlspecialchars($size_name) ?>"
                                       <?= $is_selected ? 'checked' : '' ?>>
                                <div class="px-3 py-2 rounded-lg border border-gray-200 peer-checked:border-fuchsia-300 peer-checked:bg-fuchsia-50 text-xs text-gray-600 peer-checked:text-fuchsia-600 cursor-pointer">
                                    <?= htmlspecialchars($size_name) ?>
                                </div>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <!-- Section Réduction -->
                    <section>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="has_discount" value="1" <?= $has_discount ? 'checked' : '' ?>
                                   class="hidden peer">
                            <div class="w-4 h-4 border border-gray-300 rounded peer-checked:bg-fuchsia-300 peer-checked:border-fuchsia-300 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white hidden peer-checked:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-xs text-gray-700">Avec réduction</span>
                        </label>
                    </section>
                </form>
            </div>
            <div class="p-6 border-t border-gray-200 flex gap-3">
                <button type="button" onclick="clearFilters()"
                        class="flex-1 py-3 border border-gray-200 text-gray-600 rounded-lg text-sm hover:bg-gray-50">
                    Réinitialiser
                </button>
                <button form="filterForm" type="submit" onclick="toggleFilters()"
                        class="flex-1 py-3 bg-fuchsia-500 text-white rounded-lg text-sm hover:bg-fuchsia-600">
                    Appliquer
                </button>
            </div>
        </div>
    </div>
    <script>
    let resultsRequest = null;

    function toggleFilters() {
        const menu = document.getElementById('filterMenu');
        menu.classList.toggle('hidden');
    }

    function setResultsLoading(isLoading) {
        const main = document.querySelector('main');
        const existingLoader = document.querySelector('.results-loading');

        if (isLoading) {
            if (!existingLoader) {
                document.body.insertAdjacentHTML('afterbegin', '<div class="results-loading" role="progressbar" aria-label="Mise à jour des résultats"></div>');
            }
            main?.setAttribute('aria-busy', 'true');
        } else {
            existingLoader?.remove();
            main?.removeAttribute('aria-busy');
        }
    }

    async function updateResults(url, closeFilters = false) {
        if (resultsRequest) {
            resultsRequest.abort();
        }

        resultsRequest = new AbortController();
        setResultsLoading(true);

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: resultsRequest.signal
            });

            if (!response.ok) {
                throw new Error('Impossible de mettre à jour les résultats.');
            }

            const html = await response.text();
            const documentParser = new DOMParser();
            const nextDocument = documentParser.parseFromString(html, 'text/html');
            const nextMain = nextDocument.querySelector('main');
            const nextFilterMenu = nextDocument.querySelector('#filterMenu');
            const currentMain = document.querySelector('main');
            const currentFilterMenu = document.querySelector('#filterMenu');

            if (!nextMain || !currentMain || !nextFilterMenu || !currentFilterMenu) {
                throw new Error('Réponse de recherche invalide.');
            }

            currentMain.replaceWith(nextMain);
            currentFilterMenu.replaceWith(nextFilterMenu);
            window.history.pushState({}, '', url);

            if (closeFilters) {
                document.getElementById('filterMenu')?.classList.add('hidden');
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.alert(error.message || 'Une erreur est survenue.');
            }
        } finally {
            resultsRequest = null;
            setResultsLoading(false);
        }
    }

    function clearFilters() {
        updateResults(`recherche.php?search=<?= urlencode($search_query) ?>`, true);
    }

    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (form.matches('form[action="recherche.php"], #filterForm')) {
            event.preventDefault();
            const url = new URL(form.action || window.location.href, window.location.origin);
            const formData = new FormData(form);
            url.search = new URLSearchParams(formData).toString();
            updateResults(url.toString(), form.id === 'filterForm');
        }
    });

    window.addEventListener('popstate', function() {
        updateResults(window.location.href);
    });
    document.addEventListener('DOMContentLoaded', function() {
        const activeFilters = [];
        
        <?php if($price_min > 0 || $price_max < PHP_FLOAT_MAX): ?>
            activeFilters.push({
                name: 'Prix',
                value: '<?= $price_min > 0 ? $price_min : "Min" ?> - <?= $price_max < PHP_FLOAT_MAX ? $price_max : "Max" ?>',
                clearUrl: '<?= removeQueryParam(['price_min', 'price_max']) ?>'
            });
        <?php endif; ?>
        
        <?php if(!empty($condition_filter)): ?>
            activeFilters.push({
                name: 'État',
                value: '<?= $condition_filter === "new" ? "Neuf" : "Seconde main" ?>',
                clearUrl: '<?= removeQueryParam('condition') ?>'
            });
        <?php endif; ?>
        
        <?php if(!empty($categories_filter)): ?>
            activeFilters.push({
                name: 'Catégories',
                value: '<?= implode(", ", $categories_filter) ?>',
                clearUrl: '<?= removeQueryParam('categories') ?>'
            });
        <?php endif; ?>
        
        <?php if($has_discount): ?>
            activeFilters.push({
                name: 'Réduction',
                value: 'Avec promo',
                clearUrl: '<?= removeQueryParam('has_discount') ?>'
            });
        <?php endif; ?>
        
        if (activeFilters.length > 0) {
            const filtersContainer = document.createElement('div');
            filtersContainer.className = 'mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200';
            
            let html = '<div class="flex flex-wrap items-center gap-2 text-xs">';
            html += '<span class="text-gray-500">Filtres actifs:</span>';
            
            activeFilters.forEach(filter => {
                html += `
                    <span class="inline-flex items-center gap-1 bg-fuchsia-50 text-fuchsia-600 px-2 py-1 rounded-md">
                        ${filter.name}: ${filter.value}
                        <a href="${filter.clearUrl}" class="hover:text-fuchsia-800">&times;</a>
                    </span>
                `;
            });
            
            html += `<a href="recherche.php?search=<?= urlencode($search_query) ?>" class="text-gray-500 hover:text-fuchsia-600 ml-2">
                        Tout effacer
                     </a>`;
            html += '</div>';
            
            filtersContainer.innerHTML = html;
            
            const resultsHeader = document.querySelector('main .flex.justify-between.items-center.mb-4');
            if (resultsHeader) {
                resultsHeader.parentNode.insertBefore(filtersContainer, resultsHeader.nextSibling);
            }
        }
    });
    function removeQueryParam(paramName) {
        const url = new URL(window.location.href);
        if (Array.isArray(paramName)) {
            paramName.forEach(param => url.searchParams.delete(param));
        } else {
            url.searchParams.delete(paramName);
        }
        return url.toString();
    }
    </script>
    <?php
    // Fonction helper pour PHP
    function removeQueryParam($params) {
        $params = is_array($params) ? $params : [$params];
        $query = $_GET;
        foreach ($params as $param) {
            unset($query[$param]);
        }
        return 'recherche.php?' . http_build_query($query);
    }
    ?>
</body>
</html>