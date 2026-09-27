<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../user_helper.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userPic = getUserProfilePic();
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Cascade E-Commerce' ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Cascade Design System -->
    <link rel="stylesheet" href="assets/css/design-tokens.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/animations.css">

    <!-- Core Scripts -->
    <script src="assets/js/app.js" defer></script>
    <script src="assets/js/search.js" defer></script>
    <script src="assets/js/cart.js" defer></script>
</head>
<body>

<!-- Header Navigation Bar -->
<header style="background: var(--color-surface); border-bottom: 1px solid var(--color-border); position: sticky; top: 0; z-index: 900;">
    <div class="container flex items-center justify-between" style="height: 72px;">
        <!-- Logo -->
        <a href="index.php" class="flex items-center gap-2" style="text-decoration: none; color: var(--color-text);">
            <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" style="height: 36px; object-fit: contain;" onError="this.style.display='none'">
            <span style="font-weight: 700; font-size: 1.25rem; background: linear-gradient(135deg, var(--color-primary), var(--color-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">ECASCADEUR.COM</span>
        </a>

        <!-- Live Search Bar -->
        <div style="flex: 1; max-width: 460px; margin: 0 var(--space-6); position: relative;">
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted);"></i>
                <input type="text" class="form-input js-live-search" placeholder="Rechercher des produits, catégories..." style="padding-left: 40px; border-radius: var(--radius-full);">
            </div>
        </div>

        <!-- Navigation Actions -->
        <div class="flex items-center gap-4">
            <a href="catalog.php" class="btn btn-secondary" style="border: none;">Catalogue</a>
            
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-secondary" style="border: none;">Dashboard</a>
            <?php endif; ?>

            <!-- Cart Drawer Trigger -->
            <button onclick="Cascade.drawer.open('cart-drawer'); CascadeCart.refresh();" class="btn btn-secondary btn-icon" style="position: relative;">
                <i class="fas fa-shopping-bag" style="font-size: 1.1rem;"></i>
                <span class="js-cart-count badge badge-primary" style="position: absolute; top: -4px; right: -4px; padding: 2px 6px; font-size: 10px; display: <?= $cartCount > 0 ? 'inline-flex' : 'none' ?>;">
                    <?= $cartCount ?>
                </span>
            </button>

            <!-- User Menu -->
            <?php if ($isLoggedIn): ?>
                <a href="edit_profile.php" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
                    <img src="<?= htmlspecialchars($userPic) ?>" alt="Profil" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--color-primary);">
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary">Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Cart Drawer -->
<div id="cart-drawer" class="drawer-backdrop">
    <div class="drawer">
        <div class="drawer-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: var(--space-4); border-bottom: 1px solid var(--color-border);">
            <h3 style="font-size: 1.125rem; font-weight: 600;">Mon Panier</h3>
            <button onclick="Cascade.drawer.close('cart-drawer')" style="background: none; border: none; color: var(--color-text-muted); font-size: 1.25rem; cursor: pointer;">&times;</button>
        </div>
        <div class="drawer-body" style="flex: 1; overflow-y: auto; padding: var(--space-4) 0;">
            <!-- Cart items loaded dynamically via JS -->
        </div>
        <div class="drawer-footer" style="padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
            <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-4); font-size: 1.125rem; font-weight: 600;">
                <span>Total:</span>
                <span class="cart-total" style="color: var(--color-primary);">0.00 $</span>
            </div>
            <a href="payment.php" class="btn btn-primary" style="width: 100%; text-align: center;">Passer la commande</a>
        </div>
    </div>
</div>
