<?php
// Inclure les fichiers nécessaires
include 'db.php';
require_once 'libs/vendor/autoload.php'; // Charger l'autoloader de Composer
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// Récupérer les données de session
$order_id = $_SESSION['order_id'] ?? null;
$seller_id = $_SESSION['seller_id'] ?? null;

// Vérifier si les variables nécessaires sont définies
if (!$order_id || !$seller_id) {
    die("Erreur : ID de commande ou ID du vendeur manquant.");
}

// **AJOUT** : Récupérer les données supplémentaires pour la commande
$sql = "SELECT total_amount FROM temp_orders WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Vérifiez si les données ont été récupérées correctement
if (!$order_data) {
    die("Erreur : Impossible de récupérer les données supplémentaires pour la commande.");
}

// Récupérer les informations de la commande temporaire
$stmt = $conn->prepare(
  "SELECT 
      o.id,
      o.customer_name,
      o.customer_address,
      o.customer_phone,
      o.region,
      o.payment_method,

      u.username AS seller_name,
      u.phone AS seller_phone,

      p.name AS product_name,

      o.unit_value AS unit_price,
      o.unit_type,
      o.selected_size,
      o.color_quantities,

      o.total_amount,
      o.original_amount,
      o.discount_applied,
      o.discount_percent,
      o.otpvalidated,
      o.created_at

   FROM temp_orders o
   JOIN users u ON o.seller_id = u.id
   JOIN products p ON o.product_id = p.id
   WHERE o.id = ?"
);

