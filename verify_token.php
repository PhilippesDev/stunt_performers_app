<?php
session_start();
include 'db.php';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $token = $_POST['token'];

    $stmt = $conn->prepare("SELECT * FROM admins WHERE email=? AND token=? AND token_expiry > NOW()");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($admin){
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];

        // Supprimer le token pour qu'il ne soit pas réutilisable
        $stmt = $conn->prepare("UPDATE admins SET token=NULL, token_expiry=NULL WHERE id=?");
        $stmt->bind_param("i", $admin['id']);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_panel.php");
        exit;
    } else {
        $error = "Token invalide ou expiré.";
    }
}
?>

<form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="token" placeholder="Token reçu par email" required>
    <button type="submit">Valider</button>
</form>
<p><?= $error ?? '' ?></p>
