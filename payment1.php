
<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="../img/apple-touch-icon.png" rel="apple-touch-icon">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
    <script>
        function setPaymentType(type) {
            document.getElementById('payment_type').value = type;
            
            if (type === 'card') {
                document.getElementById('card-fields').classList.remove('hidden');
                document.getElementById('card_payment').setAttribute('required', 'required');
                document.getElementById('mobile_payment').removeAttribute('required');

                if (document.getElementById('card_payment').value === '') {
                    document.getElementById('card_payment').focus();
                }
            } else {
                document.getElementById('card-fields').classList.add('hidden');
                document.getElementById('mobile_payment').setAttribute('required', 'required');
                document.getElementById('card_payment').removeAttribute('required');
             
                document.getElementById('card_number').value = '';
                document.getElementById('card_holder').value = '';
                document.getElementById('expiry').value = '';
                document.getElementById('cvv').value = '';
            }
        }

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

        function formatExpiry(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.slice(0, 2) + '/' + value.slice(2, 4);
            }
            input.value = value;
        }

        document.querySelector('form').addEventListener('submit', function(e) {
            const paymentType = document.getElementById('payment_type').value;
            
            if (paymentType === 'card') {
                const cardType = document.getElementById('card_payment').value;
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                const cardHolder = document.getElementById('card_holder').value;
                const expiry = document.getElementById('expiry').value;
                const cvv = document.getElementById('cvv').value;

                if (!cardType) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un type de carte');
                    return false;
                }

                if (!cardNumber || cardNumber.length < 13) {
                    e.preventDefault();
                    alert('Veuillez entrer un numéro de carte valide');
                    document.getElementById('card_number').focus();
                    return false;
                }

                if (!cardHolder || cardHolder.trim().length < 3) {
                    e.preventDefault();
                    alert('Veuillez entrer le nom du titulaire');
                    document.getElementById('card_holder').focus();
                    return false;
                }

                if (!expiry || expiry.length !== 5) {
                    e.preventDefault();
                    alert('Veuillez entrer une date d\'expiration valide (MM/AA)');
                    document.getElementById('expiry').focus();
                    return false;
                }

                if (!cvv || cvv.length < 3) {
                    e.preventDefault();
                    alert('Veuillez entrer un CVV valide');
                    document.getElementById('cvv').focus();
                    return false;
                }

                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'payment_method';
                hiddenInput.value = cardType;
                this.appendChild(hiddenInput);
            }
        });
    </script>
