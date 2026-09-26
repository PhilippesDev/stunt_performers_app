<?php
session_start();
require_once 'db.php'; // Assume $conn est un objet MySQLi

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Traitement multipart/form-data (pour fichiers)
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$currency = $_POST['currency'] ?? 'USD';
$condition = $_POST['condition'] ?? 'new';
$unit_type = $_POST['unit_type'] ?? 'pcs';
$quantity = floatval($_POST['quantity'] ?? 0);
$min_order = !empty($_POST['min_order']) ? floatval($_POST['min_order']) : null;
$discount_threshold = !empty($_POST['discount_threshold']) ? floatval($_POST['discount_threshold']) : null;
$discount_percent = intval($_POST['discount_percent'] ?? 0);

$category = json_decode($_POST['category'] ?? '{}', true); // Objet, pas tableau
$regions = json_decode($_POST['regions'] ?? '[]', true);
$defects = ($condition === 'used') ? json_decode($_POST['defects'] ?? '[]', true) : null;
$colors = json_decode($_POST['colors'] ?? '[]', true);
$shoe_sizes = json_decode($_POST['shoe_sizes'] ?? '[]', true);
$child_sizes = json_decode($_POST['child_sizes'] ?? '[]', true);
$adult_sizes = json_decode($_POST['adult_sizes'] ?? '[]', true);
$specifications = json_decode($_POST['specifications'] ?? '[]', true);

// Validation basique
if (empty($name) || empty($description) || $price <= 0 || $quantity <= 0 || empty($regions) || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants ou invalides']);
    exit();
}

if (count($_FILES['media']['name'] ?? []) < 5) {
    echo json_encode(['success' => false, 'message' => 'Minimum 5 médias requis']);
    exit();
}

// Gestion des erreurs PHP (log au lieu d'afficher)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/chemin/vers/error.log'); // Décommentez et adaptez si besoin

// Démarrer transaction MySQLi
mysqli_begin_transaction($conn);

$product_id = null; // Pour rollback si besoin

try {
    // Préparation de l'insertion produit
    $stmt = $conn->prepare("
        INSERT INTO products (
            user_id, name, description, price, currency, product_condition, unit_type,
            quantity, min_order, discount_threshold, discount_percent,
            category, regions, defects, colors, shoe_sizes, child_sizes, adult_sizes, specifications
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ");

    if (!$stmt) {
        throw new Exception('Préparation de la requête échouée : ' . $conn->error);
    }

    // Bind des params (MySQLi : types 'i' int, 'd' double, 's' string, 'b' blob/null)
    // Pour NULL, on utilise un placeholder et bind comme 's' avec null
    $defects_json = $defects ? json_encode($defects, JSON_UNESCAPED_UNICODE) : null;
    $colors_json = $colors ? json_encode($colors, JSON_UNESCAPED_UNICODE) : null;
    $shoe_sizes_json = $shoe_sizes ? json_encode($shoe_sizes, JSON_UNESCAPED_UNICODE) : null;
    $child_sizes_json = $child_sizes ? json_encode($child_sizes, JSON_UNESCAPED_UNICODE) : null;
    $adult_sizes_json = $adult_sizes ? json_encode($adult_sizes, JSON_UNESCAPED_UNICODE) : null;

    $stmt->bind_param(
        'issdsssdddissssssss', // Types : i(user_id), s(name), s(desc), d(price), s(currency), s(condition), s(unit), d(quantity), d(min_order), d(discount_th), i(discount_pc), s(category), s(regions), s(defects), s(colors), s(shoe), s(child), s(adult), s(specs)
        $user_id,
        $name,
        $description,
        $price,
        $currency,
        $condition,
        $unit_type,
        $quantity,
        $min_order,
        $discount_threshold,
        $discount_percent,
        json_encode($category, JSON_UNESCAPED_UNICODE),
        json_encode($regions, JSON_UNESCAPED_UNICODE),
        $defects_json,
        $colors_json,
        $shoe_sizes_json,
        $child_sizes_json,
        $adult_sizes_json,
        json_encode($specifications, JSON_UNESCAPED_UNICODE)
    );

    if (!$stmt->execute()) {
        throw new Exception('Exécution de la requête échouée : ' . $stmt->error);
    }

    $product_id = $conn->insert_id;

    // Dossier d'upload
    $upload_dir = '../uploads/products/' . $product_id . '/';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        throw new Exception('Impossible de créer le dossier d\'upload');
    }

    // Traitement des médias
    $media_files = $_FILES['media'];
    $uploaded_count = 0;

    for ($i = 0; $i < count($media_files['name']); $i++) {
        if ($media_files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $file_name = uniqid('media_') . '_' . basename($media_files['name'][$i]);
        $file_path = $upload_dir . $file_name;
        $relative_path = 'uploads/products/' . $product_id . '/' . $file_name;
        $file_type = strpos($media_files['type'][$i], 'image/') === 0 ? 'image' : 'video';

        if (move_uploaded_file($media_files['tmp_name'][$i], $file_path)) {
            $stmt_media = $conn->prepare("
                INSERT INTO product_media (product_id, file_path, file_type, sort_order)
                VALUES (?, ?, ?, ?)
            ");
            if (!$stmt_media) {
                throw new Exception('Préparation média échouée : ' . $conn->error);
            }
            $stmt_media->bind_param('issi', $product_id, $relative_path, $file_type, $i);
            if (!$stmt_media->execute()) {
                throw new Exception('Insertion média échouée : ' . $stmt_media->error);
            }
            $uploaded_count++;
            $stmt_media->close();
        }
    }

    if ($uploaded_count < 5) {
        throw new Exception('Échec de l\'upload d\'au moins 5 médias');
    }

    // Commit transaction
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté avec succès !',
        'product_id' => $product_id
    ]);

} catch (Exception $e) {
    if ($conn) {
        mysqli_rollback($conn);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement : ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_media)) $stmt_media->close();
}
?>