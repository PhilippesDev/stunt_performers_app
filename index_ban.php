<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
$mysqli = $conn;

if ($mysqli->connect_error) {
    die('Erreur de connexion (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

require_once 'user_helper.php'; // Chemin vers le fichier user_helper.php
$profilePic = getUserProfilePic(); // Appelle la fonction pour récupérer la photo de profil

// Initialiser la variable de recherche
$search_query = '';

if (isset($_GET['search'])) {
    // Échapper les caractères spéciaux pour éviter les injections SQL
    $search_query = $mysqli->real_escape_string($_GET['search']);
}

// Requête pour sélectionner les 100 derniers produits (nouveautés)
$query_new_products = 'SELECT id, name, price, description, image, image2, image3, image4, image5, product_condition FROM products ORDER BY id DESC LIMIT 100';
$result_new_products = $mysqli->query($query_new_products);

if (!$result_new_products) {
    die('Erreur : ' . $mysqli->error);
}

$orderCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("SELECT COUNT(o.id) AS order_count 
                              FROM orders o 
                              JOIN products p ON o.product_id = p.id 
                              WHERE p.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $orderCount = $row['order_count'] ?? 0;
    $stmt->close();
}

// Requête pour sélectionner tous les produits
$query_all_products = 'SELECT id, name, price, stock, description, image, image2, image3, image4, image5, product_condition, discount_percentage, min_quantity_discount, min_kilogrammes_discount, min_metres_discount, min_litres_discount FROM products ORDER BY RAND()';
$result_all_products = $mysqli->query($query_all_products);

if (!$result_all_products) {
    die('Erreur : ' . $mysqli->error);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta property="og:title" content="<?php echo htmlspecialchars($row_all['name']); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($row_all['description']); ?>">
  <meta property="og:image" content="https://seraphin.alwaysdata.net/php/uploads/<?php echo basename($row_all['image']); ?>">
  <meta property="og:url" content="https://seraphin.alwaysdata.net/php/buy_product.php?id=<?php echo $row_all['id']; ?>">
  <meta property="og:type" content="product">

  <title>Cascade - E-commerce</title>

  <link rel="icon" type="image/png" href="favicon.png">
  <link href="../img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Poppins&display=swap" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      padding-top: 0;
      margin: 0;
      overflow-x: hidden; /* Empêche le défilement horizontal */
    }
    .custom-carousel {
      display: flex;
      flex-wrap: nowrap;
      gap: 16px;
      transition: transform 0.5s ease-in-out; 
    }
    .custom-carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(0, 0, 0, 0.5);
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 1;
      transition: background 0.3s ease; 
    }

    .custom-carousel-btn:hover {
      background: rgba(0, 0, 0, 0.8); 
    }
    .titre {
      font-weight: bold;
    }
    .fixed-search-bar {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background-color: rgba(255, 255, 255, 0.95);
      padding: 10px 0;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      display: none;
    }

    .fixed-search-bar form {
      max-width: 600px;
      margin: 0 auto;
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .fixed-search-bar input {
      flex-grow: 1;
      max-width: 500px;
      background-color: #f4f4f4;
    }

    .fixed-search-bar .btndetail {
      background-color: #f25c2d;
      color: white;
    }
    .showmenu {
      border: none;
      font-weight: bold;
      outline: none;
      background: transparent;
      cursor: pointer;
      z-index: 1100; /* Au-dessus de la sidebar */
    }
    .icon {
      display: none;
    }
    @media (max-width: 576px) {
      .theform {
        width: 75%;
        margin-left: 12%;
      }
      .trouvez {
        display: none;
      }
      .icon {
        display: block;
      }
      .fixed-search-bar form {
        padding: 0 10px;
      }
      .fixed-search-bar input {
        max-width: 100%;
      }
      .suggestions {
        max-width: 100%;
      }
    }
    .suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      max-width: 500px;
      margin: 0 auto;
      background-color: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1001;
      display: none; 
    }
    .suggestions div {
      padding: 8px 12px;
      cursor: pointer;
    }
    .suggestions div:hover {
      background-color: #f0f0f0;
    }

    .badge-count {
      position: absolute;
      top: -5px;
      right: -10px;
      background-color: red;
      color: white;
      border-radius: 50%;
      padding: 2px 6px;
      font-size: 12px;
      font-weight: bold;
    }
    
    .descriptions {
      margin-top: 5px;
      margin-bottom: 5px;
      font-weight: bold;
    }
    .hero-section {
      background: url('../img/backround.jpg') no-repeat center center/cover;
      min-height: 50vh;
    }

    .searchform input {
      max-width: 500px;
    }
    .search {
      background-color: #f4f4f4a6;
      border: none;
      border-bottom: 2px solid #fe5c2d;
      color: white;
    }
    input {
      color: white;
    }
    .price {
      color: rgb(0, 176, 68);
      font-weight: bold;
    }
    .safe {
      color: rgb(0, 176, 68);
      font-weight: bold;
    }
    .notsafe {
      color: red;
      font-weight: bold;
    }
    .btnrejoindre {
      background-color: rgb(0, 176, 68);
    }
    .btnrejoindre:hover {
      background-color: rgb(2, 127, 50);
    }
    .btn-warning a {
      color: inherit;
      font-weight: bold;
    }

    .products-section .card {
      height: 100%; 
      width: 300px;
      display: flex;
      flex-direction: column;
      justify-content: space-between; 
      border: none;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .products-section .card img:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .products-section .card img {
      object-fit: cover;
      height: 200px;
      width: 100%; 
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .products-section .card-body {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: left; 
    }

    .products-section .card-title,
    .products-section .card-text {
      text-align: left;
      font-weight: bold; 
    }
    .reduction {
      color: red;
      font-weight: bold; 
    }
    .btndetail_product {
      color: #fe5c2d;
      background-color: #f4f4f4;
      padding: 3px 6px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: bold;
    }
    .products-section .card a.btn {
      align-self: center; 
    }
    .btndetail {
      background-color: #f25c2d;
      color: white;
    }
    .footers {
      background-color: rgba(4, 1, 12, 0.8);
    }
    .footer {
      position: fixed;
      bottom: 0;
      width: 100%;
      box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
    }

    .footer a {
      display: flex;
      flex-direction: column;
      align-items: center;
      font-size: 14px;
      text-decoration: none;
    }

    .footer a i {
      font-size: 20px;
    }

   
    .custom-carousel-container {
      position: relative;
      overflow: hidden;
      padding: 10px 0;
    }

    .custom-carousel-wrapper {
      display: flex;
      overflow-x: auto;
      scroll-behavior: smooth;
      -webkit-overflow-scrolling: touch;
    }

    .custom-carousel {
      display: flex;
      flex-wrap: nowrap; 
      gap: 16px;
    }

    .custom-carousel-item {
      min-width: 250px; 
      max-width: 250px; 
      flex-shrink: 0; 
    }

    .card {
      height: 100%; 
    }

    .custom-carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(0, 0, 0, 0.5);
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 1;
    }

    .prev-btn {
      left: 10px;
    }

    .next-btn {
      right: 10px;
    }

    .custom-carousel-btn:disabled {
      display: none;
    }

    @media (max-width: 576px) {
      .searchform input {
        width: 100%;
      }
      .carousel-item {
        min-width: 200px;
      }

      .carousel-btn {
        width: 30px;
        height: 30px;
      }
      .footer a {
        font-size: 12px;
      }
      .products-section .card img {
        height: 220px; 
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .products-section .card {
      opacity: 0; 
      animation: fadeInUp 0.5s ease forwards;
    }

    .products-section .card:nth-child(1) { animation-delay: 0.1s; }
    .products-section .card:nth-child(2) { animation-delay: 0.2s; }
    .products-section .card:nth-child(3) { animation-delay: 0.3s; }
    .products-section .card:nth-child(4) { animation-delay: 0.4s; }
    .products-section .card:nth-child(5) { animation-delay: 0.5s; }
    .products-section .card:nth-child(6) { animation-delay: 0.6s; }

    .scroll-animation {
      opacity: 0; 
      transform: translateY(20px); 
      transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .scroll-animation.visible {
      opacity: 1;
      transform: translateY(0);
    }
    .cardtextes {
      justify-content: left;
    }
    ::-webkit-scrollbar {
      height: 10px; 
    }
    ::-webkit-scrollbar {
      width: 8px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: #fe5c2d;
      border-radius: 5px;
    }

    .sidebar {
      display: none;
      height: 100%;
      width: 0;
      position: fixed;
      top: 0;
      left: 0;
      background-color: white; 
      overflow-x: hidden;
      transition: 0.5s;
      padding-top: 60px;
      z-index: 1050; 
      color: black;
    }

    .sidebar a {
      padding: 15px 20px;
      text-decoration: none;
      font-size: 18px;
      color: black;
      display: block;
      transition: 0.3s;
    }

    .sidebar a:hover {
      background-color: #fe5c2d;
      color: white;
    }

    .sidebar .closebtn {
      position: absolute;
      top: 10px;
      right: 25px;
      font-size: 36px;
      margin-left: 50px;
      cursor: pointer;
    }

    .sidebar .profile-section {
      padding: 20px;
      text-align: center;
      border-bottom: 1px solid lightgray;
    }

    .sidebar .profile-section img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      border: 2px solid #fe5c2d;
    }

    #main {
      transition: margin-left 0.5s;
    }

      .sidebar a {
        font-size: 16px;
      }
    .deconnect{
      color: red;
    }
    .copyright{
      color: lightgray;
      font-size: 0.8em;
      padding: 15px 20px;
    }
    .btn-share {
    background-color: #fe5c2d; 
    color: white;
    border: none;
    border-radius: 50%; 
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease-in-out;    
}

.btn-share:hover {
    transform: scale(1.1);
    box-shadow: 0 0 1em 0.45em rgba(0, 0, 0, 0.1);
    background: linear-gradient(45deg, #212121, #252525);
    color: white;
}

.btn-share:active {
    transform: scale(0.95); 
}

@media (max-width: 576px) {
    .btn-share {
        width: 28px;
        height: 28px;
        font-size: 14px;
    }
}
.share-modal .btn-success {
    background-color: rgb(0, 176, 68); 
    border: none;
}

.share-modal .btn-success:hover {
    background-color: rgb(2, 127, 50);
    transform: scale(1.05);
}

.share-modal .btn-primary {
    background-color: #3b5998; 
    border: none;
}

.share-modal .btn-primary:hover {
    background-color: #2d4373;
    transform: scale(1.05);
}
.storie{
  position: relative;
  height: 100%;
  width: 150px;
}
#storiecontainer{
  width: 150px;
}
.btnstorie{
  position: absolute;
  z-index: 2000;
  font-size: 40px;
  left: 40%;
  bottom: 200px;
}

.animated-button {
  position: relative;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 10px 36px;
  border: 4px solid;
  border-color: transparent;
  font-size: 14px;
  background-color: inherit;
  border-radius: 100px;
  font-weight: 600;
  color: #f25c2d;
  box-shadow: 0 0 0 2px #f25c2d;
  cursor: pointer;
  overflow: hidden;
  transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
}
.animated-button a{
  text-decoration : none;
}

.animated-button svg {
  position: absolute;
  width: 24px;
  fill: #f25c2d;
  z-index: 9;
  transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
}

.animated-button .arr-1 {
  right: 16px;
}

.animated-button .arr-2 {
  left: -25%;
}

.animated-button .circle {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 20px;
  height: 20px;
  background-color: #f25c2d;
  border-radius: 50%;
  opacity: 0;
  transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
}

.animated-button .text {
  position: relative;
  z-index: 1;
  transform: translateX(-12px);
  transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
}

.animated-button:hover {
  box-shadow: 0 0 0 12px transparent;
  color: #212121;
  border-radius: 12px;
}

.animated-button:hover .arr-1 {
  right: -25%;
}

.animated-button:hover .arr-2 {
  left: 16px;
}

.animated-button:hover .text {
  transform: translateX(12px);
}

.animated-button:hover svg {
  fill: #212121;
}

.animated-button:active {
  scale: 0.95;
  box-shadow: 0 0 0 4px greenyellow;
}

.animated-button:hover .circle {
  width: 220px;
  height: 220px;
  opacity: 1;
}
.call{
  padding: 40px;
  display: flex;
  justify-content: center; 
  align-items: center; 
}
.call a{
  text-decoration: none;
}
</style>
</head>
<body>
<div id="sidebar" class="sidebar">
  <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
  <div class="profile-section">
    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Photo de profil">
    <p><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Utilisateur'; ?></p>
  </div>
  <a href="index.php"><i class="bi bi-house-fill"> </i> Accueil</a>
  <a href="dashboard.php"><i class="bi bi-person-fill"> </i> Mon Compte</a>
  <a href="catalog.php"><i class="bi bi-grid-fill"> </i> Catégories</a>
  <a href="dashboard.php#commande"><i class="bi bi-cart-fill"> </i> Mes Commandes</a>
  <a href="performance.php"><i class="bi bi-graph-up-arrow"> </i> Transactions</a>
  <a href="settings.php"><i class="bi bi-gear-fill"> </i> Paramètres</a>
  <a href="logout.php" class="deconnect"><i class="bi bi-box-arrow-in-left"> </i> Déconnexion</a>
  <p class="copyright">Copyright © 2025 <br/> All rights reserved.</p>
</div>

<div id="main">
  <div class="fixed-search-bar">
    <form action="recherche.php" method="GET" class="searchform">
      <button class="showmenu" onclick="openNav()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
</svg></button>
      <input type="text" name="search" class="form-control search" id="search-input" placeholder="Que cherchez-vous ?" required>
      <button type="submit" class="btn btndetail"> <span class="trouvez">Trouvez</span> <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
  <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
</svg></span> </button>
      <div id="suggestions" class="suggestions"></div>
    </form>
  </div>
  <!-- Hero Section -->
  <section id="hero" class="hero-section text-center text-white py-5">
    <div class="container">
      <h1 class="display-4 titre">Cascade</h1>
      <form action="recherche.php" method="GET" class="searchform d-flex justify-content-center mt-4 theform">
        <input type="text" name="search" class="form-control me-2 search principal" placeholder="Que cherchez-vous ?" required>
        <button type="submit" class="btn btndetail"><span class="trouvez">Trouvez</span> <span class="icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
  <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
</svg></span></button>
      </form>
      <div>
        <img src="ecascadeur.png" alt="logo de ecascadeur.com" style="width:100px">
      </div>
      <div class="call">
      <a href="register.php">
          <button class="animated-button">
            <svg viewBox="0 0 24 24" class="arr-2" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
              ></path>
            </svg>
            <span class="text">Rejoignez-nous</span>
            <span class="circle"></span>
            <svg viewBox="0 0 24 24" class="arr-1" xmlns="http://www.w3.org/2000/svg">
              <path
                d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
              ></path>
            </svg>
          </button>
      </a>
      </div>
    </div>
  </section>

  <!-- New Products Section -->
  <section id="new-products" class="products-section py-5 bg-light">
    <div class="container">
      <h2 class="text-center mb-4">Nouveaux Produits</h2>
      <div class="custom-carousel-container position-relative">
        <!-- Boutons de navigation pour le carousel -->
        <button class="custom-carousel-btn prev-btn">‹</button>
        <div class="custom-carousel-wrapper">
          <div class="custom-carousel">
          <div class="#storiecontainer">
                <div class="card storie">
                  <img src="uploads/storie.jpg">
                  <a href="add_product.php"><i class="bi bi-plus-circle-fill btnstorie" style="text-align: center; color: #fe5c2d"></i></a>
                  <p style="text-align: center; font-weight:bold">Ajouter produit</p>
                </div>
              </div>
            <?php while ($row_new = $result_new_products->fetch_assoc()) : ?>
              <div class="custom-carousel-item">
                <div class="card">
                  <img src="<?php echo htmlspecialchars($row_new['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row_new['name']); ?>">
                  <div class="card-body cardtextes">
                    <h5 class="card-title"><?php echo htmlspecialchars($row_new['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($row_new['price']); ?> $</p>
                    <a href="#" data-bs-toggle="modal" data-bs-target="#productModal-<?php echo $row_new['id']; ?>" class="btn btndetail">Voir Détails</a>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        </div>
        <button class="custom-carousel-btn next-btn">›</button>
      </div>
    </div>
  </section>

  <!-- All Products Section -->
  <section id="all-products" class="products-section py-5">
    <div class="container">
      <h2 class="text-center mb-4">Tous les Produits</h2>
      <div class="row">
        <?php while ($row_all = $result_all_products->fetch_assoc()) : ?>
          <div class="col-6 col-md-4 mb-4">
            <div class="card scroll-animation">
            <div class="position-relative">
              <img src="<?php echo htmlspecialchars($row_all['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row_all['name']); ?>">
              <button class="btn btn-share position-absolute top-0 end-0 m-2" data-product-id="<?php echo $row_all['id']; ?>" data-product-name="<?php echo urlencode($row_all['name']); ?>" data-product-url="<?php echo urlencode('https://seraphin.alwaysdata.net/php/buy_product.php?id=' . $row_all['id']); ?>" data-product-image="<?php echo urlencode('https://seraphin.alwaysdata.net/php/uploads/' . basename($row_all['image'])); ?>">
                  <i class="bi bi-share-fill"></i>
              </button>
            </div>
              <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($row_all['name']); ?></h5>
                <p class="card-text" id="price"><?php echo htmlspecialchars($row_all['price']); ?> $</p>
               <p card-text> Commande ≥ à <?php
                        if (!empty($row_all['min_quantity_discount'])) {
                            echo htmlspecialchars($row_all['min_quantity_discount']) . "";
                        }
                        if (!empty($row_all['min_kilogrammes_discount'])) {
                            echo htmlspecialchars($row_all['min_kilogrammes_discount']) . "";
                        }
                        if (!empty($row_all['min_metres_discount'])) {
                            echo htmlspecialchars($row_all['min_metres_discount']) . "";
                        }
                        if (!empty($row_all['min_litres_discount'])) {
                            echo htmlspecialchars($row_all['min_litres_discount']) . "";
                        }
                        ?> <span class="reduction">Réduction : -<?php echo htmlspecialchars($row_all['discount_percentage']); ?>% </span></p>
                <a href="#" data-bs-toggle="modal" data-bs-target="#productModal-<?php echo $row_all['id']; ?>" class="btndetail_product">Voir Détails</a>
              </div>
            </div>
          </div>
          <!-- Modal  -->
          <div class="modal fade" id="productModal-<?php echo $row_all['id']; ?>" tabindex="-1" aria-labelledby="productModalLabel-<?php echo $row_all['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="productModalLabel-<?php echo $row_all['id']; ?>"><?php echo $row_all['name']; ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <div id="carousel-<?php echo $row_all['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">
                      <div class="carousel-item active">
                        <img src="<?php echo $row_all['image']; ?>" class="d-block w-100" alt="<?php echo $row_all['name']; ?>">
                      </div>
                      <div class="carousel-item">
                        <img src="<?php echo $row_all['image2']; ?>" class="d-block w-100" alt="<?php echo $row_all['name']; ?>">
                      </div>
                      <div class="carousel-item">
                        <img src="<?php echo $row_all['image3']; ?>" class="d-block w-100" alt="<?php echo $row_all['name']; ?>">
                      </div>
                      <div class="carousel-item">
                        <img src="<?php echo $row_all['image4']; ?>" class="d-block w-100" alt="<?php echo $row_all['name']; ?>">
                      </div>   
                      <div class="carousel-item">
                        <img src="<?php echo $row_all['image5']; ?>" class="d-block w-100" alt="<?php echo $row_all['name']; ?>">
                      </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?php echo $row_all['id']; ?>" data-bs-slide="prev">
                      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                      <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?php echo $row_all['id']; ?>" data-bs-slide="next">
                      <span class="carousel-control-next-icon" aria-hidden="true"></span>
                      <span class="visually-hidden">Next</span>
                    </button>
                  </div>
                  <p class="descriptions">Description :</p>
                  <p><?php echo $row_all['description']; ?></p>
                  <div class="stars mb-3">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star-half-alt"></i>
                  </div>
                  <p>Prix : <span class="price"><?php echo $row_all['price']; ?> $</span></p>
                  <?php if ($row_all['product_condition'] == "neuf") {
                      $safe = "safe";
                  } else {
                      $safe = "notsafe";
                  }
                  ?>
                  <p>Statut : <span class="<?php echo $safe; ?>"><?php echo $row_all['product_condition']; ?></span></p>
                </div>
                <div class="modal-footer">
                  <a href="buy_product.php?id=<?php echo $row_all['id']; ?>" class="btn btndetail">Acheter</a>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  </section>

  <!-- Footer - Bottom Menu -->
  <footer class="footer text-white py-3 footers">
    <div class="container">
      <div class="d-flex justify-content-around">
        <a href="index.php" class="text-white" id="ac">
          <i class="bi bi-house-door" style="color:rgb(255, 123, 0);"></i>
          <span>Accueil</span>
        </a>
        <a href="dashboard.php" class="text-white d-flex align-items-center position-relative">
          <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Photo de profil" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
          <span>Mon Compte</span>
          <?php if ($orderCount > 0): ?>
            <span style="
                position: absolute;
                top: -1px;
                right: -3px;
                background-color: red;
                color: white;
                border-radius: 50%;
                padding: 2px 8px;
                font-size: 12px;
            ">
                <?php echo $orderCount; ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="catalog.php" class="text-white">
          <i class="bi bi-grid-3x3-gap-fill"></i>
          <span>Catégories</span>
        </a>
        <a href="login.php" class="text-white">
          <i class="bi bi-box-arrow-in-right"></i>
          <span>Connexion</span>
        </a>
      </div>
    </div>
  </footer>
</div> 
  <script>
    document.addEventListener('DOMContentLoaded', function () {
    const shareButtons = document.querySelectorAll('.btn-share');

    shareButtons.forEach(button => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const productName = decodeURIComponent(this.getAttribute('data-product-name'));
            const productUrl = decodeURIComponent(this.getAttribute('data-product-url'));
            const productImage = decodeURIComponent(this.getAttribute('data-product-image'));

      
            const shareOptions = {
              whatsapp: `https://wa.me/?text=${encodeURIComponent(`✨ *Découvrez "${productName}" sur Cascade ! Cliquez ici pour voir l'image* : ${productImage} *---------------* *achetez ici :* ${productUrl}`)}`,
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(productUrl)}`
            };

            
            const shareModal = document.createElement('div');
            shareModal.className = 'share-modal';
            shareModal.innerHTML = `
                <div class="share-content">
                    <h5>Partager ce produit</h5>
                    <div class="share-buttons">
                        <a href="${shareOptions.whatsapp}" target="_blank" class="btn btn-success me-2">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <a href="${shareOptions.facebook}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                    </div>
                    <button class="btn btn-secondary mt-2" onclick="this.parentElement.parentElement.remove()">Fermer</button>
                </div>
            `;
            document.body.appendChild(shareModal);

            
            shareModal.style.position = 'fixed';
            shareModal.style.top = '0';
            shareModal.style.left = '0';
            shareModal.style.width = '100%';
            shareModal.style.height = '100%';
            shareModal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
            shareModal.style.display = 'flex';
            shareModal.style.alignItems = 'center';
            shareModal.style.justifyContent = 'center';
            shareModal.style.zIndex = '1060'; 

            const shareContent = shareModal.querySelector('.share-content');
            shareContent.style.backgroundColor = 'white';
            shareContent.style.padding = '20px';
            shareContent.style.borderRadius = '8px';
            shareContent.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.2)';
            shareContent.style.textAlign = 'center';
        });
    });
});
  </script>
  <script>

    function openNav() {
      document.getElementById("sidebar").style.width = "250px";
      document.getElementById("sidebar").style.display = "block";
    }

    function closeNav() {
      document.getElementById("sidebar").style.width = "0";
      document.getElementById("main").style.marginLeft = "0";
    }

    document.querySelector(".showmenu").addEventListener("click", function(e) {
      e.preventDefault(); 
      openNav();
    });

    document.addEventListener("click", function(event) {
      const sidebar = document.getElementById("sidebar");
      const showmenu = document.querySelector(".showmenu");
      if (!sidebar.contains(event.target) && event.target !== showmenu) {
        closeNav();
      }
    });
  </script>

  <script>
    window.addEventListener("scroll", function() {
      localStorage.setItem("scrollPosition", window.scrollY);
    });
  </script>
  <script>
    window.addEventListener("load", function() {
      const scrollPosition = localStorage.getItem("scrollPosition");
      if (scrollPosition) {
        window.scrollTo(0, parseInt(scrollPosition));
      }
    });
  </script>
  <!-- Bootstrap JS & Popper.js -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const productsSection = document.querySelector("#all-products");
      const productCards = document.querySelectorAll("#all-products .card");

      function checkScroll() {
        const sectionTop = productsSection.getBoundingClientRect().top;
        const windowHeight = window.innerHeight;

        if (sectionTop < windowHeight * 0.75) { 
          productCards.forEach((card, index) => {
            setTimeout(() => {
              card.style.opacity = 1;
              card.style.transform = "translateY(0)"; 
            }, index * 100); 
          });

          window.removeEventListener("scroll", checkScroll);
        }
      }

      window.addEventListener("scroll", checkScroll);
      checkScroll();
    });
  </script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const carousel = document.querySelector(".custom-carousel");
      const prevBtn = document.querySelector(".prev-btn");
      const nextBtn = document.querySelector(".next-btn");

      let scrollAmount = 250; 

      function scrollCarousel(direction) {
        if (direction === 'next') {
          carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        } else if (direction === 'prev') {
          carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        }
      }

      prevBtn.addEventListener("click", () => scrollCarousel('prev'));
      nextBtn.addEventListener("click", () => scrollCarousel('next'));

      setInterval(() => {
        scrollCarousel('next');
      }, 5000);
    });
    document.addEventListener("DOMContentLoaded", function () {
      const searchBar = document.querySelector(".fixed-search-bar");
      const allProductsSection = document.querySelector("#all-products");
      const searchInput = document.getElementById("search-input");
      const suggestionsContainer = document.getElementById("suggestions");

      function toggleSearchBar() {
        const sectionRect = allProductsSection.getBoundingClientRect();
        const windowHeight = window.innerHeight;

        if (sectionRect.top <= windowHeight && sectionRect.bottom >= 0) {
          searchBar.style.display = "block";
        } else {
          searchBar.style.display = "none";
          suggestionsContainer.style.display = "none"; 
        }
      }
      
      window.addEventListener("scroll", toggleSearchBar);
      toggleSearchBar();

      searchInput.addEventListener("input", function () {
        const query = this.value.trim();

        if (query.length > 0) {
          fetch(`suggestions.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
              suggestionsContainer.innerHTML = "";
              if (data.length > 0) {
                data.forEach(item => {
                  const suggestionDiv = document.createElement("div");
                  suggestionDiv.textContent = item;
                  suggestionDiv.addEventListener("click", function () {
                    searchInput.value = item;
                    suggestionsContainer.style.display = "none";
                    document.querySelector(".fixed-search-bar form").submit();
                  });
                  suggestionsContainer.appendChild(suggestionDiv);
                });
                suggestionsContainer.style.display = "block";
              } else {
                suggestionsContainer.style.display = "none";
              }
            })
            .catch(error => console.error("Erreur:", error));
        } else {
          suggestionsContainer.style.display = "none";
        }
      });

      document.addEventListener("click", function (e) {
        if (!suggestionsContainer.contains(e.target) && e.target !== searchInput) {
          suggestionsContainer.style.display = "none";
        }
      });
    });
  </script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const scrollElements = document.querySelectorAll(".scroll-animation");

      const elementInView = (el, dividend = 1) => {
        const elementTop = el.getBoundingClientRect().top;

        return (
          elementTop <= (window.innerHeight || document.documentElement.clientHeight) / dividend
        );
      };

      const displayScrollElement = (element) => {
        element.classList.add("visible");
      };

      const hideScrollElement = (element) => {
        element.classList.remove("visible");
      };

      const handleScrollAnimation = () => {
        scrollElements.forEach((el) => {
          if (elementInView(el, 1.25)) {
            displayScrollElement(el);
          } else {
            hideScrollElement(el);
          }
        });
      };

      window.addEventListener("scroll", () => {
        handleScrollAnimation();
      });

      handleScrollAnimation();
    });
  </script>
</body>
</html>

<?php
// Fermer la connexion à la base de données
$mysqli->close();
?>