<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : (isset($_POST['order_id']) ? intval($_POST['order_id']) : 0);

if ($order_id === 0) {
    header('Location: dashboard.php');
    exit();
}

$message_status = "";

if (isset($_POST['submit'])) {
    $reason = htmlspecialchars($_POST['reason']);

    $stmt = $conn->prepare("INSERT INTO order_cancellation_reasons (order_id, user_id, reason) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $order_id, $user_id, $reason);
    
    if ($stmt->execute()) {
        header('Location: dashboard.php?cancel=success');
        exit();
    } else {
        $message_status = "Erreur lors de l'enregistrement. Veuillez réessayer.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annulation Commande | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4">

    <div class="max-w-xl w-full">
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-800 transition-colors mb-6 group">
            <div class="p-2 rounded-full bg-white shadow-sm group-hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-arrow-left" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
                </svg>
            </div>
            <span class="font-semibold text-sm">Retour au dashboard</span>
        </a>

        <div class="bg-white rounded-[2.5rem] shadow-xl border border-gray-100 overflow-hidden">
            <div class="p-8 sm:p-12">
                
                <div class="flex items-center gap-4 mb-8">
                    <div class="size-12 bg-red-100 rounded-2xl flex items-center justify-center text-red-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900 leading-tight">Annuler ma commande</h2>
                        <p class="text-gray-400 text-sm italic">Commande #<?= $order_id ?></p>
                    </div>
                </div>

                <?php if ($message_status): ?>
                    <div class="mb-6 p-4 bg-red-50 text-red-600 rounded-2xl text-sm border border-red-100 font-medium">
                        <?= $message_status ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="space-y-6">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    
                    <div>
                        <label for="reason" class="block text-sm font-bold text-gray-700 mb-3">Motif de l'annulation</label>
                        <textarea 
                            id="reason" 
                            name="reason" 
                            rows="4" 
                            required
                            placeholder="Ex: J'ai changé d'avis, erreur de quantité..."
                            class="w-full rounded-3xl border-gray-200 bg-gray-50 p-5 text-gray-900 focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all outline-none resize-none"
                        ></textarea>
                    </div>

                    <div class="bg-amber-50 rounded-3xl p-6 border border-amber-100">
                        <div class="flex gap-3">
                            <svg class="size-6 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <div class="space-y-2">
                                <p class="text-xs font-bold text-amber-800 uppercase tracking-wider">Information importante</p>
                                <p class="text-sm text-amber-700 leading-relaxed">
                                    Veuillez préciser un motif valable. Sans ce formulaire rempli, nous ne pourrons pas procéder au remboursement. 
                                    <span class="font-bold">Délai de traitement :</span> environ 1 heure.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit" class="w-full py-5 bg-gray-900 text-white rounded-3xl font-bold text-lg hover:bg-red-600 transition-all shadow-xl active:scale-95">
                        Confirmer l'annulation
                    </button>
                </form>

            </div>
        </div>
        
        <p class="mt-8 text-center text-gray-400 text-xs">
            Besoin d'aide ? <a href="#" class="text-gray-600 underline font-semibold">Contacter le support</a>
        </p>
    </div>

</body>
</html>