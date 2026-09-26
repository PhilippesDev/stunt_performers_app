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

// Insertion dans la table d'enregistrement (à adapter)
$query = $conn->prepare("INSERT INTO registered_orders (order_id) VALUES (:order_id)");
$success = $query->execute([':order_id' => $order_id]);

// Réponse JSON
echo json_encode(['success' => $success]);
?>