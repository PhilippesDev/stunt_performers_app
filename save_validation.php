<?php
require_once __DIR__ . '/env.php';
// Connexion à la base de données
$host = env_value('LOCAL_DB_HOST', 'localhost');
$user = env_value('LOCAL_DB_USER', 'root');
$password = env_value('LOCAL_DB_PASSWORD');
$dbname = env_value('LOCAL_DB_NAME', 'ecommerce');
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Erreur de connexion à la base de données : " . $conn->connect_error);
}

// Récupérer l'ID de commande depuis la requête POST
if (isset($_POST['order_id'])) {
    $order_id = $_POST['order_id'];

    // Enregistrer l'ID de commande dans la table de validation
    $sql = "INSERT INTO validation (order_id) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $order_id);

    if ($stmt->execute()) {
        echo "L'ID de commande a été enregistré avec succès.";
    } else {
        echo "Erreur lors de l'enregistrement : " . $conn->error;
    }
}
?>