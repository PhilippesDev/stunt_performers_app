<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../libs/Router.php';
require_once __DIR__ . '/../libs/db.php';
require_once __DIR__ . '/../libs/helpers/notification_helper.php';

// Expiration automatique des commandes en attente > 2h
check_and_expire_pending_orders($conn);

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id && isset($_COOKIE['user_id'])) {
    $cookie_user_id = intval($_COOKIE['user_id']);
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $cookie_user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) === 1) {
        $_SESSION['user_id'] = $cookie_user_id;
        $user_id = $cookie_user_id;
    }
    mysqli_stmt_close($stmt);
}
$user_role = null;
$profile_pic = "images/img/avatar.jpg";
$order_count = 0;
$unread_notifs_count = 0;
$user_notifications = [];

if ($user_id) {
    // Récupérer infos user
    $stmt = mysqli_prepare($conn, "SELECT role, profile_pic FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $user_role, $profile_pic);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Notifications de l'utilisateur
    $unread_notifs_count = get_unread_notifications_count($conn, $user_id);
    $user_notifications = get_user_notifications($conn, $user_id, 15);

    // Si seller → compter les commandes reçues
    if ($user_role === 'seller' || $user_role === 'buyer') {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $order_count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
}

// avatar fallback
$avatar = ($profile_pic && file_exists($profile_pic))
    ? $profile_pic
    : "images/img/avatar.jpg";

$profile_link = $user_id ? url('dashboard') : url('login');

// IMPLEMENTATION DE LA CATEGORIE
$categories = [];

/* Catégories */
$sql = "SELECT id, name, description, image FROM categories ORDER BY name ASC";
$res = mysqli_query($conn, $sql);

while ($cat = mysqli_fetch_assoc($res)) {
    /* Sous-catégories */
    $subs = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id ,name FROM subcategories WHERE category_id = ? ORDER BY name ASC"
    );
    mysqli_stmt_bind_param($stmt, "i", $cat['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $sub_id, $sub_name);

    $sub = [];

    while (mysqli_stmt_fetch($stmt)) {
             $subs[] = [
        "id"   => $sub_id,
        "name" => $sub_name
    ];
    }
    mysqli_stmt_close($stmt);

    /* ARRAY d'objets (clé = name dans l'objet) */
    $categories[] = [
        "id"    => $cat['id'], 
        "name"  => $cat['name'],
        "image" => $cat['image'] ?? 'https://via.placeholder.com/150',
        "desc"  => $cat['description'],
        "links" => $subs
    ];
}

// Dans votre bloc PHP, remplacez la ligne $categories_json par :
$categories_json = htmlspecialchars(json_encode($categories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');


// IMPLEMENTATION NOUVEAUTE FLASH
// Récupérer les 100 derniers produits
$sql = "SELECT p.id, p.name, p.price, p.currency, p.discount_percent, p.category, pm.file_path, pm.file_type
        FROM products p
        LEFT JOIN product_media pm ON pm.product_id = p.id
        ORDER BY p.created_at DESC, pm.sort_order ASC
        LIMIT 100";

$res = mysqli_query($conn, $sql);

$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $id = $row['id'];

    if (!isset($products[$id])) {
        $products[$id] = [
            "id" => $id,
            "name" => $row['name'],
            "price" => $row['price'],
            "currency" => $row['currency'],
            "discount" => $row['discount_percent'] ?: null,
            "category" => json_decode($row['category'])[0] ?? "Autres",
            "media" => []
        ];
    }

    if ($row['file_path']) {
        $products[$id]['media'][] = [
            "type" => $row['file_type'],
            "url" => $row['file_path'],
            "duration" => 10000 // 10s par défaut
        ];
    }
}

// Réindexer pour JS
$products = array_values($products);

// Convertir en JSON
$products_json =  htmlspecialchars(json_encode($products, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

// Récupérer TOUS les produits pour le feed avec la crédibilité vendeur
$sql_all = "SELECT p.id, p.name, p.price, p.currency, p.discount_percent, p.product_condition, p.category, 
                   pm.file_path, p.regions, COALESCE(u.credibility_score, 100) AS credibility_score
            FROM products p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN product_media pm ON pm.product_id = p.id
            ORDER BY u.credibility_score DESC, p.created_at DESC, pm.sort_order ASC";

$res_all = mysqli_query($conn, $sql_all);

$all_products_list = [];
while ($row = mysqli_fetch_assoc($res_all)) {
    $id = $row['id'];

    if (!isset($all_products_list[$id])) {
        $all_products_list[$id] = [
            "id"          => $id,
            "name"        => $row['name'],
            "price"       => $row['price'],
            "currency"    => $row['currency'],
            "discount"    => $row['discount_percent'] ?: null,
            "condition"   => ($row['product_condition'] === 'new') ? 'Neuf' : 'Occasion',
            "credibility" => intval($row['credibility_score'] ?? 100),
            "image"       => null,
            "category"    => json_decode($row['category'])[0] ?? "Autres",
            "regions"     => json_decode($row['regions'], true) ?: []
        ];
    }

    if (empty($all_products_list[$id]['image']) && $row['file_path']) {
        $all_products_list[$id]['image'] = $row['file_path'];
    }
}

// Séparer les produits pour la section "Pour toi" (65% recommandés, 35% aléatoires)
$all_products = array_values($all_products_list);
$total_products_count = count($all_products);

$user_preferences = isset($_COOKIE['userPreferences']) ? json_decode($_COOKIE['userPreferences'], true) : [];

$preferred_categories = [];
if (!empty($user_preferences['categories']) && is_array($user_preferences['categories'])) {
    $preferred_categories = array_keys($user_preferences['categories']);
}

$recommended_pool = [];
$other_pool = [];

foreach ($all_products as $product) {
    if (!empty($preferred_categories) && in_array($product['category'], $preferred_categories, true)) {
        $recommended_pool[] = $product;
    } else {
        $other_pool[] = $product;
    }
}

// Tri par score de crédibilité vendeur décroissant (les vendeurs à 100% sont prioritaires sur Pour toi)
usort($recommended_pool, function($a, $b) {
    return ($b['credibility'] <=> $a['credibility']);
});
usort($other_pool, function($a, $b) {
    return ($b['credibility'] <=> $a['credibility']);
});

$limit = 20;
if (count($recommended_pool) < round($limit * 0.65)) {
    $needed_for_rec = (int)round($limit * 0.65) - count($recommended_pool);
    $extra_for_rec = array_splice($other_pool, 0, $needed_for_rec);
    $recommended_pool = array_merge($recommended_pool, $extra_for_rec);
}

$target_recommended_count = (int)round($limit * 0.65);
$target_random_count = $limit - $target_recommended_count;

$selected_recommended = array_splice($recommended_pool, 0, $target_recommended_count);
$selected_random = array_splice($other_pool, 0, $target_random_count);

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

$initial_pour_toi = [];
$r_idx = 0;
$rand_idx = 0;

while ($r_idx < count($selected_recommended) || $rand_idx < count($selected_random)) {
    for ($i = 0; $i < 2 && $r_idx < count($selected_recommended); $i++) {
        $initial_pour_toi[] = $selected_recommended[$r_idx++];
    }
    if ($rand_idx < count($selected_random)) {
        $initial_pour_toi[] = $selected_random[$rand_idx++];
    }
}

$remaining_all = array_merge($recommended_pool, $other_pool);
while (count($initial_pour_toi) < $limit && !empty($remaining_all)) {
    $initial_pour_toi[] = array_shift($remaining_all);
}

// Convertir en JSON
$pour_toi_json = htmlspecialchars(json_encode($initial_pour_toi, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cascade - Ecommerce</title>
    <link rel="stylesheet" href="https://preline.co/assets/css/main.css?v=3.0.1">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        // Gestion des préférences utilisateur
        const userPreferences = {
            init() {
                // Charger depuis localStorage ou cookies
                let prefs = localStorage.getItem('userPreferences');
                if (!prefs) {
                    // Vérifier dans les cookies
                    const cookies = document.cookie.split(';');
                    for (let cookie of cookies) {
                        if (cookie.trim().startsWith('userPreferences=')) {
                            prefs = decodeURIComponent(cookie.trim().substring('userPreferences='.length));
                            break;
                        }
                    }
                }
                
                this.preferences = prefs ? JSON.parse(prefs) : {
                    clicked: [],
                    searched: [],
                    liked: [],
                    categories: {}
                };
            },
            
            addClicked(productId, category) {
                if (!this.preferences.clicked.includes(productId)) {
                    this.preferences.clicked.push(productId);
                    if (category) {
                        this.updateCategoryPreference(category);
                    }
                    this.save();
                }
            },
            
            addSearched(query) {
                if (!this.preferences.searched.includes(query)) {
                    this.preferences.searched.push(query);
                    this.save();
                }
            },
            
            addLiked(productId, category) {
                if (!this.preferences.liked.includes(productId)) {
                    this.preferences.liked.push(productId);
                    if (category) {
                        this.updateCategoryPreference(category);
                    }
                    this.save();
                }
            },
            
            updateCategoryPreference(category) {
                if (!this.preferences.categories[category]) {
                    this.preferences.categories[category] = 0;
                }
                this.preferences.categories[category] += 1;
                this.save();
            },
            
            save() {
                localStorage.setItem('userPreferences', JSON.stringify(this.preferences));
                // Sauvegarder aussi dans un cookie (expire dans 30 jours)
                const cookieValue = encodeURIComponent(JSON.stringify(this.preferences));
                document.cookie = `userPreferences=${cookieValue}; max-age=${30*24*60*60}; path=/`;
            },
            
            getPreferences() {
                return this.preferences;
            }
        };
        
        // Initialiser au chargement
        document.addEventListener('DOMContentLoaded', () => {
            userPreferences.init();
        });
    </script>
</head>
<body class="bg-gray-50">
<header 
    class="sticky top-0 inset-x-0 z-48 w-full bg-white/90 backdrop-blur-md border-b border-gray-100 transition-colors"
    x-data='{
        open: false,
        activeCategory: null,
        searchOpen: false,
        categories: <?= $categories_json ?>
    }' 
    @mouseleave="open = false; activeCategory = null">
  <div class="flex flex-col">
    <nav class="flex items-center w-full text-sm py-3">
      <div class="max-w-[85rem] mx-auto w-full flex md:grid md:grid-cols-3 items-center px-4 sm:px-6 lg:px-8 gap-4">
        
        <div class="flex items-center" x-show="!searchOpen">
          <a class="flex items-center gap-2 rounded-md text-xl font-bold tracking-tight focus:outline-none" href="<?= url('/') ?>">
            <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
            <span class="text-gray-900">ecascadeur<span class="text-fuchsia-500 font-medium">.com</span></span>
          </a>
        </div>

        <div class="flex-1 md:block" :class="searchOpen ? 'absolute inset-x-0 top-0 h-full bg-white z-50 flex items-center px-4' : ''">
            <div class="w-full max-w-md mx-auto relative" x-data="searchHistory()">
                <button 
                    @click="searchOpen = true" 
                    x-show="!searchOpen" 
                    class="md:hidden p-2 text-gray-500 hover:bg-gray-50 rounded-full ml-auto block">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </button>

                <form @submit.prevent="saveSearch(searchQuery)" 
                      class="w-full"
                      :class="searchOpen ? 'flex items-center gap-3' : 'hidden md:block'">
                    
                    <button type="button" @click="searchOpen = false" x-show="searchOpen" class="md:hidden p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>

                    <div class="relative flex items-center w-full">
                        <input 
                            type="text"
                            id="search-input"
                            name="search"
                            x-model="searchQuery"
                            @focus="showHistory = true"
                            @blur="setTimeout(() => showHistory = false, 200)"
                            @input="filterHistory()"
                            maxlength="50"
                            placeholder="Rechercher un produit, une marque..."
                            class="py-2 px-4 ps-10 pe-4 block w-full bg-gray-50 border border-gray-200/80 rounded-lg text-xs text-gray-900 placeholder-gray-400 focus:outline-none focus:border-gray-400 focus:bg-white transition"
                        >
                        
                        <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-3.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        
                        <div x-show="showHistory && filteredHistory.length > 0" 
                             class="absolute top-full left-0 mt-1.5 w-full bg-white rounded-lg shadow-xl border border-gray-100 z-50 max-h-60 overflow-y-auto py-1"
                             x-cloak>
                            <template x-for="item in filteredHistory" :key="item">
                                <a :href="'<?= url('search') ?>?search=' + encodeURIComponent(item)"
                                   class="px-4 py-2 hover:bg-gray-50 text-xs text-gray-700 flex items-center justify-between group"
                                   @mousedown="searchQuery = item">
                                    <span x-text="item" class="group-hover:text-gray-900"></span>
                                    <button @click.prevent="removeFromHistory(item)" class="text-gray-300 hover:text-red-500 p-0.5 rounded transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </a>
                            </template>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3.5" x-show="!searchOpen">
            <!-- Centre de notifications interactif -->
            <div class="relative" x-data="{ notifOpen: false }">
                <button 
                    @click="notifOpen = !notifOpen" 
                    type="button" 
                    class="w-8 h-8 relative inline-flex justify-center items-center text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                    title="Vos notifications"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                    </svg>
                    <?php if ($unread_notifs_count > 0): ?>
                        <span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-600 text-white text-[10px] font-black rounded-full flex items-center justify-center ring-2 ring-white shadow-sm animate-pulse">
                            <?= $unread_notifs_count > 99 ? '99+' : $unread_notifs_count ?>
                        </span>
                    <?php endif; ?>
                </button>

                <!-- Menu déroulant des notifications -->
                <div 
                    x-show="notifOpen" 
                    @click.outside="notifOpen = false"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                    class="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden"
                    x-cloak
                >
                    <div class="px-4 py-3 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-900">Notifications</span>
                            <?php if ($unread_notifs_count > 0): ?>
                                <span class="bg-fuchsia-100 text-fuchsia-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                    <?= $unread_notifs_count ?> nouvelle<?= $unread_notifs_count > 1 ? 's' : '' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($unread_notifs_count > 0): ?>
                            <form method="POST" action="<?= url('api/mark-as-read') ?>" class="inline">
                                <input type="hidden" name="mark_all" value="1">
                                <button type="submit" class="text-[11px] text-fuchsia-600 hover:text-fuchsia-800 font-semibold cursor-pointer">
                                    Tout marquer comme lu
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="max-h-96 overflow-y-auto divide-y divide-gray-50">
                        <?php if (empty($user_notifications)): ?>
                            <div class="p-8 text-center text-gray-400">
                                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <p class="text-xs font-medium">Aucune notification pour le moment</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($user_notifications as $notif): ?>
                                <?php 
                                    $is_unread = intval($notif['is_read']) === 0;
                                    $action_data = !empty($notif['action_data']) ? json_decode($notif['action_data'], true) : null;
                                ?>
                                <div class="p-3.5 hover:bg-gray-50/80 transition <?= $is_unread ? 'bg-fuchsia-50/20' : '' ?>">
                                    <div class="flex items-start gap-2.5">
                                        <div class="size-2 rounded-full shrink-0 mt-1.5 <?= $is_unread ? 'bg-fuchsia-600' : 'bg-transparent' ?>"></div>
                                        <div class="flex-1 min-w-0">
                                            <?php if (!empty($notif['title'])): ?>
                                                <h4 class="text-xs font-bold text-gray-900 leading-tight mb-1">
                                                    <?= htmlspecialchars($notif['title']) ?>
                                                </h4>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-600 leading-relaxed break-words">
                                                <?= htmlspecialchars($notif['message']) ?>
                                            </p>

                                            <!-- Boutons d'action pour les commandes fournisseurs (2h de délai) -->
                                            <?php if ($action_data && !empty($action_data['accept_url']) && !empty($action_data['refuse_url']) && $is_unread): ?>
                                                <div class="mt-2.5 flex items-center gap-2">
                                                    <a href="<?= htmlspecialchars($action_data['accept_url']) ?>" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition shadow-xs active:scale-95">
                                                        <i class="bi bi-check-lg mr-1"></i> Accepter
                                                    </a>
                                                    <a href="<?= htmlspecialchars($action_data['refuse_url']) ?>" onclick="return confirm('Confirmez-vous le refus de cette commande ?');" class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold transition shadow-xs active:scale-95">
                                                        <i class="bi bi-x-lg mr-1"></i> Refuser
                                                    </a>
                                                    <span class="text-[10px] text-amber-600 font-bold ml-auto flex items-center gap-1">
                                                        <i class="bi bi-clock-history"></i> Max 2h
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Lien associé à la notification -->
                                            <?php if (!empty($notif['link']) && empty($action_data['accept_url'])): ?>
                                                <div class="mt-2">
                                                    <a href="<?= htmlspecialchars($notif['link']) ?>" class="inline-flex items-center gap-1 text-[11px] font-bold text-fuchsia-600 hover:text-fuchsia-800 transition">
                                                        <span>Voir les détails</span>
                                                        <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                </div>
                                            <?php endif; ?>

                                            <div class="mt-1.5 text-[10px] text-gray-400">
                                                <?= time_elapsed_string($notif['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="p-2.5 bg-gray-50 border-t border-gray-100 text-center">
                        <a href="<?= url('notifications') ?>" class="text-xs font-bold text-fuchsia-700 hover:text-fuchsia-900 transition flex items-center justify-center gap-1">
                            <span>Voir toutes les notifications (page dédiée)</span> &rarr;
                        </a>
                    </div>
                </div>
            </div>
            
            <a href="<?= $profile_link ?>" class="w-7 h-7 inline-flex items-center justify-center rounded-full ring-1 ring-gray-200/60 hover:ring-gray-300 transition">
                <img class="w-full h-full rounded-full object-cover" src="<?= htmlspecialchars($avatar) ?>" alt="Avatar">
            </a>
        </div>
      </div>
    </nav>

    <div class="border-t border-gray-100 relative bg-white">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
            <nav>
                <ul class="flex items-center justify-start overflow-x-auto gap-x-6 no-scrollbar scroll-smooth">
                    <template x-for="(category, index) in categories" :key="index">
                        <li class="shrink-0">
                            <button
                                @mouseenter="open = true; activeCategory = index"
                                @click="activeCategory = index"
                                class="relative py-3 text-xs font-medium tracking-wide transition-all duration-150 outline-none group"
                                :class="activeCategory === index ? 'text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-900'"
                            >
                                <span x-text="category.name"></span>
                                <span class="absolute inset-x-0 bottom-0 h-[2px] bg-gray-900 transition-transform duration-200 origin-left"
                                      :class="activeCategory === index ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </nav>
        </div>

        <style>
          .no-scrollbar::-webkit-scrollbar { display: none; }
          .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>

        <div 
            x-show="open" 
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            class="absolute left-0 w-full bg-white border-b border-gray-100 shadow-xl shadow-gray-100/40 z-50"
            x-cloak
        >
            <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 py-7">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    
                    <div class="md:col-span-8">
                        <template x-if="activeCategory !== null">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1">
                                <template x-for="link in categories[activeCategory].links" :key="link.id">
                                    <a :href="`<?= url('category-products') ?>?type=sub&id=${link.id}`" 
                                       class="group flex items-center justify-between p-2 rounded-md transition hover:bg-gray-50/80">
                                        <div class="flex items-center gap-2">
                                            <span class="w-1 h-1 rounded-full bg-fuchsia-500 opacity-0 group-hover:opacity-100 transition-opacity"></span>
                                            <span class="text-xs text-gray-600 group-hover:text-gray-900 font-medium transition-colors" x-text="link.name"></span>
                                        </div>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-gray-300 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="md:col-span-4 border-s border-gray-100 ps-6 hidden md:block">
                        <template x-if="activeCategory !== null">
                            <div class="flex flex-col gap-3">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">À la une</span>
                                <img :src="categories[activeCategory].image" class="w-full h-36 object-cover rounded-lg border border-gray-100" :alt="categories[activeCategory].name">
                                <div class="space-y-1">
                                    <h4 class="font-semibold text-xs text-gray-900" x-text="categories[activeCategory].name"></h4>
                                    <p class="text-xs text-gray-400 leading-relaxed" x-text="categories[activeCategory].desc"></p>
                                    <a :href="`<?= url('category-products') ?>?type=categ&id=${categories[activeCategory].id}`" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-gray-900 hover:text-fuchsia-600 transition">
                                        Découvrir la collection
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </div>
    </div>
  </div>
</header>

<div class="px-4 sm:px-6 lg:px-8 py-4">
  <div data-hs-carousel='{
      "loadingClasses": "opacity-0",
      "isAutoPlay": true,
      "speed": 4000
    }' class="relative">
    
    <div class="hs-carousel relative overflow-hidden w-full h-48 md:h-64 bg-gray-100 rounded-2xl dark:bg-neutral-800">
      <div class="hs-carousel-body absolute top-0 bottom-0 start-0 flex flex-nowrap transition-transform duration-700 opacity-0">
        <!-- Carrousel slides restent identiques -->
        <div class="hs-carousel-slide">
          <div class="h-full flex flex-col bg-[url('https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?q=80&w=1920&auto=format&fit=crop')] bg-cover bg-center">
            <div class="h-full w-full flex flex-col bg-black/40"> <div class="mt-auto w-full p-5 md:p-8 bg-linear-to-t from-black/80 to-transparent">
                <span class="block text-white font-semibold">Service Cascade</span>
                <span class="block text-white text-lg md:text-2xl font-medium">L'achat rime avec livraison : faites-vous livrer chez vous.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hs-carousel-slide">
          <div class="h-full flex flex-col bg-[url('https://images.unsplash.com/photo-1556742044-3c52d6e88c62?q=80&w=1920&auto=format&fit=crop')] bg-cover bg-center">
            <div class="h-full w-full flex flex-col bg-black/40"> <div class="mt-auto w-full p-5 md:p-8 bg-linear-to-t from-black/80 to-transparent">
                <span class="block text-white font-semibold">Simplicité</span>
                <span class="block text-white text-lg md:text-2xl font-medium">Trouvez vos produits en un clic. 1 click to go !</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hs-carousel-slide">
          <div class="h-full flex flex-col bg-[url('https://images.unsplash.com/photo-1522204538344-922f76ecc041?q=80&w=1920&auto=format&fit=crop')] bg-cover bg-center">
            <div class="h-full w-full flex flex-col bg-black/40"> <div class="mt-auto w-full p-5 md:p-8 bg-linear-to-t from-black/80 to-transparent">
                <span class="block text-white font-semibold">Exclusivité</span>
                <span class="block text-white text-lg md:text-2xl font-medium">La meilleure sélection de produits au meilleur prix.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hs-carousel-slide">
          <div class="h-full flex flex-col bg-[url('https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?q=80&w=1920&auto=format&fit=crop')] bg-cover bg-center">
            <div class="h-full w-full flex flex-col bg-black/40"> <div class="mt-auto w-full p-5 md:p-8 bg-linear-to-t from-black/80 to-transparent">
                <span class="block text-white font-semibold">Promotions</span>
                <span class="block text-white text-lg md:text-2xl font-medium">Des offres incroyables chaque semaine, ne les ratez pas.</span>
              </div>
            </div>
          </div>
        </div>

        <div class="hs-carousel-slide">
          <div class="h-full flex flex-col bg-[url('https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1920&auto=format&fit=crop')] bg-cover bg-center">
            <div class="h-full w-full flex flex-col bg-black/40"> <div class="mt-auto w-full p-5 md:p-8 bg-linear-to-t from-black/80 to-transparent">
                <span class="block text-white font-semibold">Garantie</span>
                <span class="block text-white text-lg md:text-2xl font-medium">Qualité garantie et service client à votre écoute.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <button type="button" class="hs-carousel-prev hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 start-0 inline-flex justify-center items-center w-10 h-full text-white hover:bg-white/20 rounded-s-2xl">
      <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
      <span class="sr-only">Précédent</span>
    </button>
    <button type="button" class="hs-carousel-next hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 end-0 inline-flex justify-center items-center w-10 h-full text-white hover:bg-white/20 rounded-e-2xl">
      <span class="sr-only">Suivant</span>
      <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
    </button>
  </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>

<section x-data="productStorySystem(<?= $products_json ?>)" class="px-4 sm:px-6 lg:px-8 py-8 bg-white">
    <div  class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800">Nouveautés Flash</h2>
        <div class="flex gap-2"> 
                <button @click="scrollLeft()" class="text-sm text-white font-black h-4 w-4 p-4 bg-gray-900 flex items-center justify-center"> < </button>
                <button @click="scrollRight()" class="text-sm text-white font-black h-4 w-4 p-4 bg-gray-900 flex items-center justify-center"> > </button>
        </div>

    </div>

    <div x-ref="storiesContainer" class="flex gap-4 overflow-x-auto no-scrollbar pb-6">
        <a href="<?= url('product/add') ?>" class="flex-shrink-0 w-44 h-72 md:w-52 md:h-80 relative rounded-2xl overflow-hidden bg-[#1c1e21] flex flex-col group transition-transform hover:scale-[1.02]">
            <div class="h-[75%] w-full relative overflow-hidden">
                <img src="images/img/add.jpg" alt="Fond" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 group-hover:brightness-90">                  
            </div>

            <div class="absolute top-[75%] -translate-y-1/2" style="position:absolute; left:50%; top:75%; transform:translateX(-50%);">
                <div class="bg-fuchsia-600 p-2 rounded-full border-1 border-[#1c1e21] flex items-center justify-center transition-colors group-hover:bg-fuchsia-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
            </div>

            <div class="h-[25%] flex items-end justify-center pb-4 px-2">
                <span class="text-white font-semibold text-sm text-center">
                    Propulsez votre produit
                </span>
            </div>
        </a>

        <template x-for="(product, index) in products" :key="index">
          <div @click="openStory(index)" class="flex-shrink-0 w-44 h-72 md:w-52 md:h-80 relative rounded-2xl overflow-hidden cursor-pointer shadow-lg group">
              <img :src="product.media[0].url" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
              
              <div class="absolute inset-0 bg-black/10 to-transparent z-10"></div>

              <template x-if="product.discount">
                  <div class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded-md uppercase tracking-wider z-20">
                      -<span x-text="product.discount"></span>%
                  </div>
              </template>

              <div class="absolute bottom-4 inset-x-4 p-4 text-white z-20 bg-black/50 rounded-xl border border-white/10">
                <p class="text-[10px] font-bold text-fuchsia-400 mb-1" x-text="product.category.sub"></p>
                <h3 class="text-sm font-bold leading-tight mb-2 line-clamp-2" x-text="product.name"></h3>
                <div class="flex items-baseline gap-2">
                    <span class="text-lg" x-text="product.price + ' ' + (product.currency == 'USD' ? '$' : 'FC')"></span>
                </div>
            </div>
          </div>
        </template>
    </div>

    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="fixed inset-0 z-[60] bg-black flex items-center justify-center" x-cloak>
        
        <div class="relative w-full max-w-md h-full md:h-[92vh] md:rounded-3xl overflow-hidden bg-gray-900 shadow-2xl">
            <div class="absolute top-4 inset-x-4 flex gap-1.5 z-30">
                <template x-for="(m, i) in products[activeIdx]?.media" :key="i">
                    <div class="h-1 flex-1 bg-white/20 rounded-full overflow-hidden">
                        <div class="h-full bg-fuchsia-500 transition-all duration-100 ease-linear"
                             :style="`width: ${i < activeMediaIdx ? '100' : (i === activeMediaIdx ? progress : '0')}%`"
                        ></div>
                    </div>
                </template>
            </div>

            <div class="absolute top-8 inset-x-4 flex items-center justify-between z-30 px-2">
                <div class="flex flex-col">
                    <span class="text-white font-bold text-base shadow-sm" x-text="products[activeIdx]?.name"></span>
                    <span class="text-fuchsia-400 text-xs font-bold" x-text="products[activeIdx]?. + ' ' + (product.currency == 'USD' ? '$' : 'FC')"></span>
                </div>
                <button @click="closeStory()" class="bg-black/20 backdrop-blur-md text-white p-2 rounded-full border border-white/20 hover:bg-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="absolute inset-0 flex z-20">
                <div @click="prevMedia()" class="w-1/3 h-full cursor-pointer"></div>
                <div @click="nextMedia()" class="w-2/3 h-full cursor-pointer"></div>
            </div>

            <div class="w-full h-full flex items-center justify-center bg-black">
                <template x-if="products[activeIdx]?.media[activeMediaIdx]?.type === 'image'">
                    <img :src="products[activeIdx]?.media[activeMediaIdx]?.url" class="w-full h-full object-contain">
                </template>
                <template x-if="products[activeIdx]?.media[activeMediaIdx]?.type === 'video'">
                    <video :id="'vid-'+activeIdx" :src="products[activeIdx]?.media[activeMediaIdx]?.url" autoplay muted playsinline class="w-full h-full object-contain"></video>
                </template>
            </div>

            <div class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black/90 via-black/40 to-transparent flex flex-col gap-5 z-30 backdrop-blur-[2px]">
                <div class="flex items-center justify-center gap-4">
                    <button @click="userPreferences.addClicked(products[activeIdx]?.id, products[activeIdx]?.category)" 
                            class="flex-1 max-w-[120px] bg-white/10 hover:bg-red-500/20 border border-white/20 text-white/80 py-1.5 rounded-full text-[11px] uppercase tracking-wider font-semibold transition-all">
                        Pas pour moi
                    </button>
                    <button @click="userPreferences.addLiked(products[activeIdx]?.id, products[activeIdx]?.category)" 
                            class="flex-1 max-w-[120px] bg-white/10 hover:bg-emerald-500/20 border border-white/20 text-white/80 py-1.5 rounded-full text-[11px] uppercase tracking-wider font-semibold transition-all">
                        J'aime
                    </button>
                </div>
                
                <div class="flex gap-3">
                    <a :href="'<?= url('product/buy') ?>?id=' + products[activeIdx]?.id" 
                       @click="userPreferences.addClicked(products[activeIdx]?.id, products[activeIdx]?.category)"
                       class="flex-[2] bg-white text-black hover:bg-fuchsia-500 hover:text-white py-3 rounded-2xl text-sm font-bold flex items-center justify-center transition-all active:scale-95 shadow-xl">
                        Voir le produit
                    </a>
                    <button @click="openShareModal(products[activeIdx]?.name, products[activeIdx]?.price, products[activeIdx]?.media[0]?.url)" 
                            class="flex-1 bg-white/20 backdrop-blur-md hover:bg-white/30 text-white py-3 rounded-2xl text-sm flex items-center justify-center transition-all active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION PRINCIPALE DU FEED "Pour toi" -->
<section x-data="productFeed(<?= $pour_toi_json ?>, <?= $total_products_count ?>)" 
         class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <div class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-800">Pour toi</h2>
            <div class="h-px flex-1 bg-gray-200 mx-4 hidden sm:block"></div>
            <span class="text-xs font-semibold text-fuchsia-600 bg-fuchsia-50 px-2.5 py-1 rounded-full border border-fuchsia-100">
                Sélection sur mesure
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-6">
            <template x-for="product in products" :key="product.id">
                <div class="group flex flex-col hover:shadow-sm rounded-xl transition-all duration-300">
                    <a :href="'<?= url('product/buy') ?>?id=' + product.id" 
                       @click="userPreferences.addClicked(product.id, product.category)"
                       class="relative block overflow-hidden rounded-t-xl">
                        <div class="aspect-square overflow-hidden relative">
                            <img
                                x-init="lazyLoad($el)"
                                :data-src="product.image"
                                :alt="product.name"
                                class="w-full h-full object-cover rounded-2xl blur-xl scale-110 opacity-0 transition-all ease-out"
                            />
                            
                            <div class="absolute top-2 left-2 flex gap-1">
                                <span :class="product.condition === 'Neuf' ? 'bg-green-100 text-green-700' : 'bg-fuchsia-100 text-fuchsia-700'" 
                                      class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                                      x-text="product.condition">
                                </span>
                            </div>

                            <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div class="bg-green-500 text-white p-1.5 rounded-lg shadow-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </div>
                            </div>

                            <template x-if="product.discount">
                                <div class="absolute bottom-2 left-2 bg-red-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded">
                                    -<span x-text="product.discount"></span>%
                                </div>
                            </template>
                        </div>
                    </a>

                    <div class="p-3">
                        <p class="text-[10px] text-gray-400 font-bold truncate mb-1" x-text="typeof product.category === 'object' ? (product.category.name || product.category.sub || 'Produit') : product.category"></p>
                        <h3 class="text-sm font-medium text-gray-800 truncate group-hover:text-fuchsia-600 transition-colors" x-text="product.name"></h3>
                        
                        <div class="flex justify-between">
                            <div class="mt-2 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-base font-bold text-gray-900" x-text="product.price + ' ' + (product.currency == 'USD' ? '$' : 'FC')"></span>
                                </div>
                            </div>
                            <div class="relative group overflow-hidden">
                                <button 
                                    @click="openShareModal(
                                        product.name,
                                        product.price,
                                        window.location.origin + '/' + (product.image ? product.image.replace(/^(\.\.\/)+/, '') : '')
                                    )"
                                    class="text-gray-500 p-2 cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center flex-wrap gap-y-1 p-3 text-[11px] text-gray-400">
                        <i class="bi bi-geo-alt me-1.5 text-gray-400"></i>
                        
                        <template x-for="(region, index) in product.regions" :key="region">
                            <span class="inline-flex items-center">
                                <span x-text="region" class="font-medium text-gray-600"></span>
                                <span x-show="index < product.regions.length - 1" class="mx-1.5 text-gray-300 select-none">•</span>
                            </span>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Skeleton Loaders affichés pendant le vrai chargement AJAX -->
            <template x-if="isLoading">
                <template x-for="i in skeletonCount" :key="'skel-' + i">
                    <div class="flex flex-col rounded-xl overflow-hidden bg-white border border-gray-100 shadow-xs animate-pulse">
                        <div class="aspect-square bg-gray-200 rounded-t-xl relative">
                            <div class="absolute top-2 left-2 w-12 h-4 bg-gray-300 rounded"></div>
                        </div>
                        <div class="p-3 space-y-2">
                            <div class="w-16 h-3 bg-gray-200 rounded"></div>
                            <div class="w-3/4 h-4 bg-gray-200 rounded"></div>
                            <div class="flex justify-between items-center pt-2">
                                <div class="w-20 h-5 bg-gray-300 rounded"></div>
                                <div class="w-6 h-6 bg-gray-200 rounded-full"></div>
                            </div>
                        </div>
                        <div class="p-3 border-t border-gray-50 flex gap-2">
                            <div class="w-12 h-3 bg-gray-200 rounded"></div>
                            <div class="w-12 h-3 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </template>
            </template>
        </div>
    </div>

    <!-- Sentinelle pour l'observer du Scroll Infini -->
    <div x-ref="sentinel" class="py-8 flex flex-col items-center justify-center">
        <div x-show="!hasMore && products.length > 0" class="text-center py-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 text-gray-400 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">Vous avez exploré tous nos produits !</p>
            <p class="text-xs text-gray-400 mt-1">Revenez plus tard pour découvrir de nouvelles offres.</p>
        </div>
    </div>
</section>


<div id="cookie-banner" class="fixed bottom-0 z-60 inset-x-0">
 <div id="cookie-banner" class="fixed bottom-0 end-0 z-60 sm:max-w-xl w-full mx-auto p-6">
  <!-- Card -->
  <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-2xs dark:bg-neutral-900 dark:border-neutral-800">
    <div class="flex gap-x-5">
      <svg class="hidden sm:block shrink-0 w-20" width="72" height="63" viewBox="0 0 72 63" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M15.5174 56.1528C16.2903 57.6825 16.929 61.4559 14.8118 60.9459C13.5013 60.5381 11.4445 57.6213 12.493 56.1528C12.661 55.8468 13.2189 55.2757 14.106 55.4389" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M15.5173 49.6263L14.0262 48.5579C13.5346 48.2056 12.8477 48.3707 12.658 48.945C12.3456 49.8907 12.1258 51.1463 12.462 52.2324C12.5336 52.4636 12.7127 52.6466 12.9449 52.7146C13.8342 52.9751 15.2568 52.9048 15.8197 51.054" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <mask id="path-3-inside-1_4542_101166" fill="currentColor" class="text-gray-800 dark:fill-neutral-200">
        <ellipse rx="1.09811" ry="0.738034" transform="matrix(0.921654 0.388014 -0.38048 0.924789 14.2069 43.4055)"/>
        </mask>
        <path d="M13.3756 43.0555C13.6288 42.4402 14.1378 42.259 14.3273 42.2214C14.5316 42.1809 14.6503 42.223 14.687 42.2384L13.1651 45.9376C13.7607 46.1884 14.4484 46.2907 15.1206 46.1574C15.7781 46.0269 16.654 45.5999 17.0622 44.6076L13.3756 43.0555ZM14.687 42.2384C14.7237 42.2539 14.8369 42.3094 14.9524 42.4846C15.0596 42.6471 15.2913 43.1401 15.0381 43.7554L11.3515 42.2034C10.9432 43.1957 11.261 44.1253 11.6329 44.689C12.0131 45.2654 12.5694 45.6868 13.1651 45.9376L14.687 42.2384ZM15.0381 43.7554C14.7849 44.3708 14.2759 44.552 14.0864 44.5895C13.8821 44.6301 13.7634 44.588 13.7267 44.5725L15.2486 40.8734C14.653 40.6226 13.9653 40.5203 13.2931 40.6536C12.6357 40.784 11.7597 41.2111 11.3515 42.2034L15.0381 43.7554ZM13.7267 44.5725C13.69 44.5571 13.5768 44.5015 13.4613 44.3264C13.3541 44.1638 13.1225 43.6709 13.3756 43.0555L17.0622 44.6076C17.4705 43.6153 17.1527 42.6857 16.7809 42.1219C16.4007 41.5455 15.8443 41.1241 15.2486 40.8734L13.7267 44.5725Z" fill="black" mask="url(#path-3-inside-1_4542_101166)"/>
        <mask id="path-5-inside-2_4542_101166" fill="currentColor" class="text-gray-800 dark:fill-neutral-200">
        <ellipse rx="1.00988" ry="1.0181" transform="matrix(0.921654 0.388014 -0.38048 0.924789 21.3702 57.2201)"/>
        </mask>
        <path d="M20.4576 56.8359C20.6581 56.3486 21.2257 56.094 21.7438 56.312L20.2219 60.0112C21.768 60.6621 23.5159 59.9153 24.1442 58.388L20.4576 56.8359ZM21.7438 56.312C22.2618 56.5301 22.4832 57.1169 22.2827 57.6043L18.5961 56.0522C17.9677 57.5795 18.6757 59.3603 20.2219 60.0112L21.7438 56.312ZM22.2827 57.6043C22.0822 58.0916 21.5146 58.3462 20.9966 58.1281L22.5185 54.429C20.9724 53.7781 19.2245 54.5249 18.5961 56.0522L22.2827 57.6043ZM20.9966 58.1281C20.4785 57.9101 20.2571 57.3233 20.4576 56.8359L24.1442 58.388C24.7726 56.8607 24.0646 55.0799 22.5185 54.429L20.9966 58.1281Z" fill="black" mask="url(#path-5-inside-2_4542_101166)"/>
        <mask id="path-7-inside-3_4542_101166" fill="currentColor" class="text-gray-800 dark:fill-neutral-200">
        <ellipse rx="1.00988" ry="1.0181" transform="matrix(0.921654 0.388014 -0.38048 0.924789 6.75397 38.8236)"/>
        </mask>
        <path d="M5.84142 38.4394C6.04192 37.9521 6.60952 37.6975 7.12756 37.9156L5.60564 41.6147C7.15177 42.2656 8.89966 41.5188 9.52804 39.9915L5.84142 38.4394ZM7.12756 37.9156C7.6456 38.1337 7.86701 38.7205 7.66651 39.2078L3.9799 37.6557C3.35152 39.1831 4.05951 40.9638 5.60564 41.6147L7.12756 37.9156ZM7.66651 39.2078C7.46601 39.6951 6.89842 39.9498 6.38037 39.7317L7.90229 36.0325C6.35616 35.3816 4.60827 36.1284 3.9799 37.6557L7.66651 39.2078ZM6.38037 39.7317C5.86233 39.5136 5.64092 38.9268 5.84142 38.4394L9.52804 39.9915C10.1564 38.4642 9.44843 36.6834 7.90229 36.0325L6.38037 39.7317Z" fill="black" mask="url(#path-7-inside-3_4542_101166)"/>
        <path d="M31.6479 50.2383C31.5807 51.2241 32.1721 53.053 35.0756 52.4819" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M50.9903 34.6769C50.1699 34.1428 48.3973 33.5907 47.8709 35.6552" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M40.9087 17.4562C40.0882 16.9221 38.3156 16.37 37.7892 18.4345" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M27.8502 29.3345C27.1279 29.998 26.1419 31.587 27.977 32.6357" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M62.1917 19.585C62.4894 18.6451 62.5577 16.7703 60.4502 16.7902" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <ellipse cx="51.2061" cy="22.3973" rx="3.02446" ry="3.05945" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M67.7398 29.6361C68.8249 31.2826 67.6381 32.6215 66.8281 33.1457C66.7645 33.1869 66.695 33.2184 66.6214 33.2363C65.0504 33.618 63.6063 31.5388 63.6063 30.0441C63.6064 28.8034 66.3283 27.4945 67.7398 29.6361Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M58.868 38.6126C57.9809 36.4914 54.6002 37.7288 53.0207 38.6126C51.7101 39.2284 52.0126 41.4681 53.6256 43.3038C54.9161 44.7723 56.5157 44.1196 57.1542 43.6097C58.0951 42.8279 59.7552 40.7339 58.868 38.6126Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M5.85665 41.8048C5.21042 40.2694 2.74791 41.1651 1.59743 41.8048C0.642774 42.2505 0.863078 43.8717 2.03804 45.2004C2.978 46.2634 4.14317 45.7909 4.60826 45.4219C5.29365 44.8559 6.50288 43.3402 5.85665 41.8048Z" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M45.4596 49.2172C40.9431 47.667 40.2844 51.6987 40.5196 53.9083C40.8221 55.3361 42.4351 55.54 43.4433 55.2341C45.5677 54.5894 51.1052 51.1548 45.4596 49.2172Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <ellipse rx="2.96295" ry="3.45694" transform="matrix(0.855131 0.518411 -0.509711 0.860345 30.4996 41.3871)" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M38.5032 29.1282C39.471 27.8228 37.8983 26.0687 36.991 25.3549C36.0836 24.6411 34.8335 24.8654 33.8657 26.1707C32.7567 27.6664 37.2934 30.7599 38.5032 29.1282Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M19.2476 18.9295C16.4247 18.2768 15.7862 19.8813 15.8198 20.7652C16.0215 23.8246 20.5582 24.4365 21.6672 23.6207C22.4364 23.0548 22.7761 19.7453 19.2476 18.9295Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M36.6888 6.79381C35.6403 4.67259 33.2947 5.02613 32.2529 5.46805C28.7042 6.61025 29.3292 8.52749 30.1358 9.13938C31.3456 10.1252 34.2289 12.0153 36.0839 11.6889C38.4027 11.281 37.9994 9.44533 36.6888 6.79381Z" fill="currentColor" class="text-gray-800 dark:fill-neutral-200"/>
        <path d="M56.9526 54.9284C57.7592 53.5006 60.2795 51.0735 65.1187 49.9313C66.0596 49.7953 67.9818 48.5647 68.1431 44.7302C68.3448 39.9371 73.5872 32.9003 69.3529 28.1072C67.5382 26.053 68.4456 23.2121 67.5382 17.7051" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M66.7316 16.176C65.1521 14.2383 60.6625 9.8939 55.3394 8.01743C48.703 5.67797 55.8063 4.55591 44.1399 4.75246C44.0816 4.75344 44.0194 4.76029 43.9617 4.76836C43.019 4.90008 40.5102 4.51266 37.2614 1.95295C37.2161 1.91728 37.1681 1.88406 37.1153 1.86091C36.6 1.63502 35.1744 1.43154 32.9584 2.2045C30.6195 3.02036 24.0531 5.46791 21.0622 6.58971C20.4237 6.92965 19.0056 8.05825 18.441 9.85312C17.7353 12.0967 5.93991 23.5187 9.56927 28.9237" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
        <path d="M10.4768 30.1484C11.9084 30.3333 14.621 31.3895 15.0562 34.1372C15.1369 34.6464 15.5068 35.0847 16.0079 35.2063C18.8253 35.8904 22.6446 38.4014 20.8122 44.4603C20.7218 44.7592 20.7652 45.0847 20.9158 45.3583C21.7327 46.8422 22.367 49.4462 20.6725 51.7386C20.1262 52.4776 20.4167 53.842 21.2912 54.1243C23.3727 54.7962 25.8398 55.985 27.2662 57.833C27.5533 58.2049 28.0338 58.3932 28.4956 58.3062C30.4142 57.9446 33.9492 57.9776 37.2937 60.233C42.1328 63.4964 42.3345 60.0291 48.6858 60.7429C53.7669 61.314 55.7765 58.3294 56.1462 56.7656" stroke="currentColor" class="dark:stroke-neutral-200" stroke-width="2" stroke-linecap="round"/>
      </svg>

        <div class="grow">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
            Nous utilisons des cookies pour améliorer votre expérience !
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-neutral-400">
            Cascade vous respecte enormement , En cliquant sur « Tout autoriser », vous acceptez l’utilisation de tous les cookies.
            Consultez notre
            <a class="inline-flex items-center gap-x-1.5 text-fuchsia-600 decoration-2 hover:underline focus:outline-hidden focus:underline font-medium dark:text-fuchsia-500" href="#">
            Politique relative aux cookies
            </a>
            pour en savoir plus. Sans acceptation, l’application ne fonctionnera pas correctement.
        </p>
        <div class="mt-5 inline-flex gap-x-2">
            <button id="allowAll" type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-fuchsia-600 text-white hover:bg-fuchsia-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
            Tout autoriser
            </button>
            <button id="rejectAll" type="button" class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 dark:focus:bg-neutral-700">
            Tout refuser
            </button>
        </div>
        </div>

    </div>
  </div>
  <!-- End Card -->
</div>
</div>

<script>
  window.addEventListener('load', () => {
    setTimeout(() => {
      document.querySelectorAll('.hs-overlay').forEach((el) => HSOverlay.open(el));
    });
  });
</script>
</div>

<!-- Modal de partage (reste identique) -->
<div id="shareModal" class="hidden fixed inset-0 z-100 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeShareModal()"></div>
    
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Partager ce produit</h3>
                <button onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>

            <div class="space-y-3">
                <a id="shareWA" target="_blank" class="flex items-center gap-4 p-3 rounded-xl bg-green-50 hover:bg-green-100 text-green-700 transition-colors">
                    <div class="bg-green-500 text-white p-2 rounded-lg">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </div>
                    <span class="font-semibold">WhatsApp</span>
                </a>

                <a id="shareX" target="_blank" class="flex items-center gap-4 p-3 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-900 transition-colors">
                    <div class="bg-black text-white p-2 rounded-lg">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.25h-6.657l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </div>
                    <span class="font-semibold">Partager sur X</span>
                </a>

                <a id="shareFB" target="_blank" class="flex items-center gap-4 p-3 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 transition-colors">
                    <div class="bg-blue-600 text-white p-2 rounded-lg">
                        <svg class="size-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </div>
                    <span class="font-semibold">Facebook</span>
                </a>
            </div>
        </div>
    </div>
</div>


<?php include "footer.html" ; ?>


<script>
// Fonctions de partage
function openShareModal(title, price, url) {
    const modal = document.getElementById('shareModal');
    
    const shareText = encodeURIComponent(`Découvre ce produit : ${title} au prix de ${price}$ !`);
    const shareUrl = encodeURIComponent(url);

    document.getElementById('shareWA').href = `https://api.whatsapp.com/send?text=${shareText}%20${shareUrl}`;
    document.getElementById('shareX').href = `https://twitter.com/intent/tweet?text=${shareText}&url=${shareUrl}`;
    document.getElementById('shareFB').href = `https://www.facebook.com/sharer/sharer.php?u=${shareUrl}`;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeShareModal() {
    const modal = document.getElementById('shareModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeShareModal();
});

// Système d'historique de recherche
function searchHistory() {
    return {
        searchQuery: '',
        showHistory: false,
        searchHistory: [],
        filteredHistory: [],
        
        init() {
            const saved = localStorage.getItem('searchHistory');
            this.searchHistory = saved ? JSON.parse(saved) : [];
            this.filteredHistory = [...this.searchHistory];
        },
        
        saveSearch(query) {
            if (!query.trim()) return;
            
            // Enregistrer dans les préférences utilisateur
            userPreferences.addSearched(query);
            
            // Ajouter à l'historique
            this.searchHistory = [query, ...this.searchHistory.filter(q => q !== query)].slice(0, 10);
            localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
            
            // Rediriger vers la page de recherche
            window.location.href = `<?= url('search') ?>?search=${encodeURIComponent(query)}`;
        },
        
        filterHistory() {
            if (!this.searchQuery.trim()) {
                this.filteredHistory = [...this.searchHistory];
            } else {
                this.filteredHistory = this.searchHistory.filter(item =>
                    item.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
        },
        
        removeFromHistory(item) {
            this.searchHistory = this.searchHistory.filter(q => q !== item);
            this.filteredHistory = this.filteredHistory.filter(q => q !== item);
            localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
        }
    }
}

// Système de feed de produits "Pour toi" avec Scroll Infini & Skeletons
function productFeed(initialProducts = [], totalProductsCount = 0) {
    return {
        products: initialProducts,
        totalProductsCount: totalProductsCount,
        isLoading: false,
        hasMore: true,
        skeletonCount: 10,
        observer: null,

        init() {
            if (this.products.length >= this.totalProductsCount) {
                this.hasMore = false;
            }
            this.setupInfiniteScroll();
        },

        setupInfiniteScroll() {
            this.$nextTick(() => {
                const sentinel = this.$refs.sentinel;
                if (!sentinel) return;

                this.observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting && !this.isLoading && this.hasMore) {
                        this.fetchNextBatch();
                    }
                }, {
                    rootMargin: '300px 0px'
                });

                this.observer.observe(sentinel);
            });
        },

        async fetchNextBatch() {
            if (this.isLoading || !this.hasMore) return;

            this.isLoading = true;
            const excludeIds = this.products.map(p => p.id);
            const userPrefs = typeof userPreferences !== 'undefined' ? userPreferences.getPreferences() : {};

            try {
                const response = await fetch('<?= url('api/feed') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        limit: 20,
                        exclude_ids: excludeIds,
                        user_preferences: userPrefs
                    })
                });

                const data = await response.json();

                if (data.success && Array.isArray(data.products)) {
                    if (data.products.length > 0) {
                        this.products.push(...data.products);
                    }
                    this.hasMore = data.has_more;
                    if (data.total_products) {
                        this.totalProductsCount = data.total_products;
                    }
                } else {
                    this.hasMore = false;
                }
            } catch (error) {
                console.error("Erreur lors du chargement du feed:", error);
            } finally {
                this.isLoading = false;
                if (this.products.length >= this.totalProductsCount) {
                    this.hasMore = false;
                }
            }
        },

        lazyLoad(img) {
            const connection = navigator.connection?.effectiveType || '4g';
            const speed = connection === '4g' ? 200 : connection === '3g' ? 400 : 600;

            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;

                    const el = entry.target;
                    if (el.dataset.src) {
                        el.src = el.dataset.src;
                        el.onload = () => {
                            el.style.transitionDuration = speed + 'ms';
                            el.classList.remove('blur-xl', 'scale-110', 'opacity-0');
                        };
                    }
                    observer.unobserve(el);
                });
            }, { rootMargin: '200px' });

            observer.observe(img);
        },

        formatPrice(val) {
            if (!val) return '';
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'USD'
            }).format(val);
        }
    }
}

