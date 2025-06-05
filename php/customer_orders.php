<?php
// Müşterinin kendi siparişlerini gördüğü sayfa
session_start();
include_once '../database.php'; // Veritabanı bağlantısı

// 1. Sadece giriş yapmış ve rolü 'customer' olanların bu sayfayı görmesini sağla
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php?status=login_required");
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = [];
$error_message = '';

try {
    // 2. Müşterinin 'users' tablosundaki ID'sini kullanarak 'musteri' tablosundaki Musteri_ID'sini bul.
    // Siparişler Musteri_ID ile tutuluyor.
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
    $stmt_musteri->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if ($musteri_data) {
        $musteri_id = $musteri_data['Musteri_ID'];

        // 3. Bulunan Musteri_ID'ye ait tüm siparişleri, en yeniden eskiye doğru sıralayarak çek.
        $stmt_orders = $conn->prepare("SELECT * FROM Siparis WHERE Musteri_ID = :musteri_id ORDER BY Siparis_Tarihi DESC");
        $stmt_orders->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "Müşteri profili bulunamadı.";
    }

} catch (PDOException $e) {
    error_log("customer_orders.php - Veritabanı Hatası: " . $e->getMessage());
    $error_message = "Siparişleriniz yüklenirken bir sorun oluştu.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişlerim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/eticaret/css/css.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 960px; }
        .table thead th { background-color: #e9ecef; }
        .status-badge { padding: 0.35em 0.65em; font-size: .75em; font-weight: 700; line-height: 1; color: #fff; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; }
        .status-beklemede { background-color: #ffc107; color: #000; }
        .status-kargoda { background-color: #17a2b8; }
        .status-teslim-edildi { background-color: #28a745; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(155, 10, 109) ;">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3" style="color:white; text-decoration:none;">ETİCARET</div>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
            </ul>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4"></i>
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                        <?php echo htmlspecialchars($_SESSION['username']); ?> (Çıkış Yap)
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                        Giriş Yap
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h1 class="text-center mb-4">Siparişlerim</h1>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif (empty($orders)): ?>
        <div class="alert alert-info text-center">Henüz bir sipariş vermediniz.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Sipariş No</th>
                            <th scope="col">Tarih</th>
                            <th scope="col">Tutar</th>
                            <th scope="col">Durum</th>
                            <th scope="col">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['Siparis_ID']); ?></td>
                                <td><?php echo date("d.m.Y", strtotime($order['Siparis_Tarihi'])); ?></td>
                                <td><?php echo number_format($order['Siparis_Tutari'], 2, ',', '.'); ?> TL</td>
                                <td>
                                    <?php 
                                        $status_class = '';
                                        if ($order['Siparis_Durumu'] == 'Beklemede') $status_class = 'status-beklemede';
                                        if ($order['Siparis_Durumu'] == 'Kargoda') $status_class = 'status-kargoda';
                                        if ($order['Siparis_Durumu'] == 'Teslim Edildi') $status_class = 'status-teslim-edildi';
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($order['Siparis_Durumu']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_order.php?id=<?php echo $order['Siparis_ID']; ?>" class="btn btn-primary btn-sm">
                                        Detayları Görüntüle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <a class="btn btn-secondary" href="../index.php">Ana Sayfaya Dön</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>