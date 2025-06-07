<?php
// customer_dashboard.php - Müşteri Paneli Sayfası

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// Kullanıcı oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$username_session = $_SESSION['username'] ?? 'Müşteri';

$customer_data = [];
$recent_orders = [];
$stats = ['total_orders' => 0, 'total_spent' => 0.0];

try {
    // Müşterinin ID'sini ve diğer bilgilerini çek
    $stmt_customer = $conn->prepare("
        SELECT m.Musteri_ID, u.username, u.email
        FROM musteri m
        JOIN users u ON m.User_ID = u.id
        WHERE m.User_ID = :user_id
    ");
    $stmt_customer->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_customer->execute();
    $customer_data = $stmt_customer->fetch(PDO::FETCH_ASSOC);

    if ($customer_data) {
        $musteri_id = $customer_data['Musteri_ID'];

        // İstatistikleri çek: Toplam sipariş sayısı ve toplam harcama
        $stmt_stats = $conn->prepare("
            SELECT COUNT(Siparis_ID) AS total_orders, SUM(Siparis_Tutari) AS total_spent
            FROM Siparis
            WHERE Musteri_ID = :musteri_id
        ");
        $stmt_stats->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_stats->execute();
        $stats_data = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        if ($stats_data) {
            $stats['total_orders'] = $stats_data['total_orders'] ?? 0;
            $stats['total_spent'] = $stats_data['total_spent'] ?? 0.0;
        }

        // Son 5 siparişi çek
        $stmt_orders = $conn->prepare("
            SELECT Siparis_ID, Siparis_Tarihi, Siparis_Durumu, Siparis_Tutari
            FROM Siparis
            WHERE Musteri_ID = :musteri_id
            ORDER BY Siparis_Tarihi DESC
            LIMIT 5
        ");
        $stmt_orders->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_orders->execute();
        $recent_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("customer_dashboard.php PDO Hatası: " . $e->getMessage());
    $error_message = "Panel bilgileri yüklenirken bir hata oluştu.";
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Montserrat', sans-serif; }
        .dashboard-header { background: linear-gradient(135deg, rgb(91, 140, 213) 0%, rgb(120, 160, 230) 100%); color: white; padding: 40px 20px; border-radius: 0 0 25px 25px; text-align: center; margin-bottom: 30px; }
        .dashboard-header h1 { font-family: 'Playfair Display', serif; }
        .stat-card { background-color: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .icon { font-size: 2.5rem; color: rgb(91, 140, 213); }
        .stat-card h5 { margin-top: 10px; color: #343a40; }
        .stat-card p { font-size: 1.5rem; font-weight: 700; color: #495057; margin-bottom: 0; }
        .section-title { font-family: 'Playfair Display', serif; color: #343a40; margin-top: 40px; margin-bottom: 20px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(91, 140, 213);">
    <!-- Diğer dosyalardaki gibi bir navbar eklenebilir. Şimdilik sade bırakıldı. -->
    <div class="container">
        <a class="navbar-brand" href="../index.php">ETİCARET</a>
        <div>
            <a href="logout.php" class="btn btn-outline-light">Çıkış Yap</a>
        </div>
    </div>
</nav>

<div class="dashboard-header">
    <h1>Hoş Geldin, <?php echo htmlspecialchars($username_session); ?>!</h1>
    <p class="lead">Siparişlerini ve hesap bilgilerini buradan yönetebilirsin.</p>
</div>

<div class="container">
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- İstatistik Kartları -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-box-seam-fill"></i></div>
                <h5>Toplam Sipariş Sayısı</h5>
                <p><?php echo $stats['total_orders']; ?></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="icon"><i class="bi bi-wallet-fill"></i></div>
                <h5>Toplam Harcama</h5>
                <p><?php echo number_format($stats['total_spent'], 2, ',', '.'); ?> TL</p>
            </div>
        </div>
    </div>

    <!-- Son Siparişler -->
    <h2 class="section-title">Son Siparişlerin</h2>
    <div class="card">
        <div class="card-body">
            <?php if (!empty($recent_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Tarih</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['Siparis_ID']); ?></td>
                                <td><?php echo date("d.m.Y", strtotime($order['Siparis_Tarihi'])); ?></td>
                                <td><?php echo number_format($order['Siparis_Tutari'], 2, ',', '.'); ?> TL</td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($order['Siparis_Durumu']); ?></span></td>
                                <td><a href="view_order.php?id=<?php echo $order['Siparis_ID']; ?>" class="btn btn-sm btn-outline-primary">Detay</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                 <div class="text-center mt-3">
                    <a href="customer_orders.php" class="btn btn-primary">Tüm Siparişleri Gör</a>
                </div>
            <?php else: ?>
                <p class="text-center text-muted">Henüz hiç sipariş vermediniz.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
