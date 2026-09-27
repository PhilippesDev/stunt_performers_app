<?php
include __DIR__ . '/../libs/db.php';
require_once 'libs/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   1. RÉCUPÉRATION COMMANDE
========================= */
$order_id  = $_SESSION['order_id']  ?? null;
$seller_id = $_SESSION['seller_id'] ?? null;

if ((!$order_id || !$seller_id) && isset($_GET['order_id'])) {
    $order_id = (int) $_GET['order_id'];
    $_SESSION['order_id'] = $order_id;
    if (isset($_GET['seller_id'])) {
        $seller_id = (int) $_GET['seller_id'];
        $_SESSION['seller_id'] = $seller_id;
    }
}


if (!$order_id || !$seller_id) die("Erreur : ID de commande ou vendeur manquant.");

/* =========================
   2. RECHERCHE COMMANDE DANS TEMP_ORDERS
========================= */
$stmt = $conn->prepare("SELECT * FROM temp_orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$temp_order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$temp_order) die("Commande introuvable.");

// Récupération de la devise du produit lié à cette commande
try {
    $product_currency = 'CDF'; // valeur par défaut
    if (!empty($temp_order['product_id'])) {
        $stmtC = $conn->prepare("SELECT currency FROM products WHERE id = ? LIMIT 1");
        $stmtC->bind_param("i", $temp_order['product_id']);
        $stmtC->execute();
        $resC = $stmtC->get_result()->fetch_assoc();
        if ($resC && !empty($resC['currency'])) {
            $product_currency = strtoupper(trim($resC['currency']));
        }
        $stmtC->close();
    }
} catch (Exception $e) {
    // en cas d'erreur, on conserve la valeur par défaut
    $product_currency = 'CDF';
}

/* =========================
   3. TRAITEMENT PAIEMENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone2'] ?? '');
        $provider = strtoupper(trim($_POST['payment_method'] ?? ''));
        $currency = strtoupper(trim($_POST['currency'] ?? 'USD')); 
        
        $gatewayMode = 0; // 0 pour TEST, 1 pour PROD

        if (!$name || !$email || !$phone || !$provider) throw new Exception("Tous les champs sont obligatoires.");

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $baseNumber = (substr($cleanPhone, 0, 3) === '243') ? $cleanPhone : 
                      ((substr($cleanPhone, 0, 1) === '0') ? '243' . substr($cleanPhone, 1) : '243' . $cleanPhone);
        $wallet = '+' . $baseNumber;

        $payload = [
            "gatewayMode" => $gatewayMode, 
            "publicApiKey" => env_value('MAISHAPAY_PUBLIC_KEY'),
            "secretApiKey" => env_value('MAISHAPAY_SECRET_KEY'),
            "transactionReference" => uniqid('order_', true),
            "amount" => (float)$temp_order['total_amount'],
            "currency" => $currency,
            "customerFullName" => $name,
            "customerPhoneNumber" => $wallet,
            "customerEmailAddress" => $email,
            "chanel" => "MOBILEMONEY",
            "provider" => $provider,
            "walletID" => $wallet
        ];

        $ch = curl_init("https://marchand.maishapay.online/api/payment/rest/vers1.0/merchant");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode($response, true);
        $apiData = $decoded['data'] ?? null;
        
        if (!$apiData) throw new Exception($decoded['description'] ?? "Erreur API MaishaPay.");

        $statusCode = (int) ($apiData['statusCode'] ?? 0);
        $statusDesc = strtoupper($apiData['statusDescription'] ?? '');
        $transaction_id = $apiData['transactionId'] ?? time();

        // --- LOGIQUE DE RÉUSSITE ---
        if (($statusCode === 200 || $statusDesc === 'SUCCESS') || ($gatewayMode === 0 && ($statusCode === 202 || $statusDesc === 'ACCEPTED'))) {
            
            // On déplace vers la table 'orders' finale
            // On envoie aussi le channel/payment provider pour être enregistré en base
            migrerCommande($conn, $order_id, $transaction_id, 'completed', $provider);
            
            // ON APPELLE LA NOUVELLE FONCTION ICI
            $pdfFileName = genererFactureProfessionnelle($conn, $temp_order, $transaction_id, $currency);
            
            echo <<<HTML
                <script src="https://cdn.tailwindcss.com"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <div class="min-h-full h-screen bg-slate-50 flex flex-col lg:flex-row antialiased text-gray-900 lg:overflow-hidden">

            <div class="w-full lg:w-5/12 bg-white p-8 lg:p-16 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-gray-200/80">
                
                <div class="space-y-8 my-auto">
                    <div class="inline-flex p-3 bg-emerald-50 rounded-2xl border border-emerald-100 text-emerald-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900">Paiement réussi</h2>
                        <p class="text-sm text-gray-500">Votre transaction a été traitée et validée avec succès par notre passerelle de paiement.</p>
                    </div>

                    <div class="p-4 bg-gray-50 border border-gray-100 rounded-xl space-y-2 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-medium uppercase tracking-wider">Référence</span>
                            <span class="font-mono font-bold text-gray-900">{$transaction_id}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-medium uppercase tracking-wider">Statut</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 uppercase tracking-wide">Approuvé</span>
                        </div>
                    </div>

                    <div class="space-y-3 pt-4">
                        <a href="dashboard.php?tab=purchases" 
                            class="w-full flex justify-center items-center rounded-xl bg-gray-950 hover:bg-gray-900 px-4 py-3.5 text-sm font-semibold text-white shadow-md transition active:scale-[0.99]">
                            Accéder au tableau de bord
                        </a>

                        <a href="{$pdfFileName}" download
                            class="w-full flex justify-center items-center gap-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 px-4 py-3.5 text-sm font-semibold text-gray-700 shadow-sm transition active:scale-[0.99]">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Télécharger la facture PDF
                        </a>
                    </div>
                </div>

                <div class="text-[11px] text-gray-400 pt-6 border-t border-gray-100 lg:mt-0">
                    Une copie de ce reçu a également été transmise à votre adresse e-mail de facturation.
                </div>
            </div>

            <div class="w-full lg:w-7/12 p-4 lg:p-8 bg-gray-100/70 flex flex-col justify-center h-full">
                <div class="w-full h-full max-h-[85vh] lg:max-h-full flex flex-col">
                    <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 lg:hidden">Aperçu du document</span>
                    <div class="w-full h-full rounded-2xl overflow-hidden border border-gray-200/80 shadow-lg bg-white">
                        <iframe src="{$pdfFileName}" class="w-full h-full border-0" style="min-height: 500px;"></iframe>
                    </div>
                </div>
            </div>

            </div>
            HTML;
            exit;
        }

        // --- CAS PENDING PIN ---
        if ($statusCode === 202 || $statusDesc === 'ACCEPTED' || $statusDesc === 'PENDING') {
            migrerCommande($conn, $order_id, $transaction_id, 'pending_pin', $provider);
            echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
                    <h2 style='color:#e68a00;'>Action Requise</h2>
                    <p>Veuillez confirmer le paiement sur votre téléphone (Code PIN).</p>
                    <a href='index.php'>Retour</a>
                  </div>";
            exit;
        }

        throw new Exception($apiData['description'] ?? "Transaction refusée.");

    } catch (Exception $e) {
        echo "<div style='color:red; padding:20px;'><strong>Erreur :</strong> " . $e->getMessage() . "</div>";
        exit;
    }
}

//migration

function migrerCommande($conn, $order_id, $trans_id, $status, $channel = null) {
    $conn->begin_transaction();
    try {
        // S'assurer que la colonne `channel` existe dans la table orders (création si nécessaire)
        $colRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'channel'");
        if ($colRes && $colRes->num_rows === 0) {
            // Tentative de création (silencieuse si échoue)
            $conn->query("ALTER TABLE orders ADD COLUMN `channel` VARCHAR(50) DEFAULT NULL");
        }
        // 1. Génération d'un OTP unique de 6 chiffres
        $otpUnique = false;
        $otpCode = "";

        while (!$otpUnique) {
            $otpCode = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            // On vérifie si cet OTP existe déjà dans la table orders
            $checkOtp = $conn->prepare("SELECT id FROM orders WHERE otpvalidated = ?");
            $checkOtp->bind_param("s", $otpCode);
            $checkOtp->execute();
            if ($checkOtp->get_result()->num_rows === 0) {
                $otpUnique = true;
            }
        }

        // 2. Récupération des données de temp_orders
        $sqlTemp = "SELECT * FROM temp_orders WHERE id = ?";
        $stmtTemp = $conn->prepare($sqlTemp);
        $stmtTemp->bind_param("i", $order_id);
        $stmtTemp->execute();
        $tempData = $stmtTemp->get_result()->fetch_assoc();

        if (!$tempData) throw new Exception("Commande temporaire introuvable.");

                        // 3. Insertion dans 'orders' avec l'OTP généré et le channel (provider)
                        // Le statut inséré doit toujours être 'pending' lors de la migration
                        $insertStatus = 'pending';
                $sqlInsert = "INSERT INTO orders (
                    temp_order_id,
                    user_id, 
                    product_id, 
                    seller_id, 
                    customer_name, 
                    customer_phone, 
                    customer_address, 
                    total_amount, 
                    quantity, 
                    status, 
                    transaction_id, 
                    channel, 
                    otpvalidated
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                    $stmtInsert = $conn->prepare($sqlInsert);

                    // On ajoute temp_order_id au début
                    $stmtInsert->bind_param(
                    "iiiisssdissss",

                    $order_id, // temp_order_id

                    $tempData['user_id'], 
                    $tempData['product_id'], 
                    $tempData['seller_id'], 
                    $tempData['customer_name'], 
                    $tempData['customer_phone'], 
                    $tempData['customer_address'], 
                    $tempData['total_amount'], 
                    $tempData['unit_value'], 
                    $insertStatus, 
                    $trans_id,
                    $channel,
                    $otpCode
                    );

        if (!$stmtInsert->execute()) {
            throw new Exception("Erreur d'insertion dans orders : " . $stmtInsert->error);
        }
        $new_order_id = $conn->insert_id;

        // 4. Mis a jour du statut dans temp_orders (optionnel, peut être supprimé si on veut juste supprimer la ligne après)
        $conn->query("UPDATE temp_orders SET status = 'paid' WHERE id = $order_id");
        
        $conn->commit();

        // Notifier le fournisseur (délai 2h avec options Accepter / Refuser)
        require_once __DIR__ . '/helpers/notification_helper.php';
        notify_supplier_new_order($conn, $new_order_id);

        return $otpCode; // On retourne l'OTP pour l'afficher sur la facture si besoin
    } catch (Exception $e) {
        $conn->rollback();
        die("Erreur : " . $e->getMessage());
    }
}

//facture.pdf

function genererFactureProfessionnelle($conn, $order, $transaction_id, $currency) {
    // Récupération du nom du vendeur
    $stmtV = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmtV->bind_param("i", $order['seller_id']);
    $stmtV->execute();
    $vendeur = $stmtV->get_result()->fetch_assoc();
    $nomVendeur = $vendeur['username'] ?? 'Vendeur E-Cascadeur';

    // Récupération du nom du produit
    $stmtP = $conn->prepare("SELECT name, price FROM products WHERE id = ?");
    $stmtP->bind_param("i", $order['product_id']);
    $stmtP->execute();
    $produit = $stmtP->get_result()->fetch_assoc();
    
    $nomProduit = $produit['name'] ?? 'Produit commande';
    $quantite = $order['unit_value'];
    $prixU = $produit['price'] ?? ($order['total_amount'] / $quantite);

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);

    $html = "
    <html>
    <head>
        <style>
            body { font-family: sans-serif; color: #333; margin: 0; padding: 0; }
            .invoice-box { padding: 30px; border: 1px solid #eee; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            .header { border-bottom: 3px solid #007bff; padding-bottom: 10px; margin-bottom: 20px; }
            .logo { font-size: 26px; font-weight: bold; color: #007bff; float: left; }
            .info { float: right; text-align: right; font-size: 12px; }
            .table { width: 100%; border-collapse: collapse; margin-top: 30px; }
            .table th { background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; text-align: left; }
            .table td { padding: 10px; border: 1px solid #dee2e6; }
            .total { font-size: 18px; font-weight: bold; color: #007bff; text-align: right; margin-top: 20px; }
            .mention { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-top: 40px; font-size: 13px; }
            .footer { text-align: center; margin-top: 50px; font-size: 11px; color: #777; }
        </style>
    </head>
    <body>
        <div class='invoice-box'>
            <div class='header'>
                <div class='logo'>E-CASCADEUR.COM</div>
                <div class='info'>
                    <strong>FACTURE #$transaction_id</strong><br>
                    Date : " . date('d/m/Y H:i') . "<br>
                    Vendeur : " . htmlspecialchars($nomVendeur) . "
                </div>
                <div style='clear:both;'></div>
            </div>

            <p><strong>Client :</strong> " . htmlspecialchars($order['customer_name']) . "<br>
            <strong>Téléphone :</strong> " . $order['customer_phone'] . "<br>
            <strong>Adresse :</strong> " . htmlspecialchars($order['customer_address']) . "</p>

            <table class='table'>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Quantité</th>
                        <th>Prix Unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>" . htmlspecialchars($nomProduit) . "</td>
                        <td>$quantite</td>
                        <td>" . number_format($prixU, 2) . " $currency</td>
                        <td>" . number_format($order['total_amount'], 2) . " $currency</td>
                    </tr>
                </tbody>
            </table>

            <div class='total'>NET À PAYER : " . number_format($order['total_amount'], 2) . " $currency</div>

            <div class='mention'>
                <strong>IMPORTANT :</strong> Client, cette commande peut être annulée à tout moment tant que vous n'avez pas encore envoyé au vendeur votre <strong>code de validation</strong> présent dans votre tableau de bord.
            </div>

            <div class='footer'>
                Merci pour la confiance que vous accordez à l'équipe e-cascadeur.com
            </div>
        </div>
    </body>
    </html>";

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $filename = "facture_$transaction_id.pdf";
    file_put_contents(__DIR__ . "/" . $filename, $dompdf->output());
    return $filename;
}
?>





<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <link href="images/img/apple-touch-icon.png" rel="apple-touch-icon">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
 <style>
        /* Motif fiduciaire de sécurité en arrière-plan */
        .bg-fiduciaire {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(circle at 50% 50%, transparent 0%, #f8fafc 80%),
                linear-gradient(rgba(217, 70, 239, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(217, 70, 239, 0.02) 1px, transparent 1px);
            background-size: 100% 100%, 16px 16px, 16px 16px;
        }
        [x-cloak] { display: none !important; }
    </style>
    <script>
        // Gestion des onglets de paiement principaux
        function setPaymentType(type) {
            document.getElementById('payment_type').value = type;
            
            const cardBtn = document.getElementById('tab-btn-card');
            const mobileBtn = document.getElementById('tab-btn-mobile');
            const cardFields = document.getElementById('card-fields');
            const mobileFields = document.getElementById('mobile-fields');

            if (type === 'card') {
                // Classes Actives / Inactives Onglets
                cardBtn.classList.add('border-fuchsia-600', 'text-fuchsia-600', 'bg-fuchsia-50/30');
                cardBtn.classList.remove('border-gray-200', 'text-gray-500');
                mobileBtn.classList.add('border-gray-200', 'text-gray-500');
                mobileBtn.classList.remove('border-fuchsia-600', 'text-fuchsia-600', 'bg-fuchsia-50/30');

                // Toggle Sections
                cardFields.classList.remove('hidden');
                mobileFields.classList.add('hidden');

                document.getElementById('card_payment').setAttribute('required', 'required');
                document.getElementById('mobile_payment').removeAttribute('required');

                if (document.getElementById('card_payment').value === '') {
                    document.getElementById('card_payment').focus();
                }
            } else {
                mobileBtn.classList.add('border-fuchsia-600', 'text-fuchsia-600', 'bg-fuchsia-50/30');
                mobileBtn.classList.remove('border-gray-200', 'text-gray-500');
                cardBtn.classList.add('border-gray-200', 'text-gray-500');
                cardBtn.classList.remove('border-fuchsia-600', 'text-fuchsia-600', 'bg-fuchsia-50/30');

                mobileFields.classList.remove('hidden');
                cardFields.classList.add('hidden');

                document.getElementById('mobile_payment').setAttribute('required', 'required');
                document.getElementById('card_payment').removeAttribute('required');
             
                document.getElementById('card_number').value = '';
                document.getElementById('card_holder').value = '';
                document.getElementById('expiry').value = '';
                document.getElementById('cvv').value = '';
            }
        }

        // Sélection visuelle de l'opérateur mobile (Remplace le select)
        function selectOperator(operatorValue) {
            document.getElementById('mobile_payment').value = operatorValue;
            
            // Réinitialiser les styles de toutes les cartes opérateurs
            document.querySelectorAll('.operator-card').forEach(card => {
                card.classList.remove('border-gray-900', 'ring-1', 'ring-gray-900', 'bg-gray-50/50');
                card.classList.add('border-gray-200');
            });

            // Appliquer le style actif sur la carte sélectionnée
            const activeCard = document.getElementById('operator-' + operatorValue.toLowerCase());
            if(activeCard) {
                activeCard.classList.remove('border-gray-200');
                activeCard.classList.add('border-gray-900', 'ring-1', 'ring-gray-900', 'bg-gray-50/50');
            }
        }

        function formatCardNumber(input) {
            let value = input.value.replace(/\s/g, '');
            let formattedValue = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) { formattedValue += ' '; }
                formattedValue += value[i];
            }
            input.value = formattedValue;
        }

        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) { value = value.slice(0, 2) + '/' + value.slice(2, 4); }
            input.value = value;
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Initialisation par défaut sur Mobile Money
            setPaymentType('mobile');
            selectOperator('MPESA');

            var form = document.querySelector('form');
            if (!form) return;
            form.addEventListener('submit', function(e) {
                const paymentType = document.getElementById('payment_type').value;
                
                if (paymentType === 'card') {
                    const cardType = document.getElementById('card_payment').value;
                    const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                    const cardHolder = document.getElementById('card_holder').value;
                    const expiry = document.getElementById('expiry').value;
                    const cvv = document.getElementById('cvv').value;

                    if (!cardType) { e.preventDefault(); alert('Veuillez sélectionner un type de carte'); return false; }
                    if (!cardNumber || cardNumber.length < 13) { e.preventDefault(); alert('Numéro de carte invalide'); document.getElementById('card_number').focus(); return false; }
                    if (!cardHolder || cardHolder.trim().length < 3) { e.preventDefault(); alert('Nom du titulaire invalide'); document.getElementById('card_holder').focus(); return false; }
                    if (!expiry || expiry.length !== 5) { e.preventDefault(); alert('Date d\'expiration invalide (MM/AA)'); document.getElementById('expiry').focus(); return false; }
                    if (!cvv || cvv.length < 3) { e.preventDefault(); alert('CVV invalide'); document.getElementById('cvv').focus(); return false; }

                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'payment_method';
                    hiddenInput.value = cardType;
                    this.appendChild(hiddenInput);
                }
            });
        });
    </script>
