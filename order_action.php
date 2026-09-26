<?php
// order_action.php - Traitement de l'acceptation ou du refus d'une commande par le fournisseur
session_start();
require_once 'db.php';
require_once 'helpers/notification_helper.php';
require_once 'helpers/refund_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$order_id = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$action = strtolower(trim($_GET['action'] ?? $_POST['action'] ?? ''));

if (!$order_id || !in_array($action, ['accept', 'refuse'])) {
    header("Location: dashboard.php?tab=completed&error=invalid_action");
    exit();
}

// 1. Vérifier que la commande existe et appartient bien à ce vendeur
$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.seller_id, o.product_id, o.status, o.seller_status, 
           o.created_at, o.customer_name, p.name AS product_name
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.id = ? AND o.seller_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: dashboard.php?tab=completed&error=order_not_found");
    exit();
}

// 2. Vérifier si la commande a déjà été traitée ou annulée
if ($order['status'] !== 'pending') {
    $currentStatus = htmlspecialchars($order['status']);
    header("Location: dashboard.php?tab=completed&error=already_processed");
    exit();
}

// 3. Vérifier le délai d'expiration de 2 heures
$order_time = strtotime($order['created_at']);
$now = time();
$two_hours_in_seconds = 2 * 3600;

if (($now - $order_time) > $two_hours_in_seconds) {
    // Expirée : Déclencher l'expiration automatique
    check_and_expire_pending_orders($conn);
    header("Location: dashboard.php?tab=completed&error=order_expired_2h");
    exit();
}

// 4. Traiter l'action
$redirect_target = (!empty($_GET['from']) && $_GET['from'] === 'notif') ? 'notifications.php' : 'dashboard.php?tab=completed';

if ($action === 'accept') {
    $stmt = $conn->prepare("UPDATE orders SET seller_status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    // Notifier le client de la validation
    notify_buyer_order_accepted($conn, $order_id);

    // Marquer les notifications du vendeur comme lues
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND type IN ('order_request', 'order_warning') AND action_data LIKE '%\"order_id\":$order_id%'");

    header("Location: $redirect_target&success=order_accepted");
    exit();

} elseif ($action === 'refuse') {
    $stmt = $conn->prepare("UPDATE orders SET seller_status = 'refused', status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $stmt->close();

    // Enregistrer dans order_cancellations
    $reason = "Refusé par le fournisseur";
    $insert = $conn->prepare("INSERT INTO order_cancellations (order_id, user_id, cancel_reason, created_at) VALUES (?, ?, ?, NOW())");
    $insert->bind_param("iis", $order_id, $user_id, $reason);
    $insert->execute();
    $insert->close();

    // Notifier le client du refus avec le lien vers propositions.php
    notify_buyer_order_refused($conn, $order_id);

    // Marquer les notifications du vendeur comme lues
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id AND type IN ('order_request', 'order_warning') AND action_data LIKE '%\"order_id\":$order_id%'");

    header("Location: $redirect_target&success=order_refused");
    exit();
}
