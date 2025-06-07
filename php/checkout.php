<?php
// checkout.php - Sipariş tamamlama sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
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

// Hata mesajlarını session'dan al ve temizle
$error_message = $_SESSION['checkout_error_message'] ?? $_SESSION['order_error_message'] ?? null;
unset($_SESSION['checkout_error_message'], $_SESSION['order_error_message']);

try {
    // 2. Müşteri ID'sini ve bilgilerini Al
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

    // 3. Sepetteki Ürünleri Çek ve Toplam Tutarı Hesapla
    $stmt_cart_items = $conn->prepare(
        "SELECT s.Urun_ID, s.Miktar, u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu, u.Urun_Gorseli
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

    foreach ($cart_items as $item) {
        if ($item['Aktiflik_Durumu'] != 1) {
            throw new CheckoutException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün artık satışta değil. Lütfen sepetinizden çıkarın.");
        }
        if ($item['Stok_Adedi'] < $item['Miktar']) {
            throw new CheckoutException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün için yeterli stok bulunmamaktadır (Stok: " . $item['Stok_Adedi'] . "). Lütfen miktarını güncelleyin.");
        }
        $total_cart_amount += (float)$item['Urun_Fiyati'] * (int)$item['Miktar'];
    }

} catch (PDOException | CheckoutException $e) {
    error_log("checkout.php Hatası: " . $e->getMessage());
    // Hata mesajını ayarla ama sayfanın geri kalanının yüklenmesini engelleme
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
        body { background-color: #f8f9fa; }
        .checkout-container { max-width: 1140px; }
        .form-section h4 { font-weight: 600; color: #333; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; margin-bottom: 20px; }
        .order-summary-card { background-color: #ffffff; border-radius: 8px; padding: 25px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); position: sticky; top: 20px; }
        .summary-product { display: flex; align-items: center; margin-bottom: 15px; }
        .summary-product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; margin-right: 15px; }
        .summary-product-info h6 { margin-bottom: 2px; font-weight: 600; }
        .summary-product-info small { color: #6c757d; }
        .summary-product-price { font-weight: 600; white-space: nowrap; }
        .summary-totals div { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 1.05em; }
        .summary-totals .grand-total { font-weight: bold; font-size: 1.2em; color: #000; }
    </style>
</head>
<body>

    <div class="container checkout-container my-5">
        <div class="text-center mb-5">
            <h1 class="display-5">Sipariş Tamamlama</h1>
            <p class="lead text-muted">Lütfen siparişinizi tamamlamak için bilgilerinizi eksiksiz doldurun.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row g-5">
            <div class="col-md-7 col-lg-8">
                <!-- ** DÜZELTME: Bu form artık bir hata olsa bile her zaman görünecek ** -->
                <form action="order.php" method="POST" id="checkoutForm" class="needs-validation" novalidate>
                    <div class="form-section">
                        <h4><i class="bi bi-truck me-2"></i>Teslimat Bilgileri</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="shipping_name" class="form-label">Ad Soyad</label>
                                <input type="text" class="form-control" id="shipping_name" name="shipping_name" value="<?php echo $shipping_name; ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir ad soyad girin.</div>
                            </div>
                            <div class="col-12">
                                <label for="shipping_address" class="form-label">Adres</label>
                                <input type="text" class="form-control" id="shipping_address" name="shipping_address" placeholder="Mahalle, Cadde, Sokak, No, Daire" value="<?php echo $shipping_address; ?>" required>
                                <div class="invalid-feedback">Lütfen teslimat adresinizi girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="shipping_city" class="form-label">Şehir</label>
                                <input type="text" class="form-control" id="shipping_city" name="shipping_city" value="<?php echo $shipping_city; ?>" required>
                                <div class="invalid-feedback">Lütfen şehir bilginizi girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="shipping_zip" class="form-label">Posta Kodu</label>
                                <input type="text" class="form-control" id="shipping_zip" name="shipping_zip" value="<?php echo $shipping_zip; ?>" required>
                                <div class="invalid-feedback">Lütfen posta kodunuzu girin.</div>
                            </div>
                            <div class="col-12">
                                <label for="shipping_phone" class="form-label">Telefon Numarası</label>
                                <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?php echo $shipping_phone; ?>" placeholder="5xxxxxxxxx" required pattern="[0-9]{10}">
                                <div class="invalid-feedback">Lütfen geçerli bir 10 haneli telefon numarası girin (başında 0 olmadan).</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="billing_same_as_shipping" name="billing_same_as_shipping" <?php if ($billing_same_as_shipping) echo 'checked'; ?>>
                        <label class="form-check-label" for="billing_same_as_shipping">Fatura adresim teslimat adresimle aynı</label>
                    </div>

                    <div class="form-section mt-4" id="billingAddressSection" style="<?php if ($billing_same_as_shipping) echo 'display: none;'; ?>">
                        <h4><i class="bi bi-receipt me-2"></i>Fatura Bilgileri</h4>
                         <div class="row g-3">
                            <div class="col-12"><input type="text" class="form-control" name="billing_name" placeholder="Ad Soyad" value="<?php echo $billing_name; ?>"></div>
                            <div class="col-12"><input type="text" class="form-control" name="billing_address" placeholder="Adres" value="<?php echo $billing_address; ?>"></div>
                            <div class="col-md-6"><input type="text" class="form-control" name="billing_city" placeholder="Şehir" value="<?php echo $billing_city; ?>"></div>
                            <div class="col-md-6"><input type="text" class="form-control" name="billing_zip" placeholder="Posta Kodu" value="<?php echo $billing_zip; ?>"></div>
                            <div class="col-12"><input type="tel" class="form-control" name="billing_phone" placeholder="Telefon" value="<?php echo $billing_phone; ?>"></div>
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <div class="form-section">
                        <h4><i class="bi bi-credit-card-2-front me-2"></i>Ödeme Yöntemi</h4>
                        <div class="my-3">
                            <div class="form-check">
                                <input id="cod" name="paymentMethod" type="radio" class="form-check-input" checked required value="kapida_odeme">
                                <label class="form-check-label" for="cod">Kapıda Ödeme</label>
                            </div>
                        </div>
                         <p class="text-muted small">Siparişiniz, kapıda ödeme seçeneği ile belirttiğiniz adrese gönderilecektir.</p>
                    </div>

                    <!-- ** DÜZELTME: Buton sepet boşsa veya hata varsa devre dışı bırakılır ** -->
                    <button class="w-100 btn btn-primary btn-lg" type="submit" <?php if(empty($cart_items) || $error_message) echo 'disabled'; ?>>Siparişi Onayla ve Tamamla</button>
                </form>
            </div>

            <div class="col-md-5 col-lg-4">
                <div class="order-summary-card">
                    <h4 class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-primary">Siparişiniz</span>
                        <span class="badge bg-primary rounded-pill"><?php echo count($cart_items); ?></span>
                    </h4>
                    <?php if (!empty($cart_items)): ?>
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="summary-product">
                                    <img src="../uploads/<?= htmlspecialchars($item['Urun_Gorseli']) ?>" alt="<?= htmlspecialchars($item['Urun_Adi']) ?>" class="summary-product-image">
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
                                <span>ÜCRETSİZ</span>
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
        // Fatura adresi checkbox'ı için basit JavaScript
        const billingSameAsShippingCheckbox = document.getElementById('billing_same_as_shipping');
        const billingAddressSection = document.getElementById('billingAddressSection');
        const billingFields = billingAddressSection.querySelectorAll('input');

        function toggleBillingAddress() {
            if (billingSameAsShippingCheckbox.checked) {
                billingAddressSection.style.display = 'none';
                billingFields.forEach(field => field.required = false);
            } else {
                billingAddressSection.style.display = 'block';
            }
        }
        billingSameAsShippingCheckbox.addEventListener('change', toggleBillingAddress);
        toggleBillingAddress();


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
        })()
    </script>
</body>
</html>
