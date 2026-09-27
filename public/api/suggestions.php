<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/db.php';
$mysqli = $conn;
if ($mysqli->connect_error) {
    die('Erreur de connexion (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

header('Content-Type: application/json');


$query = isset($_GET['query']) ? $mysqli->real_escape_string($_GET['query']) : '';

if (!empty($query)) {
    $sql = "SELECT name FROM products WHERE name LIKE ? LIMIT 10";
    $stmt = $mysqli->prepare($sql);
    $likeQuery = "%$query%";
    $stmt->bind_param("s", $likeQuery);
    $stmt->execute();
    $result = $stmt->get_result();

    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['name'];
    }

    $stmt->close();
    echo json_encode($suggestions);
} else {
    echo json_encode([]);
}

$mysqli->close();
?>