<?php
session_start();
require_once 'db.php';

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
require_once 'user_helper.php';
$profilePic = getUserProfilePic();

// Statistiques rapides
$total_products = $conn->query("SELECT COUNT(*) FROM products WHERE user_id = $user_id")->fetch_row()[0];
$total_orders_received = $conn->query("SELECT COUNT(*) FROM orders WHERE seller_id = $user_id")->fetch_row()[0];
$total_orders_ordered = $conn->query("SELECT COUNT(*) FROM orders WHERE user_id = $user_id")->fetch_row()[0];
$total_orders_pending = $conn->query("SELECT COUNT(*) FROM temp_orders WHERE user_id = $user_id")->fetch_row()[0];
$total_sales = $conn->query("SELECT SUM(total_amount) FROM orders WHERE seller_id = $user_id")->fetch_row()[0] ?? 0;
$total_purchases = $conn->query("SELECT SUM(total_amount) FROM orders WHERE user_id = $user_id")->fetch_row()[0] ?? 0;

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

// Onglet actif par défaut
$active_tab = $_GET['tab'] ?? 'dashboard';

// Récupération des données selon l'onglet actif
$products = [];
$pending_orders = [];
$completed_orders = [];
$completed_orders_delivered = [];

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
elseif ($active_tab === 'pending') {
    $stmt = $conn->prepare("
        SELECT t.id, t.customer_name, t.unit_value, t.unit_type, t.total_amount, 
               t.created_at, p.name as product_name, p.id as product_id
        FROM temp_orders t 
        JOIN products p ON t.product_id = p.id
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $pending_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} 
elseif ($active_tab === 'completed') {
    $stmt = $conn->prepare("
        SELECT o.id, o.customer_name, o.quantity, o.total_amount, o.created_at, 
               p.name as product_name, p.id as product_id
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.seller_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 30
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $completed_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Récupération des achats (commandes où l'utilisateur est l'acheteur)
$purchases = [];
if ($active_tab === 'purchases') {
    $stmt = $conn->prepare("
        SELECT o.id, o.customer_name, o.quantity, o.total_amount, o.created_at, 
               p.name as product_name, p.id as product_id,
               u.username as seller_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users u ON o.seller_id = u.id
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 30
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $purchases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Récupérer les commandes annulées (depuis order_cancellation_reasons)
$cancelled_orders = [];
$stmt = $conn->prepare("
    SELECT ocr.id, ocr.reason, ocr.created_at, 
           u.username as cancelled_by, o.customer_name,
           p.name as product_name, p.id as product_id
    FROM order_cancellation_reasons ocr
    LEFT JOIN orders o ON ocr.order_id = o.id
    LEFT JOIN users u ON ocr.user_id = u.id
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.seller_id = ? 
    ORDER BY ocr.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cancelled_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
    <link rel="icon" type="image/png" href="favicon.png">
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
        
        /* Animation pour les modales */
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-animation {
            animation: fadeIn 0.2s ease-out;
        }
        
        /* Style pour les dialogs */
        dialog {
            border: none;
            border-radius: 1.5rem;
            box-***: 0 20px 60px rgba(0, 0, 0, 0.3);
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
        
        /* Style pour mobile */
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
    </style>
</head>
<body class="min-h-screen" x-data="{
    openModal: null,
    deleteProductId: null,
    deleteProductName: '',
    showPasswordModal: false,
    
    openSettingsModal() {
        this.openModal = 'settings';
    },
    
    openPasswordModal() {
        // Fermer d'abord la modal Paramètres
        if (this.openModal === 'settings') {
            this.openModal = null;
        }
        // Ouvrir la modal Mot de passe
        this.showPasswordModal = true;
    },
    
    openPendingModal() {
        this.openModal = 'pending';
    },
    
    openCompletedModal() {
        this.openModal = 'completed';
    },
    
    // AJOUTER CETTE FONCTION
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
    
    closeModal() {
        this.openModal = null;
        this.deleteProductId = null;
        this.deleteProductName = '';
        this.showPasswordModal = false;
    }
}">
    <!-- Navigation principale (desktop) -->
    <nav class="hidden md:flex items-center justify-between px-8 py-4 bg-white border-b sticky top-0 z-50">
        <div class="flex items-center gap-2">
            <a href="index.php" class="flex items-center gap-2 text-2xl font-bold tracking-tight text-gray-900">
                <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
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
</nav>
        <div class="flex items-center gap-4">
            <button @click="openSettingsModal()" class="bg-fuchsia-700 text-white px-6 py-2 rounded-xl font-medium hover:bg-fuchsia-900 transition">
                Paramètres
            </button>
            <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profil" class="w-10 h-10 rounded-full border border-gray-200">
        </div>
    </nav>

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
        <!-- En-tête profil mobile -->
        <div class="md:hidden mb-6 bg-gradient-to-r from-fuchsia-50 to-pink-50 rounded-3xl p-6 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-64 glass-header -z-10"></div>
            <div class="flex items-center justify-between mb-4">
                <button onclick="history.back()" class="bg-white/90 p-2 rounded-xl ***-sm">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <button @click="openSettingsModal()" class="bg-white/90 p-2 rounded-xl ***-sm">
                    <i class="bi bi-gear"></i>
                </button>
            </div>
            <div class="flex items-end gap-4">
                <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($firstname) ?>" 
                     class="w-20 h-20 rounded-full border-4 border-white object-cover ***-md">
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
                    <span class="block font-bold"><?= $total_orders_received ?></span>
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
                            <div class="bg-fuchsia-700 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_ordered, 100) ?>"></div>
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
                        <span class="text-4xl font-black text-gray-900"><?php echo number_format($total_orders_received * 3.7 ,  1, '.', '')?></span>
                        <span class="text-gray-400 font-medium">/ 100 pts</span>
                    </div>

                    <div class="space-y-3">
                        <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                            <div class="bg-teal-500 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_received * 3.7, 100) ?>%"></div>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-relaxed">
                            Objectif 100 : Soyez rémunéré à hauteur de <span class="font-bold text-teal-600">20% du montant total</span> des produits vendus.
                        </p>
                    </div>
                </div>

            </div>

        <!-- En-tête profil desktop -->
        <div class="hidden md:block bg-white rounded-[40px] ***-sm overflow-hidden relative mb-8">
            <div class="absolute top-0 left-0 right-0 h-64 glass-header -z-10"></div>
                <div class="relative">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="<?= htmlspecialchars($firstname) ?>" 
                         class="w-40 h-40 rounded-full m-auto object-cover border-8 border-white ***-lg">
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
                        <span class="text-4xl font-black text-gray-900"><?= $total_orders_ordered ?></span>
                        <span class="text-gray-400 font-medium">/ 100 pts</span>
                    </div>

                    <div class="space-y-3">
                        <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                            <div class="bg-fuchsia-700 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_ordered, 100) ?>%"></div>
                        </div>
                        <p class="text-[11px] text-gray-500 leading-relaxed">
                            Plus que <span class="font-bold text-fuchsia-900"><?php echo 100 - $total_orders_ordered ?> points</span> pour obtenir un <span class="font-bold">achat gratuit (20%)</span> sur vos prochaines ventes.
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
                        <span class="text-4xl font-black text-gray-900"><?= $total_orders_received ?></span>
                        <span class="text-gray-400 font-medium">/ 100 pts</span>
                    </div>

                    <div class="space-y-3">
                        <div class="w-full bg-gray-100 h-3 rounded-full overflow-hidden">
                            <div class="bg-teal-500 h-full rounded-full transition-all duration-500" style="width: <?= min($total_orders_received, 100) ?>%"></div>
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
            <div class="bg-white p-4 rounded-3xl ***-sm relative">
                <div class="bg-yellow-100 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-box-seam text-yellow-600"></i>
                </div>
                <div class="text-2xl font-bold"><?= $total_products ?></div>
                <div class="text-xs text-gray-400">Produits</div>
            </div>
            <div class="bg-white p-4 rounded-3xl ***-sm relative">
                <div class="bg-fuchsia-100 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-clock-history text-fuchsia-900"></i>
                </div>
                <div class="text-2xl font-bold text-fuchsia-900"><?= $total_orders_pending ?></div>
                <div class="text-xs text-gray-400">Mes achats en attente</div>
            </div>
            <div class="bg-white p-4 rounded-3xl ***-sm">
                <div class="bg-emerald-50 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-bag-check text-emerald-600"></i>
                </div>
                <div class="text-2xl font-bold text-emerald-600"><?= $total_orders_received ?> / <?= number_format($total_sales, 0) ?> $</div>
                <div class="text-xs text-gray-400">Total Achats</div>
            </div>
            <div class="bg-white p-4 rounded-3xl ***-sm">
                <div class="bg-blue-50 w-10 h-10 rounded-lg flex items-center justify-center mb-3">
                    <i class="bi bi-cash-stack text-blue-600"></i>
                </div>
                <div class="text-2xl font-bold text-emerald-600"><?= $total_orders_received ?> / <?= number_format($total_sales, 0) ?> $</div>
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
        </div>

        <!-- Contenu selon onglet -->

        <!-- Dashboard principal -->
        <div x-show="'dashboard' === '<?= $active_tab ?>'" x-transition class="space-y-6">
            <!-- Panneaux interactifs -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Commandes en attente -->
                <div class="bg-white rounded-3xl p-6 ***-sm cursor-pointer hover:***-md transition-***"
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
                                <div>
                                    <p class="font-medium text-sm line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="font-bold text-fuchsia-900"><?= number_format($order['total_amount'], 0) ?> $</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($pending_orders)): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <span class="text-fuchsia-900 text-sm font-medium cursor-pointer flex items-center gap-1">
                            Voir tout <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Ventes finalisées -->
                <div class="bg-white rounded-3xl p-6 ***-sm cursor-pointer hover:***-md transition-***"
                     @click="openCompletedModal()">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="font-bold text-lg mb-1">Ventes finalisées</h3>
                            <p class="text-gray-500 text-sm">Historique récent</p>
                        </div>
                        <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-sm font-medium">
                            <?= count($completed_orders) ?>
                        </span>
                    </div>
                    <?php if (empty($completed_orders)): ?>
                        <div class="text-center py-8">
                            <i class="bi bi-bag-check text-5xl text-gray-300 mb-4 block"></i>
                            <p class="text-gray-600">Aucune vente finalisée</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach (array_slice($completed_orders, 0, 3) as $order): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-sm line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></p>
                                    <p class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($order['created_at'])) ?></p>
                                </div>
                                <span class="font-bold text-emerald-600"><?= number_format($order['total_amount'], 0) ?> $</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($completed_orders)): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <span class="text-fuchsia-900 text-sm font-medium cursor-pointer flex items-center gap-1">
                            Voir tout <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

           
            <!-- Annulations -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-3xl p-6 ***-sm cursor-pointer hover:***-md transition-***"
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
                                    <?= htmlspecialchars($c['cancelled_by']) ?> - <?= htmlspecialchars($c['reason']) ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Ajouter un produit -->
                <div class="bg-white rounded-3xl p-6 ***-sm hover:***-md transition-***">
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
                <a href="add_product1.php" class="bg-fuchsia-700 text-white px-6 py-3 rounded-xl text-sm font-medium hover:bg-fuchsia-900 transition flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Ajouter un produit
                </a>
            </div>

            <?php if (empty($products)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-box-seam text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Vous n'avez pas encore ajouté de produit</p>
                    <a href="add_product1.php" class="text-fuchsia-900 font-medium hover:underline flex items-center justify-center gap-2 text-lg">
                        Commencer maintenant <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($products as $p): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden hover:border-fuchsia-200 transition-all group ***-sm hover:***-md">
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

            <?php if (empty($pending_orders)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-clock-history text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Aucune commande en attente de paiement</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 ***-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="bg-fuchsia-100 text-fuchsia-900 px-3 py-1 rounded-lg text-sm font-medium">
                                    En attente
                                </span>
                            </div>
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
            <h2 class="text-xl font-bold text-gray-900">Ventes finalisées (<?= count($completed_orders) ?>)</h2>

            <?php if (empty($completed_orders)): ?>
                <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                    <i class="bi bi-bag-check text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-6">Aucune vente finalisée pour le moment</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($completed_orders as $order): ?>
                        <div class="bg-white rounded-2xl border border-gray-100 p-6 ***-sm">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                </div>
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-sm font-medium">
                                    Finalisée
                                </span>
                            </div>
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
                <h2 class="text-xl font-bold text-gray-900">Mes achats (<?= count($purchases) ?>)</h2>

                <?php if (empty($purchases)): ?>
                    <div class="bg-white rounded-3xl p-12 text-center border border-gray-100">
                        <i class="bi bi-cart-check text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-6">Vous n'avez effectué aucun achat</p>
                        <a href="catalog.php" class="text-fuchsia-900 font-medium hover:underline flex items-center justify-center gap-2 text-lg">
                            Parcourir les produits <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($purchases as $purchase): ?>
                            <div class="bg-white rounded-2xl border border-gray-100 p-6 ***-sm">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($purchase['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($purchase['seller_name']) ?></p>
                                    </div>
                                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm font-medium">
                                        Acheté
                                    </span>
                                </div>
                                
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

    </main>

    <!-- Footer mobile -->
    <footer class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 py-3 px-4 md:hidden z-50">
        <div class="flex justify-around items-center text-xs text-gray-600">
            <a href="index.php" class="flex flex-col items-center gap-1 hover:text-fuchsia-700 transition">
                <i class="bi bi-house-door text-lg"></i>
                <span>Accueil</span>
            </a>
            <a href="catalog.php" class="flex flex-col items-center gap-1 hover:text-fuchsia-700 transition">
                <i class="bi bi-grid-3x3-gap-fill text-lg"></i>
                <span>Catégories</span>
            </a>
            <a href="dashboard.php" class="flex flex-col items-center gap-1 text-fuchsia-700">
                <div class="w-8 h-8 bg-fuchsia-100 rounded-full flex items-center justify-center">
                    <i class="bi bi-speedometer2 text-fuchsia-900"></i>
                </div>
                <span>Dashboard</span>
            </a>
            <button @click="openSettingsModal()" class="flex flex-col items-center gap-1 hover:text-fuchsia-700 transition">
                <i class="bi bi-gear text-lg"></i>
                <span>Réglages</span>
            </button>
        </div>
    </footer>

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
                <!-- Profil -->
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
                
                <!-- Informations de compte -->
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
                
                <!-- Support -->
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
                                <a href="mailto:lukogophilippe26@gmail.com" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center ***-sm hover:***-md transition">
                                    <i class="bi bi-envelope text-blue-600"></i>
                                </a>
                                <a href="https://wa.me/+243902580019" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center ***-sm hover:***-md transition">
                                    <i class="bi bi-whatsapp text-green-600"></i>
                                </a>
                                <a href="https://t.me/Philippe mir" 
                                   class="flex-1 h-12 bg-white rounded-xl flex items-center justify-center ***-sm hover:***-md transition">
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
                <?php if (empty($completed_orders)): ?>
                    <div class="text-center py-12">
                        <i class="bi bi-bag-check text-6xl text-gray-300 mb-4 block"></i>
                        <p class="text-gray-600 text-lg mb-2">Aucune vente finalisée</p>
                        <p class="text-gray-500 text-sm">Votre historique de ventes apparaîtra ici</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($completed_orders as $order): ?>
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <div class="flex justify-between items-start mb-4">
                                    <div>
                                        <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($order['product_name'] ?? 'Produit') ?></h3>
                                        <p class="text-sm text-gray-600 mt-1">Client : <?= htmlspecialchars($order['customer_name']) ?></p>
                                    </div>
                                    <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-sm font-medium">
                                        Finalisée
                                    </span>
                                </div>
                                
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
            <?php if (empty($purchases)): ?>
                <div class="text-center py-12">
                    <i class="bi bi-cart-check text-6xl text-gray-300 mb-4 block"></i>
                    <p class="text-gray-600 text-lg mb-2">Aucun achat finalisé</p>
                    <p class="text-gray-500 text-sm">Votre historique d'achats apparaîtra ici</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($purchases as $purchase): ?>
                        <div class="bg-white border border-gray-200 rounded-xl p-5">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="font-bold text-lg line-clamp-1"><?= htmlspecialchars($purchase['product_name'] ?? 'Produit') ?></h3>
                                    <p class="text-sm text-gray-600 mt-1">Vendeur : <?= htmlspecialchars($purchase['seller_name']) ?></p>
                                </div>
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-sm font-medium">
                                    Acheté
                                </span>
                            </div>
                            
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
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-sm font-medium">
                                        Annulée
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="text-xs text-gray-500 mb-1">Raison</p>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($c['reason']) ?></p>
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
    <dialog x-show="openModal === 'delete'" @click.self="closeModal()" class="modal-animation">
        <div class="p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2" x-text="'Supprimer \"' + deleteProductName + '\" ?'"></h3>
                <p class="text-gray-600">Cette action est irréversible. Le produit sera définitivement supprimé.</p>
            </div>
            <div class="flex gap-3">
                <button @click="closeModal()" 
                        class="flex-1 py-3 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    Annuler
                </button>
                <a :href="'delete_product.php?id=' + deleteProductId" 
                   class="flex-1 py-3 bg-red-600 text-white rounded-lg text-center hover:bg-red-700 transition">
                    Supprimer
                </a>
            </div>
        </div>
    </dialog>

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

    <!-- Script pour la mise à jour du mot de passe -->
<script>
function updatePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const messageDiv = document.getElementById('passwordMessage');
    
    // Réinitialiser le message
    messageDiv.className = 'hidden p-4 rounded-lg';
    messageDiv.innerHTML = '';
    
    // Validation simple
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
    
    // Préparer les données
    const data = {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
    };
    
    // Désactiver le bouton
    const button = event.target;
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass"></i> Mise à jour en cours...';
    
    // Envoyer la requête
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
            
            // Réinitialiser les champs
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            
            // Fermer la modal après 2 secondes
            setTimeout(() => {
                showPasswordModal = false;
            }, 2000);
        } else {
            showMessage(result.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Erreur réseau. Veuillez réessayer.', 'error');
        console.error('Error:', error);
    })
    .finally(() => {
        // Réactiver le bouton
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
    <!-- Script pour gérer l'affichage des modales -->
    <script>
        // Ouvrir les modales
        function openModal(modalId) {
            const modal = document.querySelector(`dialog[data-modal="${modalId}"]`);
            if (modal) {
                modal.showModal();
                // Empêcher la fermeture au clic sur le backdrop
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.close();
                    }
                });
            }
        }
        
        // Fermer toutes les modales
        function closeAllModals() {
            document.querySelectorAll('dialog').forEach(modal => modal.close());
        }
        
        // Gestion responsive des modales
        function adjustModalForMobile() {
            const modals = document.querySelectorAll('dialog');
            modals.forEach(modal => {
                if (window.innerWidth < 768) {
                    modal.style.maxWidth = '100%';
                    modal.style.width = '100%';
                    modal.style.height = '100%';
                    modal.style.borderRadius = '0';
                    modal.style.margin = '0';
                } else {
                    modal.style.maxWidth = '500px';
                    modal.style.width = '500px';
                    modal.style.height = 'auto';
                    modal.style.borderRadius = '1.5rem';
                    modal.style.margin = 'auto';
                }
            });
        }
        
        // Événements
        window.addEventListener('resize', adjustModalForMobile);
        document.addEventListener('DOMContentLoaded', adjustModalForMobile);
        
        // Fermer avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });
    </script>

</body>
</html>