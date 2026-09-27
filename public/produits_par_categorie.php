<?php
session_start();
require_once __DIR__ . '/../libs/db.php'; // Adaptez selon votre structure

// Initialisation des variables
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$title = 'Produits';
$sub_categories = null;
$sub_cat = null;
$result_products = null;
$sort_by = $_GET['sort_by'] ?? 'newest';
$price_min = floatval($_GET['price_min'] ?? 0);
$price_max = floatval($_GET['price_max'] ?? PHP_FLOAT_MAX);
$condition_filter = $_GET['condition'] ?? '';
$getFilterValues = static function (string $key): array {
    if (!isset($_GET[$key])) {
        return [];
    }

    $values = is_array($_GET[$key]) ? $_GET[$key] : explode(',', $_GET[$key]);
    return array_values(array_filter(array_map('trim', $values), static fn ($value) => $value !== ''));
};

$regions_filter = $getFilterValues('regions');
$colors_filter = $getFilterValues('colors');
$sizes_filter = $getFilterValues('sizes');
$has_discount = isset($_GET['has_discount']);

// Validation des paramètres
if (empty($type) || $id <= 0) {
    header('Location: index.php');
    exit;
}

if (!in_array($type, ['categ', 'sub'])) {
    header('Location: index.php');
    exit;
}

// Récupérer les informations de la catégorie/sous-catégorie
if ($type === 'categ') {
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: index.php');
        exit;
    }
    
    $category = $result->fetch_assoc();
    $title = $category['name'];
    
    // Récupérer les sous-catégories
    $sub_stmt = $conn->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name");
    $sub_stmt->bind_param("i", $id);
    $sub_stmt->execute();
    $sub_categories = $sub_stmt->get_result();
    
} else {
    // Type = sub
    $stmt = $conn->prepare("SELECT s.id, s.name, c.id as category_id, c.name as category_name 
                           FROM subcategories s 
                           JOIN categories c ON s.category_id = c.id 
                           WHERE s.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: index.php');
        exit;
    }
    
    $sub_cat = $result->fetch_assoc();
    $title = $sub_cat['name'];
}

// Construction de la requête des produits avec filtres
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];
$types = "";

// Filtre par catégorie/sous-catégorie
// Filtre par catégorie/sous-catégorie (recherche flexible sur id ou name dans tous les éléments du tableau JSON)
// Filtre par catégorie/sous-catégorie (recherche sur name ou sub dans tous les éléments)
if ($type === 'categ') {
    $catName = $category['name'] ?? $title;
    $sql .= " AND (
        JSON_SEARCH(category, 'one', ?, NULL, '$[*].name') IS NOT NULL
        OR JSON_SEARCH(category, 'one', ?, NULL, '$[*].sub') IS NOT NULL
    )";
    $params[] = $catName;
    $params[] = $catName;
    $types .= "ss";
} else {
    $subName = $sub_cat['name'] ?? $title;
    $sql .= " AND (
        JSON_SEARCH(category, 'one', ?, NULL, '$[*].sub') IS NOT NULL
        OR JSON_SEARCH(category, 'one', ?, NULL, '$[*].name') IS NOT NULL
    )";
    $params[] = $subName;
    $params[] = $subName;
    $types .= "ss";
}



// Filtre par prix
if ($price_min > 0) {
    $sql .= " AND price >= ?";
    $params[] = $price_min;
    $types .= "d";
}
if ($price_max < PHP_FLOAT_MAX) {
    $sql .= " AND price <= ?";
    $params[] = $price_max;
    $types .= "d";
}

// Filtre par état
if (!empty($condition_filter)) {
    $sql .= " AND product_condition = ?";
    $params[] = $condition_filter;
    $types .= "s";
}

// Filtre par régions
// Récupérer les régions depuis les produits de cette catégorie / sous-catégorie
$regions_query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(regions, '$[0]')) as region
                 FROM products WHERE JSON_EXTRACT(regions, '$[0]') IS NOT NULL";

if ($type === 'categ') {
    $catEsc = $conn->real_escape_string($category['name'] ?? $title);
    $regions_query .= " AND (
        JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].name') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].sub') IS NOT NULL
    )";
} else {
    $subEsc = $conn->real_escape_string($sub_cat['name'] ?? $title);
    $regions_query .= " AND (
        JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].sub') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].name') IS NOT NULL
    )";
}
$regions_query .= " ORDER BY region LIMIT 10";


