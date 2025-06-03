<?php
// seller_verification.php - Admin Satıcı Doğrulama Sayfası
session_start();
include('../database.php'); // database.php PDO bağlantısını kuruyor

// Admin yetki kontrolü (ODAK NOKTASI)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

$unverified_sellers = []; // Doğrulama bekleyen satıcıları tutacak dizi
$message = ""; // Başarı/Hata mesajları

try {
    // POST isteği ile gelen doğrulama/reddetme işlemlerini yönet
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['satici_user_id'])) {
        $satici_user_id = filter_input(INPUT_POST, 'satici_user_id', FILTER_VALIDATE_INT);
        $action = $_POST['action']; // 'approve' veya 'reject'

        if ($satici_user_id === false || $satici_user_id <= 0) {
            $message = "Geçersiz satıcı ID'si.";
        } else {
            // HesapDurumu'nu güncelle: 1 = Onaylandı, 0 = Reddedildi/Doğrulama Bekliyor
            $new_status = ($action === 'approve') ? 1 : 0; 

            $conn->beginTransaction(); // İşlemi başlat

            $stmt_update_seller = $conn->prepare("UPDATE Satici SET HesapDurumu = :new_status WHERE User_ID = :user_id");
            $stmt_update_seller->bindParam(':new_status', $new_status, PDO::PARAM_INT);
            $stmt_update_seller->bindParam(':user_id', $satici_user_id, PDO::PARAM_INT);

            if ($stmt_update_seller->execute()) {
                $conn->commit();
                $message = "Satıcı başarıyla " . (($action === 'approve') ? 'onaylandı' : 'reddedildi') . ".";
            } else {
                $conn->rollBack();
                $message = "Satıcı durumu güncellenirken bir hata oluştu.";
            }
        }
    }

    // Doğrulama bekleyen satıcıları çek (veya HesapDurumu 0 olanları)
    // Satici tablosundaki HesapDurumu = 0 olanları (varsayılan kayıt durumu) çekiyoruz.
    $query_unverified_sellers = "SELECT 
                                    u.id AS user_id, 
                                    u.username, 
                                    u.email,
                                    s.Magaza_Adi,
                                    s.Ad_Soyad,
                                    s.Adres,
                                    s.HesapDurumu
                                FROM 
                                    users u
                                JOIN 
                                    Satici s ON u.id = s.User_ID
                                WHERE 
                                    s.HesapDurumu = 0"; // HesapDurumu = 0 olanları çeker
    $stmt_unverified_sellers = $conn->prepare($query_unverified_sellers);
    $stmt_unverified_sellers->execute();
    $unverified_sellers = $stmt_unverified_sellers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("seller_verification.php: Veritabanı hatası: " . $e->getMessage());
    $message = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
}
?>



<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Doğrulama</title>
    <style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f4f4;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 80%;
            max-width: 800px;
        }
        h1 {
            color: #333;
            text-align: start;
            margin-bottom: 20px;
        }
        .seller-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease-in-out;
        }
        .seller-card:hover {
            transform: scale(1.02);
        }
        .seller-info {
            display: flex;
            align-items: center;
        }
        .seller-info img {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            margin-right: 20px;
        }
        .seller-info div {
            max-width: 400px;
        }
        .seller-info h5 {
            margin: 0;
            color: #333;
        }
        .seller-info p {
            margin: 5px 0;
            color: #666;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .btn-group button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease-in-out;
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        .btn-approve:hover {
            background-color: #218838;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        .btn-reject:hover {
            background-color: #c82333;
        }
    </style>
</head>

 <!-- !BOOTSTRAP'S CSS-->
 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- !BOOTSTRAP'S CSS-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="stylesheet" href="css/css.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
/>
 <script src="https://code.jquery.com/jquery-1.8.2.min.js" integrity="sha256-9VTS8JJyxvcUR+v+RTLTsd0ZWbzmafmlzMmeZO9RFyk=" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(34, 132, 17);">
    <div class="container-fluid">
        
    <a class="navbar-brand d-flex ms-4" href="../index.php" style="margin-left: 5px;">
         
            <div class="baslik fs-3"> ELEMEK</div>
        </a>

                
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="admin_dashboard.php">
                        Admin Paneli
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="admin_user.php">
                        Kullanıcı Yönetimi
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_verification.php">
                        Satıcı Doğrulama
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="product_verification.php">
                        Ürün Doğrulama
                    </a>
                </li>
            </ul>

            <div class="d-flex me-3" style="margin-left: 145px;">
    <i class="bi bi-person-circle text-white fs-4"></i>
    <?php if (isset($_SESSION['username'])): ?>
        <!-- Kullanıcı giriş yaptıysa -->
        <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            <?php echo htmlspecialchars($_SESSION['username']); ?> <!-- Kullanıcı adı gösteriliyor -->
        </a>
    <?php else: ?>
        <!-- Kullanıcı giriş yapmamışsa -->
        <a href="login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            Giriş Yap
        </a>
    <?php endif; ?>
</div>
        </div>
    </div>
</nav>


<div class="container">
    <h1>Satıcı Doğrulama</h1>

    <?php if (!empty($message)): ?>
        <div class="message-container success-message"> <span class="close-btn">&times;</span>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($unverified_sellers)): ?>
        <p>Doğrulama bekleyen satıcı bulunmamaktadır.</p>
    <?php else: ?>
        <?php foreach ($unverified_sellers as $seller): ?>
            <div class="seller-card">
                <div class="seller-info">
                    <img src="../images/magazalogo.png" alt="Satıcı Görseli"> <div>
                        <h5><?= htmlspecialchars($seller['Magaza_Adi']) ?></h5>
                        <p><?= htmlspecialchars($seller['Ad_Soyad']) ?> (<?= htmlspecialchars($seller['username']) ?>)</p>
                        <p><?= htmlspecialchars($seller['email']) ?></p>
                        <p><?= htmlspecialchars($seller['Adres']) ?></p>
                        <p>Durum: <?php
                            if ($seller['HesapDurumu'] == 1) {
                                echo 'Aktif';
                            } else {
                                // HesapDurumu 0 ise, bu ya yeni kaydolmuş beklemede ya da reddedilmiş demektir.
                                // Reddedilenleri "Doğrulama Bekliyor"dan ayırmak için veritabanında ek bir sütuna ihtiyacımız var.
                                // Şu anki durumda, HesapDurumu 0 olanları "Doğrulama Bekliyor / Pasif" olarak göstereceğiz.
                                echo 'Doğrulama Bekliyor / Pasif'; // Admin dashboard ile uyumlu
                            }
                        ?></p>
                    </div>
                </div>
                <div class="btn-group">
                    <form action="seller_verification.php" method="POST" style="display:inline;">
                        <input type="hidden" name="satici_user_id" value="<?= htmlspecialchars($seller['user_id']) ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn-approve">Onayla</button>
                    </form>
                    <form action="seller_verification.php" method="POST" style="display:inline;">
                        <input type="hidden" name="satici_user_id" value="<?= htmlspecialchars($seller['user_id']) ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn-reject">Reddet</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div> 



<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- !BOOTSTRAP'S jS-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- !BOOTSTRAP'S jS-->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
</body>
</html>