$stmt->bind_param("i", $order_id);
$stmt->execute();
$temp_order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$temp_order) {
    die("Erreur : commande introuvable.");
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer et valider les données du formulaire
    $payment_method = htmlspecialchars(trim($_POST['payment_method'] ?? ''));
    $phone2 = htmlspecialchars(trim($_POST['phone2'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));

    // Vérification des champs obligatoires
    if (empty($payment_method) || empty($phone2) || empty($email) || empty($name)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Adresse email invalide.';
    } elseif (!preg_match('/^\d{10,15}$/', $phone2)) {
        $error_message = 'Numéro de téléphone invalide.';
    }

    if (empty($error_message)) {
        try {
            // Générer une référence de transaction unique
            $transactionReference = uniqid('order_', true);

            // Vérification de l'unicité de la transaction
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE transaction_id = ?");
            $stmt->bind_param("s", $transactionReference);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            // Si la référence existe déjà, générer une nouvelle référence unique
            if ($count > 0) {
                $transactionReference = uniqid('order_', true); // Générer un nouveau ID unique
            }

            // Préparer les données pour l'API MaishaPay
            $data = [
                "gatewayMode" => 0,
                "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
                "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
                "transactionReference" => $transactionReference, // Référence unique
                "amount" => (float) ($temp_order['total_amount'] ?? 0.00),
                "currency" => "USD",
                "customerFullName" => $name,
                "customerPhoneNumber" => $temp_order['customer_phone'],
                "customerEmailAddress" => $email,
                "chanel" => "MOBILEMONEY",
                "provider" => $payment_method,
                "walletID" => $phone2
            ];

            // Envoyer la requête à l'API MaishaPay
            $ch = curl_init('https://marchand.maishapay.online/api/payment/rest/vers1.0/merchant');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('Erreur cURL : ' . curl_error($ch));
            }
            curl_close($ch);

            // Vérifier si le paiement est accepté
            if (strpos($response, '"statusDescription":"Accepted"') !== false) {
                $transaction_id = json_decode($response, true)['original']['data']['transactionId'] ?? '';

                // Transférer les données vers la table finale
                $stmt = $conn->prepare("INSERT INTO orders SELECT * FROM temp_orders WHERE id = ?");
                $stmt->bind_param("i", $order_id); // Bind l'ID de la commande temporaire
                $stmt->execute();


                // Générer la facture avec Dompdf
                genererFacture($order_id, $temp_order, $transaction_id);

                // Supprimer la commande de la table temporaire
                $stmt = $conn->prepare("DELETE FROM temp_orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                // Rediriger vers la page de confirmation
                header("Location: succes.html");
                exit();
            } else {
                throw new Exception('Erreur : Le paiement a échoué. Veuillez réessayer.');
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Fonction pour générer la facture 

function genererFacture($order_id, $order_data, $transaction_id) {
  $options = new Options();
  $options->set('defaultFont', 'Arial');
  $dompdf = new Dompdf($options);

  $html = '<html lang="fr">
  <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
      <style>
          body {
              font-family: Arial, sans-serif;
              margin: 0;
              padding: 20px;
              /* watermark */
          }
          .header {
              text-align: center;
              margin-bottom: 30px;
              padding: 15px;
              background-color: #003399;
              color: white;
          }
          .header img {
              width: 80px;
          }
          .header .title {
              font-size: 28px;
              font-weight: bold;
              margin-top: 10px;
          }
          .invoice-details {
              margin-bottom: 20px;
          }
          .invoice-details strong {
              color: #003399;
          }
          .table th {
              background-color: #003399;
              color: white;
              text-align: center;
          }
          .table td {
              text-align: center;
          }
          .footer {
              text-align: center;
              margin-top: 30px;
              font-size: 14px;
              color: #555;
              border-top: 1px solid #ddd;
              padding-top: 15px;
          }
      </style>
  </head>
  <body>
      <div class="header">
          <img src="ecascadeur.png" alt="ecascadeur.com" style="height: 45px; margin-bottom: 10px;">
          <h1>ECASCADEUR.COM</h1>
          <div class="title">Facture</div>
      </div>
      <div class="row invoice-details">
          <div class="col-md-6">
              <strong>Client :</strong><br>
              ' . htmlspecialchars($order_data['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') . '<br>
              ' . htmlspecialchars($order_data['customer_address'] ?? '', ENT_QUOTES, 'UTF-8') . '<br>
              ' . htmlspecialchars($order_data['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8') . '
          </div>
          <div class="col-md-6 text-right">
              <strong>Vendeur :</strong><br>
              ' . htmlspecialchars($order_data['seller_name'] ?? '', ENT_QUOTES, 'UTF-8') . '
          </div>
      </div>
      <table class="table table-bordered">
          <thead>
              <tr>
                  <th>Nom du produit</th>
                  <th>Prix unitaire (USD)</th>
                  <th>Prix total (USD)</th>
              </tr>
          </thead>
          <tbody>
              <tr>
                  <td>' . htmlspecialchars($order_data['product_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>
                  <td>' . number_format((float) ($order_data['unit_price'] ?? 0), 2) . '</td>
                  <td>' . number_format((float) ($order_data['total_amount'] ?? 0), 2) . '</td>
              </tr>
          </tbody>
      </table>
      <div class="footer">
          ecascadeur.com vous remercie pour votre confiance. <br>
          <small>Transaction ID : ' . htmlspecialchars($transaction_id, ENT_QUOTES, 'UTF-8') . '</small>
      </div>
  </body>
  </html>';

  $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Définir les en-têtes pour le téléchargement
    $filename = 'facture_' . $order_id . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $dompdf->output();

    // **Note importante** : La redirection sera effectuée après téléchargement
    echo '<script>window.location.href = "succes.html";</script>';
    exit();
}

?>










<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="../img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Concert+One&display=swap" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Concert+One&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">


  <!-- Vendor CSS Files -->
  <link href="bootstrap-icons-1.11.3/package/font/bootstrap-icons.css" rel="stylesheet">
  <link href="bootstrap-icons-1.11.3/package/font/bootstrap-icons.json" rel="stylesheet">
  <link href="bootstrap-icons-1.11.3/package/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="bootstrap-icons-1.11.3/package/font/bootstrap-icons.scss" rel="stylesheet">


  <link href="../vendor/aos/aos.css" rel="stylesheet">
  <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="../vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
</head>
<body>
<h1> <a href="index.php"><svg xmlns="http://www.w3.org/2000/svg" title="retour" width="25" height="25" fill="rgb(250, 6, 6)" class="bi bi-arrow-left-circle-fill" viewBox="0 0 16 16">
            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0m3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
        </svg></a> Payement</h1>

    <?php if (!empty($error_message)): ?>
        <p style="color:red;"><?php echo $error_message; ?></p>
    <?php endif; ?>
  <div class="container">
    <form action="payment.php" method="POST">
        <label for="name">Nom complet</label>
        <input type="text" name="name" required pattern="[a-zA-Z\s]+" title="Le nom complet doit contenir uniquement des lettres et des espaces.">

        <label for="email">Adresse email</label>
        <input type="email" name="email" required>

        <label for="phone">Numéro de téléphone</label>
        <input type="text" name="phone2" required pattern="[0-9]{10,15}" title="Le numéro de téléphone doit contenir entre 10 et 15 chiffres.">
        
        <label for="payment_method">Mode de paiement</label>
        <select name="payment_method" required>
            <option value="MPESA" style ="color:#db0000; font-weight:bold">M-pesa</option>
            <option value="ORANGE" style ="color:orange; font-weight:bold">Orange Money</option>
            <option value="AIRTEL" style ="color:red; font-weight:bold;">Airtel Money</option>
        </select>
        <img src="../img/transfer.png" width="150px">
        
        <button type="submit">Payer <?php echo number_format($temp_order['total_amount'] ?? 0.00, 2); ?> $</button>
        <p>Après votre payement une facture sera téléchargée sur votre appareil pour confirmer votre payement</p>
    </form>
    </div>
</body>
</html>
<style>

p {
  color: #333; 
  border-bottom: 0.5px solid #ccc; 
  text-align: left;
  padding: 5px;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  font-weight: bold;
}

.bloc_2 input {
  width: 100%;
  padding: 10px 15px;
  margin-top: 5px;
  background-color: #fff; 
  color: #333; 
  border: 1px solid #ccc; 
}

.final {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

#category_id {
  height: 30px;
  width: 160px;
  display: inline;
  padding: 0;
}

input[type="checkbox"] + label {
  position: relative;
  padding-left: 30px;
  cursor: pointer;
}

input[type="checkbox"] + label::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  width: 20px;
  height: 20px;
  border: 2px solid #fe5c2d; 
  border-radius: 5px;
  background: #fff; 
  transition: all 0.3s;
}

input[type="checkbox"]:checked + label::before {
  background: #33d502;
  border-color: #33d502;
}

#progressContainer {
  width: 100%;
  background: #f3f3f3; 
  border: 1px solid #ccc; 
  border-radius: 5px;
  margin-top: 10px;
}

#progressBar {
  width: 0;
  height: 10px;
  background: #4caf50; 
  border-radius: 5px;
  transition: width 0.4s;
}

img {
  margin: auto;
  margin-top: 20px;
  max-width: 100%;
  border: 1px solid #ccc;
}

#progressContainer2 {
  width: 100%;
  background: #f3f3f3; 
  border: 1px solid #ccc; 
  border-radius: 5px;
  margin-top: 10px;
}

#progressBar2 {
  width: 0;
  height: 10px;
  background: #4caf50; 
  border-radius: 5px;
  transition: width 0.4s;
}

