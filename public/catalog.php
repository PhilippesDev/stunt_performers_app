<?php
require_once __DIR__ . '/../libs/Router.php';
require_once __DIR__ . '/../libs/db.php';
require_once __DIR__ . '/../libs/user_helper.php';

// Fetch profile pic
$profilePic = getUserProfilePic();

// Fetch categories and subcategories
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories ORDER BY name");
while ($cat_row = $cat_result->fetch_assoc()) {
    $categories[$cat_row['id']] = $cat_row;
    $categories[$cat_row['id']]['subcategories'] = [];
}

$sub_result = $conn->query("SELECT * FROM subcategories ORDER BY name");
while ($sub_row = $sub_result->fetch_assoc()) {
    if (isset($categories[$sub_row['category_id']])) {
        $categories[$sub_row['category_id']]['subcategories'][] = $sub_row;
    }
}

// Redirect function for categories
if (isset($_GET['cat_id']) || isset($_GET['sub_id'])) {
    $category_id = $_GET['cat_id'] ?? null;
    $subcategory_id = $_GET['sub_id'] ?? null;
    
    // Build search query based on category
    if ($subcategory_id) {
        // Get subcategory name
        $sub_query = $conn->prepare("SELECT id, name, category_id FROM subcategories WHERE id = ?");
        $sub_query->bind_param("i", $subcategory_id);
        $sub_query->execute();
        $sub_data = $sub_query->get_result()->fetch_assoc();
        
        if ($sub_data) {
            $search_query = $sub_data['name'];
            $category_id = $sub_data['category_id'];
        }
    } elseif ($category_id) {
        // Get category name
        $cat_query = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
        $cat_query->bind_param("i", $category_id);
        $cat_query->execute();
        $cat_data = $cat_query->get_result()->fetch_assoc();
        
        if ($cat_data) {
            $search_query = $cat_data['name'];
        }
    }
    
    // Redirect to search with category as search query
    if (isset($search_query)) {
        header("Location: " . url('search') . "?search=" . urlencode($search_query) . "&category_id=" . $category_id . ($subcategory_id ? "&subcategory_id=" . $subcategory_id : ""));
        exit();
    }
}

