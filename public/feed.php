<?php
// index.php - Page d'accueil dynamique
session_start();
require_once __DIR__ . '/../libs/db.php';

// Configuration de la pagination
$products_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $products_per_page;

// Récupérer les catégories depuis la base de données
$categories = [];
$subcategories_by_category = [];

// Récupérer toutes les catégories
$query = "SELECT id, name, description, icon_class FROM categories ORDER BY name";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[$row['id']] = [
            'name' => $row['name'],
            'desc' => $row['description'],
            'image' => 'https://images.unsplash.com/photo-1434389677669-e08b4cac3105?auto=format&fit=crop&w=320&h=320&q=80', // Image par défaut
            'links' => []
        ];
        
        // Récupérer les sous-catégories pour cette catégorie
        $sub_query = "SELECT name FROM subcategories WHERE category_id = " . $row['id'] . " ORDER BY name LIMIT 14";
        $sub_result = mysqli_query($conn, $sub_query);
        
        if ($sub_result && mysqli_num_rows($sub_result) > 0) {
            while ($sub_row = mysqli_fetch_assoc($sub_result)) {
                $categories[$row['id']]['links'][] = $sub_row['name'];
            }
        } else {
            // Sous-catégories par défaut si aucune n'existe
            $default_links = [
                'Habillement' => ['Chemises', 'Pantalons', 'Robes', 'Manteaux', 'T-shirts', 'Vestes', 'Jupes', 'Shorts', 'Pulls', 'Chaussures', 'Accessoires', 'Chapeaux', 'Sacs', 'Ceintures'],
                'Electronique' => ['Téléphones', 'Ordinateurs', 'Audio', 'Gaming'],
                'Chaussures' => ['Sneakers', 'Bottes', 'Sandales', 'Sport'],
                'Accessoires' => ['Montres', 'Sacs', 'Lunettes', 'Bijoux'],
                'Maison' => ['Meubles', 'Déco', 'Cuisine', 'Linge de lit'],
                'Jouets' => ['Poupées', 'Lego', 'Jeux de société', 'Puzzles'],
                'Livres' => ['Romans', 'BD', 'Cuisine', 'Histoire'],
                'Sport' => ['Fitness', 'Running', 'Yoga', 'Cyclisme'],
                'Beauté' => ['Maquillage', 'Soins visage', 'Parfums', 'Cheveux'],
                'Alimentation' => ['Bio', 'Épicerie', 'Boissons', 'Surgelés'],
                'Automobile' => ['Pneus', 'Outillage', 'Entretien', 'Gadgets'],
                'Informatique' => ['Composants', 'Périphériques', 'Logiciels', 'Réseau']
            ];
            
            $cat_name = $row['name'];
            if (isset($default_links[$cat_name])) {
                $categories[$row['id']]['links'] = $default_links[$cat_name];
            }
        }
        
        if ($sub_result) mysqli_free_result($sub_result);
    }
    mysqli_free_result($result);
}

// Récupérer les produits pour les stories (100 derniers produits)
$story_products = [];
$story_query = "
    SELECT p.id, p.name, p.price, p.currency, p.discount_percent, 
           c.name as category_name
    FROM products p
    LEFT JOIN categories c ON JSON_CONTAINS(p.category, CONCAT('[', c.id, ']'), '$')
    ORDER BY p.created_at DESC 
    LIMIT 100
";
$story_result = mysqli_query($conn, $story_query);


if ($story_result && mysqli_num_rows($story_result) > 0) {
    while ($row = mysqli_fetch_assoc($story_result)) {
        // Récupérer les médias pour ce produit
        $media_query = "SELECT file_path, file_type FROM product_media 
                        WHERE product_id = " . $row['id'] . " 
                        ORDER BY sort_order LIMIT 2";
        $media_result = mysqli_query($conn, $media_query);
        
        $media = [];
        if ($media_result && mysqli_num_rows($media_result) > 0) {
            while ($media_row = mysqli_fetch_assoc($media_result)) {
                $media[] = [
                    'type' => $media_row['file_type'],
                    'url' => $media_row['file_path'],
                    'duration' => 10000
                ];
            }
        } else {
            // Media par défaut
            $media[] = [
                'type' => 'image',
                'url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=800',
                'duration' => 10000
            ];
        }
        
        if ($media_result) mysqli_free_result($media_result);
        
        $story_products[] = [
            'name' => $row['name'],
            'price' => $row['price'],
            'oldPrice' => $row['discount_percent'] > 0 ? $row['price'] * (1 + $row['discount_percent']/100) : null,
            'discount' => $row['discount_percent'],
            'category' => $row['category_name'] ?? 'Non catégorisé',
            'link' => 'buy_product.php?id=' . $row['id'],
            'media' => $media
        ];
    }
}

if ($story_result) mysqli_free_result($story_result);

// Récupérer les produits pour le feed avec pagination
$feed_products = [];
$feed_query = "
    SELECT p.id, p.name, p.price, p.currency, p.product_condition, 
           p.discount_percent, c.name as brand
    FROM products p
    LEFT JOIN categories c ON JSON_CONTAINS(p.category, CONCAT('[', c.id, ']'), '$')
    ORDER BY p.created_at DESC 
    LIMIT $offset, $products_per_page
";
$feed_result = mysqli_query($conn, $feed_query);


