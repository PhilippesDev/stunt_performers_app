<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

include __DIR__ . '/db.php';
session_start();

$message = "";
$error = "";



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $action = $_POST['action']; // 'login' ou 'register'

    $email_whitelist = ['lukogophilippe26@gmail.com','eloindagano2000@gmail.com', 'seraphinciza659@gmail.com', 'danielmuziko078@gmail.com'];

    if(!in_array($email, $email_whitelist)) {
        echo "L'accès administrateur est restreint . Redirection...";
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 3000);</script>";
        exit();
    }

    if ($action === 'register') {
        // Vérifier si l'email existe déjà
        $check = $conn->query("SELECT id FROM admins WHERE email = '$email'");
        if ($check->num_rows > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
  
            $sql = "INSERT INTO admins (email, password) VALUES ('$email', '$hashed_password')";
            if ($conn->query($sql)) {
                $admin_id = $conn->insert_id;

               
            } else {
                $error = "Erreur lors de l'inscription.";
            }
        }
    } else {
        // Connexion
        $result = $conn->query("SELECT * FROM admins WHERE email = '$email'");


        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            $password = $admin['password'];

            
            if(!password_verify($_POST['password'], $password)) {
                $error = "Mot de passe incorrect.";
            } 
            else {
            
                    $token = bin2hex(random_bytes(30));

                    $sql = "UPDATE admins SET token = '$token', expiration  = NOW() + INTERVAL 30 MINUTE WHERE id = {$admin['id']}";
                    $conn->query($sql);

                    try {
                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = env_value('SMTP_HOST', 'smtp.gmail.com');
                            $mail->SMTPAuth = true;
                            $mail->Username = env_value('SMTP_USERNAME');
                            $mail->Password = env_value('SMTP_PASSWORD');
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port = (int) env_value('SMTP_PORT', '587');

                            $mail->setFrom(env_value('SMTP_FROM_EMAIL'), 'Cascade Admin');
                            $mail->addAddress($admin['email']);

                            $mail->isHTML(true);
                            $mail->Subject = 'Vérifiez votre email - Inscription Admin Cascade';
                            $verification_link = "https://omp.alwaysdata.net/main/php/statistiques.php?token=$token";
                            $mail->Body = "
                                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                                    <h2 style='color: #5000a0ff;'>Bienvenue Admin Cascade</h2>
                                    <p>Connexion en tant qu'administrateur Cascade.</p>
                                    <p>Veuillez cliquer sur le lien ci-dessous pour se connecter à l'espace d'administration :</p>
                                    <div style='background: #fff7ed; padding: 15px; text-align: center; margin: 20px 0; border-radius: 8px;'>
                                        <a href='$verification_link' style='display: inline-block; background: #5000a0ff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Vérifier mon email</a>
                                    </div>
                                    <p style='font-size: 12px; color: #666;'>Ce lien expire dans 24 heures.</p>
                                    <p style='font-size: 12px; color: #666; margin-top: 20px;'>Si vous n'êtes pas à l'origine de cette requête, veuillez ignorer cet email.</p>
                                </div>";

                            $mail->send();
                            $_SESSION['message'] = "Un email de vérification a été envoyé. Consultez votre boîte de réception.";
                            header('Location: admin_auth.php');
                            exit();
                        } catch (Exception $e) {
                            $error = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
                        }    
            }

        } 
        else 
        {
                    $error = "Aucun compte trouvé avec cet email.";
            }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentification Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-[#f8f9fc] h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <div class="bg-white rounded-[2.5rem] banshadow-xl border border-gray-100 p-8">
    
        <div class="flex gap-4 rounded-xl bg-white p-5 ">
    <img src="../img/cadenat.svg" class="h-8 w-8 opacity-80">

    <div>
        <h3 class="text-base font-bold text-red-700">
            Zone sécurisée
        </h3>
        <p class="mt-1 text-sm text-gray-600">
            Cette page est réservée aux administrateurs.  Si vous êtes arrivé ici par erreur, veuillez retourner à la page d’accueil.
            <a href="index.php" class="ml-1 font-semibold text-red-600 hover:underline">
                Retour à l’accueil
            </a>
        </p>
    </div>
</div>




            <div class="text-center mb-8">
                <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-10 w-auto mx-auto mb-3 object-contain">
                <h1 class="text-3xl font-black text-gray-900">Admin<span class="text-fuchsia-500">.</span></h1>
                <p id="form-subtitle" class="text-gray-500 text-sm mt-2">Connectez-vous à votre espace</p>
            </div>

        <?php if (!isset($_SESSION['message'])): ?>
            <div class="flex bg-gray-100 p-1.5 rounded-2xl mb-8">
                <button onclick="toggleForm('login')" id="btn-login" class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all bg-white banshadow-sm text-gray-900">Connexion</button>
                <button onclick="toggleForm('register')" id="btn-register" class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all text-gray-500">Inscription</button>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-xs font-bold mb-6 border border-red-100"><?= $error ?></div>
            <?php endif; ?>

            <?php if($message): ?>
                <div class="bg-green-50 text-green-600 p-4 rounded-2xl text-xs font-bold mb-6 border border-green-100"><?= $message ?></div>
            <?php endif; ?>

            <form action="" method="POST" class="space-y-5">
                <input type="hidden" name="action" id="action-field" value="login">
                
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Email</label>
                    <input type="email" name="email" required class="w-full mt-1 px-5 py-4 bg-gray-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all font-medium">
                </div>

                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Mot de passe</label>
                    <input type="password" name="password" required class="w-full mt-1 px-5 py-4 bg-gray-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all font-medium">
                </div>

                <button type="submit" id="submit-btn" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black banshadow-lg hover:bg-fuchsia-600 hover:-translate-y-1 transition-all">
                    Se connecter
                </button>
            </form>
        </div>
        <?php else: ?>
            <div class="p-6 bg-white rounded-2xl border border-gray-100 text-center">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Vérifiez votre email</h2>
                <p class="text-gray-600 mb-6">Un email avec un lien de connexion a été envoyé à votre adresse. Cliquez sur le lien pour accéder à l'administration.</p>
                <a href="admin_auth.php" class="inline-block bg-gray-900 text-white px-6 py-3 rounded-2xl font-bold hover:bg-fuchsia-600 transition">Retour</a>
            </div>
        <?php unset($_SESSION['message']); endif; ?>
    </div>

    <script>
        function toggleForm(mode) {
            const btnLogin = document.getElementById('btn-login');
            const btnRegister = document.getElementById('btn-register');
            const actionField = document.getElementById('action-field');
            const submitBtn = document.getElementById('submit-btn');
            const subtitle = document.getElementById('form-subtitle');

            if (mode === 'login') {
                actionField.value = 'login';
                subtitle.innerText = "Connectez-vous à votre espace";
                submitBtn.innerText = "Se connecter";
                btnLogin.classList.add('bg-white', 'banshadow-sm', 'text-gray-900');
                btnRegister.classList.remove('bg-white', 'banshadow-sm', 'text-gray-900');
                btnRegister.classList.add('text-gray-500');
            } else {
                actionField.value = 'register';
                subtitle.innerText = "Créez un nouvel accès administrateur";
                submitBtn.innerText = "Créer le compte";
                btnRegister.classList.add('bg-white', 'banshadow-sm', 'text-gray-900');
                btnLogin.classList.remove('bg-white', 'banshadow-sm', 'text-gray-900');
                btnLogin.classList.add('text-gray-500');
            }
        }
    </script>
</body>
</html>