<?php
// Empêcher tout envoi de contenu avant l'inclusion
ob_start();
require_once __DIR__ . '/env.php';

$servername = env_value('LOCAL_DB_HOST', 'localhost');
$username = env_value('LOCAL_DB_USER');
$password = env_value('LOCAL_DB_PASSWORD');
$dbname = env_value('LOCAL_DB_NAME');
$port = (int) env_value('LOCAL_DB_PORT', '3306');

// Créer la connexion
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Empêcher toute sortie ici pour éviter les erreurs
// echo "Connexion réussie";

ob_end_clean(); // Supprimer tout contenu tamponné avant de continuer
?>