// Système de stories (reste identique)
function productStorySystem(products = []) {
    return {
        isOpen: false,
        activeIdx: 0,
        activeMediaIdx: 0,
        progress: 0,
        interval: null,
        products: products,

        scrollLeft() {
            this.$refs.storiesContainer.scrollBy({
                left: -200,
                behavior: 'smooth'
            });
        },

        scrollRight() {
            this.$refs.storiesContainer.scrollBy({
                left: 200,
                behavior: 'smooth'
            });
        },


        formatPrice(val) { 
            if (!val) return '';
            return new Intl.NumberFormat('fr-FR', { 
                style: 'currency', 
                currency: 'USD' 
            }).format(val); 
        },

        openStory(index) {
            this.activeIdx = index;
            this.activeMediaIdx = 0;
            this.isOpen = true;
            this.startProgress();
        },

        closeStory() {
            this.isOpen = false;
            clearInterval(this.interval);
        },

        startProgress() {
            this.progress = 0;
            clearInterval(this.interval);
            let duration = this.products[this.activeIdx]?.media[this.activeMediaIdx]?.duration || 10000;
            let start = Date.now();
            this.interval = setInterval(() => {
                let elapsed = Date.now() - start;
                this.progress = (elapsed / duration) * 100;
                if (this.progress >= 100) this.nextMedia();
            }, 50);
        },

        nextMedia() {
            if (this.activeMediaIdx < (this.products[this.activeIdx]?.media?.length || 0) - 1) {
                this.activeMediaIdx++;
                this.startProgress();
            } else if (this.activeIdx < this.products.length - 1) {
                this.activeIdx++;
                this.activeMediaIdx = 0;
                this.startProgress();
            } else {
                this.closeStory();
            }
        },

        prevMedia() {
            if (this.activeMediaIdx > 0) {
                this.activeMediaIdx--;
                this.startProgress();
            } else if (this.activeIdx > 0) {
                this.activeIdx--;
                this.activeMediaIdx = (this.products[this.activeIdx]?.media?.length || 1) - 1;
                this.startProgress();
            }
        }
    }
}

