<?php
session_start();
require __DIR__ . '/../libs/db.php';
require __DIR__ . '/../vendor/autoload.php';

// Configuration Google Client
$client = new Google\Client();
$client->setClientId(env_value('GOOGLE_CLIENT_ID'));
$client->setClientSecret(env_value('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(env_value('GOOGLE_LOGIN_REDIRECT_URI'));
$client->addScope("email");
$client->addScope("profile");

// 1. GESTION DU RETOUR DE GOOGLE (OAuth Callback)
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
     if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

         // Chemin vers le fichier que vous avez gardé précieusement
         $servicePath = __DIR__ . '/vendor/google/apiclient-services/src/Oauth2.php';
    
    if (file_exists($servicePath)) {
        require_once $servicePath;
    }

    // On utilise maintenant le nom complet de la classe
    $google_oauth = new \Google\Service\Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
        
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $google_id = $google_account_info->id;

        // Vérifier si l'utilisateur existe déjà
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

       if ($user) {
            // Utilisateur trouvé -> Connexion directe
            $_SESSION['user_id'] = $user['id'];
            setcookie("user_id", $user['id'], time() + (30 * 24 * 60 * 60), "/");
            header('Location: dashboard.php');
            exit();
        } else {
            // L'utilisateur n'existe pas -> On pré-remplit la session et on redirige
            $_SESSION['google_data'] = [
                'email' => $google_account_info->email,
                'name' => $google_account_info->name,
                'picture' => $google_account_info->picture // Optionnel : pour prévisualiser
            ];
            header('Location: register.php');
            exit();
        }
    }
}

// Génération de l'URL Google pour le bouton
$url = $client->createAuthUrl();

$error = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['human_token']) || $_POST['human_token'] !== 'mouse_verified') {
        $error = "Veuillez prouver que vous êtes un humain en cochant la case.";
    } else {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                setcookie("user_id", $user['id'], time() + (30 * 24 * 60 * 60), "/");
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "E-mail ou mot de passe incorrect.";
            }
    } else {
        $error = "Adresse mail invalide";
    }
}
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-white">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/scrollreveal"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Animation de secousse pour le bouton si non vérifié */
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-6px); }
    75% { transform: translateX(6px); }
}
.animate-shake {
    animation: shake 0.2s ease-in-out 0s 3;
    background-color: #dc2626 !important; /* Rouge alerte */
}

/* Douceur des transitions */
#check-circle, #check-icon {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
    </style>
</head>
<body class="h-full">

