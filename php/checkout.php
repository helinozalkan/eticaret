<?php
// checkout.php - Sipariş tamamlama sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gerekli tüm dosyaları dahil et
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');

class CheckoutException extends Exception {}

define('PARAM_USER_ID_CO', ':user_id');
define('PARAM_MUSTERI_ID_CO', ':musteri_id');

// 1. Kullanıcı Giriş ve Rol Kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header(HTTP_HEADER_LOCATION . "login.php?status=not_logged_in&return_url=my_cart.php");
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['checkout_error_message'] = "Sadece müşteriler sipariş verebilir.";
    header(HTTP_HEADER_LOCATION . "../index.php?status=unauthorized_checkout");
    exit();
}

$user_id = $_SESSION['user_id'];
$musteri_id = null;
$cart_items = [];
$total_cart_amount = 0.0;
$customer_name_default = '';
$customer_phone_default = '';
$customer_address_default = '';

$error_message = $_SESSION['checkout_error_message'] ?? $_SESSION['order_error_message'] ?? null;
unset($_SESSION['checkout_error_message'], $_SESSION['order_error_message']);

try {
    // 2. Müşteri ID'sini ve bilgilerini Al
    // users tablosundan username'i almak için JOIN eklendi.
    $stmt_musteri = $conn->prepare(
        "SELECT m.Musteri_ID, u.username, m.Tel_No, m.Adres 
         FROM musteri m 
         JOIN users u ON m.User_ID = u.id 
         WHERE m.User_ID = " . PARAM_USER_ID_CO
    );
    $stmt_musteri->bindParam(PARAM_USER_ID_CO, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
        throw new CheckoutException("Müşteri profiliniz bulunamadı. Lütfen destek ile iletişime geçin.");
    }
    $musteri_id = $musteri_data['Musteri_ID'];
    $customer_name_default = $musteri_data['username'] ?? '';       
    $customer_phone_default = $musteri_data['Tel_No'] ?? '';
    $customer_address_default = $musteri_data['Adres'] ?? '';

    // 3. Sepetteki Ürünleri Çek
    // Factory Pattern kaldırıldığı için sorgu basitleştirilebilir, ancak mevcut haliyle de çalışır.
    $stmt_cart_items = $conn->prepare(
        "SELECT s.Urun_ID, s.Miktar, 
                u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu, u.Urun_Gorseli
         FROM Sepet s
         JOIN Urun u ON s.Urun_ID = u.Urun_ID
         WHERE s.Musteri_ID = " . PARAM_MUSTERI_ID_CO
    );
    $stmt_cart_items->bindParam(PARAM_MUSTERI_ID_CO, $musteri_id, PDO::PARAM_INT);
    $stmt_cart_items->execute();
    $cart_items = $stmt_cart_items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $_SESSION['cart_message'] = "Sepetiniz boş. Sipariş vermek için lütfen ürün ekleyin.";
        header(HTTP_HEADER_LOCATION . "my_cart.php?status=cart_empty");
        exit();
    }
    
    // Stok kontrolü ve toplam tutar hesaplaması - DOĞRUDAN VERİLERLE YAPILDI
    foreach ($cart_items as $item) {
        if ($item['Stok_Adedi'] < $item['Miktar']) {
            throw new CheckoutException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün için yeterli stok bulunmamaktadır (Stok: " . $item['Stok_Adedi'] . "). Lütfen miktarını güncelleyin.");
        }
        $total_cart_amount += (float)$item['Urun_Fiyati'] * (int)$item['Miktar'];
    }

} catch (PDOException | CheckoutException $e) {
    error_log("checkout.php Hatası: " . $e->getMessage());
    $error_message = ($e instanceof PDOException) ? "Sayfa yüklenirken bir veritabanı hatası oluştu." : $e->getMessage();
}

// Hata durumunda formu tekrar doldurmak için POST verilerini veya müşteri verilerini kullan
$shipping_name = htmlspecialchars($_POST['shipping_name'] ?? $customer_name_default);
$shipping_address = htmlspecialchars($_POST['shipping_address'] ?? $customer_address_default);
$shipping_city = htmlspecialchars($_POST['shipping_city'] ?? '');
$shipping_zip = htmlspecialchars($_POST['shipping_zip'] ?? '');
$shipping_phone = htmlspecialchars($_POST['shipping_phone'] ?? $customer_phone_default);

