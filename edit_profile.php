<?php
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$successMessage = $errorMessage = "";

// Récupération initiale
$stmt = $conn->prepare("SELECT username, email, phone, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $targetFilePath = $user['profile_pic'];

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        $fileExtension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $newFileName = time() . "_" . $userId . "." . $fileExtension;
        $targetFilePath = $targetDir . $newFileName;

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedTypes)) {
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFilePath);
        } else {
            $errorMessage = "Format d'image non supporté.";
        }
    }

    if (empty($errorMessage)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, profile_pic = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $phone, $targetFilePath, $userId);

        if ($stmt->execute()) {
            $successMessage = "Profil mis à jour avec succès !";
            // Update local variable for display
            $user['username'] = $username;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['profile_pic'] = $targetFilePath;
        } else {
            $errorMessage = "Erreur lors de la mise à jour.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr" class="h-full bg-gray-50">
<head>
    <link rel="icon" type="image/png" href="favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil | Cascade</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="h-full">

<div class="max-w-4xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Paramètres du profil</h1>
            <p class="text-gray-500">Gérez vos informations personnelles et votre avatar.</p>
        </div>
        <a href="dashboard.php" class="text-sm font-medium text-fuchsia-600 hover:text-fuchsia-500 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Retour au tableau de bord
        </a>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
        <form method="POST" enctype="multipart/form-data" class="divide-y divide-gray-100">
            
            <div class="p-8 sm:flex items-center gap-8">
                <div class="relative group mx-auto sm:mx-0">
                    <img id="preview" src="<?= htmlspecialchars($user['profile_pic'] ?: 'uploads/default.png') ?>" 
                         class="w-32 h-32 rounded-3xl object-cover ring-4 ring-fuchsia-50 shadow-md">
                    <label for="profile_pic" class="absolute -bottom-2 -right-2 bg-white p-2 rounded-xl shadow-lg border border-gray-100 cursor-pointer hover:bg-fuchsia-50 transition-colors text-fuchsia-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-width="2"/><path d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"/></svg>
                        <input type="file" id="profile_pic" name="profile_pic" class="hidden" accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <div class="mt-4 sm:mt-0 text-center sm:text-left">
                    <h3 class="text-lg font-semibold text-gray-900">Photo de profil</h3>
                    <p class="text-sm text-gray-500 mb-2">JPG, PNG ou GIF. Max 2Mo.</p>
                    <?php if ($successMessage): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 animate-pulse">
                            <?= $successMessage ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($errorMessage): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            <?= $errorMessage ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Nom d'utilisateur</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required
                           class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent transition-all outline-none border">
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Adresse Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required
                           class="w-full rounded-2xl border-gray-200 bg-gray-50 px-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent transition-all outline-none border">
                </div>

                <div class="space-y-2 md:col-span-2">
                    <label class="text-sm font-bold text-gray-700 ml-1">Numéro de téléphone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-400 font-medium">CD</span>
                        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required
                               class="w-full rounded-2xl border-gray-200 bg-gray-50 pl-12 pr-4 py-3.5 focus:bg-white focus:ring-2 focus:ring-fuchsia-500 focus:border-transparent transition-all outline-none border">
                    </div>
                </div>
            </div>

            <div class="p-8 bg-gray-50/50 flex flex-col sm:flex-row gap-4 justify-end">
                <button type="reset" class="px-6 py-3 text-sm font-bold text-gray-500 hover:text-gray-700 transition-colors">
                    Annuler les modifs
                </button>
                <button type="submit" class="px-8 py-3 bg-gray-900 text-white text-sm font-bold rounded-2xl shadow-lg shadow-gray-200 hover:bg-fuchsia-600 active:scale-95 transition-all">
                    Enregistrer les changements
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

</body>
</html>