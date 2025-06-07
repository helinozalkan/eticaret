<?php
// order_manage.php - Satıcı Sipariş Yönetimi Sayfası
session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');


// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header(HTTP_HEADER_LOCATION . "login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];

$orders = [];
$error_message = "";
$satici_id = null;

// update_order_status.php'den gelen mesajları al
if (isset($_SESSION['order_update_success'])) {
    $success_message = $_SESSION['order_update_success'];
    unset($_SESSION['order_update_success']);
}
if (isset($_SESSION['order_update_error'])) {
    $error_message = empty($error_message) ? $_SESSION['order_update_error'] : $error_message . " | " . $_SESSION['order_update_error'];
    unset($_SESSION['order_update_error']);
}


try {
    // 1. Kullanıcının Satici_ID'sini al
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        $error_message = "Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturduğunuzdan emin olun.";
    } else {
        $satici_id = $satici_data['Satici_ID'];

        // 2. Bu satıcıya ait ürünlerin olduğu siparişleri çek
        $query_orders = "SELECT
                            S.Siparis_ID,
                            S.Siparis_Tarihi,
                            S.Siparis_Durumu,
                            S.Siparis_Tutari,
                            GROUP_CONCAT(DISTINCT U.Urun_Adi SEPARATOR ', ') AS Urun_Adlari,
                            SUM(SU.Miktar) AS Toplam_Urun_Miktari,
                            USR.username AS Musteri_Adi,
                            S.Teslimat_Adresi
                         FROM
                            Siparis S
                         JOIN
                            SiparisUrun SU ON S.Siparis_ID = SU.Siparis_ID
                         JOIN
                            Urun U ON SU.Urun_ID = U.Urun_ID
                         JOIN
                            Musteri M ON S.Musteri_ID = M.Musteri_ID
                         JOIN
                            users USR ON M.User_ID = USR.id
                         WHERE
                            U.Satici_ID = :satici_id
                         GROUP BY S.Siparis_ID
                         ORDER BY
                            S.Siparis_Tarihi DESC";

        $stmt_orders = $conn->prepare($query_orders);
        $stmt_orders->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("order_manage.php: Veritabanı hatası: " . $e->getMessage());
    $error_message = "Siparişler çekilirken bir veritabanı hatası oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background-color: rgb(91, 140, 213); }
        .main-container { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-top: 20px; margin-bottom: 20px; }
        .page-title { font-family: 'Playfair Display', serif; color: #2c3e50; text-align: center; margin-bottom: 35px; font-size: 2.5rem; font-weight: 700; }
        .orders-table th { background-color: #e9ecef; font-weight: 600; color: #495057; }
        .orders-table td { vertical-align: middle; }
        .status-badge { padding: 0.4em 0.7em; border-radius: 0.3rem; color: white; font-weight: 500; font-size: 0.85em; display: inline-block; min-width: 100px; text-align: center; }
        .status-beklemede { background-color: #ffc107; color: #212529;}
        .status-kargoda { background-color: #0dcaf0; }
        .status-teslim-edildi { background-color: #198754; }
        .update-status-form .btn-update { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); color: white; }
        .btn-update:hover { background-color: rgb(70, 120, 190); border-color: rgb(70, 120, 190); }
    </style>
</head>
<body>
<!-- *** DÜZELTME: Eksik olan navigasyon menüsü eklendi *** -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3">ELEMEK</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3"><a class="nav-link" href="seller_dashboard.php">Satıcı Paneli</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="seller_manage.php">Mağaza Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="manage_product.php">Ürün Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link active" href="order_manage.php">Sipariş Yönetimi</a></li>
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
    <div class="text-center mb-4">
        <i class="bi bi-box-seam-fill fs-1 text-primary"></i>
        <h1 class="page-title mt-2">Sipariş Yönetimi</h1>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message) && !isset($success_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($orders) && empty($error_message)): ?>
        <div class="text-center p-4 bg-light rounded">
            <i class="bi bi-cart-x fs-1 mb-3 d-block text-secondary"></i>
            Şu anda yönetilecek herhangi bir sipariş bulunmamaktadır.
        </div>
    <?php elseif (!empty($orders)): ?>
        <div class="table-responsive">
            <table class="table orders-table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Sipariş ID</th>
                        <th>Müşteri</th>
                        <th>Ürünler</th>
                        <th>Tarih</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th class="text-center">Durumu Güncelle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($order['Siparis_ID']) ?></strong></td>
                            <td><?= htmlspecialchars($order['Musteri_Adi']) ?></td>
                            <td><small><?= htmlspecialchars($order['Urun_Adlari']) ?> (x<?= htmlspecialchars($order['Toplam_Urun_Miktari']) ?>)</small></td>
                            <td><?= htmlspecialchars(date("d.m.Y", strtotime($order['Siparis_Tarihi']))) ?></td>
                            <td><?= number_format(htmlspecialchars($order['Siparis_Tutari']), 2, ',', '.') ?> TL</td>
                            <td>
                                <?php
                                $status_class = 'status-' . strtolower(str_replace([' ', 'ş', 'ı', 'ö', 'ü', 'ğ', 'ç'], ['-', 's', 'i', 'o', 'u', 'g', 'c'], $order['Siparis_Durumu']));
                                ?>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= htmlspecialchars($order['Siparis_Durumu']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="update_order_status.php" method="post" class="update-status-form">
                                    <input type="hidden" name="siparis_id" value="<?= htmlspecialchars($order['Siparis_ID']) ?>">
                                    <select name="siparis_durumu" class="form-select form-select-sm">
                                        <option value="Beklemede" <?= ($order['Siparis_Durumu'] === 'Beklemede') ? 'selected' : '' ?>>Beklemede</option>
                                        <option value="Kargoda" <?= ($order['Siparis_Durumu'] === 'Kargoda') ? 'selected' : '' ?>>Kargoda</option>
                                        <option value="Teslim Edildi" <?= ($order['Siparis_Durumu'] === 'Teslim Edildi') ? 'selected' : '' ?>>Teslim Edildi</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-update" title="Güncelle"><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
