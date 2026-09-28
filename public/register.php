<?php
session_start();
require __DIR__ . '/../libs/db.php';
require __DIR__ . '/../vendor/autoload.php';

// 1. CONFIGURATION GOOGLE CLIENT
$client = new Google\Client();
$client->setClientId(env_value('GOOGLE_CLIENT_ID'));
$client->setClientSecret(env_value('GOOGLE_CLIENT_SECRET'));
$client->setRedirectUri(env_value('GOOGLE_REGISTER_REDIRECT_URI'));
$client->addScope("email");
$client->addScope("profile");

// URL pour le bouton Google
$url = $client->createAuthUrl();

$error = null;
$success = false;

// Récupération des données Google si elles existent
$google_email = isset($_SESSION['google_data']['email']) ? $_SESSION['google_data']['email'] : '';
$google_name = isset($_SESSION['google_data']['name']) ? $_SESSION['google_data']['name'] : '';
$google_picture = isset($_SESSION['google_data']['picture']) ? $_SESSION['google_data']['picture'] : '';

// 2. GESTION DE L'INSCRIPTION FORMULAIRE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['human_token']) || $_POST['human_token'] !== 'mouse_verified') {
        $error = "Veuillez prouver que vous êtes un humain en cochant la case.";
    } else {

        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $profile_pic = $_FILES['profile_pic'];

        // Validation
        if ($password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (strlen($password) < 8) {
            $error = 'Le mot de passe doit contenir au moins 8 caractères.';
        } else {
            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                // Dossier d'upload
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                
                // Sécurisation du nom de fichier
                $file_ext = pathinfo($profile_pic['name'], PATHINFO_EXTENSION);
                $new_file_name = time() . "_" . uniqid() . "." . $file_ext;
                $profile_pic_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($profile_pic['tmp_name'], $profile_pic_path)) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    
                    $query = "INSERT INTO users (username, email, phone, password, profile_pic) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('sssss', $username, $email, $phone, $hashed_password, $profile_pic_path);
                    
                    if ($stmt->execute()) { 
                        $success = true; 
                        unset($_SESSION['google_data']);
                    } else { 
                        $error = "Erreur lors de l'enregistrement en base de données."; 
                    }
                } else {
                    $error = "Erreur lors du téléchargement de la photo de profil.";
                }
            }

        }
    }
}

session_destroy(); 
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-white">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un compte | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/scrollreveal"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #ed8936; border-radius: 10px; }
    </style>
</head>
<body class="h-full">

<?php if ($success): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl animate-in fade-in zoom-in duration-300">
            <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Bienvenue chez ecascadeur.com !</h3>
            <p class="text-gray-500 mb-8">Votre compte a été créé avec succès. Vous pouvez maintenant explorer nos collections.</p>
            <a href="login.php" class="block w-full py-4 bg-fuchsia-500 hover:bg-fuchsia-600 text-white font-bold rounded-xl transition-all shadow-lg shadow-fuchsia-200">Se connecter</a>
        </div>
    </div>
<?php endif; ?>