<div class="flex min-h-full">
    
    <div class="hidden lg:flex lg:w-1/2 flex-col justify-between bg-gradient-to-br from-fuchsia-500 to-fuchsia-800 p-12 text-white relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full opacity-20 pointer-events-none">
            <svg class="absolute -bottom-20 -left-20" width="400" height="400" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path fill="#FFFFFF" d="M44.7,-76.4C58.8,-69.2,71.8,-59.1,79.6,-45.8C87.4,-32.6,90,-16.3,88.5,-0.9C87,14.5,81.4,29,72.9,41.4C64.4,53.8,53,64,40.1,71.2C27.2,78.4,13.6,82.5,-0.8,83.9C-15.1,85.2,-30.3,83.8,-43.9,77.1C-57.5,70.5,-69.6,58.6,-77.2,44.7C-84.8,30.8,-87.9,15.4,-86.6,0.8C-85.3,-13.9,-79.5,-27.7,-71.2,-40.4C-62.9,-53.1,-52.1,-64.7,-39.3,-72.6C-26.4,-80.5,-13.2,-84.7,0.7,-85.9C14.6,-87.1,29.2,-85.2,44.7,-76.4Z" transform="translate(100 100)" />
            </svg>
        </div>

        <div class="relative z-10 reveal-left">
            <div class="flex items-center gap-3 mb-12">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-10 w-auto object-contain bg-white rounded-lg p-1 shadow-lg">
                <span class="text-2xl font-bold tracking-tight text-white uppercase">ecascadeur.com</span>
            </div>

            <h1 class="text-6xl font-extrabold leading-tight mb-6">
                Bienvenue sur <br><span class="text-fuchsia-100">ecascadeur.com</span> 👋
            </h1>
            <p class="text-xl text-fuchsia-50 max-w-md leading-relaxed">
                Accédez à votre espace pour gérer vos commandes et découvrir nos dernières collections exclusives.
            </p>
        </div>

        <div class="relative z-10 text-sm text-fuchsia-100 reveal-bottom">
            © 2024 ecascadeur.com. Tous droits réservés.
        </div>
    </div>

    <div class="flex flex-1 flex-col justify-center px-6 py-12 lg:px-24 bg-gray-50">
        
        <div class="sm:mx-auto sm:w-full sm:max-w-md reveal-right">
            
            <a href="index.php" class="inline-flex items-center gap-2 mb-4">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>

            <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-2">Bon retour !</h2>
            <p class="text-gray-500 mb-8">
                Pas encore de compte ? 
                <a href="register.php" class="font-semibold text-fuchsia-600 hover:text-fuchsia-500 underline decoration-2 underline-offset-4 transition-all">
                    Inscrivez-vous ici
                </a>
            </p>

            <?php if ($error): ?>
                <div id="error-alert" class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 flex items-center gap-3 rounded animate-pulse">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span class="text-sm font-medium"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-6" id="loginForm">
                <div class="group">
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2 group-focus-within:text-fuchsia-600 transition-colors">
                        E-mail
                    </label>
                    <div class="relative">
                        <input id="username" name="email" type="text" required 
                            class="block w-full rounded-xl border-0 py-4 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-fuchsia-500 sm:text-sm transition-all outline-none"
                            placeholder="votre_email@example.com">
                    </div>
                </div>

                <div class="group">
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-sm font-semibold text-gray-700 group-focus-within:text-fuchsia-600 transition-colors">
                            Mot de passe
                        </label>
                        <a href="forgot_password.php" class="text-sm font-medium text-gray-400 hover:text-fuchsia-600 transition-colors">
                            Oublié ?
                        </a>
                    </div>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                            class="block w-full rounded-xl border-0 py-4 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-fuchsia-500 sm:text-sm transition-all outline-none"
                            placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-fuchsia-600">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </div>
                </div>


                <div class="flex items-center space-x-4 p-4 bg-white border border-gray-200 rounded-xl mb-6 select-none shadow-sm">
                    <div id="captcha-container" class="relative flex items-center justify-center w-7 h-7">
                        <div id="check-circle" class="w-full h-full border-2 border-gray-300 rounded-full cursor-pointer transition-all duration-300 hover:border-fuchsia-500"></div>
                        
                        <svg id="captcha-spinner" class="hidden animate-spin absolute w-5 h-5 text-fuchsia-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>

                        <svg id="check-icon" class="hidden absolute w-5 h-5 text-white opacity-0 transition-opacity duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    
                    <label class="text-sm font-semibold text-gray-700 cursor-pointer">Je ne suis pas un robot</label>
                    <input type="hidden" name="human_token" id="human_token" value="">
                </div>

                <button type="submit" id="submitBtn"
                    class="flex w-full justify-center rounded-xl bg-gray-900 px-4 py-4 text-sm font-bold leading-6 text-white shadow-lg hover:bg-fuchsia-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fuchsia-600 transition-all active:scale-[0.98]">
                    <span id="btnText">Se connecter maintenant</span>
                    <svg id="spinner" class="hidden animate-spin ml-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </button>
                
                <div class="relative mt-10">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-200"></div>
                    </div>
                    <div class="relative flex justify-center text-sm font-medium leading-6">
                        <span class="bg-gray-50 px-6 text-gray-400">Ou continuer avec</span>
                    </div>
                </div>

                <a href="<?php echo $url; ?>" class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-3 py-4 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition-all hover:border-fuchsia-200">
                    <img class="h-5 w-5" src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
                    <span class="text-sm font-bold leading-6">Google</span>
                </a>
            </form>
        </div>
    </div>
</div>
<script>
    const captchaContainer = document.getElementById('captcha-container');
