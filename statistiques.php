
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>

<?php
include 'db.php';
session_start();

// ================= AUTH ADMIN =================

// 1️⃣ Si un token arrive par l'URL (connexion initiale)
if (isset($_GET['token'])) {

    $token = mysqli_real_escape_string($conn, $_GET['token']);
    $result = $conn->query("SELECT id, email, expiration FROM admins WHERE token = '$token'");

    if ($result && $result->num_rows === 1) {

        $admin = $result->fetch_assoc();
        $expiration = strtotime($admin['expiration']);

        if ($expiration > time()) {
            // ✅ TOKEN VALIDE → ON LE STOCKE EN SESSION
            $_SESSION['admin_token'] = $token;
            $_SESSION['admin_id']    = $admin['id'];
        } else {
            // ❌ Token expiré
            echo '
            <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
                <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
                    <div class="flex justify-center mb-4">
                        <div class="w-16 h-16 flex items-center justify-center rounded-full bg-red-100 text-red-600 text-3xl">⛔</div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Lien expiré</h2>
                    <p class="text-gray-600 mb-6">
                        Le lien de vérification a expiré.  
                        Pour des raisons de sécurité, veuillez vous réinscrire.
                    </p>
                    <a href="admin_auth.php"
                       class="inline-block w-full rounded-xl bg-gray-900 px-6 py-3 text-white font-semibold shadow-lg hover:bg-fuchsia-600 transition-all duration-300">
                        Retour à l’authentification admin
                    </a>
                </div>
            </div>';
            
            $conn->query("DELETE FROM admins WHERE id = {$admin['id']}");
            exit;
        }

    } else {
        // ❌ Token invalide
        echo '
        <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
            <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 flex items-center justify-center rounded-full bg-red-100 text-red-600 text-3xl">⛔</div>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Token invalide</h2>
                <p class="text-gray-600 mb-6">
                    Le token fourni est invalide ou introuvable.
                </p>
                <a href="admin_auth.php"
                   class="inline-block w-full rounded-xl bg-gray-900 px-6 py-3 text-white font-semibold shadow-lg hover:bg-fuchsia-600 transition-all duration-300">
                    Retour à l’authentification admin
                </a>
            </div>
        </div>';
        exit;
    }
}

// 2️⃣ Si aucun token mais pas de session → redirection login
if (!isset($_SESSION['admin_token'])) {
    header("Location: admin_auth.php");
    exit;
}

// ================= FIN AUTH =================



// 1. Statistiques globales pour le site
$global_stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE status = 'active')   AS total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'inactive') AS banned_users,
        (SELECT COUNT(*) FROM orders)                           AS total_orders,
        (SELECT COUNT(*) FROM products)                         AS total_products,

        SUM(CASE WHEN p.currency = 'USD' THEN o.total_amount ELSE 0 END) AS revenue_usd,
        SUM(CASE WHEN p.currency = 'CDF' THEN o.total_amount ELSE 0 END) AS revenue_cdf

    FROM orders o
    INNER JOIN products p ON p.id = o.product_id
")->fetch_assoc();

// 2. Requête de base (sans WHERE / GROUP BY / ORDER BY)
$sql = "
SELECT 
    u.id,
    u.username,
    u.phone,
    u.profile_pic,
    u.email,
    u.status,
    IFNULL(SUM(CASE WHEN o.seller_id = u.id THEN o.total_amount ELSE 0 END), 0) AS total_sales,
    IFNULL(SUM(CASE WHEN o.user_id = u.id THEN o.total_amount ELSE 0 END), 0) AS total_purchases,
    COUNT(CASE WHEN o.seller_id = u.id THEN 1 END) AS sales_count
FROM users u
LEFT JOIN orders o 
    ON o.user_id = u.id OR o.seller_id = u.id
";