<div class="flex min-h-full">
    
    <div class="hidden md:flex lg:w-1/2 flex-col justify-between bg-gradient-to-br from-fuchsia-500 to-fuchsia-800 p-12 text-white relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full opacity-10">
            <svg class="absolute -bottom-20 -left-20" width="500" height="500" viewBox="0 0 200 200"><path fill="#FFFFFF" d="M44.7,-76.4C58.8,-69.2,71.8,-59.1,79.6,-45.8C87.4,-32.6,90,-16.3,88.5,-0.9C87,14.5,81.4,29,72.9,41.4C64.4,53.8,53,64,40.1,71.2C27.2,78.4,13.6,82.5,-0.8,83.9C-15.1,85.2,-30.3,83.8,-43.9,77.1C-57.5,70.5,-69.6,58.6,-77.2,44.7C-84.8,30.8,-87.9,15.4,-86.6,0.8C-85.3,-13.9,-79.5,-27.7,-71.2,-40.4C-62.9,-53.1,-52.1,-64.7,-39.3,-72.6C-26.4,-80.5,-13.2,-84.7,0.7,-85.9C14.6,-87.1,29.2,-85.2,44.7,-76.4Z" transform="translate(100 100)" /></svg>
        </div>

        <div class="relative z-10 reveal-left">
            <div class="flex items-center gap-3 mb-12">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-10 w-auto object-contain bg-white rounded-lg p-1 shadow-lg">
                <span class="text-2xl font-bold tracking-tight text-white uppercase">ecascadeur.com</span>
            </div>
            <h1 class="text-6xl font-extrabold leading-tight mb-6">Rejoignez <br><span class="text-fuchsia-100">la communauté </span> <img src="assets/images/img/fusee2.png" alt="" class="w-12 h-18"></h1>
            <p class="text-xl text-fuchsia-50 max-w-md leading-relaxed">Créez votre profil en quelques secondes et profitez d'une expérience shopping personnalisée.</p>
        </div>
        <div class="relative z-10 text-sm text-fuchsia-100 reveal-bottom">© 2024 ecascadeur.com.</div>
    </div>

    <div class="flex flex-1 flex-col justify-center px-6 py-12 lg:px-20 bg-gray-50 lg:overflow-y-auto custom-scrollbar relative bg-red overflow-hidden">
        
        <div class="sm:mx-auto sm:w-full sm:max-w-xl reveal-right">



            <a href="index.php" class="inline-flex items-center gap-2 mb-4">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>

            <h2 class="text-3xl font-bold tracking-tight text-gray-900 mb-2">Créer un compte</h2>
            <p class="text-gray-500 mb-10">Déjà membre ? <a href="login.php" class="text-fuchsia-600 font-semibold hover:underline underline-offset-4">Connectez-vous ici</a></p>

            <?php if ($error): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 flex items-center gap-3 rounded animate-shake">
                    <span class="text-sm font-medium"><?php echo $error; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($google_email)): ?>
                <div class="p-3 mb-4 text-sm text-fuchsia-700 bg-fuchsia-50 rounded-lg">
                    Compte Google reconnu ! Veuillez compléter vos informations pour finaliser l'inscription.
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" enctype="multipart/form-data" class="space-y-5" id="regForm">
                
                <div class="flex flex-col items-center mb-8">
                    <div class="relative group">
                <div class="w-24 h-24 rounded-full overflow-hidden ring-4 ring-white shadow-lg bg-gray-200">
                    <?php
                        $avatar = (!empty($google_picture)) ? $google_picture : 'assets/images/img/avatar.jpg';
                        ?>
                    <img id="preview" src=" <?= $avatar ?>" class="w-full h-full object-cover">
                </div>

                        <label for="profile_pic" class="absolute bottom-0 right-0 bg-fuchsia-500 p-2 rounded-full text-white cursor-pointer hover:bg-fuchsia-600 shadow-md transition-all group-hover:scale-110">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4" stroke-width="3" stroke-linecap="round"/></svg>
                        </label>
                        <input type="file" id="profile_pic" name="profile_pic" class="hidden" accept="image/*" required onchange="previewImage(this)">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-1 group-focus-within:text-fuchsia-600 transition-colors">Nom complet</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($google_name); ?>" required class="block w-full rounded-xl border-gray-200 py-3.5 px-4 text-gray-900 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all" placeholder="Votre nom">
                    </div>
                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-1 group-focus-within:text-fuchsia-600 transition-colors">Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($google_email); ?>" required class="block w-full rounded-xl border-gray-200 py-3.5 px-4 text-gray-900 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all" placeholder="ex:name@gmail.com">
                    </div>
                </div>

                <div class="group">
                    <label class="block text-sm font-semibold text-gray-700 mb-1 group-focus-within:text-fuchsia-600 transition-colors">Téléphone (Mobile Money)</label>
                    <input type="tel" name="phone" pattern="\[0-9]{9}" required class="block w-full rounded-xl border-gray-200 py-3.5 px-4 text-gray-900 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all" placeholder="0XXXXXXXXX">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-1 group-focus-within:text-fuchsia-600 transition-colors">Mot de passe</label>
                        <input type="password" id="pass" name="password" required class="block w-full rounded-xl border-gray-200 py-3.5 px-4 text-gray-900 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all" placeholder="••••••••">
                    </div>
                    <div class="group">
                        <label class="block text-sm font-semibold text-gray-700 mb-1 group-focus-within:text-fuchsia-600 transition-colors">Confirmation</label>
                        <input type="password" name="confirm_password" required class="block w-full rounded-xl border-gray-200 py-3.5 px-4 text-gray-900 shadow-sm ring-1 ring-gray-300 focus:ring-2 focus:ring-fuchsia-500 outline-none transition-all" placeholder="••••••••">
                    </div>
                </div>

                <div class="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                    <div id="strength-bar" class="h-full w-0 bg-red-500 transition-all duration-500"></div>
                </div>

                <div class="flex items-start gap-3 py-2">
                    <input type="checkbox" required class="mt-1 w-4 h-4 text-fuchsia-600 border-gray-300 rounded focus:ring-fuchsia-500">
                    <label class="text-sm text-gray-500">J'accepte les <a href="term_condition.html" class="text-fuchsia-600 font-medium">Conditions d'Utilisation</a> et la Politique de Confidentialité.</label>
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

                <button type="submit" id="subBtn" class="flex w-full justify-center rounded-xl bg-gray-900 px-4 py-4 text-sm font-bold text-white shadow-lg hover:bg-fuchsia-600 transition-all active:scale-[0.98]">
                    <span>Créer mon profil</span>
                    <svg id="loader" class="hidden animate-spin ml-3 h-5 w-5 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
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
    // Reveal Animations
    ScrollReveal().reveal('.reveal-left', { origin: 'left', distance: '50px', duration: 1000 });
    ScrollReveal().reveal('.reveal-right', { origin: 'right', distance: '50px', duration: 1000 });

    // Preview Photo
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => document.getElementById('preview').src = e.target.result;
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Indicateur Force MDP
    document.getElementById('pass').addEventListener('input', function(e) {
        const val = e.target.value;
        const bar = document.getElementById('strength-bar');
        let strength = 0;
        if(val.length > 5) strength += 33;
        if(/[A-Z]/.test(val)) strength += 33;
        if(/[0-9]/.test(val)) strength += 34;
        
        bar.style.width = strength + '%';
        bar.className = 'h-full transition-all duration-500 ' + 
            (strength < 40 ? 'bg-red-500' : strength < 70 ? 'bg-fuchsia-400' : 'bg-green-500');
    });

    // Submit Loading
    document.getElementById('regForm').addEventListener('submit', function() {
        document.getElementById('subBtn').querySelector('span').textContent = "Traitement...";
        document.getElementById('loader').classList.remove('hidden');
    });
</script>
</body>
</html>