if ($feed_result && mysqli_num_rows($feed_result) > 0) {
    while ($row = mysqli_fetch_assoc($feed_result)) {
        // Récupérer l'image principale
        $img_query = "SELECT file_path FROM product_media 
                      WHERE product_id = " . $row['id'] . " 
                      AND file_type = 'image' 
                      ORDER BY sort_order LIMIT 1";
        $img_result = mysqli_query($conn, $img_query);
        $image = 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?q=80&w=600'; // Image par défaut
        
        if ($img_result && mysqli_num_rows($img_result) > 0) {
            $img_row = mysqli_fetch_assoc($img_result);
            $image = $img_row['file_path'];
        }
        
        if ($img_result) mysqli_free_result($img_result);
        
        $feed_products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'brand' => $row['brand'] ?? 'Non catégorisé',
            'price' => $row['price'],
            'oldPrice' => $row['discount_percent'] > 0 ? $row['price'] * (1 + $row['discount_percent']/100) : null,
            'discount' => $row['discount_percent'],
            'condition' => $row['product_condition'] == 'new' ? 'Neuf' : 'Occasion',
            'image' => $image
        ];
    }
}

if ($feed_result) mysqli_free_result($feed_result);

// Compter le total des produits pour la pagination
$count_query = "SELECT COUNT(*) as total FROM products";
$count_result = mysqli_query($conn, $count_query);
$total_products = 0;

if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_products = $count_row['total'];
    mysqli_free_result($count_result);
}

$total_pages = ceil($total_products / $products_per_page);

// Déterminer l'URL de l'image de profil
$profile_pic = 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?auto=format&fit=facearea&facepad=2&w=32&h=32&q=80';
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT profile_pic FROM users WHERE id = $user_id";
    $user_result = mysqli_query($conn, $user_query);
    
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_row = mysqli_fetch_assoc($user_result);
        if (!empty($user_row['profile_pic'])) {
            $profile_pic = $user_row['profile_pic'];
        }
    }
    
    if ($user_result) mysqli_free_result($user_result);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cascade - Ecommerce</title>
    <link rel="stylesheet" href="https://preline.co/assets/css/main.css?v=3.0.1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50">

<header 
    class="sticky top-0 inset-x-0 z-48 w-full bg-white border-b border-gray-200"
    x-data="{ 
        open: false, 
        activeCategory: null,
        categories: <?php echo json_encode($categories); ?>
    }"
    @mouseleave="open = false; activeCategory = null"
>
  <div class="flex flex-col">
    <nav class="flex flex-wrap md:justify-start md:flex-nowrap w-full text-sm py-2.5">
      <div class="max-w-[85rem] mx-auto w-full flex md:grid md:grid-cols-3 md:gap-x-1 basis-full items-center px-4 sm:px-6 lg:px-8">
        <div class="me-5">
          <a class="flex items-center gap-2 rounded-md text-xl font-semibold focus:outline-none" href="index.php">
            <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
            <span class="text-2xl font-bold tracking-tight text-gray-800">ecascadeur<span class="text-orange-500">.com</span></span>
          </a>
        </div>
        <div class="hidden md:block">
            <div class="max-w-md">
                <label for="search-input" class="sr-only">Rechercher</label>
                <form action="recherche.php" method="GET" class="relative flex items-center">
                    <input 
                        type="text" 
                        id="search-input" 
                        name="search"
                        maxlength="50"
                        placeholder="Rechercher sur ecascadeur"
                        class="py-3 px-4 ps-11 pe-14 block w-full border-gray-200 rounded-full text-sm focus:border-blue-500 focus:ring-blue-500 bg-gray-100 border-none"
                    >
                    <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-4">
                        <svg class="shrink-0 size-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                    </div>
                    <div class="absolute inset-y-0 end-0 flex items-center pe-1.5">
                        <button type="submit" class="size-[32px] sm:size-[40px] inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-full border border-transparent bg-orange-500 text-white hover:bg-orange-600 focus:outline-none disabled:opacity-50">
                            <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>            
        </div>
        <div class="flex-1 flex flex-row justify-end items-center gap-1">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="size-9.5 relative inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 rounded-full">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </a>
            <?php else: ?>
                <button class="size-9.5 relative inline-flex justify-center items-center text-gray-800 hover:bg-gray-100 rounded-full">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                </button>
            <?php endif; ?>
            <div class="size-9.5 inline-flex justify-center items-center">
                <img class="size-8 rounded-full" src="<?php echo $profile_pic; ?>" alt="Avatar">
            </div>
        </div>
      </div>
    </nav>
    <div class="border-t border-gray-100 relative">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 border-b border-gray-100">
            <nav class="relative">
                <ul class="flex items-center justify-around overflow-x-auto gap-x-8 no-scrollbar scroll-smooth py-1">
                    <?php foreach ($categories as $id => $category): ?>
                        <li class="inline-block shrink-0">
                            <button 
                                @mouseenter="open = true; activeCategory = <?php echo $id; ?>"
                                @click="activeCategory = <?php echo $id; ?>"
                                class="relative py-3 text-sm font-medium transition-all duration-200 outline-none group"
                                :class="activeCategory === <?php echo $id; ?> ? 'text-orange-600' : 'text-gray-600 hover:text-orange-500'"
                            >
                                <span><?php echo htmlspecialchars($category['name']); ?></span>
                                <span 
                                    class="absolute inset-x-0 bottom-0 h-0.5 bg-orange-600 transition-transform duration-300 origin-left"
                                    :class="activeCategory === <?php echo $id; ?> ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100'"
                                ></span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
        
        <style>
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
        
        <div 
            x-show="open" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="absolute left-0 w-full bg-white border-b border-gray-200 shadow-xl z-50"
            x-cloak
        >
            <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                    <div class="md:col-span-8">
                        <template x-if="activeCategory">
                            <div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-1">
                                    <template x-for="link in categories[activeCategory].links" :key="link">
                                        <a href="#" class="group block p-2 rounded-lg transition-all duration-200 hover:bg-orange-50">
                                            <div class="flex items-center justify-between">
                                                <div class="space-y-0">
                                                    <p class="flex items-center gap-1 text-[13px] font-medium text-gray-700 group-hover:text-orange-600 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 24 24"
                                                            fill="currentColor"
                                                            class="w-3 h-3">
                                                            <path d="M12 2l2.9 6.1 6.7.6-5 4.5 1.5 6.6L12 16.8 5.9 19.8 7.4 13.2 2.4 8.7l6.7-.6L12 2z"/>
                                                        </svg>
                                                        <span x-text="link"></span>
                                                    </p>
                                                    <p class="text-[9px] uppercase tracking-tighter text-gray-400 font-bold none group-hover:block transition-opacity">
                                                        Voir tout
                                                    </p>
                                                </div>
                                                <div class="text-orange-500 opacity-0 -translate-x-1 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </a>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="md:col-span-4 border-s border-gray-100 ps-8 hidden md:block">
                        <template x-if="activeCategory">
                            <div class="flex flex-col gap-4">
                                <span class="text-xs font-bold uppercase tracking-wider text-orange-500">À la une</span>
                                <img :src="categories[activeCategory].image" class="w-full h-48 object-cover rounded-xl shadow-sm" :alt="categories[activeCategory].name">
                                <div>
                                    <h4 class="font-bold text-gray-800" x-text="categories[activeCategory].name"></h4>
                                    <p class="text-sm text-gray-500 mt-1" x-text="categories[activeCategory].desc"></p>
                                    <a href="#" class="mt-3 inline-flex items-center gap-x-2 text-sm font-medium text-blue-600 hover:text-blue-800">
                                        Découvrir tout <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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

