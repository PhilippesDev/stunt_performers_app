<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$query = $conn->prepare("SELECT * FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$query->close();

$sales_query = $conn->prepare("SELECT SUM(total_amount) AS total_sales FROM orders WHERE seller_id = ? ");
$sales_query->bind_param("i", $user_id);
$sales_query->execute();
$sales_result = $sales_query->get_result();
$total_sales = $sales_result->fetch_assoc()['total_sales'] ?? 0;
$sales_query->close();

$purchases_query = $conn->prepare("SELECT SUM(total_amount) AS total_purchases FROM orders WHERE user_id = ? ");
$purchases_query->bind_param("i", $user_id);
$purchases_query->execute();
$purchases_result = $purchases_query->get_result();
$total_purchases = $purchases_result->fetch_assoc()['total_purchases'] ?? 0;
$purchases_query->close();

$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$sales_data = [];
$purchases_data = [];

if ($period == 'month') {
    $sales_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS total FROM orders WHERE seller_id = ?  GROUP BY month");
    $purchases_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS total FROM orders WHERE user_id = ?  GROUP BY month");
} else {
    $sales_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS day, SUM(total_amount) AS total FROM orders WHERE seller_id = ? GROUP BY day");
    $purchases_data_query = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS day, SUM(total_amount) AS total FROM orders WHERE user_id = ?  GROUP BY day");
}

$sales_data_query->bind_param("i", $user_id);
$sales_data_query->execute();
$sales_data_result = $sales_data_query->get_result();
while ($row = $sales_data_result->fetch_assoc()) {
    $sales_data[] = $row;
}
$sales_data_query->close();

$purchases_data_query->bind_param("i", $user_id);
$purchases_data_query->execute();
$purchases_data_result = $purchases_data_query->get_result();
while ($row = $purchases_data_result->fetch_assoc()) {
    $purchases_data[] = $row;
}
$purchases_data_query->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance</title>
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Raleway:300,300i,400,400i,500,500i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Concert+One&family=Nunito:ital,wght@0,200..1000;1,200..1000&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .card {
            margin-bottom: 20px;
        }
        .card-text{
            font-size: 2em;
            font-weight: bold;
        }
        .achat{
            padding: 10px 15px;
            background-color: #fe5a2d3f;
            border: 1px solid #fe5c2d;
            color: #fe5c2d;
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
        }
        .vente{
            padding: 10px 20px;
            background-color: rgba(0, 161, 0, 0.204);
            border: 1px solid rgb(0, 161, 0);
            color: rgb(0, 161, 0);
            border-radius: 10px;
            font-weight: bold;
            text-align: center;
        }
        .chart-container {
            margin-bottom: 30px;
        }
        .btndetail{
            background-color: #fe5c2d;
            color: white;
        }
        .title{
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 10px;
                }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center title">Mes Transactions</h1>

    <!-- Cartes dynamiques -->
    <div class="row">
        <div class="col-md-6">
            <div class="card vente">
                <div class="card-body">
                    <h5 class="card-title">Total des Ventes</h5>
                    <p class="card-text"><?php echo number_format($total_sales, 2); ?> $</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card achat">
                <div class="card-body">
                    <h5 class="card-title">Total des Achats</h5>
                    <p class="card-text"><?php echo number_format($total_purchases, 2); ?> $</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de sélection de période -->
    <form method="GET" action="performance.php" class="mb-4">
        <label for="period">Période :</label>
        <select name="period" id="period" class="form-select">
            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Mensuel</option>
            <option value="day" <?php echo $period == 'day' ? 'selected' : ''; ?>>Quotidien</option>
        </select>
        <button type="submit" class="btn mt-2 btndetail">Appliquer</button>
    </form>

    <!-- Graphiques interactifs -->
    <div class="chart-container">
        <canvas id="salesChart"></canvas>
    </div>
    <div class="chart-container">
        <canvas id="purchasesChart"></canvas>
    </div>
</div>

<script>
    const salesData = <?php echo json_encode($sales_data); ?>;
    const purchasesData = <?php echo json_encode($purchases_data); ?>;

    const salesLabels = salesData.map(item => item.month || item.day);
    const salesTotals = salesData.map(item => item.total);

    const purchasesLabels = purchasesData.map(item => item.month || item.day);
    const purchasesTotals = purchasesData.map(item => item.total);

    // Configuration pour le graphique des ventes
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesGradient = salesCtx.createLinearGradient(0, 0, 0, 400);
    salesGradient.addColorStop(0, 'rgba(0, 161, 0, 0.4)');
    salesGradient.addColorStop(1, 'rgba(0, 161, 0, 0.05)');

    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: salesLabels,
            datasets: [{
                label: 'Ventes ($)',
                data: salesTotals,
                borderColor: 'rgb(0, 161, 0)',
                backgroundColor: salesGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4, // Courbes fluides
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgb(0, 161, 0)',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 3,
                pointHoverBackgroundColor: 'rgb(0, 161, 0)',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3,
                shadowOffsetX: 3,
                shadowOffsetY: 3,
                shadowBlur: 10,
                shadowColor: 'rgba(0, 161, 0, 0.3)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Évolution des Ventes',
                    font: {
                        size: 18,
                        weight: 'bold',
                        family: 'Poppins'
                    },
                    color: '#333',
                    padding: 20
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Poppins',
                            size: 14
                        },
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 12
                        },
                        color: '#666'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 12
                        },
                        color: '#666',
                        callback: function(value) {
                            return value.toLocaleString() + ' $';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: {
                    hoverRadius: 8
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });

    // Configuration pour le graphique des achats
    const purchasesCtx = document.getElementById('purchasesChart').getContext('2d');
    const purchasesGradient = purchasesCtx.createLinearGradient(0, 0, 0, 400);
    purchasesGradient.addColorStop(0, 'rgba(254, 92, 45, 0.4)');
    purchasesGradient.addColorStop(1, 'rgba(254, 92, 45, 0.05)');

    const purchasesChart = new Chart(purchasesCtx, {
        type: 'line',
        data: {
            labels: purchasesLabels,
            datasets: [{
                label: 'Achats ($)',
                data: purchasesTotals,
                borderColor: '#fe5c2d',
                backgroundColor: purchasesGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4, // Courbes fluides
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: '#fe5c2d',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 3,
                pointHoverBackgroundColor: '#fe5c2d',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3,
                shadowOffsetX: 3,
                shadowOffsetY: 3,
                shadowBlur: 10,
                shadowColor: 'rgba(254, 92, 45, 0.3)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Évolution des Achats',
                    font: {
                        size: 18,
                        weight: 'bold',
                        family: 'Poppins'
                    },
                    color: '#333',
                    padding: 20
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            family: 'Poppins',
                            size: 14
                        },
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 12
                        },
                        color: '#666'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)',
                        lineWidth: 1
                    },
                    ticks: {
                        font: {
                            family: 'Poppins',
                            size: 12
                        },
                        color: '#666',
                        callback: function(value) {
                            return value.toLocaleString() + ' $';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: {
                    hoverRadius: 8
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
</script>
</body>
</html>