<?php
session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


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
    $query_active_sellers = "SELECT COUNT(s.Satici_ID) AS active_sellers
                             FROM satici s
                             JOIN users u ON s.User_ID = u.id
                             WHERE u.role = 'seller' AND s.HesapDurumu = 1";
    $stmt_active_sellers = $conn->prepare($query_active_sellers);
    $stmt_active_sellers->execute();
    $active_sellers = $stmt_active_sellers->fetch(PDO::FETCH_ASSOC)['active_sellers'];

    // Pasif Satıcı Sayısı
    $query_inactive_sellers = "SELECT COUNT(s.Satici_ID) AS inactive_sellers
                               FROM satici s
                               JOIN users u ON s.User_ID = u.id
                               WHERE u.role = 'seller' AND s.HesapDurumu = 0";
    $stmt_inactive_sellers = $conn->prepare($query_inactive_sellers);
    $stmt_inactive_sellers->execute();
    $inactive_sellers = $stmt_inactive_sellers->fetch(PDO::FETCH_ASSOC)['inactive_sellers'];

    // Tüm Kullanıcıları Listele
    $stmt_all_users = $conn->prepare("SELECT id, username, email, role FROM users");
    $stmt_all_users->execute();
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("admin_dashboard.php - Veritabanı hatası: " . $e->getMessage());
    $total_users = 0;
    $active_sellers = 0;
    $inactive_sellers = 0;
    $all_users = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
     <link rel="stylesheet" href="../css/css.css">
     <!-- Stil kodları değişmediği için aynı kalıyor -->
     <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-admin {
            background-color: rgb(34, 132, 17);
        }
        .main-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 40px;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .stat-card {
            border: none;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.07);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            color: white;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .stat-card .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.3);
            font-size: 1.1rem;
            font-weight: 600;
            padding-bottom: 10px;
            margin-bottom: 15px;
            color: white;
        }
        .stat-card .card-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }
        .stat-card .card-text {
            font-size: 0.95rem;
             color: rgba(255,255,255,0.85);
        }
        .stat-card.bg-primary { background-color: #0d6efd !important; }
        .stat-card.bg-success { background-color: #198754 !important; }
        .stat-card.bg-danger { background-color: #dc3545 !important; }

        .table-section-title {
            font-family: 'Playfair Display', serif;
            color: #34495e;
            margin-top: 50px;
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: 600;
            text-align:center;
        }
        .table th {
            font-weight: 600;
            color: #495057;
            background-color: #e9ecef;
        }
        .table td {
            vertical-align: middle;
        }
        .status-badge {
            padding: 0.3em 0.6em;
            font-size: 0.85em;
            font-weight: 600;
            border-radius: 0.25rem;
            display: inline-block;
            min-width: 80px;
            text-align: center;
        }
        .status-aktif { background-color: #d1e7dd; color: #0f5132; }
        .status-pasif { background-color: #f8d7da; color: #842029; }
        .status-bilgi-yok { background-color: #e2e3e5; color: #495057; }
        .status-satici-bilgisi-eksik { background-color: #fff3cd; color: #664d03;}
     </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3"> Admin Paneli</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3">
                    <a class="nav-link active" href="admin_dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Kontrol Paneli
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a class="nav-link" href="admin_user.php">
                        <i class="bi bi-people-fill me-1"></i>Kullanıcı Yönetimi
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a class="nav-link" href="seller_verification.php">
                        <i class="bi bi-patch-check-fill me-1"></i>Satıcı Doğrulama
                    </a>
                </li>
            </ul>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4 me-2"></i>
                <?php if ($logged_in): ?>
                    <a href="logout.php" class="text-white" style="font-size: 15px; text-decoration: none;"><?php echo $username; ?> (Çıkış Yap)</a>
                <?php else: ?>
                    <a href="login.php" class="text-white" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container main-container">
    <h1 class="page-title"><i class="bi bi-shield-lock-fill me-2"></i>Admin Kontrol Paneli</h1>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="stat-card bg-primary">
                <div class="card-header"><i class="bi bi-people-fill me-2"></i>Toplam Kullanıcı</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $total_users ?></h5>
                    <p class="card-text">Sistemde kayıtlı tüm kullanıcıların sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-success">
                <div class="card-header"><i class="bi bi-shop me-2"></i>Aktif Satıcılar</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $active_sellers ?></h5>
                    <p class="card-text">Hesap durumu aktif olan satıcı sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card bg-danger">
                <div class="card-header"><i class="bi bi-person-x-fill me-2"></i>Pasif/Doğrulanmamış Satıcılar</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $inactive_sellers ?></h5>
                    <p class="card-text">Hesap durumu pasif veya doğrulanmamış satıcılar.</p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="table-section-title"><i class="bi bi-card-list me-2"></i>Kullanıcı Listesi</h2>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı Adı</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Durum</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($all_users)): ?>
                <?php foreach ($all_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                    <td>
                        <?php
                        $user_status = 'Bilgi Yok';
                        $status_class = 'status-bilgi-yok';

                        try {
                            if ($user['role'] === 'seller') {
                                $stmt_seller_status = $conn->prepare("SELECT HesapDurumu FROM Satici WHERE User_ID = :user_id");
                                $stmt_seller_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                                $stmt_seller_status->execute();
                                $seller_data = $stmt_seller_status->fetch(PDO::FETCH_ASSOC);
                                if ($seller_data) {
                                    if ($seller_data['HesapDurumu'] == 1) {
                                        $user_status = 'Aktif';
                                        $status_class = 'status-aktif';
                                    } else {
                                        $user_status = 'Pasif/Doğrulanmamış';
                                        $status_class = 'status-pasif';
                                    }
                                } else {
                                    $user_status = 'Satıcı Bilgisi Eksik';
                                     $status_class = 'status-satici-bilgisi-eksik';
                                }
                            } elseif ($user['role'] === 'customer') {
                                $stmt_customer_status = $conn->prepare("SELECT Uyelik_Durumu FROM Musteri WHERE User_ID = :user_id");
                                $stmt_customer_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                                $stmt_customer_status->execute();
                                $customer_data = $stmt_customer_status->fetch(PDO::FETCH_ASSOC);
                                if ($customer_data) {
                                    if($customer_data['Uyelik_Durumu'] == 1){
                                        $user_status = 'Aktif';
                                        $status_class = 'status-aktif';
                                    } else {
                                        $user_status = 'Pasif';
                                        $status_class = 'status-pasif';
                                    }
                                } else {
                                     $user_status = 'Müşteri Bilgisi Eksik';
                                     $status_class = 'status-satici-bilgisi-eksik';
                                }
                            } elseif ($user['role'] === 'admin') {
                                $user_status = 'Aktif';
                                $status_class = 'status-aktif';
                            }
                        } catch (PDOException $e) {
                            error_log("admin_dashboard.php - Kullanıcı durumu çekilirken hata (ID: ".$user['id']."): " . $e->getMessage());
                            $user_status = 'Hata';
                            $status_class = 'status-pasif';
                        }
                        echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($user_status) . '</span>';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center py-4">Sistemde kayıtlı kullanıcı bulunmamaktadır.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>