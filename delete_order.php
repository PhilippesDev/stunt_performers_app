<?php
require_once __DIR__ . '/env.php';
// Connexion à la base de données (à adapter)
$host = env_value('DB_HOST', 'localhost');
$dbname = env_value('DB_NAME');
$username = env_value('DB_USER');
$password = env_value('DB_PASSWORD');
$conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

// Lecture de l'ID de commande reçu
$order_id = $_POST['order_id'] ?? '';

// Suppression de la commande de la table
$query = $conn->prepare("DELETE FROM orders WHERE id = :order_id");
$success = $query->execute([':order_id' => $order_id]);

// Réponse JSON
echo json_encode(['success' => $success]);
?>