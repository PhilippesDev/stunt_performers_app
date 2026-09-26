<?php
session_start();
require_once 'db.php';

// Vérifier que les données de session existent
if (!isset($_SESSION['card_payment_temp'])) {
    die("Session expirée. Veuillez recommencer.");
}

$card_data = $_SESSION['card_payment_temp'];
$temp_order_id = $card_data['temp_order_id'];
$amount = $card_data['amount'];
$buyer_name = $card_data['buyer_name'];
$buyer_phone = $card_data['buyer_phone'];
$card_type = $card_data['card_type'];
$transactionReference = $card_data['transaction_reference'];

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupérer les données de la carte
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $card_holder = trim($_POST['card_holder'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');

        // Validation basique
        if (!preg_match('/^\d{13,19}$/', $card_number)) {
            throw new Exception("Numéro de carte invalide.");
        }
        if (strlen($cvv) < 3 || strlen($cvv) > 4 || !is_numeric($cvv)) {
            throw new Exception("CVV invalide.");
        }
        if (!preg_match('/^\d{2}\/\d{2}$/', $expiry_date)) {
            throw new Exception("Date d'expiration invalide (MM/AA).");
        }

        // Vérifier l'expiration
        list($exp_month, $exp_year) = explode('/', $expiry_date);
        $current_date = new DateTime();
        $card_date = DateTime::createFromFormat('m/y', $exp_month . '/' . $exp_year);
        
        if ($card_date === false || $current_date > $card_date) {
            throw new Exception("La carte est expirée.");
        }

        // Traitement du paiement par carte via API
        // Vous devez intégrer un provider de paiement comme Stripe, PayPal, ou autre service local
        
        // Exemple avec un service de paiement hypothétique
        $payment_url = "https://api.paymentprovider.com/process"; // À remplacer par votre provider
        
        $payment_data = [
            "amount" => $amount,
            "currency" => "CDF",
            "card_number" => $card_number,
            "card_holder" => $card_holder,
            "expiry_date" => $expiry_date,
            "cvv" => $cvv,
            "card_type" => $card_type,
            "transaction_reference" => $transactionReference,
            "description" => "Commande #" . $temp_order_id,
            "customer_name" => $buyer_name,
            "customer_phone" => $buyer_phone
        ];

        // Appel API sécurisé (à adapter selon votre provider)
        $ch = curl_init($payment_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // À activer en production
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("Erreur de connexion au service de paiement: " . $curl_error);
        }

        $response_data = json_decode($response, true);

        // Vérifier la réponse (adapter selon votre provider)
        if (isset($response_data['success']) && $response_data['success']) {
            // Mise à jour de la commande
            $update = $conn->prepare("UPDATE temp_orders SET payment_status=?, transaction_reference=? WHERE id=?");
            $status = 'PAID';
            $update->bind_param("ssi", $status, $transactionReference, $temp_order_id);
            $update->execute();
            $update->close();

            // Nettoyer la session
            unset($_SESSION['card_payment_temp']);

            header("Location: payment_success.php?ref=" . $transactionReference);
            exit;
        } else {
            $error_msg = $response_data['message'] ?? "Erreur lors du traitement du paiement.";
            throw new Exception($error_msg);
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement par Carte | E-commerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card-input { font-family: 'Courier New', monospace; }
        .card-logo { height: 24px; }
        #card-display {
            perspective: 1000px;
            min-height: 200px;
        }
        .card-front {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform-style: preserve-3d;
            transition: transform 0.6s;
        }
        .card-back {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform-style: preserve-3d;
            transition: transform 0.6s;
        }
    </style>
</head>
<body class="h-full">
<div class="flex min-h-full flex-col justify-center py-8 sm:py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-left items-center gap-2 mb-6 sm:mb-8">
            <span class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">E-commerce<span class="text-orange-500">.shop</span></span>
        </div>
        <h2 class="mt-4 sm:mt-6 text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">Paiement par Carte</h2>
        <p class="mt-2 text-center text-xs sm:text-sm text-gray-600">
            Entrez vos informations de carte pour finaliser votre paiement.
        </p>
    </div>
    
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-[440px]">
        <div class="bg-white py-8 sm:py-10 px-6 sm:px-8 shadow-xl shadow-gray-200/50 sm:rounded-3xl border border-gray-100">
            
            <!-- Affichage de la carte -->
            <div id="card-display" class="mb-8 perspective">
                <div class="card-front w-full h-48 rounded-2xl p-6 text-white shadow-xl" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="flex justify-between items-start mb-12">
                        <div>
                            <p class="text-xs opacity-70">Card Number</p>
                            <p class="text-lg font-bold card-input" id="display-card-number">•••• •••• •••• ••••</p>
                        </div>
                        <div id="card-logo-display">
                            <?php if ($card_type === 'VISA'): ?>
                                <img src="data:image/svg+xml,%3Csvg viewBox='0 0 48 32' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='48' height='32' fill='%231A1F71'/%3E%3Ctext x='24' y='22' font-size='14' fill='white' text-anchor='middle' font-weight='bold'%3EVISA%3C/text%3E%3C/svg%3E" class="card-logo">
                            <?php elseif ($card_type === 'MASTERCARD'): ?>
                                <img src="data:image/svg+xml,%3Csvg viewBox='0 0 48 32' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='48' height='32' fill='%23EB001B'/%3E%3Ccircle cx='18' cy='16' r='8' fill='%23FF5F00'/%3E%3Ccircle cx='30' cy='16' r='8' fill='%23FFB81C'/%3E%3C/svg%3E" class="card-logo">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-xs opacity-70">Card Holder</p>
                            <p class="text-sm font-semibold card-input" id="display-card-holder">YOUR NAME</p>
                        </div>
                        <div>
                            <p class="text-xs opacity-70">Valid Thru</p>
                            <p class="text-sm font-semibold card-input" id="display-expiry">MM/YY</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 flex items-center gap-3 rounded-xl">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path></svg>
                    <span class="text-sm font-medium"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form class="space-y-5" method="POST" action="">
                <!-- Type de carte (affiché mais non modifiable) -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Type de Carte</label>
                    <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200 text-gray-700 font-semibold">
                        <?= $card_type === 'VISA' ? '🔵 Visa' : '🔴 Mastercard' ?>
                    </div>
                    <input type="hidden" name="card_type" value="<?= htmlspecialchars($card_type) ?>">
                </div>

                <!-- Numéro de carte -->
                <div>
                    <label for="card_number" class="block text-sm font-semibold text-gray-700 mb-2">Numéro de Carte</label>
                    <input id="card_number" name="card_number" type="text" inputmode="numeric" placeholder="1234 5678 9012 3456" required
                        maxlength="19"
                        class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-orange-600 sm:text-sm transition-all outline-none card-input"
                        onkeyup="formatCardNumber(this); updateCardDisplay()">
                </div>

                <!-- Titulaire de la carte -->
                <div>
                    <label for="card_holder" class="block text-sm font-semibold text-gray-700 mb-2">Titulaire de la Carte</label>
                    <input id="card_holder" name="card_holder" type="text" placeholder="JOHN DOE" required
                        class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-orange-600 sm:text-sm transition-all outline-none uppercase"
                        onkeyup="updateCardDisplay()">
                </div>

                <!-- Date d'expiration et CVV -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="expiry_date" class="block text-sm font-semibold text-gray-700 mb-2">Expiration</label>
                        <input id="expiry_date" name="expiry_date" type="text" placeholder="MM/YY" required
                            maxlength="5"
                            class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-orange-600 sm:text-sm transition-all outline-none card-input"
                            onkeyup="formatExpiry(this); updateCardDisplay()">
                    </div>
                    <div>
                        <label for="cvv" class="block text-sm font-semibold text-gray-700 mb-2">CVV</label>
                        <input id="cvv" name="cvv" type="password" placeholder="•••" required
                            inputmode="numeric" maxlength="4"
                            class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-orange-600 sm:text-sm transition-all outline-none card-input">
                    </div>
                </div>

                <!-- Résumé du montant -->
                <div class="p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Montant à payer</span>
                        <span class="text-xl font-bold text-gray-900"><?= number_format($amount, 2) ?> CDF</span>
                    </div>
                    <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                        <span>Commande #<?= $temp_order_id ?></span>
                        <span><?= date('d/m/Y H:i') ?></span>
                    </div>
                </div>

                <!-- Acceptation des conditions -->
                <div class="flex items-center">
                    <input id="accept_terms" name="accept_terms" type="checkbox" required
                        class="h-4 w-4 rounded border-gray-300 text-orange-600 focus:ring-orange-600">
                    <label for="accept_terms" class="ml-3 text-xs sm:text-sm text-gray-700">
                        J'accepte les <a href="#" class="text-orange-600 hover:text-orange-500 font-semibold">conditions de paiement</a>
                    </label>
                </div>

                <!-- Bouton de paiement -->
                <div>
                    <button type="submit"
                        class="flex w-full justify-center items-center rounded-2xl bg-gray-900 px-4 py-4 text-sm font-bold text-white shadow-lg hover:bg-orange-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-orange-600 transition-all active:scale-[0.98]">
                        💳 Payer <?= number_format($amount, 2) ?> CDF
                    </button>
                </div>

                <p class="text-center text-xs text-gray-500">Vos données de carte sont sécurisées et cryptées.</p>
            </form>

            <!-- Lien retour -->
            <div class="mt-6">
                <a href="payment1.php" class="flex items-center justify-center gap-2 text-sm font-semibold text-orange-600 hover:text-orange-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Retour au paiement
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Formater le numéro de carte
function formatCardNumber(input) {
    let value = input.value.replace(/\s/g, '');
    let formattedValue = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) {
            formattedValue += ' ';
        }
        formattedValue += value[i];
    }
    input.value = formattedValue;
}

// Formater l'expiration
function formatExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.slice(0, 2) + '/' + value.slice(2, 4);
    }
    input.value = value;
}

// Mettre à jour l'affichage de la carte
function updateCardDisplay() {
    const cardNumber = document.getElementById('card_number').value || '•••• •••• •••• ••••';
    const cardHolder = document.getElementById('card_holder').value.toUpperCase() || 'YOUR NAME';
    const expiry = document.getElementById('expiry_date').value || 'MM/YY';

    document.getElementById('display-card-number').textContent = cardNumber || '•••• •••• •••• ••••';
    document.getElementById('display-card-holder').textContent = cardHolder;
    document.getElementById('display-expiry').textContent = expiry;
}

// Validation au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateCardDisplay();
});
</script>
</body>
</html>
