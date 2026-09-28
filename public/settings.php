<?php
// Afficher les erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session
session_start();

require_once __DIR__ . '/db.php';
$mysqli = $conn;

if ($mysqli->connect_error) {
    die('Erreur de connexion (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations de l'utilisateur
$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT username, email, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (isset($_POST['delete_account'])) {
 
  $mysqli->begin_transaction();

  try {
      
      $stmt = $mysqli->prepare("DELETE FROM orders WHERE user_id = ? OR seller_id = ?");
      $stmt->bind_param("ii", $user_id, $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM notifications WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM order_cancellations WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM order_cancellation_reasons WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM products WHERE user_id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM transactions WHERE buyer_id = ? OR seller_id = ?");
      $stmt->bind_param("ii", $user_id, $user_id);
      $stmt->execute();
      $stmt->close();

      
      $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
      $stmt->bind_param("i", $user_id);
      $stmt->execute();
      $stmt->close();

      
      $mysqli->commit();

      
      session_destroy();
      header('Location: index.php');
      exit();
  } catch (Exception $e) {
      
      $mysqli->rollback();
      die("Erreur lors de la suppression du compte : " . $e->getMessage());
  }
}


$dark_mode = isset($_SESSION['dark_mode']) ? $_SESSION['dark_mode'] : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paramètres - Cascade</title>

  <!-- Forcer le mode sombre si activé -->
  <?php if ($dark_mode): ?>
    <meta name="color-scheme" content="dark">
  <?php else: ?>
    <meta name="color-scheme" content="light">
  <?php endif; ?>

  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link href="assets/images/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans&family=Poppins&display=swap" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      padding-top: 0;
      margin: 0;
      overflow-x: hidden;
    }

    .settings-container {
      max-width: 800px;
      margin: 50px auto;
      padding: 20px;
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    a {
      color: #fe5c2d;
      margin-top: 20px
    }
    .client_service{
      background-color: #f4f4f4;
      padding: 20px;
      margin-top: 20px;
    }
    h5{
      font-weight: bold;
    }
    ul{
      list-style : none;
      display: flex;
      font-size: 30px;
      width: 30%;
      justify-content: space-between;
    }
    @media (max-width : 400px){
      ul{
        width: 50%;
      }
      ul a{
        margin-left: 10px;
      }
    }
    
  </style>
</head>
<body>
  <div id="main">
    <div class="settings-container">
      <h2><a href="index.php"><svg xmlns="http://www.w3.org/2000/svg" title="retour" width="25" height="25" fill="rgb(250, 6, 6)" class="bi bi-arrow-left-circle-fill" viewBox="0 0 16 16">
            <path d="M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0m3.5 7.5a.5.5 0 0 1 0 1H5.707l2.147 2.146a.5.5 0 0 1-.708.708l-3-3a.5.5 0 0 1 0-.708l3-3a.5.5 0 1 1 .708.708L5.707 7.5z"/>
        </svg></a> Paramètres</h2>
      
      
      <a href="term_condition.html">Voir les termes et conditions d'utilisation <i class="bi bi-box-arrow-up-right"></i></a>
      <div class="client_service">
        <h5>Service client</h5>
        <h6>Nous contacter via : </h6>
        <ul>
        <a href="mailto:lukogophilippe26@gmail.com"><i class="bi bi-envelope-arrow-down-fill"></i></a>
        <a href="https://wa.me/+243902580019"><i class="bi bi-whatsapp"></i></a>
        <a href="https://t.me/Philippe mir"><i class="bi bi-telegram"></i></a>
        <a href="https://www.facebook.com/profile.php?id=61573616080964"><i class="bi bi-facebook"></i></a>
        </ul>
      </a>      
      </div>
      <form method="POST" action="settings.php" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.');">
        <div class="mt-5">
          <h5>Supprimer le compte</h5>
          <p>Cette action supprimera définitivement votre compte et toutes les données associées.</p>
          <button type="submit" name="delete_account" class="btn btn-danger"><i class="bi bi-trash-fill"></i> Supprimer mon compte</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openNav() {
      document.getElementById("sidebar").style.width = "250px";
      document.getElementById("sidebar").style.display = "block";
    }

    function closeNav() {
      document.getElementById("sidebar").style.width = "0";
      document.getElementById("main").style.marginLeft = "0";
    }

    document.querySelector(".showmenu").addEventListener("click", function(e) {
      e.preventDefault(); 
      openNav();
    });

    document.addEventListener("click", function(event) {
      const sidebar = document.getElementById("sidebar");
      const showmenu = document.querySelector(".showmenu");
      if (!sidebar.contains(event.target) && event.target !== showmenu) {
        closeNav();
      }
    });

    // Forcer le mode sombre en fonction des préférences de l'utilisateur
    const darkModeEnabled = <?php echo $dark_mode ? 'true' : 'false'; ?>;

    if (darkModeEnabled) {
      // Ajouter la méta-balise pour forcer le mode sombre
      const meta = document.createElement('meta');
      meta.name = 'color-scheme';
      meta.content = 'dark';
      document.head.appendChild(meta);
    } else {
      // Ajouter la méta-balise pour forcer le mode clair
      const meta = document.createElement('meta');
      meta.name = 'color-scheme';
      meta.content = 'light';
      document.head.appendChild(meta);
    }

    // Basculer manuellement entre les modes
    document.getElementById('dark_mode').addEventListener('change', function() {
      const isDark = this.checked;
      const meta = document.querySelector('meta[name="color-scheme"]');
      if (meta) {
        meta.content = isDark ? 'dark' : 'light';
      } else {
        const newMeta = document.createElement('meta');
        newMeta.name = 'color-scheme';
        newMeta.content = isDark ? 'dark' : 'light';
        document.head.appendChild(newMeta);
      }

      // Envoyer le formulaire pour enregistrer le choix
      this.form.submit();
    });
  </script>
</body>
</html>

<?php
// Fermer la connexion à la base de données
$mysqli->close();
?>