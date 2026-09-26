<?php
// 1. Démarrer la session au tout début
session_start();

// 2. Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php'; 

$user_id = $_SESSION['user_id']; // ID de l'utilisateur connecté
$otp = null;
$order_id = $_GET['id'] ?? null;
$otp_remaining = 120; // Temps par défaut (2 minutes en secondes)

$colonne_acheteur = 'user_id'; 

if ($order_id) {
    // 1. On récupère l'OTP ET sa date de création
    $query = "SELECT otpvalidated, otp_updated_at FROM orders WHERE id = ? AND {$colonne_acheteur} = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($otp_db, $otp_updated_at);
    $stmt->fetch();
    $stmt->close();

    if ($otp_db !== null) {
        $currentTime = time();
        // Calcul de l'âge de l'OTP en secondes (si pas de date, on force un grand nombre pour le régénérer)
        $otpAge = $otp_updated_at ? ($currentTime - strtotime($otp_updated_at)) : 99999;

        // 2. Si l'OTP est expiré (>= 120s) ou vide, on en génère un nouveau
        if (empty($otp_db) || $otpAge >= 120) {
            $otp = random_int(100000, 999999); // Génère un code sécurisé à 6 chiffres
            $otp_remaining = 120;

            // Mise à jour dans la base de données
            $update_query = "UPDATE orders SET otpvalidated = ?, otp_updated_at = NOW() WHERE id = ? AND {$colonne_acheteur} = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sii", $otp, $order_id, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            // L'OTP est encore valide, on le garde et on calcule le temps restant
            $otp = $otp_db;
            $otp_remaining = 120 - $otpAge;
        }
    } else {
        // Accès refusé ou commande inexistante
        $otp = null; 
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affichage OTP | Cascade</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        .bg-fiduciaire {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(circle at 50% 50%, transparent 0%, #f8fafc 85%),
                linear-gradient(rgba(217, 70, 239, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(217, 70, 239, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 16px 16px, 16px 16px;
        }
    </style>
</head>
<body class="h-full bg-fiduciaire antialiased text-gray-900 lg:h-screen lg:overflow-hidden flex flex-col justify-center items-center px-4 py-12 sm:px-6 lg:px-8">

    <div class="w-full max-w-[460px] space-y-6 relative z-10">
        
        <div class="text-center space-y-2">
            <a href="index.php" class="inline-flex items-center justify-center gap-2">
                <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-2xl font-bold tracking-tight text-gray-900">
                    ecascadeur<span class="text-fuchsia-500 font-medium">.com</span>
                </span>
            </a>
            <h2 class="text-xl font-bold tracking-tight text-gray-900">
                Code de validation sécurisé
            </h2>
            <p class="text-xs text-gray-400 font-medium">
                Commande associée : <span class="font-mono font-bold text-gray-700">#<?php echo htmlspecialchars($order_id ?? '---'); ?></span>
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200/80 shadow-xl shadow-slate-200/40 p-6 sm:p-10 relative overflow-hidden">
            
            <div class="absolute top-0 right-0 p-4 text-gray-100 pointer-events-none select-none">
                <i class="bi bi-shield-lock-fill text-6xl opacity-40"></i>
            </div>

            <div class="text-center relative z-10">
                <?php if ($otp): ?>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-5 flex items-center justify-center gap-1.5">
                        <i class="bi bi-shield-check text-xs text-fuchsia-500"></i> Jeton d'authentification de livraison
                    </p>
                    
                    <div class="group relative cursor-pointer inline-block transition active:scale-[0.98]" onclick="copyToClipboard('<?php echo $otp; ?>')">
                        <div class="text-4xl sm:text-5xl font-mono font-black tracking-wider text-gray-900 bg-slate-50 border border-gray-200 rounded-xl px-8 py-5 group-hover:border-fuchsia-500 group-hover:bg-fuchsia-50/30 transition duration-200">
                            <?php echo htmlspecialchars($otp); ?>
                        </div>
                        
                        <div class="mt-2 flex items-center justify-center gap-1 text-[10px] text-gray-400 group-hover:text-fuchsia-600 transition">
                            <i class="bi bi-copy"></i>
                            <span>Cliquer pour copier le code</span>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-center gap-1.5 text-xs text-gray-400 font-medium">
                        <i class="bi bi-arrow-clockwise animate-spin text-fuchsia-500"></i>
                        <span>Échéance du code : <span id="countdown" class="font-mono font-bold text-gray-700">--:--</span></span>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="p-4 rounded-xl bg-slate-50 border border-gray-200/60 flex items-start gap-3 text-left">
                            <i class="bi bi-info-circle text-gray-400 mt-0.5 shrink-0 text-sm"></i>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                Protocole de déblocage : Présentez ce code au livreur <span class="font-semibold text-gray-900">uniquement après</span> vérification et réception conforme de votre colis. Cette action valide le transfert des fonds.
                            </p>
                        </div>

                        <button onclick="location.href='dashboard.php';" 
                                class="w-full flex justify-center items-center rounded-xl bg-gray-950 hover:bg-fuchsia-600 px-4 py-3.5 text-sm font-semibold text-white shadow-md transition active:scale-[0.99] cursor-pointer">
                            Retourner au tableau de bord
                        </button>
                    </div>

                <?php else: ?>
                    <div class="py-4 space-y-4">
                        <div class="inline-flex p-3 bg-red-50 rounded-2xl border border-red-100 text-red-600 mb-2">
                            <i class="bi bi-exclamation-triangle text-2xl"></i>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-gray-900">Code introuvable</h3>
                            <p class="text-xs text-gray-400 max-w-xs mx-auto">
                                Impossible de charger la clé de validation unique associée à cette référence de commande.
                            </p>
                        </div>
                        
                        <button onclick="location.href='dashboard.php';" 
                                class="w-full flex justify-center items-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-3.5 text-sm font-semibold text-gray-700 transition active:scale-[0.99] cursor-pointer">
                            Retourner au tableau de bord
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center flex items-center justify-center gap-1.5 text-[10px] text-gray-400 font-medium">
                <i class="bi bi-patch-check-fill text-gray-300"></i>
                <span>Sécurité Cascade &copy; 2026 • Chiffrement Escrow Activé</span>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-8 left-1/2 -translate-x-1/2 bg-gray-950 text-white px-5 py-2.5 rounded-xl text-xs font-semibold shadow-xl border border-gray-800 opacity-0 transition-all duration-300 transform translate-y-2 pointer-events-none z-50 flex items-center gap-2">
        <i class="bi bi-check-circle-fill text-emerald-400"></i>
        <span>Code copié dans le presse-papier</span>
    </div>

    <script>
        // Gestion du compte à rebours et rafraîchissement automatique
        let timeRemaining = <?php echo (int)$otp_remaining; ?>;
        const countdownEl = document.getElementById('countdown');

        if (countdownEl && timeRemaining > 0) {
            const timer = setInterval(() => {
                timeRemaining--;

                if (timeRemaining <= 0) {
                    clearInterval(timer);
                    location.reload(); // Recharge la page pour forcer la génération du nouvel OTP en PHP
                } else {
                    let minutes = Math.floor(timeRemaining / 60);
                    let seconds = timeRemaining % 60;
                    countdownEl.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                }
            }, 1000);
        }

        // Fonction de copie existante
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.classList.remove('opacity-0', 'translate-y-2');
                toast.classList.add('opacity-100', 'translate-y-0');
                
                setTimeout(() => {
                    toast.classList.remove('opacity-100', 'translate-y-0');
                    toast.classList.add('opacity-0', 'translate-y-2');
                }, 2500);
            }).catch(err => {
                console.error('Erreur lors de la copie : ', err);
            });
        }
    </script>
</body>
</html>