<?php
header('Content-Type: application/json');
require_once __DIR__ . '/env.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['order_id'])) {
    $orderId = $_POST['order_id'];

    // Connexion à la base de données (ajustez les paramètres selon votre configuration)
    $servername = env_value('LOCAL_DB_HOST', 'localhost');
    $username = env_value('LOCAL_DB_USER', 'root');
    $password = env_value('LOCAL_DB_PASSWORD');
    $dbname = env_value('LOCAL_DB_NAME', 'ecommerce');

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        echo json_encode(["success" => false, "message" => "Erreur de connexion à la base de données."]);
        exit();
    }

    // Vérification de l'ID de commande dans la table 'orders'
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // L'ID existe - Enregistrement dans une autre table
        $stmt = $conn->prepare("INSERT INTO scanned_orders (order_id) VALUES (?)");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();

        // Suppression de la commande de la table 'orders'
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->bind_param("s", $orderId);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "Commande traitée avec succès."]);
    } else {
        echo json_encode(["success" => false, "message" => "ID de commande invalide."]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(["success" => false, "message" => "Requête invalide."]);
}
?>