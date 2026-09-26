<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Succès</title>
    <!-- Lien vers Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Lien vers le fichier CSS (ajoutez votre fichier CSS si nécessaire) -->
    <link rel="stylesheet" href="styles.css">
    <style>
        .container {
            margin-top: 50px;
        }
        .message-box {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
        }
        ._success {
            background-color: #e0ffe0;
            color: #28a745;
        }
        ._failed {
            background-color: #ffe0e0;
            color: #dc3545;
        }
        .message-box i {
            font-size: 50px;
            margin-bottom: 15px;
        }
        h2 {
            margin-bottom: 10px;
        }
        p {
            font-size: 16px;
            line-height: 1.5;
        }
        hr {
            margin: 40px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <?php if (!empty($_SESSION['facture_path'])): ?>
            <div class="col-md-5">
                <div class="message-box _success">
                    <i class="fa fa-check-circle" aria-hidden="true"></i>
                    <h2>Your payment was successful</h2>
                    <p>Thank you for your payment. We will <br> be in contact with more details shortly.</p>
                    <a href="<?= htmlspecialchars($_SESSION['facture_path']); ?>" download="Facture.pdf" class="btn btn-success">Download Invoice</a>
                </div>
            </div>
        <?php else: ?>
            <div class="col-md-5">
                <div class="message-box _failed">
                    <i class="fa fa-times-circle" aria-hidden="true"></i>
                    <h2>Your payment failed</h2>
                    <p>Try again later.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