</head>
<body class="h-full">
<div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-left items-center gap-2 mb-8">
            <a href="index.php" class="flex items-center gap-2">
                <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="h-9 w-auto object-contain">
                <span class="text-2xl font-bold tracking-tight text-gray-900">ecascadeur<span class="text-fuchsia-500">.com</span></span>
            </a>
        </div>
        <h2 class="mt-6 text-center text-3xl font-bold tracking-tight text-gray-900">Procéder au paiement</h2>
        <p class="mt-2 text-center text-sm text-gray-600">
            Remplissez vos informations pour finaliser la commande.
        </p>
    </div>
    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-[440px]">
        <div class="bg-white py-10 px-8 shadow-xl shadow-gray-200/50 sm:rounded-3xl border border-gray-100">
            <?php if (!empty($error_message)): ?>
                <div class="mb-6 p-4 bg-fuchsia-50 border-l-4 border-fuchsia-500 text-fuchsia-800 flex items-center gap-3 rounded-xl animate-pulse">
                    <svg class="w-5 h-5 text-fuchsia-500" fill="currentColor" viewBox="0 0 20 20"><path d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"></path></svg>
                    <span class="text-sm font-medium"><?= htmlspecialchars($error_message) ?></span>
                </div>
            <?php endif; ?>
            <form class="space-y-6" action="payment.php" method="POST">
                <div>
                    <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Nom complet</label>
                    <input id="name" name="name" type="text" autocomplete="name" required pattern="[a-zA-Z\s]+" title="Le nom complet doit contenir uniquement des lettres et des espaces."
                        class="block w-full rounded-2xl border-0 py-4 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none">
                </div>
                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Adresse e-mail</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </div>
                        <input id="email" name="email" type="email" autocomplete="email" required
                            class="block w-full rounded-2xl border-0 py-4 pl-12 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none"
                            placeholder="exemple@email.com">
                    </div>
                </div>
                <div>
                    <label for="phone2" class="block text-sm font-semibold text-gray-700 mb-2">Numéro de téléphone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 font-medium">CD</span>
                        <input id="phone2" name="phone2" type="text" required pattern="[0-9]{10,15}" title="Le numéro de téléphone doit contenir entre 10 et 15 chiffres."
                            class="block w-full rounded-2xl border-0 py-4 pl-12 pr-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none">
                    </div>
                </div>
                <div>
                    <label for="payment_method" class="block text-sm font-semibold text-gray-700 mb-3">Mode de paiement</label>
                    <div class="space-y-3">
                        <!-- Paiement Mobile Money -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2 block">💬 Paiement Mobile</label>
                            <div class="relative">
                                <select id="mobile_payment" name="payment_method" onchange="setPaymentType('mobile')"
                                    class="block w-full rounded-2xl border-0 py-4 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none appearance-none">
                                    <option value="MPESA" style="color:#db0000; font-weight:bold">🇰🇪 M-pesa</option>
                                    <option value="ORANGE" style="color:#FF6600; font-weight:bold">🟠 Orange Money</option>
                                    <option value="AIRTEL" style="color:#E20612; font-weight:bold">🔴 Airtel Money</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Paiement par Carte Bancaire -->
                        <div>
                            <label class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2 block">💳 Paiement par Carte</label>
                            <div class="relative">
                                <select id="card_payment" name="card_type" onchange="setPaymentType('card')"
                                    class="block w-full rounded-2xl border-0 py-4 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none appearance-none">
                                    <option value="">Sélectionnez une carte...</option>
                                    <option value="VISA" style="color:#1A1F71; font-weight:bold">🔵 Visa</option>
                                    <option value="MASTERCARD" style="color:#EB001B; font-weight:bold">🔴 Mastercard</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Champs additionnels pour carte bancaire (cachés par défaut) -->
                    <div id="card-fields" class="hidden mt-4 space-y-3">
                        <div>
                            <label for="card_number" class="block text-sm font-semibold text-gray-700 mb-2">Numéro de carte</label>
                            <input id="card_number" name="card_number" type="text" inputmode="numeric" placeholder="1234 5678 9012 3456" maxlength="19"
                                class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none"
                                onkeyup="formatCardNumber(this)">
                        </div>
                        <div>
                            <label for="card_holder" class="block text-sm font-semibold text-gray-700 mb-2">Titulaire de la carte</label>
                            <input id="card_holder" name="card_holder" type="text" placeholder="NOM" 
                                class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none uppercase">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="expiry" class="block text-sm font-semibold text-gray-700 mb-2">Expiration</label>
                                <input id="expiry" name="expiry" type="text" placeholder="MM/AA" maxlength="5"
                                    class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none"
                                    onkeyup="formatExpiry(this)">
                            </div>
                            <div>
                                <label for="cvv" class="block text-sm font-semibold text-gray-700 mb-2">CVV</label>
                                <input id="cvv" name="cvv" type="password" placeholder="•••" inputmode="numeric" maxlength="4"
                                    class="block w-full rounded-2xl border-0 py-3 px-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-1 focus:ring-fuchsia-600 sm:text-sm transition-all outline-none">
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="payment_type" id="payment_type" value="mobile">
                </div>
                <div class="flex justify-center">
                    <img src="../img/transfer.png" alt="Paiement mobile" class="w-40 opacity-80">
                </div>
                <div>
                    <button type="submit"
                        class="flex w-full justify-center items-center rounded-2xl bg-gradient-to-r from-gray-900 to-gray-800 hover:from-fuchsia-600 hover:to-fuchsia-500 px-4 py-4 text-sm font-bold text-white shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-fuchsia-600 transition-all active:scale-[0.98]">
                        Payer <?= number_format($temp_order['total_amount'] ?? 0.00, 2); ?> CDF
                    </button>
                </div>
                <p class="text-center text-xs sm:text-sm text-gray-500">💳 Paiement sécurisé - Après confirmation, votre facture sera générée automatiquement.</p>
            </form>
            <div class="mt-8">
                <a href="index.php" class="flex items-center justify-center gap-2 text-sm font-semibold text-fuchsia-600 hover:text-fuchsia-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>