$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/preline@2.0.3/dist/preline.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link rel="apple-touch-icon" href="images/img/apple-touch-icon.png">
    <style>
        body { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .category-card {
            transition: all 0.2s ease;
        }
        .category-card:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50" x-data="{ searchQuery: '<?php echo $search_query; ?>' }">
    <!-- Background pattern -->
    <div class="pointer-events-none fixed inset-0 opacity-[0.03] bg-grid"></div>

    <div class="max-w-6xl mx-auto pb-20">
        <!-- Header -->
        <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-sm border-b border-gray-100">
            <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
                <!-- Logo -->
                <a href="<?= url('/') ?>" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                    <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                    <span>ecascadeur<span class="text-fuchsia-500">.com</span></span>
                </a>

                <!-- User Menu -->
                <div class="flex items-center gap-4">
                    <a href="<?= url('dashboard') ?>" class="flex items-center gap-2 text-gray-600 hover:text-fuchsia-500 transition-colors">
                        <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Profil" class="w-8 h-8 rounded-full object-cover border border-gray-200">
                        <span class="text-sm font-medium hidden md:inline">Mon Compte</span>
                    </a>
                    <a href="<?= url('logout') ?>" class="p-2 text-gray-400 hover:text-fuchsia-500 transition-colors">
                        <i class="bi bi-box-arrow-right text-lg"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Search Bar -->
        <div class="px-4 pt-6 pb-4">
            <form action="<?= url('search') ?>" method="GET" class="max-w-2xl mx-auto">
                <div class="relative group">
                    <input type="text" 
                           name="search" 
                           x-model="searchQuery" 
                           placeholder="Rechercher un produit, une marque, une catégorie..." 
                           required
                           class="w-full rounded-xl border border-gray-200 bg-white pl-12 pr-4 py-3.5 text-sm focus:ring-2 focus:ring-fuchsia-400 focus:border-fuchsia-400 outline-none shadow-sm transition-all duration-200">
                    <button type="submit" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-fuchsia-500 transition-colors">
                        <i class="bi bi-search text-lg"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Main Content -->
        <main class="px-4 pt-2">
            <!-- Page Title -->
            <div class="mb-6">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Catalogue des catégories</h1>
                <p class="text-gray-500 text-sm mt-1">Parcourez nos catégories pour trouver ce dont vous avez besoin</p>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <?php foreach ($categories as $cat): ?>
                    <div class="category-card bg-white rounded-2xl p-5 shadow-sm border border-gray-100 hover:shadow-lg transition-all">
                        <!-- Category Header -->
                        <div class="flex items-start gap-4 mb-5">
                            <div class="w-14 h-14 rounded-xl bg-fuchsia-50 flex items-center justify-center flex-shrink-0">
                                <i class="<?php echo htmlspecialchars($cat['icon_class']); ?> text-2xl text-fuchsia-500"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1 truncate"><?php echo htmlspecialchars($cat['name']); ?></h3>
                                <p class="text-sm text-gray-500 line-clamp-2"><?php echo htmlspecialchars($cat['description']); ?></p>
                            </div>
                        </div>

                        <!-- Subcategories -->
                        <?php if (!empty($cat['subcategories'])): ?>
                            <div class="space-y-3 mb-5">
                                <?php foreach (array_slice($cat['subcategories'], 0, 4) as $index => $sub): ?>
                                    <a href="<?= url('category-products') ?>?type=sub&id=<?php echo $sub['id']; ?>" 
                                       class="group flex items-center justify-between p-3 bg-gray-50 hover:bg-fuchsia-50 rounded-xl transition-colors">
                                        <span class="text-sm font-medium text-gray-700 group-hover:text-fuchsia-600">
                                            <?php echo htmlspecialchars($sub['name']); ?>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-400 group-hover:text-fuchsia-500 text-sm"></i>
                                    </a>
                                <?php endforeach; ?>
                                
                                <?php if (count($cat['subcategories']) > 4): ?>
                                    <button type="button" 
                                            class="hs-collapse-toggle w-full flex items-center justify-between text-sm font-medium text-gray-500 hover:text-fuchsia-600 p-3"
                                            data-hs-collapse="#more-subs-<?php echo $cat['id']; ?>">
                                        Voir plus de sous-catégories
                                        <i class="bi bi-chevron-down text-xs"></i>
                                    </button>
                                    
                                    <div id="more-subs-<?php echo $cat['id']; ?>" class="hs-collapse hidden space-y-3">
                                        <?php foreach (array_slice($cat['subcategories'], 4) as $sub): ?>
                                            <a href="<?= url('category-products') ?>?type=sub&id=<?php echo $sub['id']; ?>" 
                                               class="group flex items-center justify-between p-3 bg-gray-50 hover:bg-fuchsia-50 rounded-xl transition-colors">
                                                <span class="text-sm font-medium text-gray-700 group-hover:text-fuchsia-600">
                                                    <?php echo htmlspecialchars($sub['name']); ?>
                                                </span>
                                                <i class="bi bi-chevron-right text-gray-400 group-hover:text-fuchsia-500 text-sm"></i>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- View All Button -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <span class="text-xs text-gray-500">
                                <?php 
                                $sub_count = count($cat['subcategories']);
                                echo $sub_count . " sous-catégorie" . ($sub_count > 1 ? 's' : '');
                                ?>
                            </span>
                            <a href="<?= url('category-products') ?>?type=categ&id=<?php echo $cat['id']; ?>" 
                               class="inline-flex items-center gap-2 px-4 py-2 bg-fuchsia-500 hover:bg-fuchsia-600 text-white text-sm font-medium rounded-lg transition-colors">
                                Tout voir
                                <i class="bi bi-arrow-right-short text-lg"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <?php if (empty($categories)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="bi bi-grid-3x3 text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Aucune catégorie disponible</h3>
                    <p class="text-gray-500 text-sm">Les catégories seront bientôt ajoutées.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include "footer.html" ?>


    <script>
        // Smooth hover effects
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize HS Collapse
            document.querySelectorAll('.hs-collapse-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-hs-collapse');
                    const target = document.querySelector(targetId);
                    if (target) {
                        target.classList.toggle('hidden');
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.classList.toggle('bi-chevron-down');
                            icon.classList.toggle('bi-chevron-up');
                        }
                    }
                });
            });

            // Add click animation to category cards
            document.querySelectorAll('.category-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Only trigger if clicking on the card itself, not on links inside
                    if (e.target.tagName !== 'A' && !e.target.closest('a')) {
                        const link = this.querySelector('a[href*="cat_id"]');
                        if (link) {
                            e.preventDefault();
                            link.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                link.style.transform = '';
                                window.location.href = link.href;
                            }, 150);
                        }
                    }
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === '/' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
        });
    </script>
</body>
</html>
<?php
if (isset($cat_result)) $cat_result->free();
if (isset($sub_result)) $sub_result->free();
$conn->close();
?>