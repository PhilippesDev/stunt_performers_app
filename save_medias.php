<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    // Vérifier si un produit est spécifié
    if (empty($_POST['product_id'])) {
        throw new Exception('ID produit manquant');
    }
    
    $product_id = $_POST['product_id'];
    $user_id = $_SESSION['user_id'];
    
    // Vérifier que le produit appartient à l'utilisateur
    $check = $conn->prepare("SELECT id FROM products WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $product_id, $user_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows === 0) {
        throw new Exception('Produit non trouvé ou non autorisé');
    }
    $check->close();
    
    // Dossier de destination
    $upload_dir = '../uploads/products/' . $product_id . '/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_files = [];
    
    // Traiter chaque fichier
    foreach ($_FILES['media']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['media']['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validation du type de fichier
        $file_type = $_FILES['media']['type'][$key];
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'video/mp4', 'video/mpeg', 'video/quicktime'
        ];
        
        if (!in_array($file_type, $allowed_types)) {
            continue;
        }
        
        // Validation de la taille (50MB max)
        $file_size = $_FILES['media']['size'][$key];
        if ($file_size > 50 * 1024 * 1024) {
            continue;
        }
        
        // Générer un nom de fichier unique
        $original_name = $_FILES['media']['name'][$key];
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Déplacer le fichier
        if (move_uploaded_file($tmp_name, $filepath)) {
            // Déterminer le type (image ou vidéo)
            $media_type = strpos($file_type, 'image/') === 0 ? 'image' : 'video';
            
            // Insérer en base de données
            $stmt = $conn->prepare("
                INSERT INTO product_media (product_id, file_path, file_type, position) 
                VALUES (?, ?, ?, ?)
            ");
            $position = count($uploaded_files) + 1;
            $relative_path = 'uploads/products/' . $product_id . '/' . $filename;
            $stmt->bind_param("issi", $product_id, $relative_path, $media_type, $position);
            $stmt->execute();
            $stmt->close();
            
            $uploaded_files[] = [
                'name' => $original_name,
                'path' => $relative_path,
                'type' => $media_type
            ];
        }
    }
    
    // Si c'est la première image, la définir comme miniature
    if (count($uploaded_files) > 0) {
        $first_image = $conn->prepare("
            SELECT id FROM product_media 
            WHERE product_id = ? AND file_type = 'image' 
            ORDER BY position ASC LIMIT 1
        ");
        $first_image->bind_param("i", $product_id);
        $first_image->execute();
        $result = $first_image->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $update = $conn->prepare("
                UPDATE product_media SET is_thumbnail = 1 WHERE id = ?
            ");
            $update->bind_param("i", $row['id']);
            $update->execute();
            $update->close();
        }
        $first_image->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => count($uploaded_files) . ' fichier(s) téléchargé(s)',
        'files' => $uploaded_files
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>