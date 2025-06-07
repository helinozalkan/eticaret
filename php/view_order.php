<?php
// view_order.php - Sipariş görüntüleme sayfası (PDO ile güncellendi ve güvenlik eklendi)

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Sadece müşteri rolündeki kullanıcıların devam edebilmesini sağla
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$order = null; // Sipariş verisini tutacak değişken
$error_message = '';

// URL'den gelen sipariş ID'sini al
if (isset($_GET['id'])) {
    $order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if ($order_id) {
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
            // GÜVENLİK ADIMI: Müşterinin sadece kendi siparişini görebilmesi için
            // önce oturumdaki User_ID'den Musteri_ID'yi bulalım.
            $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
            $stmt_musteri->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt_musteri->execute();
            $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

            if ($musteri_data) {
                $musteri_id = $musteri_data['Musteri_ID'];

                // PDO ile güvenli sorgu hazırlama
                $stmt = $conn->prepare("SELECT * FROM Siparis WHERE Siparis_ID = :order_id AND Musteri_ID = :musteri_id");
                $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                $stmt->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT); // Sadece kendi siparişini çek
                $stmt->execute();
                
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    $error_message = "Sipariş bulunamadı veya bu siparişi görme yetkiniz yok.";
                }
            } else {
                 $error_message = "Müşteri profili bulunamadı.";
            }

        } catch (PDOException $e) {
            error_log("view_order.php PDO Hatası: " . $e->getMessage());
            $error_message = "Sipariş detayı getirilirken bir hata oluştu.";
        }
    } else {
        $error_message = "Geçersiz sipariş ID'si.";
    }
} else {
    $error_message = "Sipariş ID'si belirtilmemiş.";
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Stil kodları değişmediği için aynı kalıyor -->
    <style>
        body { background-color: #f8f9fa; }
        .card { max-width: 700px; margin: 40px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Sipariş Detayı</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($order): ?>
                    <p><strong>Sipariş No:</strong> #<?php echo htmlspecialchars($order['Siparis_ID']); ?></p>
                    <p><strong>Sipariş Tarihi:</strong> <?php echo date("d.m.Y", strtotime($order['Siparis_Tarihi'])); ?></p>
                    <p><strong>Durum:</strong> <?php echo htmlspecialchars($order['Siparis_Durumu']); ?></p>
                    <p><strong>Toplam Tutar:</strong> <?php echo number_format($order['Siparis_Tutari'], 2, ',', '.'); ?> TL</p>
                    <hr>
                    <p><strong>Teslimat Adresi:</strong> <?php echo htmlspecialchars($order['Teslimat_Adresi']); ?></p>
                    <p><strong>Fatura Adresi:</strong> <?php echo htmlspecialchars($order['Fatura_Adresi']); ?></p>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <div class="mt-4">
                    <a href="customer_orders.php" class="btn btn-secondary">Siparişlerime Dön</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
