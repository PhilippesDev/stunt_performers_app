<?php
// Inclure le fichier de connexion à la base de données
Include 'db.php';

If (isset($_GET['product_id']) && isset($_GET['quantity'])) {
    $product_id = intval($_GET['product_id']);
    $quantity = intval($_GET['quantity']);

    // Récupérer la quantité actuelle en stock pour ce produit
    $query = "SELECT stock FROM products WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $stmt->bind_result($current_stock);
    $stmt->fetch();
    $stmt->close();

    // Calculer la nouvelle quantité en stock
    $new_stock = $current_stock - $quantity;

    If ($new_stock < 0) {
        Echo json_encode(['success' => false, 'message' => 'Stock insuffisant.']);
    } else {
        // Mettre à jour la quantité en stock dans la base de données
        $query = "UPDATE products SET stock = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $new_stock, $product_id);
        $stmt->execute();
        $stmt->close();

        // Envoyer une notification au vendeur
        $query = "SELECT seller_id FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->bind_result($seller_id);
        $stmt->fetch();
        $stmt->close();

        // Récupérer le numéro de téléphone du vendeur
        $query = "SELECT phone FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $seller_id);
        $stmt->execute();
        $stmt->bind_result($seller_phone);
        $stmt->fetch();
        $stmt->close();

        // Envoyer une notification (ici, nous utilisons une fonction fictive sendNotification)
        sendNotification($seller_phone, "La quantité en stock du produit $product_id a été mise à jour. Nouvelle quantité : $new_stock.");

        echo json_encode(['success' => true]);
    }
} else {
    Echo json_encode(['success' => false, 'message' => 'Paramètres invalides.']);
}

Function sendNotification($phone, $message) {
    // Cette fonction envoie une notification au téléphone donné
    // Implémentation de la notification (SMS, email, etc.) selon votre infrastructure
}
?>
<script src="../js/scripts.js"></script>