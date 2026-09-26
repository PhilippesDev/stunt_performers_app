<?php
// Inclure la connexion à la base de données
include 'db.php';

session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Traitement du formulaire si envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $category_name = $_POST['category_name'];
    $category_description = $_POST['category_description'];
    $category_icon_class = $_POST['category_icon_class'];

    // Vérifier si le nom de la catégorie est valide
    if (!empty($category_name)) {
        // Insertion de la nouvelle catégorie dans la base de données
        $query = "INSERT INTO categories (name, description, icon_class) VALUES (?,?,?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $category_name, $category_description, $category_icon_class);

        if ($stmt->execute()) {
            echo 'Catégorie ajoutée avec succès.';
        } else {
            echo 'Erreur lors de l\'ajout de la catégorie : ' . $stmt->error;
        }

        $stmt->close();
    } else {
        echo 'Le nom de la catégorie ne peut pas être vide.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une catégorie</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="container">
        <h1>Ajouter une nouvelle catégorie</h1>
        <form action="add_category.php" method="POST">
            <input type="text" name="category_name" placeholder="Nom de la catégorie" required>
            <input type="text" name="category_description" placeholder="description" required>
            <input type="text" name="category_icon_class" placeholder="icone" required>
            <button type="submit">Ajouter</button>
        </form>
        <a href="dashboard.php">Retour au tableau de bord</a>
    </div>
</body>
</html>