<?php
session_start();
require_once __DIR__ . '/../libs/db.php';
require_once __DIR__ . '/../libs/helpers/notification_helper.php';
require_once __DIR__ . '/../libs/user_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']);

// Vérification expiration et alertes 2h
check_and_expire_pending_orders($conn);

// Gestion "Tout marquer comme lu"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: notifications.php" . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
    exit();
}

// Gestion "Marquer une notification comme lue"
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    if (!empty($_GET['redirect'])) {
        header("Location: " . $_GET['redirect']);
        exit();
    }
    header("Location: notifications.php" . (!empty($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
    exit();
}

// Récupérer infos utilisateur
$user_stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$username = $user_data['username'] ?? 'Utilisateur';
$user_role = $user_data['role'] ?? 'buyer';
$profilePic = getUserProfilePic($conn);

// Filtre actif
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all', 'unread', 'orders'])) {
    $tab = 'all';
}

$unread_count = get_unread_notifications_count($conn, $user_id);
$notifications = get_user_notifications($conn, $user_id, 50, $tab);
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre de Notifications - Cascade</title>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-full flex flex-col text-slate-800" x-data="notificationsPage()">

    <!-- En-tête principal -->
    <header class="bg-white/80 backdrop-blur-md sticky top-0 z-40 border-b border-gray-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="index.php" class="flex items-center gap-2 text-fuchsia-900 font-extrabold text-xl tracking-tight">
                    <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-fuchsia-700 to-pink-600 flex items-center justify-center text-white text-lg shadow-sm">
                        <i class="bi bi-bell-fill"></i>
                    </span>
                    <span>Cascade</span>
                </a>

                <nav class="hidden md:flex items-center gap-4 text-sm font-medium text-gray-600">
                    <a href="index.php" class="hover:text-fuchsia-900 transition">Accueil</a>
                    <a href="catalog.php" class="hover:text-fuchsia-900 transition">Catégories</a>
                    <a href="dashboard.php" class="hover:text-fuchsia-900 transition">Tableau de bord</a>
                </nav>
            </div>

            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 px-3.5 py-2 rounded-xl transition flex items-center gap-1.5">
                    <i class="bi bi-speedometer2"></i>
                    <span class="hidden sm:inline">Mon Dashboard</span>
                </a>
                <a href="dashboard.php" class="w-9 h-9 rounded-full ring-2 ring-fuchsia-100 overflow-hidden">
                    <img src="<?= htmlspecialchars($profilePic) ?>" alt="Profil" class="w-full h-full object-cover">
                </a>
            </div>
        </div>
    </header>

    <!-- Notification Toast Banner dynamique en haut d'écran -->
    <div 
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
             :class="toast.isWarning ? 'bg-amber-100 text-amber-600' : 'bg-fuchsia-100 text-fuchsia-700'">
            <i class="bi text-lg" :class="toast.isWarning ? 'bi-exclamation-triangle-fill' : 'bi-bell-fill'"></i>
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="text-xs font-bold text-gray-900 line-clamp-1" x-text="toast.title"></h4>
            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2" x-text="toast.message"></p>
            <div class="mt-2 flex items-center gap-2">
                <a :href="toast.link || '#'" class="text-[11px] font-bold text-fuchsia-700 hover:underline">
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

    <!-- Contenu Principal -->
    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- En-tête de la page -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Notifications</h1>
                    <?php if ($unread_count > 0): ?>
                        <span class="px-2.5 py-0.5 bg-fuchsia-100 text-fuchsia-800 text-xs font-black rounded-full">
                            <?= $unread_count ?> non lue<?= $unread_count > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-500 mt-1">Consultez vos commandes, alertes 2h et avis en temps réel.</p>
            </div>

            <?php if ($unread_count > 0): ?>
                <form method="POST" class="shrink-0">
                    <button type="submit" name="mark_all_read" value="1" class="w-full sm:w-auto px-4 py-2.5 bg-white border border-gray-200 hover:border-gray-300 text-gray-700 hover:text-gray-900 text-xs font-bold rounded-xl shadow-xs transition flex items-center justify-center gap-2 active:scale-95">
                        <i class="bi bi-check2-all text-sm text-emerald-600"></i>
                        <span>Tout marquer comme lu</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Onglets Filtres -->
        <div class="flex items-center gap-2 border-b border-gray-200 pb-3 mb-6 overflow-x-auto">
            <a href="?tab=all" class="px-4 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap <?= $tab === 'all' ? 'bg-fuchsia-700 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="bi bi-collection mr-1.5"></i> Toutes
            </a>
            <a href="?tab=unread" class="px-4 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap flex items-center gap-1.5 <?= $tab === 'unread' ? 'bg-fuchsia-700 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="bi bi-envelope-open mr-1.5"></i> Non lues
                <?php if ($unread_count > 0): ?>
                    <span class="px-1.5 py-0.2 bg-red-500 text-white text-[10px] rounded-full font-extrabold"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=orders" class="px-4 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap <?= $tab === 'orders' ? 'bg-fuchsia-700 text-white shadow-sm' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?>">
                <i class="bi bi-bag-check mr-1.5"></i> Commandes & Alertes
            </a>
        </div>

        <!-- Liste des notifications -->
        <?php if (empty($notifications)): ?>
            <div class="bg-white rounded-3xl p-12 text-center border border-gray-100 shadow-sm max-w-lg mx-auto">
                <div class="w-16 h-16 bg-gray-50 text-gray-300 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-bell-slash text-3xl"></i>
                </div>
                <h3 class="text-base font-bold text-gray-900 mb-1">Aucune notification</h3>
                <p class="text-xs text-gray-500 mb-6">
                    <?= $tab === 'unread' ? "Vous avez lu toutes vos notifications !" : "Votre boîte de notification est vide pour le moment." ?>
                </p>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-fuchsia-800 text-white text-xs font-bold rounded-xl hover:bg-fuchsia-900 transition">
                    <i class="bi bi-arrow-left"></i>
                    <span>Retour au tableau de bord</span>
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($notifications as $notif): ?>
                    <?php 
                        $is_unread = intval($notif['is_read']) === 0;
                        $action_data = !empty($notif['action_data']) ? json_decode($notif['action_data'], true) : null;
                        $type = $notif['type'] ?? 'general';

                        // Définition visuelle selon le type
                        $is_urgent_warning = ($type === 'order_warning');
                        $is_order_request = ($type === 'order_request');
                        $is_refund = ($type === 'refund_success' || $type === 'order_refunded');
                        $is_credibility = ($type === 'credibility_alert');

                        $card_border = $is_unread 
                            ? ($is_urgent_warning ? 'border-amber-400 bg-amber-50/20' : 'border-fuchsia-200 bg-fuchsia-50/10') 
                            : 'border-gray-100 bg-white';
                    ?>
                    <div class="p-4 sm:p-5 rounded-2xl border <?= $card_border ?> shadow-xs hover:shadow-md transition relative">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <!-- Icône -->
                            <div class="w-10 h-10 rounded-xl shrink-0 flex items-center justify-center text-lg
                                <?php 
                                    if ($is_urgent_warning) echo 'bg-amber-100 text-amber-700 animate-bounce';
                                    elseif ($is_order_request) echo 'bg-blue-100 text-blue-700';
                                    elseif ($is_refund) echo 'bg-emerald-100 text-emerald-700';
                                    elseif ($is_credibility) echo 'bg-red-100 text-red-600';
                                    elseif ($type === 'order_accepted') echo 'bg-emerald-100 text-emerald-700';
                                    else echo 'bg-gray-100 text-gray-600';
                                ?>">
                                <i class="bi 
                                    <?php 
                                        if ($is_urgent_warning) echo 'bi-alarm-fill';
                                        elseif ($is_order_request) echo 'bi-bag-plus-fill';
                                        elseif ($is_refund) echo 'bi-cash-coin';
                                        elseif ($is_credibility) echo 'bi-shield-exclamation';
                                        elseif ($type === 'order_accepted') echo 'bi-check-circle-fill';
                                        else echo 'bi-bell-fill';
                                    ?>"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                        <?= htmlspecialchars($notif['title']) ?>
                                        <?php if ($is_unread): ?>
                                            <span class="w-2 h-2 rounded-full bg-fuchsia-600"></span>
                                        <?php endif; ?>
                                    </h3>
                                    <span class="text-[11px] text-gray-400 whitespace-nowrap">
                                        <?= time_elapsed_string($notif['created_at']) ?>
                                    </span>
                                </div>

                                <p class="text-xs text-gray-600 leading-relaxed">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </p>

                                <!-- Affichage spécifique montant pour les alertes commandes -->
                                <?php if ($action_data && !empty($action_data['amount'])): ?>
                                    <div class="mt-2.5 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-extrabold">
                                        <i class="bi bi-wallet2"></i>
                                        <span>Montant de la commande : <?= number_format($action_data['amount'], 0) ?> <?= htmlspecialchars($action_data['currency'] ?? 'USD') ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Boutons d'action pour le fournisseur (Validation / Refus) -->
                                <?php if ($action_data && !empty($action_data['accept_url']) && !empty($action_data['refuse_url'])): ?>
                                    <div class="mt-3.5 flex flex-wrap items-center gap-2.5">
                                        <a href="<?= htmlspecialchars($action_data['accept_url']) ?>" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5 active:scale-95">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Accepter la commande</span>
                                        </a>

                                        <a href="<?= htmlspecialchars($action_data['refuse_url']) ?>" onclick="return confirm('Attention, refuser cette commande va l\'annuler et proposer des alternatives au client. Confirmer ?');" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition shadow-xs flex items-center gap-1.5 active:scale-95">
                                            <i class="bi bi-x-circle-fill"></i>
                                            <span>Refuser</span>
                                        </a>

                                        <span class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-lg ml-auto flex items-center gap-1.5">
                                            <i class="bi bi-hourglass-split animate-spin"></i>
                                            <span>Délai strict : 2 heures</span>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <!-- Liens complémentaires -->
                                <div class="mt-3 flex items-center justify-between pt-2 border-t border-gray-100/80">
                                    <?php if (!empty($notif['link']) && empty($action_data['accept_url'])): ?>
                                        <a href="<?= htmlspecialchars($notif['link']) ?>" class="text-xs font-bold text-fuchsia-700 hover:text-fuchsia-900 flex items-center gap-1 transition">
                                            <span>Voir le détail</span>
                                            <i class="bi bi-arrow-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>

                                    <?php if ($is_unread): ?>
                                        <a href="notifications.php?mark_read=<?= $notif['id'] ?>&tab=<?= $tab ?>" class="text-[11px] font-medium text-gray-400 hover:text-gray-700 transition">
                                            Marquer comme lue
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- Script Alpine et Notifications Web Push & Polling -->
    <script>
        function notificationsPage() {
            return {
                toast: {
                    show: false,
                    title: '',
                    message: '',
                    link: '',
                    isWarning: false
                },
                init() {
                    // Demande d'autorisation pour les notifications de bureau / push
                    if ("Notification" in window) {
                        if (Notification.permission === "default") {
                            Notification.requestPermission();
                        }
                    }

                    // Démarrage du polling toutes les 20 secondes pour notifier en temps réel
                    setInterval(() => {
                        this.checkForNewNotifications();
                    }, 20000);
                },
                async checkForNewNotifications() {
                    try {
                        const res = await fetch('api/api_notifications_poll.php');
                        const data = await res.json();
                        if (data && data.has_new && data.latest) {
                            this.triggerNotification(data.latest);
                        }
                    } catch (e) {
                        console.error("Erreur de vérification des notifications:", e);
                    }
                },
                triggerNotification(notif) {
                    this.toast.title = notif.title || 'Nouvelle notification';
                    this.toast.message = notif.message || '';
                    this.toast.link = notif.link || 'notifications.php';
                    this.toast.isWarning = (notif.type === 'order_warning');
                    this.toast.show = true;

                    // Déclenchement de la vraie notification système/navigateur
                    if ("Notification" in window && Notification.permission === "granted") {
                        try {
                            const n = new Notification(notif.title, {
                                body: notif.message,
                                icon: 'assets/images/favicon.png',
                                tag: 'order-notif-' + notif.id
                            });
                            n.onclick = function() {
                                window.focus();
                                window.location.href = notif.link || 'notifications.php';
                            };
                        } catch (err) {
                            console.error("Erreur Notification API:", err);
                        }
                    }

                    // Auto fermeture du toast après 8 secondes si ce n'est pas une alerte urgente
                    if (!this.toast.isWarning) {
                        setTimeout(() => {
                            this.toast.show = false;
                        }, 8000);
                    }
                }
            };
        }
    </script>
</body>
</html>