// 3. Filtre par statut (WHERE AVANT GROUP BY)
if (isset($_GET['statusFilter']) && $_GET['statusFilter'] !== 'all') {
    $status = $_GET['statusFilter'] === 'active' ? 'active' : 'inactive';
    $sql .= " WHERE u.status = '$status'";
}

// 4. GROUP BY obligatoire
$sql .= " GROUP BY u.id";

// 5. Tri (UN SEUL ORDER BY)
if (isset($_GET['sortBy'])) {
    switch ($_GET['sortBy']) {
        case 'sales_desc':
            $sql .= " ORDER BY total_sales DESC";
            break;
        case 'sales_asc':
            $sql .= " ORDER BY total_sales ASC";
            break;
        case 'purchases_desc':
            $sql .= " ORDER BY total_purchases DESC";
            break;
        case 'purchases_asc':
            $sql .= " ORDER BY total_purchases ASC";
            break;
        default:
            $sql .= " ORDER BY total_sales DESC";
    }
} else {
    // tri par défaut
    $sql .= " ORDER BY total_sales DESC";
}

// 6. Exécution
$result = $conn->query($sql);

// ============ SECTION PRODUITS ============
// Requête pour les produits
// ================= SECTION PRODUITS =================
$products_sql = "
SELECT 
    p.id,
    p.name,
    p.price,
    p.currency,
    p.product_condition,
    p.quantity,
    p.unit_type,
    p.min_order,
    p.discount_percent,
    p.discount_threshold,
    p.category,
    p.specifications,
    p.created_at,
    u.username AS seller_name,
    u.profile_pic AS seller_pic
FROM products p
INNER JOIN users u ON u.id = p.user_id
WHERE 1=1
";

// Recherche produit
if (!empty($_GET['productSearch'])) {
    $search = $conn->real_escape_string($_GET['productSearch']);
    $products_sql .= " AND (p.name LIKE '%$search%' OR u.username LIKE '%$search%')";
}

// Tri produit
$products_sql .= match ($_GET['productSort'] ?? 'newest') {
    'price_high' => " ORDER BY p.price DESC",
    'price_low'  => " ORDER BY p.price ASC",
    'stock_low'  => " ORDER BY p.quantity ASC",
    default      => " ORDER BY p.created_at DESC"
};

$products_result = $conn->query($products_sql);
;


//Suppression d'utilisateur
if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);

    // Supprimer l'utilisateur de la base de données
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Rediriger pour éviter la resoumission du formulaire
    header("Location: statistiques.php");
    exit();
}

// Suppression de produit
if (isset($_GET['delete_product'])) {
    $product_id = intval($_GET['delete_product']);

    // Supprimer les médias du produit d'abord
    $delete_media = $conn->prepare("DELETE FROM product_media WHERE product_id = ?");
    $delete_media->bind_param("i", $product_id);
    $delete_media->execute();
    $delete_media->close();

    // Ensuite supprimer le produit
    $delete_product = $conn->prepare("DELETE FROM products WHERE id = ?");
    $delete_product->bind_param("i", $product_id);
    $delete_product->execute();
    $delete_product->close();

    // Rediriger pour éviter la resoumission
    header("Location: statistiques.php");
    exit();
}


//ban user
if(isset($_GET['ban'])){
    $user_id = intval($_GET['user_id']);

    // Mettre à jour le statut de l'utilisateur
    $new_status = ($_GET['ban'] === 'yes') ? 'inactive' : 'active';
    $ban_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $ban_stmt->bind_param("si", $new_status, $user_id);
    $ban_stmt->execute();
    $ban_stmt->close();

    // Rediriger pour éviter la resoumission
    header("Location: statistiques.php");
    exit();
}

//deban user 
if(isset($_GET['unban'])){
    $user_id = intval($_GET['user_id']);

    // Mettre à jour le statut de l'utilisateur
    $new_status = 'active';
    $ban_stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $ban_stmt->bind_param("si", $new_status, $user_id);
    $ban_stmt->execute();
    $ban_stmt->close();

    // Rediriger pour éviter la resoumission
    header("Location: statistiques.php");
    exit();
}

