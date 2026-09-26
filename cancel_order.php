<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Vérifier si un ID de commande est fourni
if (isset($_GET['id'])) {
    $order_id = intval($_GET['id']);
    
    // Supprimer la commande de la base de données
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $order_id, $user_id);
    
    if ($stmt->execute()) {
        // Rediriger vers la page de saisie du motif d'annulation
        header("Location: cancellation_reason.php?order_id=$order_id");
    } else {
        echo "Erreur lors de la suppression de la commande.";
    }

    $stmt->close();
} else {
    echo "ID de commande manquant.";
}
?>