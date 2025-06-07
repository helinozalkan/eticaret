<?php
// seller_verification.php - Admin Satıcı Doğrulama Sayfası

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

$unverified_sellers = []; // Doğrulama bekleyen satıcıları tutacak dizi
$message = ""; // Başarı/Hata mesajları
$message_type = "info"; // Mesaj türü (info, success, error)

try {
    // POST isteği ile gelen doğrulama/reddetme işlemlerini yönet
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['satici_user_id'])) {
        $satici_user_id_posted = filter_input(INPUT_POST, 'satici_user_id', FILTER_VALIDATE_INT);
        $action = $_POST['action'];

        if ($satici_user_id_posted === false || $satici_user_id_posted <= 0) {
            $message = "Geçersiz satıcı ID'si.";
            $message_type = "error";
        } else {
            $new_status = ($action === 'approve') ? 1 : 0; // Onay = 1, Red = 0

            $conn->beginTransaction();

            $stmt_update_seller = $conn->prepare("UPDATE Satici SET HesapDurumu = :new_status WHERE User_ID = :user_id");
            $stmt_update_seller->bindParam(':new_status', $new_status, PDO::PARAM_INT);
            $stmt_update_seller->bindParam(':user_id', $satici_user_id_posted, PDO::PARAM_INT);

            if ($stmt_update_seller->execute()) {
                $conn->commit();
                $message = "Satıcı başarıyla " . (($action === 'approve') ? 'onaylandı' : 'reddedildi/pasif hale getirildi') . ".";
                $message_type = "success";
            } else {
                $conn->rollBack();
                $message = "Satıcı durumu güncellenirken bir hata oluştu.";
                $message_type = "error";
            }
        }
    }

    // Doğrulama bekleyen satıcıları çek
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
                                    s.HesapDurumu = 0 AND u.role = 'seller'";
    $stmt_unverified_sellers = $conn->prepare($query_unverified_sellers);
    $stmt_unverified_sellers->execute();
    $unverified_sellers = $stmt_unverified_sellers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("seller_verification.php: Veritabanı hatası: " . $e->getMessage());
    $message = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
    $message_type = "error";
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
}

// *** DÜZELTME: İç içe ternary operatörü yerine switch-case yapısı ***
// Mesaj türüne göre gösterilecek CSS sınıfını belirleyen mantık.
if (!empty($message)) {
    $alert_class = '';
    switch ($message_type) {
        case 'success':
            $alert_class = 'alert-success-custom alert-success';
            break;
        case 'info':
            $alert_class = 'alert-info-custom alert-info';
            break;
        default: // 'error' ve diğer tüm durumlar için
            $alert_class = 'alert-danger-custom alert-danger';
            break;
    }
}
?>


