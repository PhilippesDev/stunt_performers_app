<?php
// index.php - Page d'accueil ecascadeur.com (version corrigée & améliorée)
session_start();
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// 1. Catégories + sous-catégories
$categories = [];
$stmt = $conn->prepare("SELECT id, name, icon_class, description FROM categories ORDER BY id");
$stmt->execute();
$categories_result = $stmt->get_result();

while ($cat = $categories_result->fetch_assoc()) {
    $sub_stmt = $conn->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name");
    $sub_stmt->bind_param("i", $cat['id']);
    $sub_stmt->execute();
    $sub_result = $sub_stmt->get_result();
    
    $cat['subcategories'] = $sub_result->fetch_all(MYSQLI_ASSOC);
    $categories[] = $cat;
    
    $sub_stmt->close();
}
$stmt->close();

// 2. Stories (produits récents)
$stories_query = "
    SELECT 
        p.id, 
        p.name, 
        p.price,
        p.currency,
        pm.file_path as image,
        DATE_FORMAT(p.created_at, '%d/%m') as date_short
    FROM products p
    LEFT JOIN product_media pm 
        ON p.id = pm.product_id 
        AND pm.file_type = 'image' 
        AND pm.sort_order = 0
    WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 15
";
$stmt = $conn->prepare($stories_query);
$stmt->execute();
$stories_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Préparation du tableau JavaScript sécurisé
$stories_json = json_encode(array_map(function($item) {
    return [
        'id'       => $item['id'],
        'name'     => htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'),
        'price'    => number_format($item['price'], 0),
        'currency' => $item['currency'],
        'image'    => htmlspecialchars($item['image'] ?? '/img/placeholder-product.jpg', ENT_QUOTES, 'UTF-8'),
        'date'     => $item['date_short']
    ];
}, $stories_raw));
?>

