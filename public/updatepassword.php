<?php
session_start();
require_once __DIR__ . '/../libs/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['current_password'], $data['new_password'], $data['confirm_password'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

$current_password = $data['current_password'];
$new_password = $data['new_password'];
$confirm_password = $data['confirm_password'];

// Validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Les nouveaux mots de passe ne correspondent pas']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères']);
    exit();
}

// Vérifier l'ancien mot de passe
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    exit();
}

// Vérifier le mot de passe actuel (ajustez selon votre méthode de hash)
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Mot de passe actuel incorrect']);
    exit();
}

// Mettre à jour le mot de passe
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update_stmt->bind_param("si", $hashed_password, $user_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
}

$stmt->close();
$update_stmt->close();
$conn->close();
?>