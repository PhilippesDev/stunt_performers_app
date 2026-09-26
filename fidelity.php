<?php

include "db.php";

// Démarrer la session pour récupérer le user_id
session_start();

// Vérifier si l'utilisateur est connecté et que user_id existe dans la session
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    // Si l'utilisateur n'est pas connecté, rediriger ou afficher une erreur
    die("Utilisateur non connecté. Veuillez vous connecter.");
}

// Récupérer le nombre d'achats
$sql_achats = "SELECT COUNT(*) AS total_achats FROM orders WHERE user_id = ? ";
$stmt_achats = $conn->prepare($sql_achats);
$stmt_achats->bind_param("i", $user_id);
$stmt_achats->execute();
$result_achats = $stmt_achats->get_result();
$data_achats = $result_achats->fetch_assoc();
$total_achats = $data_achats['total_achats'];

// Récupérer le nombre de ventes
$sql_ventes = "SELECT COUNT(*) AS total_ventes FROM orders WHERE seller_id = ? ";
$stmt_ventes = $conn->prepare($sql_ventes);
$stmt_ventes->bind_param("i", $user_id);
$stmt_ventes->execute();
$result_ventes = $stmt_ventes->get_result();
$data_ventes = $result_ventes->fetch_assoc();
$total_ventes = $data_ventes['total_ventes'];

// Calculer le total des opérations
$total_operations = $total_achats + $total_ventes;

// Fermer les connexions
$stmt_achats->close();
$stmt_ventes->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Points | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .gradient-text {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="h-full flex flex-col justify-center items-center p-4">

    <div class="mb-10 text-center">
        <a href="index.php" class="inline-flex items-center justify-center gap-2">
            <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
            <span class="text-2xl font-black tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
        </a>
    </div>

    <div class="relative w-full max-w-md overflow-hidden">
        <div class="absolute -top-10 -right-10 size-40 bg-fuchsia-200 rounded-full blur-3xl opacity-50"></div>
        <div class="absolute -bottom-10 -left-10 size-40 bg-blue-100 rounded-full blur-3xl opacity-50"></div>

        <div class="glass-card relative z-10 p-8 rounded-[3rem] shadow-2xl border border-gray-100 text-center">
            
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-fuchsia-100 text-fuchsia-700 text-xs font-bold mb-6 tracking-wide uppercase">
                <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Membre Privilège
            </div>

            <div class="mb-2">
                <h1 class="text-7xl font-black text-fuchsia-600 ">
                    <?php echo number_format($total_operations); ?>
                </h1>
                <p class="text-gray-400 font-bold uppercase text-[10px] tracking-[0.2em]">Points cumulés</p>
            </div>

            <hr class="my-8 border-gray-100">

            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="p-4 bg-gray-50 rounded-[2rem] border border-gray-100">
                    <p class="text-xl font-bold text-gray-800"><?php echo $total_achats; ?></p>
                    <p class="text-[10px] text-gray-400 font-semibold uppercase">Achats</p>
                </div>
                <div class="p-4 bg-gray-50 rounded-[2rem] border border-gray-100">
                    <p class="text-xl font-bold text-gray-800"><?php echo $total_ventes; ?></p>
                    <p class="text-[10px] text-gray-400 font-semibold uppercase">Ventes</p>
                </div>
            </div>

            <p class="text-sm text-gray-500 mb-8 leading-relaxed">
                Continuez vos opérations sur <span class="font-bold text-gray-900">Cascade</span> pour débloquer des réductions exclusives !
            </p>

            <div class="flex flex-col gap-3">
                <button onclick="window.location.href='dashboard.php'" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-bold shadow-xl shadow-gray-200 hover:scale-[0.98] transition-all">
                    Retour au Dashboard
                </button>
                <button class="w-full py-4 bg-white border-2 border-gray-100 text-gray-700 rounded-2xl font-bold text-sm hover:bg-gray-50">
                    Comment ça marche ?
                </button>
            </div>
        </div>
    </div>

    <p class="mt-10 text-gray-400 text-xs">
        1 opération = 1 point Cascade.
    </p>

</body>
</html>