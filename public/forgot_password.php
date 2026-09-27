<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once __DIR__ . '/env.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = $_POST['email'];
    $otp = rand(100000, 999999);

    session_start();
    $_SESSION['otp'] = $otp;
    $_SESSION['email'] = $email;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = env_value('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = env_value('SMTP_USERNAME');
        $mail->Password = env_value('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) env_value('SMTP_PORT', '587');

        $mail->setFrom(env_value('SMTP_FROM_EMAIL'), 'Cascade Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Code de verification - Cascade';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #ea580c;'>Réinitialisation de mot de passe</h2>
                <p>Bonjour,</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte <b>Cascade</b>.</p>
                <p>Votre code de vérification (OTP) est le suivant :</p>
                <div style='background: #fff7ed; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; color: #ea580c; border-radius: 8px; letter-spacing: 5px;'>
                    $otp
                </div>
                <p style='font-size: 12px; color: #666; margin-top: 20px;'>Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet e-mail.</p>
            </div>";

        $mail->send();
        header('Location: reset_password.php');
        exit();
    } catch (Exception $e) {
        $message = "L'envoi a échoué. Erreur technique.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/scrollreveal"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full w-full relative bg-white overflow-hidden">


<div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8  relative bg-red overflow-hidden">

    <!-- Image incrustée -->
    <img
        src="../img/background4.jpg"
        class="pointer-events-none fixed inset-0 m-auto w-full opacity-[0.09]"
        alt=""
    >

    <div class="sm:mx-auto sm:w-full sm:max-w-md reveal-top">
        <div class="flex justify-left items-center gap-2 mb-8 reveal-top">
            <a href="index.php" class="flex items-center gap-2">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-2xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>
        </div>
        
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Mot de passe oublié ?</h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            Pas de panique ! Entrez votre email et nous vous enverrons un code.
        </p>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[440px] reveal-bottom">
        <div class="bg-white py-10 px-8 shadow-xl shadow-gray-200/50 sm:rounded-3xl border border-gray-100">
            
            <?php if (isset($message)): ?>
                <div class="mb-6 p-4 bg-fuchsia-50 border-l-4 border-fuchsia-500 text-fuchsia-800 flex items-center gap-3 rounded-xl animate-pulse">
                    <svg class="w-5 h-5 text-fuchsia-500" fill="currentColor" viewBox="0 0 20 20"><path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path></svg>
                    <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-6" action="forgot_password.php" method="POST" id="forgotForm">
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Adresse e-mail</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </div>
                        <input id="email" name="email" type="email" autocomplete="email" required 
                            class="block w-full rounded-2xl border-0 py-4 pl-12 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none" 
                            placeholder="exemple@email.com">
                    </div>
                </div>

                <div>
                    <button type="submit" id="submitBtn"
                        class="flex w-full justify-center items-center rounded-2xl bg-gray-900 px-4 py-4 text-sm font-bold text-white shadow-lg hover:bg-fuchsia-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-fuchsia-600 transition-all active:scale-[0.98]">
                        <span id="btnText">Envoyer le code OTP</span>
                        <svg id="spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-8">
                <a href="login.php" class="flex items-center justify-center gap-2 text-sm font-semibold text-fuchsia-600 hover:text-fuchsia-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Retour à la connexion
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    ScrollReveal().reveal('.reveal-top', { origin: 'top', distance: '30px', duration: 1000 });
    ScrollReveal().reveal('.reveal-bottom', { origin: 'bottom', distance: '30px', duration: 1000, delay: 200 });

    const forgotForm = document.getElementById('forgotForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const spinner = document.getElementById('spinner');

    forgotForm.addEventListener('submit', function() {
        btnText.textContent = 'Envoi en cours...';
        spinner.classList.remove('hidden');
        submitBtn.classList.add('opacity-80', 'cursor-not-allowed', 'bg-fuchsia-600');
    });
</script>

</body>
</html>