// Filtre par couleurs
// Récupérer les tailles (shoe_sizes, child_sizes, adult_sizes) pour la catégorie / sous-catégorie
$sizes_query = "(" .
    "SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products, " .
    "JSON_TABLE(shoe_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes " .
    "WHERE shoe_sizes != '[]' ";

if ($type === 'categ') {
    $catEsc = $conn->real_escape_string($category['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].name') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].sub') IS NOT NULL
    )";
} else {
    $subEsc = $conn->real_escape_string($sub_cat['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].sub') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].name') IS NOT NULL
    )";
}
$sizes_query .= " LIMIT 5) UNION (" .
    "SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products, " .
    "JSON_TABLE(child_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes " .
    "WHERE child_sizes != '[]' ";

if ($type === 'categ') {
    $catEsc = $conn->real_escape_string($category['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].name') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].sub') IS NOT NULL
    )";
} else {
    $subEsc = $conn->real_escape_string($sub_cat['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].sub') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].name') IS NOT NULL
    )";
}
$sizes_query .= " LIMIT 5) UNION (" .
    "SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products, " .
    "JSON_TABLE(adult_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes " .
    "WHERE adult_sizes != '[]' ";

if ($type === 'categ') {
    $catEsc = $conn->real_escape_string($category['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].name') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$catEsc', NULL, '$[*].sub') IS NOT NULL
    )";
} else {
    $subEsc = $conn->real_escape_string($sub_cat['name'] ?? $title);
    $sizes_query .= " AND (
        JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].sub') IS NOT NULL
        OR JSON_SEARCH(category, 'one', '$subEsc', NULL, '$[*].name') IS NOT NULL
    )";
}
$sizes_query .= " LIMIT 5) LIMIT 10";

// Filtre par tailles
if (!empty($sizes_filter)) {
    $placeholders = implode(',', array_fill(0, count($sizes_filter), '?'));
    $sql .= " AND (
        EXISTS (SELECT 1 FROM JSON_TABLE(shoe_sizes, '$[*]' COLUMNS (size VARCHAR(50) PATH '$')) AS sz 
                WHERE sz.size IN ($placeholders)) OR
        EXISTS (SELECT 1 FROM JSON_TABLE(child_sizes, '$[*]' COLUMNS (size VARCHAR(50) PATH '$')) AS sz 
                WHERE sz.size IN ($placeholders)) OR
        EXISTS (SELECT 1 FROM JSON_TABLE(adult_sizes, '$[*]' COLUMNS (size VARCHAR(50) PATH '$')) AS sz 
                WHERE sz.size IN ($placeholders))
    )";
    $params = array_merge($params, $sizes_filter);
    $types .= str_repeat("s", count($sizes_filter));
}

// Filtre par réduction
if ($has_discount) {
    $sql .= " AND discount_percent > 0";
}

// Tri
switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY price DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Exécution de la requête
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result_products = $stmt->get_result();

// Fonction helper pour PHP (utilisée dans le JS)
function removeQueryParam($params) {
    $params = is_array($params) ? $params : [$params];
    $query = $_GET;
    foreach ($params as $param) {
        unset($query[$param]);
    }
    return 'produits_par_categorie.php?' . http_build_query($query);
}
?>

<!DOCTYPE html>
<html lang="fr" class="bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - ecascadeur.com</title>
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
    <div class="max-w-6xl mx-auto px-4 py-3 flex justify-center md:justify-start">
        <a href="index.php" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
            <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
            <span>ecascadeur<span class="text-fuchsia-500">.com</span></span>
        </a>
    </div>

    <div class="max-w-6xl mx-auto px-4 pb-4 flex items-center gap-3">
        <a href="index.php" class="p-2">
            <i class="bi bi-chevron-left text-lg text-gray-600"></i>
        </a>

        <div class="flex-1">
            <h1 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($title) ?></h1>
            <?php if ($type === 'sub'): ?>
                <p class="text-xs text-gray-500">Sous-catégorie de <?= htmlspecialchars($sub_cat['category_name']) ?></p>
            <?php endif; ?>
        </div>

        <button onclick="toggleFilters()" class="p-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-600">
            <i class="bi bi-sliders text-sm"></i>
        </button>
    </div>
</header>

