<?php
// success.php
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Succès - ecascadeur.com</title>
    <style>
        /* Style global */
        body {
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #00c6ff, #0072ff);
            color: #fff;
            text-align: center;
            flex-direction: column;
            padding: 20px;
        }

        /* Style du logo */
        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 20px;
            animation: fadeIn 1.5s ease-in-out;
        }

        /* Animation du texte */
        @keyframes fadeIn {
            0% { opacity: 0; transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .message {
            font-size: 24px;
            font-weight: bold;
            animation: slideIn 2s ease-in-out;
        }

        /* Effet typing sur le texte */
        .text-animated {
            display: inline-block;
            overflow: hidden;
            white-space: nowrap;
            letter-spacing: 2px;
            border-right: 2px solid #fff;
            animation: typing 3s steps(30, end) 1s forwards, blink 0.75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }

        @keyframes blink {
            50% { border-color: transparent; }
        }

        /* Bouton de retour */
        .btn {
            margin-top: 20px;
            padding: 12px 25px;
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid #fff;
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            background-color: #fff;
            color: #0072ff;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .message {
                font-size: 20px;
            }

            .btn {
                font-size: 14px;
                padding: 10px 20px;
            }
        }
    </style>
</head>
<body>

    <img src="ecascadeur.png" alt="Logo ecascadeur.com" class="logo">

    <div class="message">
        <span class="text-animated">ecascadeur.com vous remercie pour votre confiance</span>
    </div>

    <a href="index.php" class="btn">Retour à l'accueil</a>

    <script>
        // Redirection automatique après 5 secondes
        setTimeout(function() {
            window.location.href = "index.php";
        }, 10000);
    </script>

</body>
</html>
