<?php
require_once __DIR__ . '/db.php';


session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    die("Utilisateur non connecté. Veuillez vous connecter.");
}
$user_id = $_SESSION['user_id'];

// Requêtes  pour les annulations
$sql_client_cancellations = "SELECT reason, created_at FROM order_cancellation_reasons WHERE user_id = ? ORDER BY created_at DESC";
$stmt_client = $conn->prepare($sql_client_cancellations);
$stmt_client->bind_param("i", $user_id);
$stmt_client->execute();
$result_client_cancellations = $stmt_client->get_result();

$sql_vendor_cancellations = "SELECT cancel_reason AS reason, created_at FROM order_cancellations WHERE user_id = ? ORDER BY created_at DESC";
$stmt_vendor = $conn->prepare($sql_vendor_cancellations);
$stmt_vendor->bind_param("i", $user_id);
$stmt_vendor->execute();
$result_vendor_cancellations = $stmt_vendor->get_result();

$sql_purchases = "SELECT id, total_amount, status, created_at 
                 FROM orders 
                 WHERE user_id = ? AND status = 'paid'
                 ORDER BY created_at DESC";
$stmt_purchases = $conn->prepare($sql_purchases);
$stmt_purchases->bind_param("i", $user_id);
$stmt_purchases->execute();
$result_purchases = $stmt_purchases->get_result();


$sql_sales = "SELECT id, total_amount, status, created_at 
              FROM orders 
              WHERE user_id = ? AND status = 'paid'
              ORDER BY created_at DESC";
$stmt_sales = $conn->prepare($sql_sales);
$stmt_sales->bind_param("i", $user_id);
$stmt_sales->execute();
$result_sales = $stmt_sales->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700|Raleway:300,400,500,600,700" rel="stylesheet">
    <title>Cancelation Page</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        header {
            background-color: #333;
            color: white;
            padding: 20px 0;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            font-family: 'Raleway', sans-serif;
        }
        main {
            padding: 20px;
        }
        h3 {
            color: #fe5c2d;
            padding-bottom: 5px;
            margin-bottom: 20px;
            font-family: 'Raleway', sans-serif;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #333;
            color: white;
            text-transform: uppercase;
            font-family: 'Raleway', sans-serif;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .no-data {
            text-align: center;
            font-style: italic;
            color: #999;
        }
        footer {
            text-align: center;
            padding: 10px 0;
            background-color: #333;
            color: white;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        .btn-primary {
            background-color: #fe5c2d;
            border-color: #fe5c2d;
            font-family: 'Poppins', sans-serif;
        }
        .btn-primary:hover {
            background-color: #e64a1e;
            border-color: #e64a1e;
        }
    </style>
</head>
<body>
    <header>
        Historique des Transactions et Annulations
    </header>
    <main>
        <h3>Commandes annulées par les clients</h3>
        <table>
            <thead>
                <tr>
                    <th>Raison</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_client_cancellations->num_rows > 0): ?>
                    <?php while ($row = $result_client_cancellations->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="no-data">Aucune commande annulée par les clients.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <h3>Commandes refusées par les vendeurs</h3>
        <table>
            <thead>
                <tr>
                    <th>Raison</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_vendor_cancellations->num_rows > 0): ?>
                    <?php while ($row = $result_vendor_cancellations->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reason']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="no-data">Aucune commande refusée par les vendeurs.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>
    <div class="text-center">
        <a href="dashboard.php" class="btn btn-primary">Retour</a>
    </div>
</body>
</html>

<?php
// Fermeture de la connexion
$conn->close();
?>