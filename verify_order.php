<?php
require_once __DIR__ . '/env.php';
// Connexion à la base de données (à adapter)
$host = env_value('LOCAL_DB_HOST', 'localhost');
$dbname = env_value('LOCAL_DB_NAME', 'ecommerce');
$username = env_value('LOCAL_DB_USER', 'root');
$password = env_value('LOCAL_DB_PASSWORD');
$conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

// Lecture de l'ID de commande reçu
$order_id = $_POST['order_id'] ?? '';

// Vérification dans la table des commandes
$query = $conn->prepare("SELECT COUNT(*) FROM orders WHERE id = :order_id");
$query->bindParam(':order_id', $order_id);
$query->execute();
$count = $query->fetchColumn();

// Réponse JSON
echo json_encode(['valid' => $count > 0]);
?>