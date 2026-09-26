<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Utilisateur non connecté.");
}

if (isset($_POST['cancellation_id'])) {
    $cancellation_id = $_POST['cancellation_id'];
    $user_id = $_SESSION['user_id'];

    require_once __DIR__ . '/db.php';

    // Supprimer l'entrée
    $sql = "DELETE FROM order_cancellation_reasons WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $cancellation_id, $user_id);
    $stmt->execute();

    // Redirection
    header("Location: cancellation_history.php");
    exit();
} else {
    die("ID d'annulation non spécifié.");
}
?>