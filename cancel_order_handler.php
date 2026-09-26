<?php
session_start();
require_once 'db.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier que la requête est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order_id']) || !isset($data['cancel_reason'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

$order_id = (int) $data['order_id'];
$user_id = $_SESSION['user_id'];
$cancel_reason = trim($data['cancel_reason']);

// Valider la raison (minimum 5 caractères)
if (strlen($cancel_reason) < 5) {
    echo json_encode(['success' => false, 'message' => 'La raison doit contenir au moins 5 caractères']);
    exit;
}

if (strlen($cancel_reason) > 500) {
    echo json_encode(['success' => false, 'message' => 'La raison ne peut pas dépasser 500 caractères']);
    exit;
}

try {
    // Vérifier que la commande existe et que l'utilisateur est propriétaire
    $stmt = $conn->prepare("SELECT id, user_id, status FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        exit;
    }

    // Vérifier que l'utilisateur est le propriétaire de la commande
    if ($order['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas le droit d\'annuler cette commande']);
        exit;
    }

    // Démarrer une transaction
    $conn->begin_transaction();

    try {
        // 1. Insérer dans la table order_cancellations
        $stmt = $conn->prepare("
            INSERT INTO order_cancellations (order_id, user_id, cancel_reason, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $order_id, $user_id, $cancel_reason);
        $stmt->execute();
        $stmt->close();

        // 2. Supprimer de la table orders
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Impossible de supprimer la commande");
        }
        $stmt->close();

        // Valider la transaction
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Commande annulée avec succès et déplacée vers l\'historique des annulations'
        ]);

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors de l\'annulation : ' . $e->getMessage()
    ]);
    exit;
}
?>
