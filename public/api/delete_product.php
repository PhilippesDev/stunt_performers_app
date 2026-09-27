<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../../libs/db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID du produit est fourni
if (!isset($_GET['id'])) {
    die("ID du produit non spécifié.");
}

// Récupérer l'ID du produit à supprimer
$product_id = $_GET['id'];

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Vérifier si le produit appartient à l'utilisateur connecté
$query = $conn->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
if (!$query) {
    die("Erreur de préparation de la requête : " . $conn->error);
}
$query->bind_param("ii", $product_id, $user_id);
$query->execute();
$result = $query->get_result();
$product = $result->fetch_assoc();
$query->close();

// Si le produit n'appartient pas à l'utilisateur ou n'existe pas
if (!$product) {
    die("Produit non trouvé ou vous n'avez pas l'autorisation de le supprimer.");
}

// Supprimer le produit de la base de données
$query = $conn->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
if (!$query) {
    die("Erreur de préparation de la requête (suppression) : " . $conn->error);
}
$query->bind_param("ii", $product_id, $user_id);
if ($query->execute()) {
    echo "Le produit a été supprimé avec succès.";
    // Redirection vers le tableau de bord après suppression
    header('Location: dashboard.php');
    exit();
} else {
    die("Erreur lors de la suppression du produit : " . $query->error);
}

$query->close();
$conn->close();
?>