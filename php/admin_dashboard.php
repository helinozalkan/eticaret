<?php
session_start();
include('../database.php'); // database.php zaten PDO bağlantısı kuruyor

// Admin yetki kontrolü: Sadece adminler bu sayfaya erişebilir.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// --- İstatistikleri Dinamik Olarak Çekme (PDO Kullanımı) ---
try {
    // Toplam Kullanıcı Sayısı
    $query_total_users = "SELECT COUNT(id) AS total_users FROM users";
    $stmt_total_users = $conn->prepare($query_total_users);
    $stmt_total_users->execute();
    $total_users = $stmt_total_users->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Aktif Satıcı Sayısı
    // Proje dokümanına göre satıcıların HesapDurumu '1' (aktif) olduğunda aktif sayılır.
    $query_active_sellers = "SELECT COUNT(s.Satici_ID) AS active_sellers
                             FROM satici s
                             JOIN users u ON s.User_ID = u.id
                             WHERE u.role = 'seller' AND s.HesapDurumu = 1";
    $stmt_active_sellers = $conn->prepare($query_active_sellers);
    $stmt_active_sellers->execute();
    $active_sellers = $stmt_active_sellers->fetch(PDO::FETCH_ASSOC)['active_sellers'];

    // Pasif Satıcı Sayısı (Proje dokümanında 'Pasif Kullanıcılar' denmiş, satıcılar için HesapDurumu 0 olanları alalım)
    $query_inactive_sellers = "SELECT COUNT(s.Satici_ID) AS inactive_sellers
                               FROM satici s
                               JOIN users u ON s.User_ID = u.id
                               WHERE u.role = 'seller' AND s.HesapDurumu = 0";
    $stmt_inactive_sellers = $conn->prepare($query_inactive_sellers);
    $stmt_inactive_sellers->execute();
    $inactive_sellers = $stmt_inactive_sellers->fetch(PDO::FETCH_ASSOC)['inactive_sellers'];

    // Tüm Kullanıcıları Listele (müşteri, satıcı ve admin rolleri için)
    $stmt_all_users = $conn->prepare("SELECT id, username, email, role FROM users");
    $stmt_all_users->execute();
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Veritabanı hatası durumunda hata logla ve kullanıcıya bilgi ver
    error_log("admin_dashboard.php - Veritabanı hatası: " . $e->getMessage());
    $total_users = 0;
    $active_sellers = 0;
    $inactive_sellers = 0;
    $all_users = [];
    // İsteğe bağlı: Kullanıcıya dostça bir hata mesajı gösterilebilir veya boş değerler atanabilir.
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
    
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(34, 132, 17);">
    <div class="container-fluid">
        
    <a class="navbar-brand d-flex ms-4" href="../index.php" style="margin-left: 5px;">
         
            <div class="baslik fs-3"> E-Ticaret</div>
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
        <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            <?php echo htmlspecialchars($_SESSION['username']); ?> </a>
    <?php else: ?>
        <a href="login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            Giriş Yap
        </a>
    <?php endif; ?>
</div>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h1>Admin Paneli</h1>
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Toplam Kullanıcı</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $total_users ?></h5>
                    <p class="card-text">Sistemde kayıtlı toplam kullanıcı sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Aktif Satıcılar</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $active_sellers ?></h5>
                    <p class="card-text">Sistemde aktif olarak satış yapan satıcı sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Pasif Kullanıcılar</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $inactive_sellers ?></h5>
                    <p class="card-text">Sistemde pasif durumda olan kullanıcı sayısı.</p>
                </div>
            </div>
        </div>
    </div>

    <h2>Kullanıcılar</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Adı Soyadı</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Durum</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($all_users as $user) { ?>
        <tr>
            <td><?php echo htmlspecialchars($user['id']); ?></td>
            <td><?php echo htmlspecialchars($user['username']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['role']); ?></td>
            <td>
                <?php
    $user_status = 'Bilinmiyor'; // Varsayılan değer

    // Kullanıcının rolüne göre ilgili tablodan durumu çekelim
    try {
        if ($user['role'] === 'seller') {
            $stmt_seller_status = $conn->prepare("SELECT HesapDurumu FROM Satici WHERE User_ID = :user_id");
            $stmt_seller_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt_seller_status->execute();
            $seller_data = $stmt_seller_status->fetch(PDO::FETCH_ASSOC);
            if ($seller_data) {
                $user_status = ($seller_data['HesapDurumu'] == 1) ? 'Aktif' : 'Pasif (Doğrulama Bekliyor)';
            } else {
                $user_status = 'Satıcı Bilgisi Eksik';
            }
        } elseif ($user['role'] === 'customer') {
            $stmt_customer_status = $conn->prepare("SELECT Uyelik_Durumu FROM Musteri WHERE User_ID = :user_id");
            $stmt_customer_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
            $stmt_customer_status->execute();
            $customer_data = $stmt_customer_status->fetch(PDO::FETCH_ASSOC);
            if ($customer_data) {
                $user_status = ($customer_data['Uyelik_Durumu'] == 1) ? 'Aktif' : 'Pasif';
            } else {
                $user_status = 'Müşteri Bilgisi Eksik';
            }
        } elseif ($user['role'] === 'admin') {
            // Adminlerin durumu genellikle her zaman aktif kabul edilir.
            // Admin tablosunda doğrudan bir aktiflik durumu sütunu yok, Yetki_Seviyesi var.
            $user_status = 'Aktif';
        }
    } catch (PDOException $e) { 
        error_log("admin_dashboard.php - Kullanıcı durumu çekilirken hata: " . $e->getMessage());
        $user_status = 'Hata Oluştu';
    }
    echo $user_status;
    ?>
            </td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
</body>
</html>