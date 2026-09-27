<?php
// 1. Démarrer la session au tout début
session_start();

// 2. Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../libs/db.php';
require_once __DIR__ . '/../libs/helpers/notification_helper.php';

$user_id = $_SESSION['user_id']; // ID du vendeur connecté
$otp_error = "";
$success_message = "";

// Helper to append to a log file
function append_log($path, $entry) {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}

// On récupère l'ID de la commande pour l'affichage initial et le traitement
$order_id = $_GET['id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['otp'])) {
    $entered_otp = trim($_POST['otp']);

    if ($order_id) {
        // 3. SÉCURITÉ CRITIQUE : On vérifie l'OTP ET on s'assure que la commande appartient bien au vendeur connecté
        $stmt = $conn->prepare("SELECT otpvalidated FROM orders WHERE id = ? AND seller_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $stmt->bind_result($otp_db);
        $stmt->fetch();
        $stmt->close();

        if ($otp_db) {
            if ($otp_db == $entered_otp) {
                // 4. L'OTP est correct ET c'est le bon vendeur. On peut dérouler la logique MaishaPay.
                
                $infoStmt = $conn->prepare("SELECT o.transaction_id, o.status, o.total_amount, p.currency, u.id AS user_id FROM orders o INNER JOIN products p ON o.product_id = p.id INNER JOIN users u ON o.seller_id = u.id WHERE o.id = ? AND o.seller_id = ?");
                if ($infoStmt) {
                    $infoStmt->bind_param('ii', $order_id, $user_id);
                    $infoStmt->execute();
                    $infoStmt->bind_result($transaction_reference, $current_status, $amount, $currency, $seller_user_id);
                    $found = $infoStmt->fetch();
                    $infoStmt->close();

                    $seller_phone = '';
                    $seller_name = '';
                    $seller_provider_db = '';

                    if ($found) {
                        $possiblePhone = [ 'phone_number', 'phone', 'mobile', 'telephone', 'msisdn' ];
                        $possibleName = [ 'full_name', 'fullname', 'name', 'fullName', 'first_name', 'last_name', 'username', 'nom' ];
                        $possibleProvider = [ 'provider', 'payment_provider', 'mobile_provider' ];
                        $all = array_unique(array_merge($possiblePhone, $possibleName, $possibleProvider));
                        $inList = "'" . implode("','", $all) . "'";
                        $colQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME IN ($inList)";
                        $colRes = $conn->query($colQuery);
                        $existing = [];
                        if ($colRes) {
                            while ($r = $colRes->fetch_assoc()) { $existing[] = $r['COLUMN_NAME']; }
                        }

                        $phoneCol = null; $nameCol = null; $providerCol = null;
                        foreach ($possiblePhone as $c) { if (in_array($c, $existing)) { $phoneCol = $c; break; } }
                        foreach ($possibleName as $c) { if (in_array($c, $existing)) { $nameCol = $c; break; } }
                        foreach ($possibleProvider as $c) { if (in_array($c, $existing)) { $providerCol = $c; break; } }

                        $colsToSelect = [];
                        if ($nameCol) $colsToSelect[] = $nameCol;
                        if ($phoneCol) $colsToSelect[] = $phoneCol;
                        if ($providerCol) $colsToSelect[] = $providerCol;
                        if (count($colsToSelect) > 0) {
                            $colList = implode(',', $colsToSelect);
                            $safeUserId = intval($seller_user_id);
                            $rowRes = $conn->query("SELECT $colList FROM users WHERE id = $safeUserId LIMIT 1");
                            if ($rowRes && $row = $rowRes->fetch_assoc()) {
                                if ($nameCol && isset($row[$nameCol])) $seller_name = $row[$nameCol];
                                if ($phoneCol && isset($row[$phoneCol])) $seller_phone = $row[$phoneCol];
                                if ($providerCol && isset($row[$providerCol])) $seller_provider_db = $row[$providerCol];
                            }
                        }
                    }

                    if (!$found) {
                        $otp_error = "Impossible de retrouver les détails de la commande pour traitement.";
                    } elseif ($current_status === 'delivered') {
                        $otp_error = "Cette commande a déjà été validée et payée.";
                    } else {
                        // Détection du provider Mobile Money local (RDC)
                        function detectProviderFromPhoneLocal($phone) {
                            $digits = preg_replace('/\D/', '', $phone);
                            if (strpos($digits, '243') === 0) { $local = substr($digits, 3); }
                            elseif (strpos($digits, '0') === 0) { $local = substr($digits, 1); }
                            else { $local = $digits; }
                            if (strlen($local) < 2) return null;
                            $prefix = substr($local, 0, 2);
                            $map = [
                                '81' => 'VODACOM', '82' => 'VODACOM', '83' => 'VODACOM',
                                '97' => 'AIRTEL', '98' => 'AIRTEL', '99' => 'AIRTEL',
                                '84' => 'ORANGE', '85' => 'ORANGE', '89' => 'ORANGE', '80' => 'ORANGE'
                            ];
                            return $map[$prefix] ?? null;
                        }

                        $provider = detectProviderFromPhoneLocal($seller_phone) ?? strtoupper(trim((string)$seller_provider_db)) ?: 'AIRTEL';

                        $cleanPhone = preg_replace('/\D/', '', $seller_phone);
                        if (strpos($cleanPhone, '243') === 0) { $walletID = '+' . $cleanPhone; }
                        elseif (strpos($cleanPhone, '0') === 0) { $walletID = '+243' . substr($cleanPhone, 1); }
                        else { $walletID = '+243' . $cleanPhone; }

                        // Application des frais de 12%
                        $original_amount = floatval($amount);
                        $net_amount = round($original_amount * 0.88, 2);
                        $amount = $net_amount;

                        // Payload API
                        $payload = [
                            "transactionReference" => "REL-" . $transaction_reference . "-" . time(),
                            "gatewayMode" => "0",
                            "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
                            "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
                            "order" => [
                                "motif" => "Déblocage fonds Commande #" . $order_id,
                                "amount" => (string)$amount,
                                "currency" => $currency,
                                "customerFullName" => $seller_name,
                                "customerEmailAdress" => ""
                            ],
                            "paymentChannel" => [
                                "provider" => $provider,
                                "walletID" => $walletID,
                                "callbackUrl" => "https://omp.alwaysdata.net/main/php/dashboard.php"
                            ]
                        ];

                        // Curl MaishaPay
                        $ch = curl_init("https://marchand.maishapay.online/api/b2c/store/transfert/mobilemoney");
                        curl_setopt_array($ch, [
                            CURLOPT_POST => true,
                            CURLOPT_POSTFIELDS => json_encode($payload),
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                            CURLOPT_SSL_VERIFYPEER => false
                        ]);
                        $response = curl_exec($ch);
                        $response_data = json_decode($response, true);
                        curl_close($ch);

                        $status_code = $response_data['status'] ?? $response_data['status_code'] ?? 0;
                        if ($status_code == 200 || $status_code == 202) {
                            $update = $conn->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?");
                            $update->bind_param("i", $order_id);
                            if ($update->execute()) {
                                $success_message = "Félicitations ! Le code est correct et les fonds ont été envoyés.";

                                // 1. Gratifier le vendeur (+1 point de crédibilité pour livraison honorée)
                                $conn->query("UPDATE users SET credibility_score = LEAST(100, credibility_score + 1) WHERE id = $user_id");

                                // 2. Notification au fournisseur
                                $seller_msg = "Livraison confirmée pour la commande #$order_id ! Les fonds ($amount $currency) ont été débloqués et transférés vers votre compte.";
                                send_notification($conn, $user_id, "Livraison réussie et paiement débloqué", $seller_msg, "delivery_success", "dashboard.php?tab=completed");

                                // 3. Notification à l'acheteur pour noter le vendeur
                                $buyer_stmt = $conn->prepare("SELECT user_id, customer_name FROM orders WHERE id = ?");
                                $buyer_stmt->bind_param("i", $order_id);
                                $buyer_stmt->execute();
                                $b_res = $buyer_stmt->get_result()->fetch_assoc();
                                $buyer_stmt->close();

                                if ($b_res && !empty($b_res['user_id'])) {
                                    $buyer_uid = intval($b_res['user_id']);
                                    $b_name = $b_res['customer_name'] ?: 'Cher client';
                                    $buyer_msg = "Bonjour $b_name, votre commande #$order_id a été livrée ! Merci de donner votre avis et vos étoiles au vendeur.";
                                    send_notification($conn, $buyer_uid, "Commande livrée - Donnez votre avis", $buyer_msg, "order_delivered", "dashboard.php?tab=purchases&review_order=$order_id");
                                }
                            } else {
                                $otp_error = "Succès API mais impossible de mettre à jour le statut en base.";
                            }
                            $update->close();
                        } else {
                            $api_error = $response_data['message'] ?? "Le service de paiement a refusé la transaction.";
                            $otp_error = "Erreur MaishaPay : " . $api_error;
                        }
                    }
                }
            } else {
                $otp_error = "Le code OTP saisi est incorrect.";
            }
        } else {
            // L'OTP est faux OU ce n'est pas la commande de ce vendeur
            $otp_error = "Le code OTP saisi est incorrect ou vous n'êtes pas autorisé à valider cette commande.";
        }
    } else {
        $otp_error = "ID de commande invalide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification OTP | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
     <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        /* Motif fiduciaire de sécurité Cascade */
        .bg-fiduciaire {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(circle at 50% 50%, transparent 0%, #f8fafc 85%),
                linear-gradient(rgba(217, 70, 239, 0.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(217, 70, 239, 0.015) 1px, transparent 1px);
            background-size: 100% 100%, 16px 16px, 16px 16px;
        }

        /* Suppression native des flèches de sélection sur les inputs numériques */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>
<body class="h-full bg-fiduciaire antialiased text-gray-900 lg:h-screen lg:overflow-hidden flex flex-col justify-center items-center px-4 py-12 sm:px-6 lg:px-8">

    <div class="w-full max-w-[460px] space-y-6 relative z-10">
        
        <div class="text-center space-y-4">
            <a href="index.php" class="inline-flex items-center justify-center gap-2">
                <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-8 w-auto object-contain">
                <span class="text-2xl font-bold tracking-tight text-gray-900">
                    ecascadeur<span class="text-fuchsia-500 font-medium">.com</span>
                </span>
            </a>
            
            <img 
                src="assets\images\cash-out.png" 
                alt="Cashout"
                class="w-20 h-20 mx-auto object-contain"
            />
            
            <h2 class="text-xl font-bold tracking-tight text-gray-900">
                Déblocage des fonds
            </h2>
            <p class="text-xs text-gray-500 max-w-sm mx-auto leading-relaxed">
                Saisissez le jeton d'authentification remis par l'acheteur pour valider la livraison et encaisser le solde de la commande <span class="font-mono font-bold text-gray-800">#<?php echo htmlspecialchars($_GET['id'] ?? '---'); ?></span>.
            </p>
        </div>

        <div class="p-6 sm:p-10">
            
            <form method="POST" id="otp-form" class="space-y-6">
                
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold uppercase text-gray-400 text-center mb-4">
                        Code de validation
                    </label>
                    
                  <div class="flex justify-center gap-2 sm:gap-3 w-full otp-field px-2">
                    
                    <?php for($i=0; $i<6; $i++): ?>
                    
                    <input 
                        type="text"
                        maxlength="1"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        placeholder="-"
                        class="
                            otp-input
                            w-10 h-10
                            sm:w-12 sm:h-12
                            md:w-14 md:h-14
                            
                            text-center
                            text-lg sm:text-xl md:text-2xl
                            
                            font-mono font-bold
                            
                            border border-gray-400
                            bg-white
                            rounded-full
                            
                            focus:outline-none
                            focus:border-fuchsia-500
                            
                            placeholder-slate-300
                            
                            shrink-0
                        "
                        required
                    />
                    
                    <?php endfor; ?>

                </div>
                </div>

                <input type="hidden" name="otp" id="otp-input" />

                <?php if ($otp_error): ?>
                    <div class="rounded-xl bg-red-50 p-3.5 border border-red-100 flex items-center gap-2.5 text-left animate-shake">
                        <i class="bi bi-exclamation-circle text-red-500 text-sm shrink-0"></i>
                        <p class="text-xs text-red-700 font-medium"><?php echo htmlspecialchars($otp_error); ?></p>
                    </div>
                <?php endif; ?>

                <div class="space-y-3 pt-2">
                    <button type="submit" 
                            class="w-full flex justify-center items-center rounded-xl bg-gray-950 hover:bg-fuchsia-600 px-4 py-3.5 text-sm font-semibold text-white shadow-md transition active:scale-[0.99] cursor-pointer">
                        Confirmer la transaction
                    </button>
                    
                    <a href="dashboard.php" 
                       class="w-full flex justify-center items-center rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-3.5 text-sm font-semibold text-gray-600 transition active:scale-[0.99]">
                        Annuler la vérification
                    </a>
                </div>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <span class="inline-flex items-center gap-1.5 text-[10px] text-gray-400 font-medium bg-slate-50 px-3 py-1 rounded-full border border-gray-100">
                    <i class="bi bi-lock-fill text-fuchsia-500"></i> Traitement sécurisé par protocole de séquestre
                </span>
            </div>

        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll(".otp-field input");
        const hiddenInput = document.getElementById("otp-input");
        const form = document.getElementById("otp-form");

        inputs.forEach((input, index) => {
            input.dataset.index = index;
            input.addEventListener("input", handleOtp);
            input.addEventListener("keydown", handleKeyDown);
            input.addEventListener("paste", handleOnPasteOtp);
        });

        function handleOtp(e) {
            const input = e.target;
            let value = input.value;
            
            if (value.length > 1) {
                value = value.charAt(value.length - 1);
                input.value = value;
            }

            const fieldIndex = parseInt(input.dataset.index);

            if (value && fieldIndex < inputs.length - 1) {
                inputs[fieldIndex + 1].focus();
            }

            updateHiddenInputAndCheck();
        }

        function handleKeyDown(e) {
            const fieldIndex = parseInt(e.target.dataset.index);
            
            if (e.key === "Backspace" && !e.target.value && fieldIndex > 0) {
                inputs[fieldIndex - 1].focus();
            }
        }

        function handleOnPasteOtp(e) {
            e.preventDefault();
            const data = e.clipboardData.getData("text").trim();
            if (data.length === inputs.length && /^\d+$/.test(data)) {
                inputs.forEach((input, index) => {
                    input.value = data[index];
                });
                updateHiddenInputAndCheck();
            }
        }

        function updateHiddenInputAndCheck() {
            const code = Array.from(inputs).map(input => input.value).join("");
            hiddenInput.value = code;

            // EXCELLENCE UX : Si le code est complet (6 chiffres), soumission automatique du formulaire
            if (code.length === inputs.length) {
                form.submit();
            }
        }
    </script>
</body>

</html>