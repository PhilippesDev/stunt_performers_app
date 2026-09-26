<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

if(!isset($_SESSION['user_id']) && isset($_COOKIE['user_id'])){
    $_SESSION['user_id'] = $_COOKIE['user_id'];
}
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Correction du header
    exit();
}

// ID de l'utilisateur connecté (exemple)
$user_id = $_SESSION['user_id']; // Assurez-vous que $_SESSION['user_id'] est défini

// Compter les annulations des clients pour cet utilisateur
$sql_client_cancellations = "SELECT COUNT(*) AS total FROM order_cancellation_reasons WHERE user_id = ?";
$stmt_client = $conn->prepare($sql_client_cancellations);
$stmt_client->bind_param("i", $user_id);
$stmt_client->execute();
$result_client_cancellations = $stmt_client->get_result();
$total_client_cancellations = 0;
if ($result_client_cancellations->num_rows > 0) {
    $row = $result_client_cancellations->fetch_assoc();
    $total_client_cancellations = $row['total'];
}
$stmt_client->close();

// Compter les refus des vendeurs pour cet utilisateur
$sql_vendor_cancellations = "SELECT COUNT(*) AS total FROM order_cancellations WHERE user_id = ?";
$stmt_vendor = $conn->prepare($sql_vendor_cancellations);
$stmt_vendor->bind_param("i", $user_id);
$stmt_vendor->execute();
$result_vendor_cancellations = $stmt_vendor->get_result();
$total_vendor_cancellations = 0;
if ($result_vendor_cancellations->num_rows > 0) {
    $row = $result_vendor_cancellations->fetch_assoc();
    $total_vendor_cancellations = $row['total'];
}
$stmt_vendor->close();

