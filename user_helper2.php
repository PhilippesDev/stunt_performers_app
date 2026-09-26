<?php
require_once 'db.php'; // Assurez-vous de fournir le chemin correct pour la connexion à la base de données

/**
 * Récupère la photo de profil de l'utilisateur connecté.
 * 
 * @return string Chemin de la photo de profil ou photo par défaut.
 */
function getUserProfilePic() {
    global $conn; // Utilise la connexion à la base de données
    $defaultPic = 'uploads/default.png'; // Chemin de la photo par défaut

    // Vérifie si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        return $defaultPic;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $profilePic = $user['profile_pic'];

        // Vérifie si le fichier existe
        if (!empty($profilePic) && file_exists($profilePic)) {
            return $profilePic;
        }
    }
    $stmt->close();

    return $defaultPic;
}
?>