$billing_same_as_shipping = isset($_POST['billing_same_as_shipping']);
$billing_name = htmlspecialchars($_POST['billing_name'] ?? ($billing_same_as_shipping ? $shipping_name : ''));
$billing_address = htmlspecialchars($_POST['billing_address'] ?? ($billing_same_as_shipping ? $shipping_address : ''));
$billing_city = htmlspecialchars($_POST['billing_city'] ?? ($billing_same_as_shipping ? $shipping_city : ''));
$billing_zip = htmlspecialchars($_POST['billing_zip'] ?? ($billing_same_as_shipping ? $shipping_zip : ''));
$billing_phone = htmlspecialchars($_POST['billing_phone'] ?? ($billing_same_as_shipping ? $shipping_phone : ''));

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Tamamlama - E-Ticaret</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Montserrat', sans-serif; }
        .checkout-container { margin-top: 2rem; margin-bottom: 2rem; }
        .order-summary-card { background-color: #ffffff; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .summary-product { display: flex; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;}
        .summary-product:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .summary-product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 0.375rem; margin-right: 1rem; }
        .summary-product-info { flex-grow: 1; }
        .summary-product-info h6 { font-size: 0.95rem; font-weight: 600; margin-bottom: 0.2rem; }
        .summary-product-info small { color: #6c757d; }
        .summary-product-price { font-weight: 600; color: #343a40; }
        .summary-totals div { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .grand-total { font-size: 1.2rem; font-weight: 700; }
    </style>
</head>
<body>

    <div class="container checkout-container">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">Sipariş Tamamlama</h1>
            <p class="lead text-muted">Lütfen siparişinizi tamamlamak için bilgilerinizi eksiksiz doldurun.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row g-5">
            <!-- Sol Taraf: Adres Formu -->
            <div class="col-md-7 col-lg-8">
                <form action="order.php" method="POST" id="checkoutForm" class="needs-validation" novalidate>
                    <!-- Teslimat Adresi -->
                    <h4 class="mb-3">Teslimat Adresi</h4>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="shipping_name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="shipping_name" name="shipping_name" value="<?php echo $shipping_name; ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="shipping_address" class="form-label">Adres</label>
                            <input type="text" class="form-control" id="shipping_address" name="shipping_address" value="<?php echo $shipping_address; ?>" placeholder="Mahalle, Cadde, Sokak, No, Daire" required>
                        </div>
                        <div class="col-md-6">
                            <label for="shipping_city" class="form-label">Şehir</label>
                            <input type="text" class="form-control" id="shipping_city" name="shipping_city" value="<?php echo $shipping_city; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="shipping_zip" class="form-label">Posta Kodu</label>
                            <input type="text" class="form-control" id="shipping_zip" name="shipping_zip" value="<?php echo $shipping_zip; ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="shipping_phone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?php echo $shipping_phone; ?>" placeholder="05xxxxxxxxx" required>
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <!-- Ödeme Yöntemi -->
                    <h4 class="mb-3">Ödeme Yöntemi</h4>
                    <div class="my-3">
                        <div class="form-check">
                            <input id="cod" name="paymentMethod" type="radio" class="form-check-input" checked required value="kapida_odeme">
                            <label class="form-check-label" for="cod">Kapıda Ödeme</label>
                        </div>
                    </div>

                    <hr class="my-4">

                    <button class="w-100 btn btn-primary btn-lg" type="submit" <?php if(empty($cart_items) || $error_message) echo 'disabled'; ?>>Siparişi Onayla ve Tamamla</button>
                </form>
            </div>

            <!-- Sağ Taraf: Sipariş Özeti -->
            <div class="col-md-5 col-lg-4 order-md-last">
                <div class="order-summary-card">
                    <h4 class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-primary">Siparişiniz</span>
                        <span class="badge bg-primary rounded-pill"><?php echo count($cart_items); ?></span>
                    </h4>
                    <?php if (!empty($cart_items)): ?>
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-product">
                                    <img src="../uploads/<?= htmlspecialchars($item['Urun_Gorseli'] ?: 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['Urun_Adi']) ?>" class="summary-product-image">
                                    <div class="summary-product-info">
                                        <h6><?php echo htmlspecialchars($item['Urun_Adi']); ?></h6>
                                        <small>Miktar: <?php echo (int)$item['Miktar']; ?></small>
                                    </div>
                                    <span class="summary-product-price"><?php echo number_format((float)$item['Urun_Fiyati'] * (int)$item['Miktar'], 2, ',', '.'); ?> TL</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <div class="summary-totals">
                            <div>
                                <span>Ara Toplam</span>
                                <span><?php echo number_format($total_cart_amount, 2, ',', '.'); ?> TL</span>
                            </div>
                             <div>
                                <span>Kargo</span>
                                <span class="text-success">ÜCRETSİZ</span>
                            </div>
                            <hr>
                            <div class="grand-total">
                                <span>Genel Toplam</span>
                                <span><?php echo number_format($total_cart_amount, 2, ',', '.'); ?> TL</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Sepetinizde ürün bulunmamaktadır.</p>
                        <a href="../index.php" class="btn btn-outline-primary w-100">Alışverişe Başla</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap'in varsayılan form validasyonunu etkinleştirme
        (function () {
          'use strict'
          var forms = document.querySelectorAll('.needs-validation')
          Array.prototype.slice.call(forms)
            .forEach(function (form) {
              form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                  event.preventDefault()
                  event.stopPropagation()
                }
                form.classList.add('was-validated')
              }, false)
            })
        })();
    </script>
</body>
</html>