<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Doğrulama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css">
    <!-- Stil kodları aynı kalıyor -->
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-admin { background-color: rgb(34, 132, 17); }
        .main-container { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-top: 20px; margin-bottom: 20px; }
        .page-title { font-family: 'Playfair Display', serif; color: #2c3e50; text-align: center; margin-bottom: 35px; font-size: 2.5rem; font-weight: 700; }
        .seller-card { background-color: #fff; border: 1px solid #e0e0e0; border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; display: flex; flex-direction: column; gap: 15px; }
        @media (min-width: 768px) { .seller-card { flex-direction: row; align-items: center; } }
        .seller-card:hover { transform: translateY(-4px); box-shadow: 0 8px 18px rgba(0, 0, 0, 0.09); }
        .seller-avatar { width: 80px; height: 80px; border-radius: 50%; background-color: rgb(34, 132, 17, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 0; margin-bottom: 15px; flex-shrink: 0; }
        @media (min-width: 768px) { .seller-avatar { margin-right: 20px; margin-bottom: 0; } }
        .seller-avatar i { font-size: 2.5rem; color: rgb(34, 132, 17); }
        .seller-info { flex-grow: 1; }
        .seller-info h5 { font-family: 'Playfair Display', serif; margin-bottom: 5px; color: #34495e; font-size: 1.4rem; font-weight: 600; }
        .seller-info p { margin-bottom: 4px; color: #555; font-size: 0.9rem; }
        .seller-info p strong { color: #333; }
        .btn-group-actions { display: flex; gap: 10px; margin-top: 15px; align-self: stretch; }
        @media (min-width: 768px) { .btn-group-actions { margin-left: auto; margin-top: 0; align-self: center; } }
        .btn-group-actions .btn { padding: 0.5rem 1rem; font-size: 0.9rem; font-weight: 500; border-radius: 6px; }
        .btn-approve { background-color: #198754; border-color: #198754; color: white; }
        .btn-approve:hover { background-color: #157347; border-color: #146c43; }
        .btn-reject { background-color: #dc3545; border-color: #dc3545; color: white; }
        .btn-reject:hover { background-color: #bb2d3b; border-color: #b02a37; }
        .alert-custom { border-left-width: 5px; border-radius: 6px; padding: 0.9rem 1.1rem; font-size: 0.95rem; }
        .alert-danger-custom { border-left-color: #dc3545; }
        .alert-success-custom { border-left-color: #198754; }
        .alert-info-custom { border-left-color: #0dcaf0; }
        .no-verification-message { text-align: center; padding: 40px; background-color: #e9ecef; border-radius: 10px; color: #6c757d; font-size: 1.1rem; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3">Admin Paneli</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Kontrol Paneli</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="admin_user.php"><i class="bi bi-people-fill me-1"></i>Kullanıcı Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link active" href="seller_verification.php"><i class="bi bi-patch-check-fill me-1"></i>Satıcı Doğrulama</a></li>
            </ul>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4 me-2"></i>
                <?php if ($logged_in): ?>
                    <a href="logout.php" class="text-white" style="font-size: 15px; text-decoration: none;"><?php echo $username_session; ?> (Çıkış Yap)</a>
                <?php else: ?>
                    <a href="login.php" class="text-white" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container main-container">
    <h1 class="page-title"><i class="bi bi-person-check-fill me-2"></i>Satıcı Doğrulama İşlemleri</h1>

    <?php if (!empty($message)): ?>
        <!-- *** DÜZELTME: HTML içinde artık sadece temiz bir değişken kullanıyoruz. *** -->
        <div class="alert alert-custom <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($unverified_sellers) && empty($message) || (empty($unverified_sellers) && $message_type !== 'error' && $message_type !== 'success') ): ?>
         <div class="no-verification-message">
            <i class="bi bi-person-badge fs-1 mb-3 d-block"></i>
            Şu anda doğrulama bekleyen satıcı bulunmamaktadır.
        </div>
    <?php else: ?>
        <?php foreach ($unverified_sellers as $seller): ?>
            <div class="seller-card">
                <div class="seller-avatar">
                    <i class="bi bi-shop"></i>
                </div>
                <div class="seller-info">
                    <h5><?= htmlspecialchars($seller['Magaza_Adi']) ?></h5>
                    <p><strong>Yetkili:</strong> <?= htmlspecialchars($seller['Ad_Soyad']) ?> (Kullanıcı Adı: <?= htmlspecialchars($seller['username']) ?>)</p>
                    <p><strong>E-posta:</strong> <?= htmlspecialchars($seller['email']) ?></p>
                    <p><strong>Adres:</strong> <?= htmlspecialchars($seller['Adres'] ?: 'Belirtilmemiş') ?></p>
                    <p><strong>Mevcut Durum:</strong> <span class="badge bg-warning text-dark">Doğrulama Bekliyor</span></p>
                </div>
                <div class="btn-group-actions">
                    <form action="seller_verification.php" method="POST" class="d-inline">
                        <input type="hidden" name="satici_user_id" value="<?= htmlspecialchars($seller['user_id']) ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-approve"><i class="bi bi-check-circle-fill me-1"></i>Onayla</button>
                    </form>
                    <form action="seller_verification.php" method="POST" class="d-inline">
                        <input type="hidden" name="satici_user_id" value="<?= htmlspecialchars($seller['user_id']) ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="btn btn-reject"><i class="bi bi-x-circle-fill me-1"></i>Reddet/Pasif Yap</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript kısmı aynı kalıyor
    document.addEventListener("DOMContentLoaded", function() {
        var closeBtns = document.querySelectorAll(".alert .btn-close");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.closest('.alert').style.display = 'none';
            });
        });
    });
</script>
</body>
</html>