// Total des annulations pour cet utilisateur
$cancelationCount = $total_client_cancellations + $total_vendor_cancellations;

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
if (!$query) {
    die("Erreur de préparation de la requête (utilisateur) : " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$query->close();

require_once 'user_helper2.php'; // Chemin vers le fichier user_helper.php
$profilePic = getUserProfilePic(); // Appelle la fonction pour récupérer la photo de profil

// Récupérer les produits ajoutés par l'utilisateur
$query = $conn->prepare("SELECT * FROM products WHERE user_id = ?");
if (!$query) {
    die("Erreur de préparation de la requête (produits) : " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);
$query->close();
// Récupérer les ventes et achats
$sales_query = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM orders WHERE seller_id = ? ");
$sales_query->bind_param("i", $user_id);
$sales_query->execute();
$sales_result = $sales_query->get_result();
$total_sales = $sales_result->fetch_assoc()['total_sales'] ?? 0;
$sales_query->close();

$purchases_query = $conn->prepare("SELECT SUM(total_amount) AS total_purchases FROM orders WHERE user_id = ? ");
$purchases_query->bind_param("i", $user_id);
$purchases_query->execute();
$purchases_result = $purchases_query->get_result();
$total_purchases = $purchases_result->fetch_assoc()['total_purchases'] ?? 0;
$purchases_query->close();

$sales_data = [];
$purchases_data = [];
$sales_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS total FROM orders WHERE seller_id = ? ");
$purchases_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS total FROM orders WHERE user_id = ? ");
$sales_data_query->bind_param("i", $user_id);
$sales_data_query->execute();
$sales_data_result = $sales_data_query->get_result();
while ($row = $sales_data_result->fetch_assoc()) {
    $sales_data[] = $row;
}
$sales_data_query->close();

$purchases_data_query->bind_param("i", $user_id);
$purchases_data_query->execute();
$purchases_data_result = $purchases_data_query->get_result();
while ($row = $purchases_data_result->fetch_assoc()) {
    $purchases_data[] = $row;
}
$purchases_data_query->close();
// Récupérer les commandes reçues
$orders_received = []; // Initialisation de la variable
$query = $conn->prepare("SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE p.user_id = ?");
if (!$query) {
    die("Erreur de préparation de la requête (commandes reçues) : " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$orders_received = $result->fetch_all(MYSQLI_ASSOC);
$query->close();

$orderCount = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(o.id) AS order_count 
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
$premier_nom = explode(" ", $user['username'])[0];
// Récupérer les commandes passées par l'utilisateur
$orders_placed = []; // Initialisation de la variable
$query = $conn->prepare("SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE o.user_id = ?");
if (!$query) {
    die("Erreur de préparation de la requête (commandes passées) : " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$orders_placed = $result->fetch_all(MYSQLI_ASSOC);
$query->close();

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Utilisateur</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="../img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Concert+One&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">

    <!-- Icon pack for bottom menu -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Poppins', sans-serif;
             background: #e9ecef;
        }
        .header{
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .header-info{
            background: #f8f9fa;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 15px;
            border-radius: 12px;
        }
        .button{
            background-color:rgba(254, 90, 45, 0.8);
            border-radius: 10px;
            padding: 12px 24px;
            border: none;
        }
        .button:hover{
            background-color:rgba(254, 90, 45);
        }
        .btnrevenu {
            background: rgba(254, 90, 45, 1);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 14px 28px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btnrevenu:hover {
            background: rgba(254, 70, 25, 1);
            transform: translateY(-3px);
            color: white;
        }

        .titre{
            border-bottom: 3px solid gray;
            padding: 10px;
            color: black;
        }
        .dashboard{
            background: #f8f9fa;
            padding: 25px;
            border-radius: 16px;
        }
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
            border-radius: 16px;
            padding: 20px;
            background: #f8f9fa;
        }
        .profile-details{
            display: flex;
            gap: 20px;
            padding: 20px;
            background-color: rgba(254, 90, 45, 0.92);
            justify-content: center;
            color: white;
            border-radius: 16px;
        }
        .transaction{
            background:  #f8f9fa;
            align-items: center;
            color: #2c3e50;
            font-weight: medium;
            border-radius: 16px;
        }
        @media (min-width: 700px){
            .transaction {
                padding: 20px;
            }
        }
        .achat{
            padding: 11px 17px;
            background-color: #fe5a2d3f;
            border: 2px solid #fe5c2d;
            color: #fe5c2d;
            border-radius: 12px;
            font-weight: bold;
            text-align: center;
        }
        .detail-transaction {
            padding: 11px 17px;
            background-color: white;
            color: #2c3e50;
            border-radius: 12px;
            font-weight: bold;
            text-align: center;
        }
        .vente{
            padding: 11px 17px;
            background-color: rgba(0, 161, 0, 0.204);
            border: 2px solid rgb(0, 161, 0);
            color: rgb(0, 161, 0);
            border-radius: 12px;
            font-weight: bold;
            text-align: center;
        }
        @media (max-width: 600px){
            .header{
                padding: 15px;
            }
            .header-info{
                padding: 10px;
            }
            .profile-details{
                flex-direction: column;
            }
            .transaction{
                flex-direction: initial;
                justify-content: space-between;
            }
            .bloc_achat {
                display: flex;
                align-items: center;
                justify-content: center;
                flex-direction: column;
            }
        }
        .detail{
            border-bottom : 1px solid white;
        }
        .details{
            display: flex;
            gap: 10px;
        }
        .profile-pic img {
            border-radius: 3%;
        }

        .profile-info h1 {
            font-size: 1.8rem;
        }

        .btn-custom {
            font-size: 1.2rem;
        }
        .footers{
      background-color:rgba(4, 1, 12, 0.8);
q    }
        .footer {
          height: auto;
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
        .position-relative {
            position: relative;
        }
        .btnrejoindre {
            background: rgb(0, 196, 78);
            color: white;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
        }

        .btnrejoindre:hover {
            background: rgb(0, 146, 58);t
            color: white;
            transform: translateY(-2px);
        }
        .badge-count {
                position: absolute;
                top: -1px;
                right: -3px;
                background-color: red;
                color: white;
                border-radius: 50%;
                padding: 2px 8px;
                font-size: 12px;
        }
        .action{
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            font-weight: bold;
            padding: 8px;
            border-radius: 8px;
        }
        .action:hover{
            transform: translateY(-2px) scale(1.1);
        }
        .action svg{
            font-weight: bold;
        }
        svg:hover{
            transition: 0.3 ease-in-out;
            transform: scale(1.2)
        }
        td a {
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .table-container {
    max-height: 400px; 
    overflow-y: auto; 
    margin-bottom: 30px;
    padding: 15px;
    border-radius: 8px;
    background-color: white;
}

.table-container table {
    transition: all 0.3s ease;
    width: 100%;
    border-radius: 10px;
    border-collapse: collapse;
}

.table-container table tbody tr {
    height: 50px; 
    font-size: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}

.table-container table tbody tr.expanded {
    height: auto; 
}

.table-container table tbody tr td {
    transition: all 0.3s ease;
    padding: 10px;
    vertical-align: top;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 200px;
}

.table-container table thead tr th {
    width: 200px;
    background:  #34495e;
    color: white;
    padding: 15px 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
}

.table-container table tbody tr.expanded td {
    white-space: normal; 
}

.table-container table tbody tr.selected {
    background-color: #f0f0f0; 
}
.btnrevenu:hover {
    background-color: rgba(254, 90, 45, 1); 
    transform: scale(1.05); 
    transition: background-color 0.3s ease, transform 0.3s ease;
}
        .footer a i {
          font-size: 20px;
        }

        /* Réglages pour mobile */
        @media (max-width: 576px) {

          .footer a {
            font-size: 12px;
          }
        }
        ::-webkit-scrollbar {
    height: 5px; 
}
    ::-webkit-scrollbar{
    width: 8px;
    }
    ::-webkit-scrollbar-thumb{
    background-color: #fe5c2d;
    border-radius: 5px;

    }
    </style>
</head>
<body>


<div class="container mt-5">
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 header">
            <div class="profile-pic">
                <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'default.png'); ?>" alt="Photo de profil" class="rounded-circle" width="120" height="120">
            </div>
            <div class="profile-info text-center header-info">
                <h1 class="h4"><?php echo htmlspecialchars($premier_nom); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></p>
                <button class="btn btn-primary button" onclick="window.location.href='edit_profile.php'">Modifier</button>
            </div>
        </div>

        <!-- Account Details -->
        <div class="profile-details mb-5">
            <h2 class="h5 mb-3 detail-title">Détails du Compte</h2>
            <div class="details">
            <div class="detail">
                <strong>Nom :</strong> <?php echo htmlspecialchars($user['username']); ?>
            </div>
            <div class="detail">
                <strong>N° Tél :</strong> <?php echo htmlspecialchars($user['phone']); ?>
            </div>
            </div>
        </div>
        <div class="profile-details mb-5 transaction">
            <div class="bloc_achat">
            <strong> Ventes : </strong> <span class="achat"><?php echo number_format($total_sales, 2); ?> $</span>
            </div>
            <div>
                <a href="performance.php" class="detail-transaction"><i class="bi bi-caret-down-fill"></i></a>
            </div>
            <div class="bloc_achat">
            <strong> Achats  </strong> <span class="vente"><?php echo number_format($total_purchases, 2); ?> $</span>
            </div>
        </div>
        <!-- Dashboard -->
        <div class="dashboard">
            <div class="text-center mb-4">
               <!-- <h3 class="d-inline titre">TABLEAU DE BORD</h3> -->
            </div>

            <!-- Produits ajoutés -->
            <h4 class="h5 mb-3">Produits ajoutés</h4>
            <div class="table-container">
                <?php if (empty($products)) : ?>
                    <p>Aucun produit trouvé.</p>
                <?php else : ?>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Produit</th>
                                <th>Prix</th>
                                <th>Description</th>
                                <th>Région</th>
                                <th>Stock</th>
                                <th>Création</th>
                                <th>Mise à jour</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo isset($product['Price']) ? htmlspecialchars($product['Price']) : 'Non spécifié'; ?></td>
                                    <td><?php echo htmlspecialchars($product['description']); ?></td>
                                    <td><?php echo htmlspecialchars($product['region']); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($product['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($product['updated_at']); ?></td>
                                    <td>
                                        <a href="modify_product.php?id=<?php echo $product['id']; ?>" class="action"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="rgb(0, 176, 68)" class="bi bi-pencil-square" viewBox="0 0 16 16">
  <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
  <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
</svg></a>
                                        <a href="#" class="action" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?php echo $product['id']; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="red" class="bi bi-trash-fill" viewBox="0 0 16 16">
  <path d="M2.5 1a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1H3v9a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V4h.5a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1zm3 4a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 .5-.5M8 5a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-1 0v-7A.5.5 0 0 1 8 5m3 .5v7a.5.5 0 0 1-1 0v-7a.5.5 0 0 1 1 0"/>
</svg></a>


                                        <!-- Fenêtre modale -->
                                        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="deleteModalLabel">Confirmer la suppression</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.
                                                </div>
                                                <div class="modal-footer">
                                                    <a href="#" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</a>
                                                    <a href="#" id="confirmDeleteButton" class="btn btn-danger">Supprimer</a>
                                                </div>
                                            </div>
                                        </div>
                                        </div>



                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <a href="add_product.php" class="btn btnrejoindre btn-lg mt-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus" viewBox="0 0 16 16">
                        <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                    </svg> Ajouter produit
                </a>
            </div>

            <!-- Commandes reçues -->
            <h4 class="h5 mb-3" id="commande">Commandes reçues</h4>
            <div class="table-container">
                <?php if (empty($orders_received)) : ?>
                    <p>Aucune commande trouvée.</p>
                <?php else : ?>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Client</th>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Livraison</th>
                                <th>Téléphone</th>
                                <th>Date</th>
                                <th>Taille</th>
                                <th>Couleur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders_received as $order) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['quantity'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_address'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($order['size_number'] ?? ''); ?>; <?php echo htmlspecialchars($order['size_letter'] ?? ''); ?></td>
                                    <td>
                                    <?php 
                                        // Vérifie si l'URL de l'image est définie dans la variable $order['color']
                                        if (!empty($order['color'])) {
                                            // Affiche l'image avec la source spécifiée dans $order['color']
                                            echo '<img src="' . htmlspecialchars($order['color']) . '" alt="Couleur" style="width: 50px; height: auto;">';
                                        } else {
                                            echo 'Pas d\'image'; // Si aucune image n'est associée à la couleur
                                        }
                                    ?>
                                    </td>

                                    <td>
                                        <a href="seller_qr.php?id=<?php echo $order['id']; ?>" class="baction"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="rgb(0, 176, 68)" class="bi bi-bag-check-fill" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M10.5 3.5a2.5 2.5 0 0 0-5 0V4h5zm1 0V4H15v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V4h3.5v-.5a3.5 3.5 0 1 1 7 0m-.646 5.354a.5.5 0 0 0-.708-.708L7.5 10.793 6.354 9.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0z"/>
</svg></a>
                                        <a href="#" class="action" data-bs-toggle="modal" data-bs-target="#confirmModal" data-id="<?php echo $order['id']; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="red" class="bi bi-bag-x" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M6.146 8.146a.5.5 0 0 1 .708 0L8 9.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 10l1.147 1.146a.5.5 0 0 1-.708.708L8 10.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 10 6.146 8.854a.5.5 0 0 1 0-.708"/>
  <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1m3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4zM2 5h12v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/>
</svg></a>


                                        <!-- Fenêtre modale -->
                                        <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="confirmModalLabel">Confirmer l'action</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                Êtes-vous sûr de vouloir refuser cette commande ?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                <a href="#" id="confirmRefuseLink" class="btn btn-danger">Confirmer</a>
                                            </div>
                                            </div>
                                        </div>
                                        </div>





                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Commandes passées -->
            <h4 class="h5 mb-3">Commandes passées</h4>
            <div class="table-container">
                <?php if (empty($orders_placed)) : ?>
                    <p>Aucune commande passée trouvée.</p>
                <?php else : ?>
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Date</th>
                                <th>Taille</th>
                                <th>Couleur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders_placed as $order) : ?>
                                <tr >
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['quantity'] ?? ''); ?>; <?php echo htmlspecialchars($order['metres'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars($order['taille_chiffre'] ?? ''); ?>; <?php echo htmlspecialchars($order['taille_lettre'] ?? ''); ?></td>
                                    <td>
                                    <?php 
                                        // Vérifie si l'URL de l'image est définie dans la variable $order['color']
                                        if (!empty($order['color'])) {
                                            // Affiche l'image avec la source spécifiée dans $order['color']
                                            echo '<img src="' . htmlspecialchars($order['color']) . '" alt="Couleur" style="width: 50px; height: auto;">';
                                        } else {
                                            echo 'Pas d\'image'; // Si aucune image n'est associée à la couleur
                                        }
                                    ?>
                                    </td>
                                    <td>
                                        <a href="scan_qr.php?id=<?php echo $order['id']; ?>" class="action"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="rgb(0, 176, 68)" class="bi bi-bag-check-fill" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M10.5 3.5a2.5 2.5 0 0 0-5 0V4h5zm1 0V4H15v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V4h3.5v-.5a3.5 3.5 0 1 1 7 0m-.646 5.354a.5.5 0 0 0-.708-.708L7.5 10.793 6.354 9.646a.5.5 0 1 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0z"/>
</svg></a>
                                        <a href="#" class="action" data-bs-toggle="modal" data-bs-target="#cancelModal" data-id="<?php echo $order['id']; ?>"><svg xmlns="http://www.w3.org/2000/svg" width="25" height="25" fill="red" class="bi bi-bag-x" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M6.146 8.146a.5.5 0 0 1 .708 0L8 9.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 10l1.147 1.146a.5.5 0 0 1-.708.708L8 10.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 10 6.146 8.854a.5.5 0 0 1 0-.708"/>
  <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1m3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4zM2 5h12v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/>
</svg></a>


                                        <!-- Fenêtre modale -->
                                        <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="cancelModalLabel">Confirmer l'annulation</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    Êtes-vous sûr de vouloir annuler cette commande ? Cette action est irréversible.
                                                </div>
                                                <div class="modal-footer">
                                                    <a href="#" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</a>
                                                    <a href="#" id="confirmCancelButton" class="btn btn-danger">Confirmer l'annulation</a>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="text-center">
                <a href="historique.php" class="btn btnrevenu btn-lg">Mes revenus</a>
            </div>
        </div>
    </div>
</div> <br><br><br><br>
  <!-- Footer - Bottom Menu -->
  <footer class="footer text-white py-3 footers">
    <div class="container">
      <div class="d-flex justify-content-around">
        <a href="index.php" class="text-white" id="ac">
          <i class="bi bi-house-door"></i>
          <span>Accueil</span>
        </a>
        <a href="dashboard.php" class="text-white d-flex align-items-center position-relative">
          <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="Photo de profil" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; margin-right: 8px; border: 2px solid #fe5c2d">
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
        <a href="logout.php" class="text-white">
          <i class="bi bi-box-arrow-in-left"></i>
          <span>Déconnexion</span>
        </a>
        <a href="cancelation.php" class="text-white position-relative">
        <i class="bi bi-bag-x"></i>
        <span>Cmd Ann</span>
        <!-- Cercle rouge avec le nombre d'annulations -->
        <?php if ($cancelationCount > 0): ?>
            <span class="badge-count"><?php echo $cancelationCount; ?></span>
        <?php endif; ?>
    </a>
      </div>
    </div>
  </footer>

  
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
<script>
    
document.addEventListener('DOMContentLoaded', function () {
    const rows = document.querySelectorAll('.table-container tbody tr');

    rows.forEach(row => {
        row.addEventListener('click', function () {
            
            rows.forEach(r => {
                if (r !== row) {
                    r.classList.remove('expanded', 'selected');
                }
            });

            
            row.classList.toggle('expanded');
            row.classList.toggle('selected');
        });
    });
});
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const confirmModal = document.getElementById('confirmModal');
    const confirmRefuseLink = document.getElementById('confirmRefuseLink');

    confirmModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget; // Bouton qui déclenche la modal
      const orderId = button.getAttribute('data-id'); // Récupération de l'ID

      // Mise à jour du lien pour refuser la commande
      confirmRefuseLink.href = `cancel_reason.php?id=${orderId}`;
    });
  });

  document.addEventListener('DOMContentLoaded', function () {
      const deleteModal = document.getElementById('deleteModal');
      const confirmDeleteButton = document.getElementById('confirmDeleteButton');

      deleteModal.addEventListener('show.bs.modal', function (event) {
          const triggerLink = event.relatedTarget; // Lien qui a déclenché la modale
          const productId = triggerLink.getAttribute('data-id'); // Récupère l'ID du produit

          // Met à jour le lien de suppression avec l'ID
          confirmDeleteButton.href = `delete_product.php?id=${productId}`;
      });
  });

  document.addEventListener('DOMContentLoaded', function () {
      const cancelModal = document.getElementById('cancelModal');
      const confirmCancelButton = document.getElementById('confirmCancelButton');

      cancelModal.addEventListener('show.bs.modal', function (event) {
          const triggerLink = event.relatedTarget; // Lien qui a déclenché la modale
          const orderId = triggerLink.getAttribute('data-id'); // Récupère l'ID de la commande

          // Met à jour le lien d'annulation avec l'ID
          confirmCancelButton.href = `cancel_order.php?id=${orderId}`;
      });
  });
</script>