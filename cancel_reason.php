<?php
session_start();
require 'db.php';

$order_id = $_GET['id'] ?? null;
$user_id = $_SESSION['user_id']; // ID de l'utilisateur connecté

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancel_reason = $_POST['cancel_reason'];

    // Enregistrer le motif du refus dans la table order_cancellations
    $insert_query = $conn->prepare("INSERT INTO order_cancellations (order_id, user_id, cancel_reason) VALUES (?, ?, ?)");
    if (!$insert_query) {
        die("Erreur de préparation de la requête : " . $conn->error);
    }
    $insert_query->bind_param("iis", $order_id, $user_id, $cancel_reason);
    $insert_query->execute();
    $insert_query->close();

    // Envoyer une notification au client dans son tableau de bord
    $notification_query = $conn->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
    $message = "Votre commande #{$order_id} a échoué. Motif : {$cancel_reason}";
    $notification_query->bind_param("is", $user_id, $message);
    $notification_query->execute();
    $notification_query->close();

    // Supprimer la commande de la base de données
    $delete_query = $conn->prepare("DELETE FROM orders WHERE id = ?");
    if (!$delete_query) {
        die("Erreur de préparation de la requête : " . $conn->error);
    }
    $delete_query->bind_param("i", $order_id);
    $delete_query->execute();
    $delete_query->close();

    // Rediriger vers le tableau de bord après soumission
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600|Raleway:300,400,500,600" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Concert+One&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600|Raleway:300,400,500,600" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Concert+One&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Concert+One&display=swap" rel="stylesheet">

    <title>Motif de refus</title>
</head>
<body>
    <h2><a href="dashboard.php"><svg xmlns="http://www.w3.org/2000/svg" title="retour" width="25" height="25" fill="rgb(250, 6, 6)" class="bi bi-arrow-left-circle-fill" viewBox="0 0 16 16">
            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0m3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
        </svg></a> Annuler la commande</h2>
    <form method="POST">
        <label for="cancel_reason">Motif de refus :</label><br>
        <textarea name="cancel_reason" placeholder="Veuillez saisir votre motif ici ..." id="cancel_reason" rows="5" required></textarea><br><br>
        <button type="submit">Soumettre le motif</button>
    </form>
</body>
</html>
<style>
    
.Poppins {
  font-family: "Poppins", sans-serif;
  font-optical-sizing: auto;
  font-weight: weight;
  font-style: normal;
}
body {
  background-color: #f4f4f4;
  font-family: "Poppins", sans-serif;
    color: #272829;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
  }


@media (max-width : 650px)
{
  .container
{
  flex-wrap: wrap;
  justify-content: center;
  max-width: 80%;
   min-width: 80%;
   gap: 0;
}
  
}
h1
{
  text-align: center;
}
form
{
  background-color:  white;
  padding: 15px;
  border-radius: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 90%;
  width: 400px;
  height: 400px;
  margin: auto;
  gap: 10px;
}
option{
    padding: 5px;
}
option:hover{
    background-color: gray;
}
input
{
  padding: 10px 15px;
  border-radius: 8px;
  border: none;
  outline: none;
  
}

input:focus
{
  border: 1px solid #fe5c2d;
}
button
{
  font-family: "Poppins", sans-serif;
  background-color: green;
  color: white;
  padding: 10px;
  border-radius: 10px;
  border: none;
  outline: none;
  font-size: 0.9rem;
  font-weight: bold;
  cursor:pointer;
  transition: 0.3s ease-in-out;
  text-align: center;
}
button:hover
{
  background-color: rgb(2, 100, 2);
}
select
{
  padding: 10px;
  border-radius: 10px;
  border: none;
  outline: none;
  font-size: 0.9rem;
  cursor:pointer;
  display: flex;
}
label
{
  font-weight: bold;
  margin: 3px;
}
img
{
  margin:auto;
}

.value
{
color: green;
}
.desc
{
  font-weight: normal;
  font-size: 0.9rem;
}
h2
{
  color:  #fe5c2d;
  text-align: center;
  font-weight: bold;
  padding: 5px;
  border-radius: 30px;
}

.return
{
  margin: 10px;
  display: flex;
  justify-content: center;
}
textarea
{
  font-family: "Poppins", sans-serif;
    height: 300px;
    padding: 15px;
    border-radius: 8px;
    border : 1px solid gray;
    outline : none;
}
textarea:focus
{
  border : 1px solid #fe5c2d;
}
@media (max-width:450px)
{
  form
{
  width: 90%;
  margin-right: 5%;
}
input, button, select {
  width: 90%;
}
} 
::-webkit-scrollbar{
    width: 8px;
  }
  ::-webkit-scrollbar-thumb{
    background-color: #fe5c2d;
  }

</style>