<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Cascade • Acheter & Vendre en toute simplicité</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"/>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #FF5C01;
            --primary-dark: #E04F00;
            --light-bg: #FDF2F0;
        }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: var(--light-bg);
        }
        .text-primary { color: var(--primary); }
        .bg-primary { background-color: var(--primary); }
        .hover\:bg-primary:hover { background-color: var(--primary); }

        .story-item {
            transition: all 0.4s ease;
        }
        .story-active {
            transform: scale(1.08);
            z-index: 10;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="min-h-screen" x-data="appData()">

    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-xl border-b border-gray-100 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 md:h-20">
                <div class="flex items-center gap-10 flex-1">
                    <a href="index.php" class="flex items-center gap-2 text-3xl font-black">
                        <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                        <span>ecascadeur<span class="text-primary">.</span>com</span>
                    </a>

                    <div class="hidden md:flex flex-1 max-w-2xl relative">
                        <input type="text" placeholder="Rechercher..." class="w-full pl-12 pr-5 py-3 bg-gray-100 rounded-full focus:ring-2 focus:ring-primary/50 outline-none">
                        <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    </div>
                </div>

                <div class="flex items-center gap-6">
                    <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="flex items-center gap-2 text-gray-700 hover:text-primary">
                        <i class="bi bi-person-circle text-2xl"></i>
                        <span class="hidden md:inline font-medium">Compte</span>
                    </a>
                    <?php else: ?>
                    <a href="login.php" class="px-6 py-2.5 bg-primary text-white rounded-full font-medium hover:bg-primary-dark transition">
                        Connexion
                    </a>
                    <?php endif; ?>

                    <button @click="mobileMenu = !mobileMenu" class="md:hidden text-2xl">
                        <i x-show="!mobileMenu" class="bi bi-list"></i>
                        <i x-show="mobileMenu" class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation catégories (mega menu) -->
    <nav class="bg-white border-b border-gray-100 sticky top-16 md:top-20 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex gap-8 md:gap-12 py-4 overflow-x-auto hide-scrollbar whitespace-nowrap">
                <?php foreach ($categories as $cat): ?>
                <div class="relative group" x-data="{ open: false }">
                    <button 
                        @mouseenter="open = true" 
                        @mouseleave="open = false"
                        @focus="open = true"
                        @blur="open = false"
                        class="flex items-center gap-2 text-gray-800 font-medium hover:text-primary transition">
                        <?php if (!empty($cat['icon_class'])): ?>
                        <i class="<?= htmlspecialchars($cat['icon_class']) ?>"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($cat['name']) ?>
                    </button>

                    <div x-show="open"
                         x-transition
                         @mouseenter="open = true"
                         @mouseleave="open = false"
                         class="absolute top-full left-0 pt-3 z-50 hidden md:block">
                        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 w-[760px] p-8 grid grid-cols-4 gap-x-10 gap-y-6">
                            <?php foreach ($cat['subcategories'] as $sub): ?>
                            <a href="#" class="text-gray-700 hover:text-primary transition text-sm">
                                <?= htmlspecialchars($sub['name']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <!-- Menu mobile -->
    <div x-show="mobileMenu" 
         x-transition
         class="fixed inset-0 z-50 bg-white pt-24 px-6 md:hidden">
        <div class="space-y-8 text-lg">
            <a href="#" class="block py-3 border-b">Accueil</a>
            <a href="#" class="block py-3 border-b">Catégories</a>
            <a href="add_product1.php" class="block py-3 border-b">Vendre</a>
            <?php if ($is_logged_in): ?>
            <a href="dashboard.php" class="block py-4 text-primary font-bold">Mon compte</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero + Stories -->
    <section class="pt-8 md:pt-12 pb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black tracking-tight">
                    Achetez & Vendez <span class="text-primary">local</span>
                </h1>
                <p class="mt-4 text-lg md:text-xl text-gray-600">
                    Annonces vérifiées • Paiement sécurisé • Livraison rapide
                </p>
            </div>

            <!-- Stories -->
            <div class="relative">
                <div class="flex gap-3 overflow-x-auto hide-scrollbar pb-6 snap-x snap-mandatory">
                    <!-- Bouton Ajouter -->
                    <a href="add_product1.php" class="flex-none w-28 md:w-32 snap-start">
                        <div class="w-28 h-44 md:w-32 md:h-52 rounded-3xl bg-gradient-to-br from-primary/30 to-primary/5 flex items-center justify-center border-4 border-white shadow-lg">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mb-2 shadow-md mx-auto">
                                    <i class="bi bi-plus-lg text-4xl text-primary"></i>
                                </div>
                                <p class="text-sm font-medium text-gray-800">Publier</p>
                            </div>
                        </div>
                    </a>

                    <!-- Stories -->
                    <template x-for="(story, index) in stories" :key="index">
                        <div class="flex-none w-28 md:w-32 snap-start cursor-pointer"
                             :class="{ 'story-active': currentStory === index }"
                             @click="openStory(index)">
                            <div class="relative w-28 h-44 md:w-32 md:h-52 rounded-3xl overflow-hidden border-4 border-white shadow-xl">
                                <img :src="story.image" 
                                     :alt="story.name"
                                     class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
                                <div class="absolute bottom-3 left-3 right-3">
                                    <p class="text-xs md:text-sm font-medium text-white line-clamp-2 drop-shadow-md">
                                        <template x-text="story.name"></template>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Story Viewer (lightbox) -->
                <template x-if="currentStory >= 0">
                <div class="fixed inset-0 z-[100] bg-black/95 flex items-center justify-center" @click="closeStory()">
                    <div class="relative w-full max-w-lg h-full md:h-auto" @click.stop>
                        <!-- Barre de progression -->
                        <div class="absolute top-4 left-4 right-4 z-20 flex gap-1.5 px-4">
                            <template x-for="(_, i) in stories">
                                <div class="flex-1 h-1 bg-white/30 rounded-full overflow-hidden">
                                    <div class="h-full bg-white transition-all duration-[10000ms] ease-linear"
                                         :style="progressStyle(i)"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Image principale -->
                        <img :src="stories[currentStory].image"
                             class="w-full h-full md:h-[85vh] object-contain md:rounded-2xl"
                             :alt="stories[currentStory].name">

                        <!-- Infos produit -->
                        <div class="absolute bottom-6 left-6 right-6 bg-black/60 backdrop-blur-lg p-6 rounded-2xl">
                            <h3 class="text-white text-xl font-bold mb-2 line-clamp-2" x-text="stories[currentStory].name"></h3>
                            <div class="flex items-center justify-between">
                                <div class="text-3xl font-black text-white">
                                    <span x-text="stories[currentStory].price"></span>
                                    <span class="text-2xl" x-text="stories[currentStory].currency"></span>
                                </div>
                                <a :href="'buy_product.php?id=' + stories[currentStory].id"
                                   class="bg-primary text-white px-8 py-4 rounded-xl font-bold hover:bg-primary-dark transition">
                                    Voir l'offre →
                                </a>
                            </div>
                        </div>

                        <!-- Contrôles -->
                        <button @click="prevStory()" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-5xl p-4 opacity-70 hover:opacity-100">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button @click="nextStory()" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-5xl p-4 opacity-70 hover:opacity-100">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button @click="closeStory()" class="absolute top-4 right-4 text-white text-4xl p-3 opacity-70 hover:opacity-100">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                </template>
            </div>
        </div>
    </section>

    <!-- Script Alpine global -->
    <script>
    function appData() {
        return {
            stories: <?= $stories_json ?? '[]' ?>,
            currentStory: -1,
            timer: null,
            mobileMenu: false,

            init() {
                this.$watch('currentStory', (val) => {
                    if (val >= 0) {
                        this.startTimer();
                    } else {
                        this.stopTimer();
                    }
                });
            },

            openStory(index) {
                this.currentStory = index;
            },

            closeStory() {
                this.currentStory = -1;
            },

            nextStory() {
                if (this.currentStory < this.stories.length - 1) {
                    this.currentStory++;
                } else {
                    this.closeStory();
                }
            },

            prevStory() {
                if (this.currentStory > 0) {
                    this.currentStory--;
                }
            },

            startTimer() {
                this.stopTimer();
                this.timer = setTimeout(() => {
                    this.nextStory();
                }, 10000);
            },

            stopTimer() {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
            },

            progressStyle(index) {
                if (this.currentStory === -1) return 'width: 0%';
                if (index < this.currentStory) return 'width: 100%';
                if (index > this.currentStory) return 'width: 0%';
                // Pour la story courante : on simule l'avancement (approximation)
                return 'width: 0%'; // → peut être amélioré avec requestAnimationFrame
            }
        }
    }
    </script>

    <!-- ScrollReveal -->
    <script>
    ScrollReveal().reveal('.product-card', {
        delay: 80,
        distance: '30px',
        origin: 'bottom',
        interval: 70
    });
    </script>

</body>
</html>