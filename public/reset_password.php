<?php 
session_start();

// Vérifier si l'OTP et l'email sont définis
if (!isset($_SESSION['otp']) || !isset($_SESSION['email'])) {
    header('Location: forgot_password.php');
    exit();
}

include __DIR__ . '/../libs/db.php'; // On utilise ton fichier db.php pour la cohérence
$message = null;
$status = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp']) && isset($_POST['new_password'])) {
    $inputOtp = $_POST['otp'];
    $newPassword = $_POST['new_password'];

    if ($inputOtp == $_SESSION['otp']) {
        $email = $_SESSION['email'];
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            session_destroy();
            $status = 'success';
            $message = 'Mot de passe mis à jour !';
        } else {
            $status = 'error';
            $message = 'Erreur lors de la mise à jour.';
        }
    } else {
        $status = 'error';
        $message = 'Code OTP incorrect.';
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/scrollreveal"></script>
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full flex items-center justify-center p-6 relative bg-red overflow-hidden">

    <!-- Image incrustée -->
    <img
        src="../img/background4.jpg"
        class="pointer-events-none fixed inset-0 m-auto w-full opacity-[0.09]"
        alt=""
    >

    <div class="max-w-md w-full">
        <div class="flex justify-left items-center gap-2 mb-8 reveal-top">
            <a href="index.php" class="flex items-center gap-2">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-2xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-200/50 p-8 border border-gray-100 reveal-bottom">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-2">Vérification</h2>
            <p class="text-gray-700 text-center text-sm mb-8">Entrez le code reçu par mail et votre nouveau mot de passe.</p>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-2xl flex items-center gap-3 <?= $status === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                    <?php if($status === 'success'): ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"></path></svg>
                    <?php else: ?>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"></path></svg>
                    <?php endif; ?>
                    <span class="text-sm font-bold uppercase tracking-wider"><?= $message ?></span>
                </div>
            <?php endif; ?>

            <?php if ($status !== 'success'): ?>
        <form action="reset_password.php" method="POST" id="otp-form" class="space-y-6">
    
            <div class="w-full max-w-sm mx-auto">
                <label class="block text-xs font-bold text-gray-600 tracking-widest mb-4 text-center uppercase">
                    Code de vérification
                </label>
                
                <div class="flex justify-center gap-1 sm:gap-2" id="otp-inputs">
                    <input type="text" 
                        maxlength="1" 
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" 
                        required>
                    <input type="text" maxlength="1" inputmode="numeric" class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" required>
                    <input type="text" maxlength="1" inputmode="numeric" class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" required>
                    <input type="text" maxlength="1" inputmode="numeric" class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" required>
                    <input type="text" maxlength="1" inputmode="numeric" class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" required>
                    <input type="text" maxlength="1" inputmode="numeric" class="otp-field w-full max-w-[3rem] aspect-square text-center text-xl sm:text-2xl font-bold bg-gray-50 border-2 border-gray-600 rounded-xl focus:border-fuchsia-500 focus:ring-2 focus:ring-fuchsia-200 transition-all outline-none" required>
                </div>
                
                <input type="hidden" name="otp" id="full-otp">
            </div>

    <div class="relative">
        <label class="block text-xs font-bold text-gray-600  tracking-widest mb-2 ml-1">Nouveau mot de passe</label>
        <input type="password" name="new_password" required
            class="block w-full rounded-2xl border-0 py-4 px-5 bg-gray-50 text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-1 focus:ring-fuchsia-500 transition-all outline-none" 
            placeholder="••••••••">
    </div>

    <button type="submit" class="w-full bg-gray-900 hover:bg-fuchsia-600 text-white font-bold py-4 rounded-2xl shadow-lg transition-all active:scale-[0.98]">
        Confirmer le changement
    </button>
</form>
            <?php else: ?>
                <a href="login.php" class="block w-full bg-fuchsia-600 text-center text-white font-bold py-4 rounded-2xl shadow-lg shadow-fuchsia-100 transition-all hover:bg-fuchsia-700">
                    Aller à la connexion
                </a>
            <?php endif; ?>
        </div>
        
        <p class="text-center mt-8 text-sm text-gray-400">
            Vous n'avez pas reçu de code ? <a href="forgot_password.php" class="text-fuchsia-600 font-bold hover:underline">Renvoyer</a>
        </p>
    </div>

    <script>
        ScrollReveal().reveal('.reveal-top', { origin: 'top', distance: '20px', duration: 1000 });
        ScrollReveal().reveal('.reveal-bottom', { origin: 'bottom', distance: '20px', duration: 1000, delay: 200 });

        function togglePass() {
            const input = document.getElementById('new_password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
    <script>
    const inputs = document.querySelectorAll('.otp-field');
    const fullOtpInput = document.getElementById('full-otp');
    const form = document.getElementById('otp-form');

    // Gérer la saisie et le focus automatique
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus(); // Passe au suivant
            }
            updateFullOtp();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus(); // Retour au précédent sur effacement
            }
        });
    });

    // Fusionner les 6 chiffres dans le input hidden
    function updateFullOtp() {
        let otpValue = "";
        inputs.forEach(input => otpValue += input.value);
        fullOtpInput.value = otpValue;
    }

    // Sécurité au submit
    form.addEventListener('submit', (e) => {
        updateFullOtp();
        if (fullOtpInput.value.length < 6) {
            e.preventDefault();
            alert("Veuillez remplir les 6 chiffres du code.");
        }
    });
</script>
</body>
</html>