?>

<body class="flex min-h-screen bg-[#f8f9fc]">

    <aside class="w-64 bg-white border-r border-gray-200 hidden md:flex flex-col fixed h-full">
        <div class="p-6">
            <span class="text-2xl font-black text-gray-900">Admin<span class="text-fuchsia-500">.</span></span>
        </div>
        <nav class="flex-1 px-4 space-y-2 mt-4">
            <a href="#" class="flex items-center gap-3 p-3 bg-fuchsia-50 text-fuchsia-600 rounded-xl font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Dashboard
            </a>
            <a onclick="showuserspanel()"  href="#" class="flex items-center gap-3 p-3 text-gray-500 hover:bg-gray-50 rounded-xl font-medium transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Utilisateurs
            </a>
            <a onclick="showproductspanel()" href="#" class="flex items-center gap-3 p-3 text-gray-500 hover:bg-gray-50 rounded-xl font-medium transition-all">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Produits
            </a>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <button class="w-full flex items-center gap-3 p-3 text-red-500 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <a href="logout_admin.php" class="w-full flex items-center gap-3 p-3 text-red-500 font-bold">
                Déconnexion
                </a>
            </button>
        </div>
    </aside>

    <main class="flex-1 md:ml-64 p-8">
        
        <div class="flex justify-between items-end mb-10">
            <div>
                <h1 class="text-3xl font-black text-gray-900">Vue d'ensemble</h1>
                <p class="text-gray-500">Bienvenue dans votre centre de contrôle Cascade.</p>
            </div>
            <div class="flex gap-3">
                <button class="px-5 py-2.5 bg-white border border-gray-200 rounded-xl font-bold shadow-sm hover:bg-gray-50">Exporter</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Chiffre d'Affaire USD</p>
                <h3 class="text-2xl font-black"><?= number_format($global_stats['revenue_usd'], 2) ?> $</h3>
                <span class="text-gray-500 text-xs font-bold">Commission cascade : <?= number_format($global_stats['revenue_usd'] * 0.05, 2) ?> $</span><br>
                <span class="text-gray-500 text-xs font-bold">Frais API : <?= number_format($global_stats['revenue_usd'] * 0.07, 2) ?> $</span>
               <br>
                <span class="text-green-500 text-xs font-bold">Prix brut : <?= number_format($global_stats['revenue_usd'], 2) - number_format($global_stats['revenue_usd'] * 0.12, 2) ?> $</span>
            </div>
                        <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Chiffre d'Affaire CDF</p>
                <h3 class="text-2xl font-black"><?= number_format($global_stats['revenue_cdf'], 2) ?> FC</h3>
                <span class="text-gray-500 text-xs font-bold">Commission cascade : <?= number_format($global_stats['revenue_cdf'] * 0.05, 2) ?> FC</span><br>
                <span class="text-gray-500 text-xs font-bold">Frais API : <?= number_format($global_stats['revenue_cdf'] * 0.07, 2) ?> FC</span>
                <br>
                <span class="text-green-500 text-xs font-bold">Prix brut : <?= number_format($global_stats['revenue_cdf'], 2) -  number_format($global_stats['revenue_cdf'] * 0.12, 2) ?> FC</span>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Utilisateurs</p>
                <h3 class="text-2xl font-black"><?= $global_stats['total_users'] ?></h3>
                <span class="text-blue-500 text-xs font-bold">Inscrits actifs</span>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Utilisateurs</p>
                <h3 class="text-2xl font-black"><?= $global_stats['banned_users'] ?></h3>
                <span class="text-red-500 text-xs font-bold">Bannissement</span>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Commandes</p>
                <h3 class="text-2xl font-black"><?= $global_stats['total_orders'] ?></h3>
                <span class="text-fuchsia-500 text-xs font-bold">Traitées avec succès</span>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-gray-100">
                <p class="text-gray-400 text-xs font-bold uppercase mb-2">Inventaire</p>
                <h3 class="text-2xl font-black"><?= $global_stats['total_products'] ?></h3>
                <span class="text-gray-400 text-xs font-bold">Produits en ligne</span>
            </div>

        </div>

        <div id="usersPanel" class="hidden bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-8 border-b border-gray-50 flex justify-between items-center">
                <h2 class="text-xl font-black text-gray-900">Gestion des Utilisateurs</h2>
                <!-- Filtrer par statut ou par trier par volume d'achats ou de vente -->
                 <form action="" method="GET" class="flex items-center gap-4">
                 <div>
                    <label for="statusFilter" class="sr-only">Filtrer par statut</label>
                    <select id="statusFilter" name="statusFilter" class="rounded-xl border-gray-200 py-2.5 px-4 text-sm text-gray-700 shadow-sm focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all">
                        <option value="all">Tous les utilisateurs</option>
                        <option value="active">Actifs</option>
                        <option value="inactive">Bannis</option>
                    </select>
                 </div>

                 <div>
                    <label for="sortBy" class="sr-only">Trier par</label>
                    <select id="sortBy"  name="sortBy" class="rounded-xl border-gray-200 py-2.5 px-4 text-sm text-gray-700 shadow-sm focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all">
                        <option value="sales_desc">Ventes (plus élevé)</option>
                        <option value="sales_asc">Ventes (plus bas)</option>
                        <option value="purchases_desc">Achats (plus élevé)</option>
                        <option value="purchases_asc">Achats (plus bas)</option>
                    </select>
                 </div>

                    <button type="submit" class="px-4 py-2.5 bg-gray-900 text-white rounded-xl font-bold">Go</button>
            </form>
                <div class="relative">
                    <input oninput="filteruser()" id="searchInput" name="search" type="text" placeholder="Rechercher..." class="pl-10 pr-4 py-2 bg-gray-50 border-none rounded-xl text-sm outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>


            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-widest">
                        <tr>
                            <th class="px-8 py-4">Utilisateur</th>
                            <th class="px-8 py-4">Ventes</th>
                            <th class="px-8 py-4">Achats</th>
                            <th class="px-8 py-4 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50" id="userTableBody">
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors user-row" data-username="<?= htmlspecialchars(strtolower($row['username'])) ?>" data-phone="<?= htmlspecialchars($row['phone']) ?>">
                            <td class="px-8 py-5">
                                <div class="flex items-center gap-4">
                                    <img src="<?= htmlspecialchars($row['profile_pic']) ?>" class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-100">
                                    <div>
                                        <p class="font-bold text-gray-900"><?= htmlspecialchars($row['username']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($row['phone']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-5">
                                <div class="text-sm font-bold text-green-600"><?= number_format($row['total_sales'], 2) ?> $</div>
                                <div class="text-[10px] text-gray-400"><?= $row['sales_count'] ?> ventes conclues</div>
                            </td>
                            <td class="px-8 py-5 text-sm font-bold text-gray-700">
                                <?= number_format($row['total_purchases'], 2) ?> $
                            </td>
                            <td class="px-8 py-5">
                                <div class="flex justify-center gap-2">

                                <a href="?delete_user=<?= $row['id'] ?>" class="p-2 text-gray-400 hover:text-red-500 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>

                                 <a href="?ban=yes&user_id=<?= $row['id'] ?>"
                                class="p-2 <?= $row['status'] === 'active' ? 'block text-red-400' : 'hidden' ?>  hover:text-red-500 transition-colors">
                                Ban temporaire
                                </a>

                                <a href="?unban=yes&user_id=<?= $row['id'] ?>"
                                class="p-2 <?= $row['status'] !== 'active' ? 'block text-green-400' : 'hidden' ?>  hover:text-red-500 transition-colors">
                                Deban
                                </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION PRODUITS -->
        <div id="productsPanel" class="hidden mt-10">
            <div class="mb-10">
                <h1 class="text-3xl font-black text-gray-900">Gestion des Produits</h1>
                <p class="text-gray-500">Contrôlez et gérez tous vos produits en ligne.</p>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-8 border-b border-gray-50 flex justify-between items-center flex-wrap gap-4">
                    <h2 class="text-xl font-black text-gray-900">Inventaire des Produits</h2>
                    
                    <form action="" method="GET" class="flex items-center gap-3 flex-wrap">
                        <!-- Champs cachés pour garder les filtres utilisateur -->
                        <input type="hidden" name="statusFilter" value="<?= $_GET['statusFilter'] ?? 'all' ?>">
                        <input type="hidden" name="sortBy" value="<?= $_GET['sortBy'] ?? 'sales_desc' ?>">

                        <!-- Tri des produits -->
                        <div>
                            <label for="productSort" class="sr-only">Trier par</label>
                            <select id="productSort" name="productSort" class="rounded-xl border-gray-200 py-2.5 px-4 text-sm text-gray-700 shadow-sm focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all">
                                <option value="newest">Plus récent</option>
                                <option value="price_high">Prix (plus élevé)</option>
                                <option value="price_low">Prix (plus bas)</option>
                                <option value="stock_low">Stock faible</option>
                            </select>
                        </div>

                        <!-- Recherche produits -->
                        <div class="relative">
                            <input oninput="filterProducts()" id="productSearchInput" name="productSearch" type="text" placeholder="Rechercher un produit..." class="pl-10 pr-4 py-2 bg-gray-50 border-none rounded-xl text-sm outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all">
                            <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>

                        <button type="submit" class="px-4 py-2.5 bg-gray-900 text-white rounded-xl font-bold hover:bg-fuchsia-600 transition-all">Appliquer</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 text-gray-400 text-[10px] uppercase font-black tracking-widest">
                            <tr>
                                <th class="px-8 py-4">Produit</th>
                                <th class="px-8 py-4">Vendeur</th>
                                <th class="px-8 py-4">Prix</th>
                                <th class="px-8 py-4">Stock</th>
                                <th class="px-8 py-4">État</th>
                                <th class="px-8 py-4 text-center">Actions</th>
                            </tr>
                        </thead>
                       <tbody class="divide-y divide-gray-50" id="productTableBody">
<?php while($product = $products_result->fetch_assoc()): 

    $category = json_decode($product['category'], true);
    $category_name = is_array($category) && isset($category[0]['name'])
        ? $category[0]['name']
        : 'Non classé';

    $is_low_stock = $product['min_order'] !== null 
        ? $product['quantity'] <= $product['min_order']
        : $product['quantity'] <= 5;
?>
<tr class="hover:bg-gray-50/50 product-row"
    data-name="<?= strtolower(htmlspecialchars($product['name'])) ?>"
    data-seller="<?= strtolower(htmlspecialchars($product['seller_name'])) ?>">

    <!-- Produit -->
    <td class="px-8 py-5">
        <p class="font-bold text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
        <p class="text-xs text-gray-400"><?= htmlspecialchars($category_name) ?></p>
    </td>

    <!-- Vendeur -->
    <td class="px-8 py-5 flex items-center gap-2">
        <img src="<?= htmlspecialchars($product['seller_pic']) ?>" class="w-8 h-8 rounded-full">
        <span class="text-sm font-semibold"><?= htmlspecialchars($product['seller_name']) ?></span>
    </td>

    <!-- Prix -->
    <td class="px-8 py-5">
        <div class="font-bold">
            <?= number_format($product['price'], 2) ?> <?= $product['currency'] ?>
        </div>
        <?php if ($product['discount_percent'] > 0): ?>
            <div class="text-xs text-green-600">
                -<?= $product['discount_percent'] ?>%
            </div>
        <?php endif; ?>
    </td>

    <!-- Stock -->
    <td class="px-8 py-5">
        <div class="font-bold">
            <?= number_format($product['quantity'], 2) ?> <?= $product['unit_type'] ?>
        </div>
        <?php if ($is_low_stock): ?>
            <div class="text-xs text-orange-600 font-semibold">⚠ Stock faible</div>
        <?php endif; ?>
    </td>

    <!-- État -->
    <td class="px-8 py-5">
        <span class="px-3 py-1 rounded-full text-xs font-bold
            <?= $product['product_condition'] === 'new'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-amber-100 text-amber-700' ?>">
            <?= $product['product_condition'] === 'new' ? 'Neuf' : 'Occasion' ?>
        </span>
    </td>

    <!-- Actions -->
    <td class="px-8 py-5 text-center">
        <div class="flex justify-center gap-2">
            <a href="?delete_product=<?= $product['id'] ?>"
               onclick="return confirm('Supprimer ce produit ?')"
               class="text-red-500">🗑</a>
        </div>
    </td>

</tr>
<?php endwhile; ?>
</tbody>

                    </table>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
<script>
    function filteruser(){
        // Récupérer la valeur de recherche
        const searchInput = document.getElementById('searchInput').value.toLowerCase();
        
        // Récupérer toutes les lignes du tableau
        const rows = document.querySelectorAll('.user-row');
        
        // Nombre de résultats trouvés
        let visibleCount = 0;
        
        // Itérer sur chaque ligne
        rows.forEach(row => {
            // Récupérer les attributs de données
            const username = row.getAttribute('data-username');
            const phone = row.getAttribute('data-phone');
            
            // Récupérer aussi le contenu texte de la ligne pour plus de flexibilité
            const rowText = row.textContent.toLowerCase();
            
            // Vérifier si la recherche correspond au nom d'utilisateur ou au numéro de téléphone
            const matches = username.includes(searchInput) || 
                          phone.includes(searchInput) || 
                          rowText.includes(searchInput);
            
            // Afficher ou masquer la ligne
            if (searchInput === '' || matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Afficher un message si aucun résultat
        const tableBody = document.getElementById('userTableBody');
        
        // Vérifier s'il y a déjà un message "aucun résultat"
        let noResultsRow = tableBody.querySelector('.no-results-row');
        
        if (visibleCount === 0 && searchInput !== '') {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="4" class="px-8 py-12 text-center">
                        <p class="text-gray-400 text-sm">Aucun utilisateur trouvé pour "<strong>${searchInput}</strong>"</p>
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    }

    // Filtrer les produits
    function filterProducts(){
        const searchInput = document.getElementById('productSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('.product-row');
        
        let visibleCount = 0;
        
        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const seller = row.getAttribute('data-seller');
            const rowText = row.textContent.toLowerCase();
            
            const matches = name.includes(searchInput) || 
                          seller.includes(searchInput) || 
                          rowText.includes(searchInput);
            
            if (searchInput === '' || matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const tableBody = document.getElementById('productTableBody');
        let noResultsRow = tableBody.querySelector('.no-results-products');
        
        if (visibleCount === 0 && searchInput !== '') {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-products';
                noResultsRow.innerHTML = `
                    <td colspan="6" class="px-8 py-12 text-center">
                        <p class="text-gray-400 text-sm">Aucun produit trouvé pour "<strong>${searchInput}</strong>"</p>
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        } else if (noResultsRow) {
            noResultsRow.remove();
        }
    }

    function showuserspanel(){
        document.getElementById('usersPanel').classList.remove('hidden');
        document.getElementById('productsPanel').classList.add('hidden');
    }
    function showproductspanel(){
        document.getElementById('productsPanel').classList.remove('hidden');
        document.getElementById('usersPanel').classList.add('hidden');
    }
</script>