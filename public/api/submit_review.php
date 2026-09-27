<?php
// submit_review.php - Enregistrement des avis et étoiles sur les ventes livrées
session_start();
require_once __DIR__ . '/../../libs/db.php';
require_once __DIR__ . '/../../libs/helpers/notification_helper.php';

if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    header("Location: login.php");
    exit;
}

$buyer_id = intval($_SESSION['user_id']);
$order_id = intval($_POST['order_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$order_id || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Données invalides. La note doit être comprise entre 1 et 5 étoiles.']);
    exit;
}

// 1. Vérifier la commande et s'assurer qu'elle est bien livrée
$stmt = $conn->prepare("
    SELECT o.id, o.user_id, o.seller_id, o.status, p.name AS product_name
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $buyer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Commande introuvable ou non autorisée.']);
    exit;
}

if ($order['status'] !== 'delivered') {
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez noter que les commandes dont la livraison a été confirmée.']);
    exit;
}

$seller_id = intval($order['seller_id']);
$product_name = $order['product_name'] ?: 'article';

// 2. Vérifier si un avis a déjà été laissé
$check = $conn->prepare("SELECT id FROM seller_reviews WHERE order_id = ?");
$check->bind_param("i", $order_id);
$check->execute();
$existing_review = $check->get_result()->fetch_assoc();
$check->close();

if ($existing_review) {
    // Mise à jour de l'avis existant
    $update = $conn->prepare("UPDATE seller_reviews SET rating = ?, comment = ?, created_at = NOW() WHERE id = ?");
    $review_id = $existing_review['id'];
    $update->bind_param("isi", $rating, $comment, $review_id);
    $update->execute();
    $update->close();
} else {
    // Nouvel avis
    $insert = $conn->prepare("
        INSERT INTO seller_reviews (order_id, buyer_id, seller_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insert->bind_param("iiiis", $order_id, $buyer_id, $seller_id, $rating, $comment);
    $insert->execute();
    $insert->close();
}

// 3. Impact sur le score de crédibilité du vendeur
$credibility_delta = 0;
if ($rating === 5) {
    $credibility_delta = 3;
} elseif ($rating === 4) {
    $credibility_delta = 1;
} elseif ($rating === 2) {
    $credibility_delta = -4;
} elseif ($rating === 1) {
    $credibility_delta = -8;
}

if ($credibility_delta > 0) {
    $conn->query("UPDATE users SET credibility_score = LEAST(100, credibility_score + $credibility_delta) WHERE id = $seller_id");
} elseif ($credibility_delta < 0) {
    $abs_delta = abs($credibility_delta);
    $conn->query("UPDATE users SET credibility_score = GREATEST(0, credibility_score - $abs_delta) WHERE id = $seller_id");
}

// 4. Notification au vendeur
$stars_text = str_repeat("★", $rating) . str_repeat("☆", 5 - $rating);
$seller_msg = "Un acheteur a laissé un avis sur votre commande #$order_id ($stars_text) : \"$comment\".";
send_notification($conn, $seller_id, "Nouvel avis client reçu", $seller_msg, "seller_review", "dashboard.php?tab=completed");

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    echo json_encode(['success' => true, 'message' => 'Votre avis a été enregistré avec succès. Merci !']);
    exit;
}

header("Location: dashboard.php?tab=purchases&review_success=1");
exit;
