<?php
include 'db.php';
session_start();

$message = "";
$messageType = ""; // 'success' ou 'error'

// --- LOGIQUE DE CONNEXION / INSCRIPTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $action = $_POST['action']; // 'login' ou 'register'

    if (!empty($email)) {
        // Vérifier si l'admin existe pour le login
        $check = $conn->prepare("SELECT id FROM admins WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($action === 'register' && $result->num_rows > 0) {
            $message = "Cet email est déjà enregistré.";
            $messageType = "error";
        } elseif ($action === 'login' && $result->num_rows === 0) {
            $message = "Aucun compte admin trouvé avec cet email.";
            $messageType = "error";
        } else {
            // Création de l'admin si register
            if ($action === 'register') {
                $ins = $conn->prepare("INSERT INTO admins (email) VALUES (?)");
                $ins->bind_param("s", $email);
                $ins->execute();
            }

            // Génération du Token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $stmt = $conn->prepare("INSERT INTO admin_auth_tokens (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $token, $expires);
            
            if ($stmt->execute()) {
                // SIMULATION D'ENVOI D'EMAIL
                $loginLink = "login.php?token=" . $token;
                $message = "Lien magique généré (Simulé) : <a href='$loginLink' class='underline font-bold text-fuchsia-600'>Cliquez ici pour entrer</a>";
                $messageType = "success";
            }
        }
    }
}

// --- VALIDATION DU TOKEN ---
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("SELECT email FROM admin_auth_tokens WHERE token = ? AND expires_at > ? AND used = 0");
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $row = $res->fetch_assoc();
        
        // Marquer le token comme utilisé
        $update = $conn->prepare("UPDATE admin_auth_tokens SET used = 1 WHERE token = ?");
        $update->bind_param("s", $token);
        $update->execute();

        $_SESSION['admin_logged'] = true;
        $_SESSION['admin_email'] = $row['email'];
        
        header("Location: statistiques.php"); // Redirection vers votre page admin
        exit();
    } else {
        $message = "Le lien est invalide ou a expiré.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès Admin | ecascadeur.com</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="h-full flex items-center justify-center p-6 bg-[#f8f9fc]">

    <div class="w-full max-w-md">
        <div class="text-center mb-10">
            <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-10 w-auto mx-auto mb-3 object-contain">
            <span class="text-3xl font-black text-gray-900">Admin<span class="text-fuchsia-500">.</span></span>
            <p class="text-gray-500 mt-2">Accès sécurisé par lien magique</p>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-gray-100">
            
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-2xl text-sm <?= $messageType === 'success' ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-red-50 text-red-700 border border-red-100' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="flex bg-gray-100 p-1 rounded-2xl mb-8">
                <button onclick="toggleAuth('login')" id="btn-login" class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all bg-white shadow-sm text-gray-900">
                    Connexion
                </button>
                <button onclick="toggleAuth('register')" id="btn-register" class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all text-gray-500">
                    Inscription
                </button>
            </div>

            <form action="" method="POST" id="authForm" class="space-y-6">
                <input type="hidden" name="action" id="actionInput" value="login">
                
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Adresse Email Admin</label>
                    <input type="email" name="email" required placeholder="admin@ecascadeur.com" 
                           class="w-full px-5 py-4 bg-gray-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-fuchsia-500 transition-all text-gray-900 font-medium">
                </div>

                <button type="submit" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black shadow-lg hover:bg-fuchsia-600 hover:-translate-y-1 transition-all">
                    <span id="submitText">Recevoir le lien d'accès</span>
                </button>
            </form>
        </div>

        <p class="text-center text-gray-400 text-xs mt-8">
            &copy; 2024 ecascadeur.com Admin System. Sécurisé par Token.
        </p>
    </div>

    <script>
        function toggleAuth(type) {
            const btnLogin = document.getElementById('btn-login');
            const btnRegister = document.getElementById('btn-register');
            const actionInput = document.getElementById('actionInput');
            const submitText = document.getElementById('submitText');

            if (type === 'login') {
                actionInput.value = 'login';
                btnLogin.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
                btnLogin.classList.remove('text-gray-500');
                btnRegister.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
                btnRegister.classList.add('text-gray-500');
                submitText.innerText = "Recevoir le lien d'accès";
            } else {
                actionInput.value = 'register';
                btnRegister.classList.add('bg-white', 'shadow-sm', 'text-gray-900');
                btnRegister.classList.remove('text-gray-500');
                btnLogin.classList.remove('bg-white', 'shadow-sm', 'text-gray-900');
                btnLogin.classList.add('text-gray-500');
                submitText.innerText = "Créer mon compte admin";
            }
        }
    </script>
</body>
</html>