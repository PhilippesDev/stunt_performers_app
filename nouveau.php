<?php
// Connexion à la base de données (assurez-vous que la connexion est établie ici)
require_once __DIR__ . '/env.php';
$conn = new mysqli(
    env_value('LOCAL_DB_HOST', 'localhost'),
    env_value('LOCAL_DB_USER', 'root'),
    env_value('LOCAL_DB_PASSWORD'),
    env_value('LOCAL_DB_NAME', 'ecommerce')
);
if ($conn->connect_error) {
    die("Échec de la connexion : " . $conn->connect_error);
}

// Requête pour récupérer les 100 premiers produits triés par date
$sql = "SELECT id, name, price, description, image FROM products ORDER BY created_at DESC LIMIT 100"; // Assurez-vous que 'created_at' est la colonne de date
$result = $conn->query($sql);

// Vérifiez si des résultats sont retournés
if ($result->num_rows > 0) {
    echo '<div class="new-products-section">';
    echo '<h1 class="titrenew"><span> DECOUVREZ  LES</span>   NOUVEAUTES </h1>';
    echo '<br/>';
    
    echo '<div class="new-products-carousel">';
    echo '<button class="carousel-btn prev-btn1"><i class="bi bi-chevron-left"></i></button>';
    echo '<button class="carousel-btn next-btn1"><i class="bi bi-chevron-right"></i></button>';
    
    echo '<div class="carousel-track">';
    
    // Boucle pour afficher chaque produit
    while ($row = $result->fetch_assoc()) {
        echo '<div class="product-card">';
        echo '<img src="' . htmlspecialchars($row['image']) . '" alt="' . htmlspecialchars($row['name']) . '" class="product-imagenew">';
        echo '<h3 class="product-namenew">' . htmlspecialchars($row['name']) . '</h3>';
        echo '<span><p class="product-pricenew">$' . number_format($row['price'], 2) . '</p>';
        echo '<button class="buy-buttonnew">Voir</button></span>';
        echo '</div>';
    }

    echo '</div>'; // Fin de carousel-track
    echo '</div>'; // Fin de new-products-carousel
    echo '</div>'; // Fin de new-products-section
} else {
    echo "<p>Aucun produit disponible.</p>";
}

?>