<main class="max-w-6xl mx-auto p-4">
    <?php if ($type === 'categ' && $sub_categories->num_rows > 0): ?>
        <div class="mb-6">
            <h2 class="text-sm font-medium text-gray-900 mb-3">Sous-catégories</h2>
            <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-2">
                <?php while ($sub = $sub_categories->fetch_assoc()): ?>
                    <a href="produits_par_categorie.php?type=sub&id=<?= $sub['id'] ?>" 
                       class="flex-shrink-0 px-4 py-2 bg-white border border-gray-200 rounded-lg hover:border-fuchsia-300 hover:bg-fuchsia-50 transition-colors">
                        <span class="text-xs text-gray-700"><?= htmlspecialchars($sub['name']) ?></span>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

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
            <i class="bi bi-box-seam text-4xl text-gray-300"></i>
            <p class="mt-4 text-gray-500 text-sm">Aucun produit dans cette catégorie pour le moment.</p>
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
            <form id="filterForm" method="GET" action="produits_par_categorie.php">
                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                
                <section>
                    <label class="block text-xs text-gray-500 mb-3">Trier par</label>
                    <div class="grid grid-cols-2 gap-3">
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

                <?php
                // Récupérer les régions depuis les produits de cette catégorie
                $regions_query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(regions, '$[0]')) as region
                                 FROM products WHERE JSON_EXTRACT(regions, '$[0]') IS NOT NULL";
                if ($type === 'categ') {
                    $regions_query .= " AND JSON_EXTRACT(category, '$[0].id') = '$id'";
                } else {
                    $regions_query .= " AND JSON_EXTRACT(category, '$[1].id') = '$id'";
                }
                $regions_query .= " ORDER BY region LIMIT 10";
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

                <?php
                // Récupérer les couleurs
                $colors_query = "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(colors, '$[0].hex')) as color
                                FROM products WHERE JSON_EXTRACT(colors, '$[0].hex') IS NOT NULL
                                AND JSON_UNQUOTE(JSON_EXTRACT(colors, '$[0].hex')) != ''";
                if ($type === 'categ') {
                    $colors_query .= " AND JSON_EXTRACT(category, '$[0].id') = '$id'";
                } else {
                    $colors_query .= " AND JSON_EXTRACT(category, '$[1].id') = '$id'";
                }
                $colors_query .= " LIMIT 8";
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

                <?php
                // Récupérer les tailles
                $sizes_query = "(
                    SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                    JSON_TABLE(shoe_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                    WHERE shoe_sizes != '[]'";
                if ($type === 'categ') {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[0].id') = '$id'";
                } else {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[1].id') = '$id'";
                }
                $sizes_query .= " LIMIT 5
                ) UNION (
                    SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                    JSON_TABLE(child_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                    WHERE child_sizes != '[]'";
                if ($type === 'categ') {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[0].id') = '$id'";
                } else {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[1].id') = '$id'";
                }
                $sizes_query .= " LIMIT 5
                ) UNION (
                    SELECT DISTINCT JSON_UNQUOTE(value) as size FROM products,
                    JSON_TABLE(adult_sizes, '$[*]' COLUMNS (value VARCHAR(50) PATH '$')) AS sizes
                    WHERE adult_sizes != '[]'";
                if ($type === 'categ') {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[0].id') = '$id'";
                } else {
                    $sizes_query .= " AND JSON_EXTRACT(category, '$[1].id') = '$id'";
                }
                $sizes_query .= " LIMIT 5
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
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextMain = nextDocument.querySelector('main');
        const nextFilterMenu = nextDocument.querySelector('#filterMenu');
        const currentMain = document.querySelector('main');
        const currentFilterMenu = document.querySelector('#filterMenu');

        if (!nextMain || !nextFilterMenu || !currentMain || !currentFilterMenu) {
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
    updateResults(`produits_par_categorie.php?type=<?= $type ?>&id=<?= $id ?>`, true);
}

document.addEventListener('submit', function(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.id !== 'filterForm') {
        return;
    }

    event.preventDefault();
    const url = new URL(form.action, window.location.origin);
    url.search = new URLSearchParams(new FormData(form)).toString();
    updateResults(url.toString(), true);
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
    
    <?php if(!empty($regions_filter)): ?>
        activeFilters.push({
            name: 'Régions',
            value: '<?= implode(", ", $regions_filter) ?>',
            clearUrl: '<?= removeQueryParam('regions') ?>'
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
        
        html += `<a href="produits_par_categorie.php?type=<?= $type ?>&id=<?= $id ?>" class="text-gray-500 hover:text-fuchsia-600 ml-2">
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

?>
</body>
</html>