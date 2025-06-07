<?php
// checkout.php - Sipariş tamamlama sayfası (Factory Pattern ile güncellendi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Gerekli tüm dosyaları dahil et
include_once '../database.php';
include_once __DIR__ . '/models/AbstractProduct.php';
include_once __DIR__ . '/models/GenericProduct.php';
include_once __DIR__ . '/models/CeramicProduct.php';
include_once __DIR__ . '/models/DokumaProduct.php';
include_once __DIR__ . '/factories/ProductFactory.php';

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

    // 3. Sepetteki Ürünleri Çek ve Nesnelere Dönüştür
    // YENİ: Sorguya kategori adı da dahil edildi.
    $stmt_cart_items = $conn->prepare(
        "SELECT s.Urun_ID, s.Miktar, 
                u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu, u.Urun_Gorseli, u.Urun_Aciklamasi,
                k.Kategori_Adi
         FROM Sepet s
         JOIN Urun u ON s.Urun_ID = u.Urun_ID
         LEFT JOIN KategoriUrun ku ON u.Urun_ID = ku.Urun_ID
         LEFT JOIN Kategoriler k ON ku.Kategori_ID = k.Kategori_ID
         WHERE s.Musteri_ID = " . PARAM_MUSTERI_ID_CO . "
         GROUP BY s.Sepet_ID"
    );
    $stmt_cart_items->bindParam(PARAM_MUSTERI_ID_CO, $musteri_id, PDO::PARAM_INT);
    $stmt_cart_items->execute();
    $cartDataFromDb = $stmt_cart_items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cartDataFromDb)) {
        $_SESSION['cart_message'] = "Sepetiniz boş. Sipariş vermek için lütfen ürün ekleyin.";
        header(HTTP_HEADER_LOCATION . "my_cart.php?status=cart_empty");
        exit();
    }
    
    // YENİ: Ham veriyi nesnelerle zenginleştir
    foreach ($cartDataFromDb as $itemData) {
        $itemData['product_object'] = ProductFactory::create($itemData);
        $cart_items[] = $itemData;
    }

    // YENİ: Kontrolleri ve hesaplamaları nesneler üzerinden yap
    foreach ($cart_items as $item) {
        $productObject = $item['product_object'];
        if ($productObject->getStock() < $item['Miktar']) {
            throw new CheckoutException("Sepetinizdeki '" . htmlspecialchars($productObject->getName()) . "' adlı ürün için yeterli stok bulunmamaktadır (Stok: " . $productObject->getStock() . "). Lütfen miktarını güncelleyin.");
        }
        $total_cart_amount += $productObject->getPrice() * (int)$item['Miktar'];
    }

} catch (PDOException | CheckoutException $e) {
    error_log("checkout.php Hatası: " . $e->getMessage());
    $error_message = ($e instanceof PDOException) ? "Sayfa yüklenirken bir veritabanı hatası oluştu." : $e->getMessage();
}

// Hata durumunda formu tekrar doldurmak için POST verilerini veya müşteri verilerini kullan
$shipping_name = htmlspecialchars($_POST['shipping_name'] ?? $customer_name_default);
$shipping_address = htmlspecialchars($_POST['shipping_address'] ?? $customer_address_default);
// ... (Form doldurma değişkenleri aynı kalabilir) ...

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
        /* CSS Stilleri aynı kalabilir */
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
                <form action="order.php" method="POST" id="checkoutForm" class="needs-validation" novalidate>
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
                                <?php $productObject = $item['product_object']; // Okunabilirliği artırmak için nesneyi bir değişkene alalım ?>
                                <div class="summary-product">
                                    <img src="../uploads/<?= htmlspecialchars($productObject->getImageUrl()) ?>" alt="<?= htmlspecialchars($productObject->getName()) ?>" class="summary-product-image">
                                    <div class="summary-product-info">
                                        <h6><?php echo htmlspecialchars($productObject->getName()); ?></h6>
                                        <small>Miktar: <?php echo (int)$item['Miktar']; ?></small>
                                    </div>
                                    <span class="summary-product-price"><?php echo number_format($productObject->getPrice() * (int)$item['Miktar'], 2, ',', '.'); ?> TL</span>
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