// Gestion des cookies (reste identique)
document.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookie-banner');
    if (!banner) return;


    if (localStorage.getItem('cookiesConsent')) {
        banner.remove();
        return;
    }

    document.getElementById('allowAll').addEventListener('click', () => {
        localStorage.setItem('cookiesConsent', 'all');
        banner.remove();
    });

    document.getElementById('rejectAll').addEventListener('click', () => {
        localStorage.setItem('cookiesConsent', 'necessary');
        banner.remove();
    });
});
</script>

<style>
    @keyframes bounce-x {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(5px); }
    }
    .animate-bounce-x { animation: bounce-x 1s infinite; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<!-- Toast de notifications en temps réel pour index.php -->
<div 
    x-data="globalNotifToast()" 
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
            <a :href="toast.link || '<?= url('notifications') ?>'" class="text-[11px] font-bold text-fuchsia-700 hover:underline">
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

<script>
function globalNotifToast() {
    return {
        toast: {
            show: false,
            title: '',
            message: '',
            link: '',
            isWarning: false
        },
        init() {
            if ("Notification" in window && Notification.permission === "default") {
                Notification.requestPermission();
            }
            setInterval(() => {
                this.checkPoll();
            }, 20000);
        },
        async checkPoll() {
            try {
                const res = await fetch('<?= url('api/notifications-poll') ?>');
                const data = await res.json();
                if (data && data.has_new && data.latest) {
                    this.toast.title = data.latest.title || 'Nouvelle notification';
                    this.toast.message = data.latest.message || '';
                    this.toast.link = data.latest.link || '<?= url('notifications') ?>';
                    this.toast.isWarning = (data.latest.type === 'order_warning');
                    this.toast.show = true;

                    if ("Notification" in window && Notification.permission === "granted") {
                        try {
                            const n = new Notification(this.toast.title, {
                                body: this.toast.message,
                                icon: 'assets/images/favicon.png',
                                tag: 'notif-' + data.latest.id
                            });
                            n.onclick = () => {
                                window.focus();
                                window.location.href = this.toast.link;
                            };
                        } catch(e) {}
                    }

                    if (!this.toast.isWarning) {
                        setTimeout(() => { this.toast.show = false; }, 8000);
                    }
                }
            } catch(e) {}
        }
    };
}
</script>

<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>
</body>
</html>