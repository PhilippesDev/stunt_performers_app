<?php
require 'db.php'; // Connexion à la base de données

// Activer l'affichage des erreurs pour debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Récupérer les données de la requête
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    die(json_encode(["error" => "ID de commande manquant"]));
}

// Connexion à la base de données MySQL
// Vérifier si la commande existe et récupérer les infos
$query = "SELECT o.total_amount, u.phone FROM temp_orders o JOIN users u ON o.seller_id = u.id WHERE o.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(["error" => "Commande introuvable"]));
}

$order = $result->fetch_assoc();
$stmt->close();

// Déterminer le fournisseur de paiement
$phone = $order['phone'];
$provider = "";
if (preg_match("/^\+243(98|99|97|96|98)/", $phone)) {
    $provider = "AIRTEL";
} elseif (preg_match("/^\+243(89|84|85)/", $phone)) {
    $provider = "ORANGE";
} elseif (preg_match("/^\+243(81|82|83)/", $phone)) {
    $provider = "MPESA";
} else {
    die(json_encode(["error" => "Numéro non valide"]));
}

// Préparer les données pour l'API Maishapay
$data = [
    "transactionReference" => uniqid(),
    "gatewayMode" => "0",
    "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
    "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
    "order" => [
        "motif" => "Transfert vers Momo",
        "amount" => $order['total_amount'],
        "currency" => "USD",
        "customerFullName" => "Landry Ngoya",
        "customerEmailAdress" => ""
    ],
    "paymentChannel" => [
        "provider" => $provider,
        "walletID" => $phone,
        "callbackUrl" => "https://seraphin.alwaysdata.net/php/callback.php"
    ]
];

// Envoyer la requête CURL
$ch = curl_init('https://marchand.maishapay.online/api/b2c/store/transfert/mobilemoney');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);

// Vérifier s'il y a une erreur dans cURL
if (curl_errno($ch)) {
    die(json_encode(["error" => "Erreur cURL : " . curl_error($ch)]));
}

curl_close($ch);

// Vérifier si la réponse contient "transactionStatus":"SUCCESS"
if (strpos($response, '"transactionStatus":"SUCCESS"') !== false) {
    // ✅ Enregistrer uniquement l'ID de la commande dans la table `validation`
    $query = "INSERT INTO validation (order_id) VALUES (?)";
    $stmt = $conn->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            // 🔄 Succès : Rediriger vers la page success
            header("Location: success.php");
            exit();
        } else {
            // ❌ Erreur d'insertion
            die(json_encode(["error" => "Échec de l'enregistrement de la validation", "sql_error" => $stmt->error]));
        }
    } else {
        // ❌ Erreur de préparation de requête
        die(json_encode(["error" => "Erreur SQL", "sql_error" => $conn->error]));
    }
} else {
    // ❌ Échec du paiement
    die(json_encode(["error" => "Échec du paiement", "details" => json_decode($response, true)]));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfert de fonds sécurisé | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <style>
        @keyframes float-money {
            0% { transform: translateY(0) rotate(0); opacity: 0; }
            20% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }
        .bill {
            position: fixed;
            pointer-events: none;
            z-index: 0;
            color: #22c55e;
            font-size: 24px;
        }
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        .progress-bar-fill {
            width: 0%;
            transition: width 3s linear;
        }
    </style>
</head>
<body class="bg-slate-50 overflow-hidden h-screen flex items-center justify-center">

    <div id="money-container"></div>

    <div class="relative z-10 w-full max-w-md px-4">
        <div class="glass rounded-3xl p-8 ***-2xl text-center">
            
            <div class="mb-6 relative">
                <div class="w-24 h-24 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="bi bi-safe2-fill text-4xl text-orange-600"></i>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-slate-800 mb-2">Traitement Financier</h2>
            <p class="text-slate-500 mb-8 text-sm italic">Transfert de fonds vers le portefeuille <?= $provider ?>...</p>

            <div class="bg-slate-900 text-white py-4 px-6 rounded-2xl mb-8 flex justify-between items-center ***-inner">
                <span class="text-slate-400 text-xs font-bold uppercase tracking-wider">Montant Transaction</span>
                <span class="text-2xl font-mono font-bold text-green-400"><?= number_format($order['total_amount'], 2) ?> USD</span>
            </div>

            <div class="w-full bg-slate-200 h-3 rounded-full overflow-hidden mb-4 border border-slate-300">
                <div id="progress-fill" class="h-full bg-gradient-to-r from-orange-500 to-green-500 progress-bar-fill"></div>
            </div>
            
            <div class="flex justify-between text-[10px] text-slate-400 font-bold uppercase mb-8">
                <span>Initialisation</span>
                <span>Vérification Bancaire</span>
                <span>Terminé</span>
            </div>

            <div class="flex items-center justify-center gap-2 text-green-600 text-sm font-semibold">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Transaction monétisée sécurisée</span>
            </div>
        </div>
        
       
    </div>

    <script>
        // Création de billets et pièces qui flottent
        const container = document.getElementById('money-container');
        const icons = ['bi-cash', 'bi-coin', 'bi-currency-dollar', 'bi-bank'];
        
        for(let i=0; i<20; i++) {
            const bill = document.createElement('i');
            bill.className = `bill bi ${icons[Math.floor(Math.random()*icons.length)]}`;
            bill.style.left = Math.random() * 100 + 'vw';
            bill.style.top = '110vh';
            bill.style.animation = `float-money ${3 + Math.random()*4}s linear infinite`;
            bill.style.animationDelay = Math.random() * 5 + 's';
            container.appendChild(bill);
        }

        // Animation de la barre de progression
        setTimeout(() => {
            document.getElementById('progress-fill').style.width = '100%';
        }, 100);

        // Redirection après l'animation visuelle (3.5 secondes pour laisser l'utilisateur apprécier l'aspect monétaire)
        setTimeout(() => {
            <?php if (strpos($response, '"transactionStatus":"SUCCESS"') !== false): ?>
                window.location.href = "success.php";
            <?php else: ?>
                // Gestion d'erreur visuelle
                alert("Erreur de transaction");
                window.location.href = "index.php";
            <?php endif; ?>
        }, 4000);
    </script>
</body>
</html>