<section x-data="productStorySystem()" class="px-4 sm:px-6 lg:px-8 py-8 bg-white">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800">Nouveautés Flash</h2>
        <span class="text-sm text-orange-500 font-medium">Tout voir</span>
    </div>
    <div class="flex gap-4 overflow-x-auto no-scrollbar pb-6">
        
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'seller'): ?>
            <a href="ajout_produit.php" class="flex-shrink-0 w-44 h-72 md:w-52 md:h-80 relative rounded-2xl overflow-hidden bg-[#1c1e21] flex flex-col group transition-transform hover:scale-[1.02]">
                <div class="h-[75%] w-full relative overflow-hidden">
                    <img src="img/add.jpg" alt="Fond" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 group-hover:brightness-90">                  
                </div>
                <div class="absolute top-[75%] -translate-y-1/2" style="position:absolute; left:50%; top:75%; transform:translateX(-50%);">
                    <div class="bg-orange-600 p-2 rounded-full border-1 border-[#1c1e21] flex items-center justify-center transition-colors group-hover:bg-orange-500">
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
        <?php endif; ?>
        
        <?php foreach ($story_products as $index => $product): ?>
            <div onclick="openStory(<?php echo $index; ?>)" class="flex-shrink-0 w-44 h-72 md:w-52 md:h-80 relative rounded-2xl overflow-hidden cursor-pointer shadow-lg group">
                <img src="<?php echo $product['media'][0]['url']; ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                <div class="absolute inset-0 bg-black/70 to-transparent z-10"></div>
                <?php if ($product['discount']): ?>
                    <div class="absolute top-3 left-3 bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded-md uppercase tracking-wider z-20">
                        -<?php echo $product['discount']; ?>%
                    </div>
                <?php endif; ?>
                <div class="absolute bottom-0 inset-x-0 p-4 text-white z-20">
                    <p class="text-[10px] uppercase font-bold text-orange-400 mb-1"><?php echo $product['category']; ?></p>
                    <h3 class="text-sm font-bold leading-tight mb-2 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg font-black"><?php echo number_format($product['price'], 2); ?> <?php echo $product['currency'] ?? '€'; ?></span>
                        <?php if ($product['oldPrice']): ?>
                            <span class="text-xs text-gray-400 line-through"><?php echo number_format($product['oldPrice'], 2); ?> €</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div id="storyModal" 
         class="hidden fixed inset-0 z-[60] bg-black flex items-center justify-center" x-cloak>
        
        <div class="relative w-full max-w-md h-full md:h-[92vh] md:rounded-3xl overflow-hidden bg-gray-900 shadow-2xl">
            
            <div class="absolute top-4 inset-x-4 flex gap-1.5 z-30" id="storyProgress">
                <!-- Progress bars will be added by JavaScript -->
            </div>
            
            <div class="absolute top-8 inset-x-4 flex items-center justify-between z-30 px-2">
                <div class="flex flex-col">
                    <span class="text-white font-bold text-base shadow-sm" id="storyTitle"></span>
                    <span class="text-orange-400 text-xs font-bold" id="storyPrice"></span>
                </div>
                <button onclick="closeStory()" class="bg-black/20 backdrop-blur-md text-white p-2 rounded-full border border-white/20 hover:bg-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            
            <div class="absolute inset-0 flex z-20">
                <div onclick="prevMedia()" class="w-1/3 h-full cursor-pointer"></div>
                <div onclick="nextMedia()" class="w-2/3 h-full cursor-pointer"></div>
            </div>
            
            <div class="w-full h-full flex items-center justify-center bg-black">
                <img id="storyImage" class="w-full h-full object-contain hidden">
                <video id="storyVideo" autoplay muted playsinline class="w-full h-full object-contain hidden"></video>
            </div>
            
            <div class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black via-black/60 to-transparent flex flex-col gap-4 z-30">
                <div class="flex items-center gap-3">
                    <button class="flex-1 bg-white/10 hover:bg-white/20 text-white py-4 rounded-2xl text-xs font-bold transition">
                       Pas intéressé
                    </button>
                    <button class="flex-1 bg-white/10 hover:bg-white/20 text-white py-4 rounded-2xl text-xs font-bold transition">
                        Intéressé
                    </button>
                </div>
                
                <div class="flex gap-3">
                    <a id="storyLink" class="w-full bg-orange-500 hover:bg-orange-600 text-white py-4 rounded-2xl text-sm font-black flex items-center justify-center transition-transform active:scale-95 shadow-lg shadow-orange-500/20">
                        Voir le produit
                    </a>
                    <button class="w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-2xl text-sm font-black flex items-center justify-center gap-3 transition-transform active:scale-95 shadow-lg"
                            onclick="openShareModal('', '', '')">
                        Partager
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<section x-data="productFeed()" class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8 py-10">
    
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Découvrir les produits</h2>
        <div class="h-px flex-1 bg-gray-200 mx-4 hidden sm:block"></div>
        <span class="text-sm font-medium text-gray-500">Ceci pourrait vous intéresser</span>
    </div>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3 sm:gap-6">
        
        <?php foreach ($feed_products as $product): ?>
            <div class="group flex flex-col hover:shadow-sm rounded-xl transition-all duration-300">
                <a href="buy_product.php?id=<?php echo $product['id']; ?>" class="relative block overflow-hidden rounded-t-xl">
                    <div class="aspect-square overflow-hidden relative">
                        <img
                            src="<?php echo $product['image']; ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            class="w-full h-full object-cover rounded-2xl"
                            loading="lazy"
                        />
                        
                        <div class="absolute top-2 left-2 flex gap-1">
                            <span class="<?php echo $product['condition'] === 'Neuf' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'; ?> px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide">
                                <?php echo $product['condition']; ?>
                            </span>
                        </div>
                        
                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <div class="bg-green-500 text-white p-1.5 rounded-lg shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                        </div>
                        
                        <?php if ($product['discount']): ?>
                            <div class="absolute bottom-2 left-2 bg-red-600 text-white text-[10px] font-black px-1.5 py-0.5 rounded">
                                -<?php echo $product['discount']; ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                
                <div class="p-3">
                    <p class="text-[10px] text-gray-400 uppercase font-bold truncate mb-1"><?php echo htmlspecialchars($product['brand']); ?></p>
                    <h3 class="text-sm font-medium text-gray-800 truncate group-hover:text-orange-600 transition-colors"><?php echo htmlspecialchars($product['name']); ?></h3>
                    
                    <div class="flex justify-between">
                        <div class="mt-2 flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-base font-bold text-gray-900"><?php echo number_format($product['price'], 2); ?> €</span>
                                <?php if ($product['oldPrice']): ?>
                                    <span class="text-xs text-gray-400 line-through"><?php echo number_format($product['oldPrice'], 2); ?> €</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="relative group overflow-hidden">
                            <button 
                                onclick="openShareModal('<?php echo addslashes($product['name']); ?>', '<?php echo $product['price']; ?>', '<?php echo $product['image']; ?>')"
                                class="text-gray-500 p-2 cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="mt-12 flex justify-center">
            <nav class="flex items-center gap-x-1" aria-label="Pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>" class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100">
                        <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        <span>Précédent</span>
                    </a>
                <?php else: ?>
                    <span class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-400 opacity-50 cursor-not-allowed">
                        <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
                        <span>Précédent</span>
                    </span>
                <?php endif; ?>
                
                <div class="flex items-center gap-x-1">
                    <?php for ($i = 1; $i <= min(3, $total_pages); $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="min-h-9.5 min-w-9.5 flex justify-center items-center bg-gray-200 text-gray-800 py-2 px-3 text-sm rounded-lg focus:outline-none focus:bg-gray-300" aria-current="page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>" class="min-h-9.5 min-w-9.5 flex justify-center items-center text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm rounded-lg"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($total_pages > 3 && $current_page > 3): ?>
                        <span class="min-h-9.5 min-w-9.5 flex justify-center items-center text-gray-800 py-2 px-1">...</span>
                        <a href="?page=<?php echo $current_page; ?>" class="min-h-9.5 min-w-9.5 flex justify-center items-center bg-gray-200 text-gray-800 py-2 px-3 text-sm rounded-lg"><?php echo $current_page; ?></a>
                    <?php endif; ?>
                </div>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>" class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-800 hover:bg-gray-100 focus:outline-none focus:bg-gray-100">
                        <span>Suivant</span>
                        <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </a>
                <?php else: ?>
                    <span class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm rounded-lg text-gray-400 opacity-50 cursor-not-allowed">
                        <span>Suivant</span>
                        <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                    </span>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</section>

<div id="cookie-banner" class="fixed bottom-0 z-60 inset-x-0">
  <div class="p-4 sm:p-6 bg-white border border-gray-200 shadow-2xs dark:bg-neutral-900 dark:border-neutral-800">
    <div class="max-w-[85rem] mx-auto">
      <div class="grid lg:grid-cols-4 xl:grid-cols-5 gap-5 items-center">
        <div class="col-span-1">
          <a href="index.php" class="flex-none inline-block">
            <svg class="w-32 md:w-40 h-auto" width="116" height="32" viewBox="0 0 116 32" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M33.5696 30.2968V10.7968H37.4474V13.1789H37.6229C37.7952 12.7972 38.0445 12.4094 38.3707 12.0155C38.7031 11.6154 39.134 11.283 39.6634 11.0183C40.1989 10.7475 40.8636 10.6121 41.6577 10.6121C42.6918 10.6121 43.6458 10.8829 44.5199 11.4246C45.3939 11.9601 46.0926 12.7695 46.6158 13.8529C47.139 14.93 47.4006 16.2811 47.4006 17.9061C47.4006 19.488 47.1451 20.8237 46.6342 21.9132C46.1295 22.9966 45.4401 23.8183 44.5661 24.3784C43.6982 24.9324 42.7256 25.2094 41.6484 25.2094C40.8852 25.2094 40.2358 25.0832 39.7003 24.8308C39.1709 24.5785 38.737 24.2615 38.3984 23.8799C38.0599 23.4921 37.8014 23.1012 37.6229 22.7073H37.5028V30.2968H33.5696ZM37.4197 17.8877C37.4197 18.7309 37.5367 19.4665 37.7706 20.0943C38.0045 20.7222 38.343 21.2115 38.7862 21.5624C39.2294 21.9071 39.768 22.0794 40.402 22.0794C41.0421 22.0794 41.5838 21.904 42.027 21.5532C42.4702 21.1961 42.8056 20.7037 43.0334 20.0759C43.2673 19.4419 43.3842 18.7125 43.3842 17.8877C43.3842 17.069 43.2704 16.3488 43.0426 15.7272C42.8149 15.1055 42.4794 14.6192 42.0362 14.2683C41.593 13.9175 41.0483 13.7421 40.402 13.7421C39.7618 13.7421 39.2202 13.9113 38.777 14.2499C38.34 14.5884 38.0045 15.0685 37.7706 15.6902C37.5367 16.3119 37.4197 17.0444 37.4197 17.8877ZM49.2427 24.9786V10.7968H53.0559V13.2712H53.2037C53.4622 12.391 53.8961 11.7262 54.5055 11.2769C55.1149 10.8214 55.8166 10.5936 56.6106 10.5936C56.8076 10.5936 57.02 10.6059 57.2477 10.6306C57.4754 10.6552 57.6755 10.689 57.8478 10.7321V14.2222C57.6632 14.1668 57.4077 14.1175 57.0815 14.0745C56.7553 14.0314 56.4567 14.0098 56.1859 14.0098C55.6073 14.0098 55.0903 14.136 54.6348 14.3884C54.1854 14.6346 53.8284 14.9793 53.5638 15.4225C53.3052 15.8657 53.176 16.3765 53.176 16.9551V24.9786H49.2427ZM64.9043 25.2556C63.4455 25.2556 62.1898 24.9601 61.1373 24.3692C60.0909 23.7721 59.2845 22.9289 58.7182 21.8394C58.1519 20.7437 57.8688 19.448 57.8688 17.9523C57.8688 16.4935 58.1519 15.2132 58.7182 14.1114C59.2845 13.0096 60.0816 12.1509 61.1096 11.5354C62.1437 10.9199 63.3563 10.6121 64.7474 10.6121C65.683 10.6121 66.5539 10.7629 67.3603 11.0645C68.1728 11.36 68.8806 11.8062 69.4839 12.4033C70.0932 13.0004 70.5672 13.7513 70.9057 14.6561C71.2443 15.5548 71.4135 16.6074 71.4135 17.8138V18.8941H59.4384V16.4566H67.7111C67.7111 15.8903 67.588 15.3886 67.3418 14.9516C67.0956 14.5146 66.754 14.1729 66.317 13.9267C65.8861 13.6744 65.3844 13.5482 64.812 13.5482C64.2149 13.5482 63.6856 13.6867 63.2239 13.9637C62.7684 14.2345 62.4114 14.6007 62.1529 15.0624C61.8944 15.5179 61.762 16.0257 61.7559 16.5858V18.9033C61.7559 19.605 61.8851 20.2113 62.1437 20.7222C62.4083 21.2331 62.7807 21.627 63.2608 21.904C63.741 22.181 64.3103 22.3195 64.9689 22.3195C65.406 22.3195 65.8061 22.2579 66.1692 22.1348C66.5324 22.0117 66.8432 21.8271 67.1018 21.5808C67.3603 21.3346 67.5572 21.033 67.6927 20.676L71.3304 20.9161C71.1458 21.7901 70.7672 22.5534 70.1948 23.2058C69.6285 23.8522 68.896 24.3569 67.9974 24.7201C67.1048 25.0771 66.0738 25.2556 64.9043 25.2556ZM77.1335 6.06949V24.9786H73.2003V6.06949H77.1335ZM79.5043 24.9786V10.7968H83.4375V24.9786H79.5043ZM81.4801 8.96863C80.8954 8.96863 80.3937 8.77474 79.9752 8.38696C79.5628 7.99302 79.3566 7.52214 79.3566 6.97431C79.3566 6.43265 79.5628 5.96792 79.9752 5.58014C80.3937 5.1862 80.8954 4.98923 81.4801 4.98923C82.0649 4.98923 82.5635 5.1862 82.9759 5.58014C83.3944 5.96792 83.6037 6.43265 83.6037 6.97431C83.6037 7.52214 83.3944 7.99302 82.9759 8.38696C82.5635 8.77474 82.0649 8.96863 81.4801 8.96863ZM89.7415 16.7797V24.9786H85.8083V10.7968H89.5569V13.2989H89.723C90.037 12.4741 90.5632 11.8216 91.3019 11.3415C92.0405 10.8552 92.9361 10.6121 93.9887 10.6121C94.9735 10.6121 95.8322 10.8275 96.5647 11.2584C97.2971 11.6893 97.8665 12.3048 98.2728 13.105C98.679 13.899 98.8821 14.8469 98.8821 15.9487V24.9786H94.9489V16.6505C94.9551 15.7826 94.7335 15.1055 94.2841 14.6192C93.8348 14.1268 93.2162 13.8806 92.4283 13.8806C91.8989 13.8806 91.4311 13.9944 91.0249 14.2222C90.6248 14.4499 90.3109 14.7823 90.0831 15.2193C89.8615 15.6502 89.7477 16.1703 89.7415 16.7797ZM107.665 25.2556C106.206 25.2556 104.951 24.9601 103.898 24.3692C102.852 23.7721 102.045 22.9289 101.479 21.8394C100.913 20.7437 100.63 19.448 100.63 17.9523C100.63 16.4935 100.913 15.2132 101.479 14.1114C102.045 13.0096 102.842 12.1509 103.87 11.5354C104.905 10.9199 106.117 10.6121 107.508 10.6121C108.444 10.6121 109.315 10.7629 110.121 11.0645C110.934 11.36 111.641 11.8062 112.245 12.4033C112.854 13.0004 113.328 13.7513 113.667 14.6561C114.005 15.5548 114.174 16.6074 114.174 17.8138V18.8941H102.199V16.4566H110.472C110.472 15.8903 110.349 15.3886 110.103 14.9516C109.856 14.5146 109.515 14.1729 109.078 13.9267C108.647 13.6744 108.145 13.5482 107.573 13.5482C106.976 13.5482 106.446 13.6867 105.985 13.9637C105.529 14.2345 105.172 14.6007 104.914 15.0624C104.655 15.5179 104.523 16.0257 104.517 16.5858V18.9033C104.517 19.605 104.646 20.2113 104.905 20.7222C105.169 21.2331 105.542 21.627 106.022 21.904C106.502 22.181 107.071 22.3195 107.73 22.3195C108.167 22.3195 108.567 22.2579 108.93 22.1348C109.293 22.0117 109.604 21.8271 109.863 21.5808C110.121 21.3346 110.318 21.033 110.454 20.676L114.091 20.9161C113.907 21.7901 113.528 22.5534 112.956 23.2058C112.389 23.8522 111.657 24.3569 110.758 24.7201C109.866 25.0771 108.835 25.2556 107.665 25.2556Z" class="fill-blue-600 dark:fill-white"/>
              <path d="M1 28.9786V15.9786C1 9.35116 6.37258 3.97858 13 3.97858C19.6274 3.97858 25 9.35116 25 15.9786C25 22.606 19.6274 27.9786 13 27.9786H12" class="stroke-blue-600 dark:stroke-white" stroke-width="2"/>
              <path d="M5 28.9786V16.1386C5 11.6319 8.58172 7.97858 13 7.97858C17.4183 7.97858 21 11.6319 21 16.1386C21 20.6452 17.4183 24.2986 13 24.2986H12" class="stroke-blue-600 dark:stroke-white" stroke-width="2"/>
              <circle cx="13" cy="16" r="5" class="fill-blue-600 dark:fill-white"/>
            </svg>
          </a>
        </div>
        <div class="lg:col-span-3">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
            Utilisation des cookies
          </h2>
          <p class="mt-2 text-sm text-gray-600 dark:text-neutral-400">
            Nous utilisons des cookies pour assurer le bon fonctionnement du site, personnaliser le contenu,
            analyser le trafic et proposer des fonctionnalités sociales. Vous pouvez accepter ou refuser
            tout ou partie des cookies.
          </p>
          <div class="mt-5 flex flex-col md:flex-row md:items-center gap-3">
            <div class="flex items-center justify-between md:justify-start w-full">
              <label class="md:order-2 text-sm text-gray-500 md:ms-3 dark:text-neutral-400">Cookies nécessaires</label>
              <label class="relative inline-block w-11 h-6 cursor-pointer">
                <input type="checkbox" checked disabled class="peer sr-only">
                <span class="absolute inset-0 bg-gray-200 rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-blue-600 dark:bg-neutral-700 dark:peer-checked:bg-blue-500 peer-disabled:opacity-50 peer-disabled:pointer-events-none"></span>
                <span class="absolute top-1/2 start-0.5 -translate-y-1/2 size-5 bg-white rounded-full shadow-sm !transition-transform duration-200 ease-in-out peer-checked:translate-x-full dark:bg-neutral-400 dark:peer-checked:bg-white"></span>
              </label>
            </div>
            <div class="flex items-center justify-between md:justify-start w-full">
              <label for="hs-cookies-preferences" class="md:order-2 text-sm text-gray-500 md:ms-3 dark:text-neutral-400">Préférences</label>
              <label for="hs-cookies-preferences" class="relative inline-block w-11 h-6 cursor-pointer">
                <input type="checkbox" id="hs-cookies-preferences" class="peer sr-only">
                <span class="absolute inset-0 bg-gray-200 rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-blue-600 dark:bg-neutral-700 dark:peer-checked:bg-blue-500 peer-disabled:opacity-50 peer-disabled:pointer-events-none"></span>
                <span class="absolute top-1/2 start-0.5 -translate-y-1/2 size-5 bg-white rounded-full shadow-sm !transition-transform duration-200 ease-in-out peer-checked:translate-x-full dark:bg-neutral-400 dark:peer-checked:bg-white"></span>
              </label>
            </div>
            <div class="flex items-center justify-between md:justify-start w-full">
              <label for="hs-cookies-statistics" class="md:order-2 text-sm text-gray-500 md:ms-3 dark:text-neutral-400">Statistiques</label>
              <label for="hs-cookies-statistics" class="relative inline-block w-11 h-6 cursor-pointer">
                <input type="checkbox" id="hs-cookies-statistics" class="peer sr-only">
                <span class="absolute inset-0 bg-gray-200 rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-blue-600 dark:bg-neutral-700 dark:peer-checked:bg-blue-500 peer-disabled:opacity-50 peer-disabled:pointer-events-none"></span>
                <span class="absolute top-1/2 start-0.5 -translate-y-1/2 size-5 bg-white rounded-full shadow-sm !transition-transform duration-200 ease-in-out peer-checked:translate-x-full dark:bg-neutral-400 dark:peer-checked:bg-white"></span>
              </label>
            </div>
            <div class="flex items-center justify-between md:justify-start w-full">
              <label for="hs-cookies-marketing" class="md:order-2 text-sm text-gray-500 md:ms-3 dark:text-neutral-400">Marketing</label>
              <label for="hs-cookies-marketing" class="relative inline-block w-11 h-6 cursor-pointer">
                <input type="checkbox" id="hs-cookies-marketing" class="peer sr-only">
                <span class="absolute inset-0 bg-gray-200 rounded-full transition-colors duration-200 ease-in-out peer-checked:bg-blue-600 dark:bg-neutral-700 dark:peer-checked:bg-blue-500 peer-disabled:opacity-50 peer-disabled:pointer-events-none"></span>
                <span class="absolute top-1/2 start-0.5 -translate-y-1/2 size-5 bg-white rounded-full shadow-sm !transition-transform duration-200 ease-in-out peer-checked:translate-x-full dark:bg-neutral-400 dark:peer-checked:bg-white"></span>
              </label>
            </div>
          </div>
        </div>
        <div class="col-span-full col-start-2 xl:col-start-5 xl:col-span-1">
          <div class="grid sm:grid-cols-3 xl:grid-cols-1 gap-y-2 sm:gap-y-0 sm:gap-x-5 xl:gap-y-2 xl:gap-x-0">
            <button id="allowAll" type="button" class="py-2 px-3 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:bg-blue-700 disabled:opacity-50 disabled:pointer-events-none">
              Tout accepter
            </button>
            <button id="allowSelection" type="button" class="py-2 px-3 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-blue-600 text-blue-600 hover:border-blue-500 hover:text-blue-500 focus:outline-hidden focus:border-blue-500 focus:text-blue-500 disabled:opacity-50 disabled:pointer-events-none dark:border-blue-500 dark:text-blue-500 dark:hover:text-blue-400 dark:hover:border-blue-400 dark:focus:text-blue-400 dark:focus:border-blue-400">
              Personnaliser
            </button>
            <button id="rejectAll" type="button" class="py-2 px-3 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden focus:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-800 dark:focus:bg-neutral-800">
              Tout refuser
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const banner = document.getElementById('cookie-banner');
  if (!banner) return;

  if (localStorage.getItem('cookiesConsent')) {
    banner.remove();
    return;
  }

  const pref = document.getElementById('hs-cookies-preferences');
  const stats = document.getElementById('hs-cookies-statistics');
  const marketing = document.getElementById('hs-cookies-marketing');

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

<script>
function openShareModal(title, price, url) {
    const modal = document.getElementById('shareModal');
    
    // Message personnalisé pour le partage
    const shareText = encodeURIComponent(`Découvre ce produit : ${title} au prix de ${price}€ !`);
    const shareUrl = encodeURIComponent(url || window.location.href);
    
    // Mise à jour des liens de partage
    document.getElementById('shareWA').href = `https://api.whatsapp.com/send?text=${shareText}%20${shareUrl}`;
    document.getElementById('shareX').href = `https://twitter.com/intent/tweet?text=${shareText}&url=${shareUrl}`;
    document.getElementById('shareFB').href = `https://www.facebook.com/sharer/sharer.php?u=${shareUrl}`;
    
    // Afficher le modal avec animation
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeShareModal() {
    const modal = document.getElementById('shareModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Fermer avec la touche Echap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeShareModal();
});
</script>

<script>
// Variables globales pour les stories
let currentStoryIndex = 0;
let currentMediaIndex = 0;
let storyInterval = null;
let storyProgress = 0;
const storyProducts = <?php echo json_encode($story_products); ?>;

function openStory(index) {
    currentStoryIndex = index;
    currentMediaIndex = 0;
    storyProgress = 0;
    
    const modal = document.getElementById('storyModal');
    const product = storyProducts[currentStoryIndex];
    
    // Mettre à jour les infos du produit
    document.getElementById('storyTitle').textContent = product.name;
    document.getElementById('storyPrice').textContent = product.price + ' €';
    document.getElementById('storyLink').href = product.link;
    
    // Créer les barres de progression
    const progressContainer = document.getElementById('storyProgress');
    progressContainer.innerHTML = '';
    
    product.media.forEach((media, i) => {
        const progressBar = document.createElement('div');
        progressBar.className = 'h-1 flex-1 bg-white/20 rounded-full overflow-hidden';
        progressBar.innerHTML = `<div class="h-full bg-orange-500 transition-all duration-100 ease-linear" 
                                 id="progress-${i}" style="width: ${i === 0 ? storyProgress : 0}%"></div>`;
        progressContainer.appendChild(progressBar);
    });
    
    // Afficher le premier média
    showMedia();
    
    // Démarrer la progression
    startProgress();
    
    // Afficher le modal
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeStory() {
    const modal = document.getElementById('storyModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    
    if (storyInterval) {
        clearInterval(storyInterval);
        storyInterval = null;
    }
}

function showMedia() {
    const product = storyProducts[currentStoryIndex];
    const media = product.media[currentMediaIndex];
    
    const imageEl = document.getElementById('storyImage');
    const videoEl = document.getElementById('storyVideo');
    
    if (media.type === 'image') {
        imageEl.src = media.url;
        imageEl.classList.remove('hidden');
        videoEl.classList.add('hidden');
        if (videoEl.src) {
            videoEl.pause();
            videoEl.currentTime = 0;
        }
    } else {
        videoEl.src = media.url;
        videoEl.classList.remove('hidden');
        imageEl.classList.add('hidden');
        videoEl.play();
    }
}

function startProgress() {
    const product = storyProducts[currentStoryIndex];
    const media = product.media[currentMediaIndex];
    
    if (storyInterval) {
        clearInterval(storyInterval);
    }
    
    storyProgress = 0;
    const duration = media.duration;
    const startTime = Date.now();
    
    storyInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        storyProgress = (elapsed / duration) * 100;
        
        // Mettre à jour la barre de progression
        const progressBar = document.getElementById(`progress-${currentMediaIndex}`);
        if (progressBar) {
            progressBar.style.width = storyProgress + '%';
        }
        
        if (storyProgress >= 100) {
            nextMedia();
        }
    }, 50);
}

function nextMedia() {
    const product = storyProducts[currentStoryIndex];
    
    if (currentMediaIndex < product.media.length - 1) {
        currentMediaIndex++;
        storyProgress = 0;
        showMedia();
        startProgress();
    } else if (currentStoryIndex < storyProducts.length - 1) {
        currentStoryIndex++;
        currentMediaIndex = 0;
        storyProgress = 0;
        
        // Mettre à jour les infos du produit
        const newProduct = storyProducts[currentStoryIndex];
        document.getElementById('storyTitle').textContent = newProduct.name;
        document.getElementById('storyPrice').textContent = newProduct.price + ' €';
        document.getElementById('storyLink').href = newProduct.link;
        
        // Recréer les barres de progression
        const progressContainer = document.getElementById('storyProgress');
        progressContainer.innerHTML = '';
        
        newProduct.media.forEach((media, i) => {
            const progressBar = document.createElement('div');
            progressBar.className = 'h-1 flex-1 bg-white/20 rounded-full overflow-hidden';
            progressBar.innerHTML = `<div class="h-full bg-orange-500 transition-all duration-100 ease-linear" 
                                     id="progress-${i}" style="width: 0%"></div>`;
            progressContainer.appendChild(progressBar);
        });
        
        showMedia();
        startProgress();
    } else {
        closeStory();
    }
}

function prevMedia() {
    const product = storyProducts[currentStoryIndex];
    
    if (currentMediaIndex > 0) {
        currentMediaIndex--;
        storyProgress = 0;
        showMedia();
        startProgress();
    } else if (currentStoryIndex > 0) {
        currentStoryIndex--;
        const prevProduct = storyProducts[currentStoryIndex];
        currentMediaIndex = prevProduct.media.length - 1;
        storyProgress = 0;
        
        // Mettre à jour les infos du produit
        document.getElementById('storyTitle').textContent = prevProduct.name;
        document.getElementById('storyPrice').textContent = prevProduct.price + ' €';
        document.getElementById('storyLink').href = prevProduct.link;
        
        // Recréer les barres de progression
        const progressContainer = document.getElementById('storyProgress');
        progressContainer.innerHTML = '';
        
        prevProduct.media.forEach((media, i) => {
            const progressBar = document.createElement('div');
            progressBar.className = 'h-1 flex-1 bg-white/20 rounded-full overflow-hidden';
            progressBar.innerHTML = `<div class="h-full bg-orange-500 transition-all duration-100 ease-linear" 
                                     id="progress-${i}" style="width: ${i === currentMediaIndex ? 100 : 0}%"></div>`;
            progressContainer.appendChild(progressBar);
        });
        
        showMedia();
        startProgress();
    }
}

// Gestionnaires d'événements pour les boutons du modal de partage
document.addEventListener('DOMContentLoaded', function() {
    const shareModal = document.getElementById('shareModal');
    if (shareModal) {
        shareModal.innerHTML = `
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
        `;
    }
});
</script>

<script>
function productFeed() {
    return {
        products: <?php echo json_encode($feed_products); ?>,
        
        lazyLoad(img) {
            const speed =
                navigator.connection?.effectiveType === '4g' ? 2000 :
                navigator.connection?.effectiveType === '3g' ? 4000 :
                1200;
            
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
            }, {
                rootMargin: '200px'
            });
            
            observer.observe(img);
        },
        
        formatPrice(val) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(val);
        }
    }
}
</script>

<style>
    @keyframes bounce-x {
        0%, 100% { transform: translateX(0); }
        50% { transform: translateX(5px); }
    }
    .animate-bounce-x { animation: bounce-x 1s infinite; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    [x-cloak] { display: none !important; }
</style>

<script src="https://cdn.jsdelivr.net/npm/preline/dist/index.js"></script>
</body>
</html>