</head>
<body class="min-h-screen bg-fiduciaire antialiased text-gray-900">

<div class="flex flex-col lg:flex-row min-h-screen">
    
    <div class="w-full lg:w-5/12 bg-gray-900 text-white p-8 lg:p-16 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-gray-800">
        <div class="space-y-12">
            <div class="flex items-center gap-2">
                <a href="index.php" class="flex items-center gap-2">
                    <img src="assets/images/ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                    <span class="text-2xl font-bold tracking-tight text-white">ecascadeur<span class="text-fuchsia-500 font-medium">.com</span></span>
                </a>
            </div>

            <div class="space-y-2">
                <span class="text-xs uppercase tracking-widest text-gray-400 font-semibold">Montant total à régler</span>
                <div class="text-4xl lg:text-5xl font-extrabold tracking-tight text-white">
                    <?= number_format($temp_order['total_amount'] ?? 0.00, 2); ?> <span class="text-lg lg:text-xl font-medium text-gray-400"><?= htmlspecialchars($product_currency ?? 'CDF') ?></span>
                </div>
            </div>

            <div class="space-y-6 pt-6 border-t border-gray-800">
                <div class="flex gap-4">
                    <div class="shrink-0 p-2 bg-gray-800/60 rounded-xl border border-gray-700/50 flex items-center justify-center w-10 h-10">
                        <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-200">Cryptage de niveau bancaire</h4>
                        <p class="text-xs text-gray-400 mt-1">Vos données de transaction sont chiffrées de bout en bout via le protocole sécurisé AES-256.</p>
                    </div>
                </div>

                <div class="flex gap-4">
                    <div class="shrink-0 p-2 bg-gray-800/60 rounded-xl border border-gray-700/50 flex items-center justify-center w-10 h-10">
                        <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-gray-200">Traitement instantané</h4>
                        <p class="text-xs text-gray-400 mt-1">Dès confirmation du réseau, la validation est immédiate et votre reçu numérique est généré.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-12 lg:mt-0 pt-6 border-t border-gray-800 flex items-center justify-between text-[11px] text-gray-500">
            <span>ID Transaction : #<?= htmlspecialchars($temp_order['id'] ?? rand(1000,9999)) ?></span>
            <span class="font-medium tracking-wider uppercase">Conforme PCI-DSS</span>
        </div>
    </div>

    <div class="w-full lg:w-7/12 p-6 sm:p-12 lg:p-16 flex flex-col justify-center lg:overflow-y-auto">
        <div class="max-w-xl w-full mx-auto space-y-8">
            
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Finaliser la transaction</h2>
                <p class="text-xs text-gray-400 mt-1">Renseignez vos informations de facturation et sélectionnez votre canal de paiement.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 flex items-center gap-3 rounded-xl">
                    <svg class="w-4 h-4 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path></svg>
                    <span class="text-xs font-semibold"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-5" action="payment.php" method="POST">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nom complet</label>
                        <input id="name" name="name" type="text" autocomplete="name" placeholder="John Doe" required
                            class="block w-full rounded-xl border border-gray-200 bg-white/50 py-3 px-4 text-sm text-gray-950 placeholder:text-gray-400 focus:border-gray-900 focus:bg-white transition outline-none">
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Adresse e-mail</label>
                        <input id="email" name="email" type="email" autocomplete="email" required placeholder="adresse@domaine.com"
                            class="block w-full rounded-xl border border-gray-200 bg-white/50 py-3 px-4 text-sm text-gray-950 placeholder:text-gray-400 focus:border-gray-900 focus:bg-white transition outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label for="phone2" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Numéro de téléphone</label>
                        <div class="relative flex items-center">
                            <span class="absolute left-4 text-xs font-bold text-gray-400 select-none border-r border-gray-200 pr-2.5">+243</span>
                            <input id="phone2" name="phone2" type="tel" required pattern="[0-9]{10,15}" placeholder="0812345678"
                                class="block w-full rounded-xl border border-gray-200 bg-white/50 py-3 pl-14 pr-4 text-sm text-gray-950 placeholder:text-gray-400 focus:border-gray-900 focus:bg-white transition outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Devise</label>
                        <div class="block w-full rounded-xl border border-gray-200 bg-gray-100/60 py-3 px-4 text-sm text-gray-500 font-semibold text-center select-none">
                            <?= htmlspecialchars($product_currency ?? 'CDF') ?>
                        </div>
                        <input type="hidden" name="currency" value="<?= htmlspecialchars($product_currency ?? 'CDF') ?>">
                    </div>
                </div>

                <div class="pt-2">
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Mode de paiement</label>
                    <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-xl">
                        <button type="button" id="tab-btn-mobile" onclick="setPaymentType('mobile')"
                                class="py-2.5 text-xs font-semibold rounded-lg border border-transparent transition text-center focus:outline-none">
                            Paiement Mobile
                        </button>
                        <button type="button" id="tab-btn-card" onclick="setPaymentType('card')"
                                class="py-2.5 text-xs font-semibold rounded-lg border border-transparent transition text-center focus:outline-none">
                            Carte Bancaire
                        </button>
                    </div>
                    <input type="hidden" name="payment_type" id="payment_type" value="mobile">
                </div>

                <div id="mobile-fields" class="space-y-3">
                    <span class="block text-xs font-medium text-gray-400">Sélectionnez votre opérateur réseau :</span>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <div id="operator-mpesa" onclick="selectOperator('MPESA')"
                             class="operator-card border border-gray-200 rounded-xl p-4 flex flex-col items-center justify-center gap-2 cursor-pointer transition hover:bg-gray-50 bg-white">
                            <span class="text-sm font-bold tracking-tight text-[#db0000]">M-Pesa</span>
                            <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tight">Vodacom</span>
                        </div>
                        
                        <div id="operator-orange" onclick="selectOperator('ORANGE')"
                             class="operator-card border border-gray-200 rounded-xl p-4 flex flex-col items-center justify-center gap-2 cursor-pointer transition hover:bg-gray-50 bg-white">
                            <span class="text-sm font-bold tracking-tight text-[#FF6600]">Orange</span>
                            <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tight">Money</span>
                        </div>

                        <div id="operator-airtel" onclick="selectOperator('AIRTEL')"
                             class="operator-card border border-gray-200 rounded-xl p-4 flex flex-col items-center justify-center gap-2 cursor-pointer transition hover:bg-gray-50 bg-white">
                            <span class="text-sm font-bold tracking-tight text-[#E20612]">Airtel</span>
                            <span class="text-[9px] text-gray-400 font-medium uppercase tracking-tight">Money</span>
                        </div>
                    </div>
                    <input type="hidden" id="mobile_payment" name="payment_method" value="MPESA">
                </div>

                <div id="card-fields" class="space-y-4 hidden">
                    <div>
                        <label for="card_payment" class="block text-xs font-medium text-gray-400 mb-1.5">Type de carte</label>
                        <select id="card_payment" class="block w-full rounded-xl border border-gray-200 bg-white py-3 px-4 text-sm text-gray-900 outline-none">
                            <option value="">Sélectionner le réseau</option>
                            <option value="VISA">Visa</option>
                            <option value="MASTERCARD">Mastercard</option>
                        </select>
                    </div>
                    <div>
                        <label for="card_number" class="block text-xs font-medium text-gray-400 mb-1.5">Numéro de carte</label>
                        <input id="card_number" type="text" maxlength="19" oninput="formatCardNumber(this)" placeholder="0000 0000 0000 0000"
                            class="block w-full rounded-xl border border-gray-200 bg-white py-3 px-4 text-sm outline-none">
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <label for="card_holder" class="block text-xs font-medium text-gray-400 mb-1.5">Titulaire de la carte</label>
                            <input id="card_holder" type="text" placeholder="NOM PRENOM" class="block w-full rounded-xl border border-gray-200 bg-white py-3 px-4 text-sm outline-none uppercase">
                        </div>
                        <div>
                            <label for="expiry" class="block text-xs font-medium text-gray-400 mb-1.5">Expiration</label>
                            <input id="expiry" type="text" maxlength="5" oninput="formatExpiry(this)" placeholder="MM/AA" class="block w-full rounded-xl border border-gray-200 bg-white py-3 px-4 text-sm text-center outline-none">
                        </div>
                    </div>
                    <div>
                        <label for="cvv" class="block text-xs font-medium text-gray-400 mb-1.5">Code CVV</label>
                        <input id="cvv" type="password" maxlength="4" placeholder="•••" class="block w-full rounded-xl border border-gray-200 bg-white py-3 px-4 text-sm outline-none">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="w-full flex justify-center items-center rounded-xl bg-gray-950 hover:bg-fuchsia-600 px-4 py-4 text-sm font-semibold text-white shadow-lg transition active:scale-[0.99] cursor-pointer">
                        Confirmer le règlement de <?= number_format($temp_order['total_amount'] ?? 0.00, 2); ?> <?= htmlspecialchars($product_currency ?? 'CDF') ?>
                    </button>
                </div>
            </form>

            <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
                <a href="index.php" class="inline-flex items-center gap-1.5 font-medium text-gray-500 hover:text-gray-900 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Annuler et retourner à l'accueil
                </a>
                
                <div class="flex items-center gap-2 group">
                    <svg class="w-3.5 h-3.5 text-gray-400 group-hover:text-fuchsia-500 transition" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <a href="dashboard.php?tab=pending" class="font-medium text-gray-500 hover:text-gray-900 transition">
                        Mettre la commande en attente pour payer plus tard
                    </a>
                </div>
            </div>

            <?php if (!empty($api_response_html) || !empty($debug_output)): ?>
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-200/60 mt-4">
                    <button type="button" id="toggle-api-response" class="text-xs font-semibold text-gray-600 hover:text-gray-900 underline">Afficher les détails techniques de la passerelle</button>
                    <div id="api-response" class="mt-3 hidden text-xs font-mono text-gray-600 space-y-2">
                        <?= $api_response_html ?? '' ?>
                        <?= $debug_output ?? '' ?>
                    </div>
                </div>
                <script>
                    document.getElementById('toggle-api-response').addEventListener('click', function(){
                        var box = document.getElementById('api-response');
                        if (box.classList.contains('hidden')) {
                            box.classList.remove('hidden');
                            this.textContent = 'Masquer les détails techniques';
                        } else {
                            box.classList.add('hidden');
                            this.textContent = 'Afficher les détails techniques de la passerelle';
                        }
                    });
                </script>
            <?php endif; ?>

        </div>
    </div>
</div>

</body>
</html>