.Poppins {
  font-family: "Poppins", sans-serif;
  font-optical-sizing: auto;
  font-weight: weight;
  font-style: normal;
}

body {
  background-color: #f4f4f4; 
  font-family: "Poppins", sans-serif;
  color: #333; 
}

.container {
  width: 350px;
  margin: auto;
  background-color: #fff; 
  border-radius: 30px;
  padding: 20px;
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1); 
}

.image_contenair {
  display: flex;
  width: 100%;
}
@media (max-width: 600px){
   .container{
     width: 90%;
   }
}
@media (max-width: 950px) {
  .container {
    flex-direction: column;
  }
  .bloc_2 {
    padding: 15px;
    border-radius: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    width: 100%;
    gap: 10px;
    margin: 10px;
  }
  .bloc_2 input, select {
    width: 100%;
    padding: 10px 15px;
    margin-top: 5px;
    border-radius: 8px;
    font-family: "Poppins", sans-serif;
  }
}
select {
    width: 100%;
    padding: 10px 15px;
    margin-top: 5px;
    border-radius: 8px;
    font-family: "Poppins", sans-serif;
}

h1 {
  text-align: center;
  color: #fe5c2d; 
}

form {
  background-color: transparent;
  padding: 15px;
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 90%;
  gap: 10px;
}