const checkCircle = document.getElementById('check-circle');
const captchaSpinner = document.getElementById('captcha-spinner');
const checkIcon = document.getElementById('check-icon');
const humanToken = document.getElementById('human_token');

let isVerified = false;

captchaContainer.addEventListener('mousedown', function() {
    if (isVerified) return;

    // 1. Début de l'animation : On cache le cercle, on montre le spin
    checkCircle.classList.add('scale-0', 'opacity-0');
    captchaSpinner.classList.remove('hidden');

    // 2. Simulation d'un délai de vérification (1.2 seconde)
    setTimeout(() => {
        // Cacher le spinner
        captchaSpinner.classList.add('hidden');
        
        // Transformer le cercle en vert et rond
        checkCircle.classList.remove('scale-0', 'opacity-0', 'border-gray-300', 'hover:border-fuchsia-500');
        checkCircle.classList.add('bg-green-500', 'border-green-500', 'scale-110');
        
        // Afficher l'icône Check avec un fondu
        checkIcon.classList.remove('hidden');
        setTimeout(() => {
            checkIcon.classList.remove('opacity-0');
            checkIcon.classList.add('opacity-100');
        }, 50);

        // Valider le token pour le PHP
        humanToken.value = 'mouse_verified';
        isVerified = true;
    }, 1200);
});

// Modification de la soumission du formulaire
loginForm.addEventListener('submit', function(e) {
    if (!isVerified) {
        e.preventDefault();
        
        // Animation de secousse sur le bouton
        submitBtn.classList.add('animate-shake'); 
        btnText.textContent = "Vérifiez que vous êtes humain !";
        
        setTimeout(() => {
            submitBtn.classList.remove('animate-shake');
            btnText.textContent = "Se connecter maintenant";
        }, 2000);
        
        // Réinitialiser le spinner du bouton si nécessaire
        spinner.classList.add('hidden');
        return false;
    }
});
</script>
<script>
    // Détection du clic humain réel
const checkbox = document.getElementById('human_check');
const humanToken = document.getElementById('human_token');

// On utilise 'mousedown' car les robots de base simulent souvent 'click' ou changent juste la valeur
checkbox.addEventListener('mousedown', function() {
    // Cette valeur n'est injectée que si une souris physique appuie sur l'élément
    humanToken.value = 'mouse_verified';
});

// Bloquer la soumission si la case n'est pas cochée
loginForm.addEventListener('submit', function(e) {
    if (!checkbox.checked || humanToken.value !== 'mouse_verified') {
        e.preventDefault();
        alert("Veuillez cocher la case 'Je ne suis pas un robot'.");
        
        // Réinitialiser l'animation du bouton que vous aviez déjà
        btnText.textContent = 'Se connecter maintenant';
        spinner.classList.add('hidden');
        submitBtn.classList.remove('opacity-80', 'cursor-not-allowed', 'bg-fuchsia-600');
        return false;
    }
    
    // Si c'est bon, le reste de votre code d'animation s'exécute...
    btnText.textContent = 'Connexion...';
    spinner.classList.remove('hidden');
    submitBtn.classList.add('opacity-80', 'cursor-not-allowed', 'bg-fuchsia-600');
});
</script>
<script>
    // Initialisation ScrollReveal
    ScrollReveal().reveal('.reveal-left', { origin: 'left', distance: '50px', duration: 1000, delay: 200 });
    ScrollReveal().reveal('.reveal-right', { origin: 'right', distance: '50px', duration: 1000, delay: 400 });
    ScrollReveal().reveal('.reveal-bottom', { origin: 'bottom', distance: '20px', duration: 1000, delay: 600 });

    // Animation au submit
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const spinner = document.getElementById('spinner');

    loginForm.addEventListener('submit', function() {
        btnText.textContent = 'Connexion...';
        spinner.classList.remove('hidden');
        submitBtn.classList.add('opacity-80', 'cursor-not-allowed', 'bg-fuchsia-600');
    });

    // Toggle Password
    function togglePassword() {
        const input = document.getElementById('password');
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
    }
</script>

</body>
</html>