label {
  color: #333; 
  margin-bottom: 5px;
}

input, textarea {
  font-family: "Poppins", sans-serif;
  background-color: #fff; 
  color: #333; 
  padding: 10px 15px;
  border-radius: 8px;
  border: 1px solid #ccc; 
  outline: none;
}

.main {
  border-radius: 10px;
  box-shadow: 0px 0px 3px 0px rgba(0, 0, 0, 0.1); 
  padding: 40px;
  background-color: #fff; 
  margin-top: 160px;
}

input:focus, textarea:focus {
  border: 1px solid #fe5c2d; 
}

button {
  font-family: "Poppins", sans-serif;
  width: 100%;
  background-color: rgb(7, 210, 61); 
  color: white;
  padding: 10px;
  border-radius: 10px;
  border: none;
  outline: none;
  font-size: 0.9rem;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s ease-in-out;
  text-align: center;
}

button:hover {
  background-color: rgb(2, 100, 2); 
}

.label1 {
  font-weight: bold;
  margin: 3px;
}

img {
  border-radius: 30px;
}

.value {
  font-weight: bold;
  color: rgb(6, 250, 71); 
}

.desc {
  font-weight: normal;
  font-size: 0.9rem;
}

h2 {
  color: #333; 
  text-align: center;
  font-weight: bold;
  background-color: transparent;
  border: 1px solid #ccc; 
  font-size: 1em;
  padding: 5px;
  border-radius: 8px;
}

a {
  text-align: center;
  text-decoration: none;
  padding: 5px 10px;
  color: white;
  border-radius: 30px;
  font-weight: bold;
}

.return {
  margin: 10px;
  display: flex;
  justify-content: center;
}

@media (max-width: 450px) {
  form {
    width: 95%;
    margin-right: 5%;
  }
}

/* Style de l'input file */
input[type=file] {
  color: #333; /* Texte sombre */
  padding: 8px 12px;
  background-color: #fff; /* Fond blanc */
  border: 1px solid #ccc; /* Bordure plus claire */
}

input[type=file]::file-selector-button {
  margin-right: 8px;
  border: none;
  background: #084cdf; /* Conserve la couleur du bouton */
  padding: 8px 12px;
  color: #fff;
  cursor: pointer;
}

input[type=file]::file-selector-button:hover {
  background: #0d45a5; /* Conserve la couleur au survol */
}

input[type=file]:focus {
  outline: 2px dashed #ccc; /* Bordure plus claire */
  outline-offset: 2px;
}

/* Style de la barre de défilement */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-thumb {
  background-color: #fe5c2d; /* Conserve la couleur d'accent */
}

/* Style des options de couleur */
.color-options {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.color-radio {
  display: none;
}

.color-circle-img, .color-square-img {
  width: 60px;
  height: 60px;
  object-fit: cover;
  cursor: pointer;
  transition: transform 0.3s ease-in-out;
}

.color-circle-img {
  border-radius: 50%;
}

.color-square-img {
  border-radius: 5px;
}

.color-circle-img:hover, .color-square-img:hover {
  transform: scale(1.2);
}

.color-circle input:checked + .color-circle-img {
  border: 3px solid #000; /* Bordure plus épaisse */
  box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
}

/* Style de la modale */
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background-color: #fff; /* Fond blanc */
  border-radius: 10px;
  padding: 20px;
  width: 50%;
  max-width: 500px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  animation: fadeIn 0.3s ease;
  position: relative;
}

.modal-image {
  width: 100%;
  max-height: 300px;
  object-fit: contain;
  margin-bottom: 20px;
  border-radius: 5px;
}

.modal-actions a {
  display: inline-block;
  margin: 10px;
  padding: 10px 20px;
  text-decoration: none;
  color: #fff;
  border-radius: 5px;
}

.modal-actions .btn-primary {
  background-color: #007bff; /* Conserve la couleur du bouton */
}

.modal-actions .btn-secondary {
  background-color: #6c757d; /* Conserve la couleur du bouton */
}

.close {
  position: absolute;
  top: 10px;
  right: 10px;
  font-size: 24px;
  font-weight: bold;
  color: #333; /* Texte sombre */
  cursor: pointer;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
</style>