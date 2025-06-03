<?php
// checkout.php - Sipariş tamamlama sayfası

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı (PDO)

// Özel İstisna Sınıfı
class CheckoutException extends Exception {}

// PDO Parametre Sabitleri
define('PARAM_USER_ID_CO', ':user_id'); // _CO (Checkout)
define('PARAM_MUSTERI_ID_CO', ':musteri_id');

// 1. Kullanıcı Giriş ve Rol Kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?status=not_logged_in&return_url=my_cart.php"); // Sepete geri yönlendir
    exit();
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    $_SESSION['checkout_error_message'] = "Sadece müşteriler sipariş verebilir.";
    header("Location: ../index.php?status=unauthorized_checkout"); // Ana sayfaya yönlendir
    exit();
}
$user_id = $_SESSION['user_id'];
$musteri_id = null;
$cart_items = [];
$total_cart_amount = 0;

// Hata mesajlarını göstermek için
$error_message = $_SESSION['checkout_error_message'] ?? $_SESSION['order_error_message'] ?? null;
unset($_SESSION['checkout_error_message']); // Mesajı gösterdikten sonra sil
unset($_SESSION['order_error_message']);

try {
    // 2. Müşteri ID'sini Al
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID, Ad_Soyad, Tel_No, Adres FROM musteri WHERE User_ID = " . PARAM_USER_ID_CO); // Kayıtlı adres ve telefon bilgilerini de alabiliriz
    $stmt_musteri->bindParam(PARAM_USER_ID_CO, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
        throw new CheckoutException("Müşteri profiliniz bulunamadı. Lütfen destek ile iletişime geçin.");
    }
    $musteri_id = $musteri_data['Musteri_ID'];
    // Formu önceden doldurmak için müşteri bilgilerini alalım
    $customer_name_default = $musteri_data['Ad_Soyad'] ?? '';
    $customer_phone_default = $musteri_data['Tel_No'] ?? '';
    $customer_address_default = $musteri_data['Adres'] ?? '';


    // 3. Sepetteki Ürünleri Çek ve Toplam Tutarı Hesapla
    $stmt_cart_items = $conn->prepare(
        "SELECT s.Urun_ID, s.Miktar, s.Boyut, u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu, u.Urun_Gorseli
         FROM Sepet s
         JOIN Urun u ON s.Urun_ID = u.Urun_ID
         WHERE s.Musteri_ID = " . PARAM_MUSTERI_ID_CO
    );
    $stmt_cart_items->bindParam(PARAM_MUSTERI_ID_CO, $musteri_id, PDO::PARAM_INT);
    $stmt_cart_items->execute();
    $cart_items = $stmt_cart_items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        $_SESSION['cart_message'] = "Sepetiniz boş. Sipariş vermek için lütfen ürün ekleyin.";
        header("Location: my_cart.php?status=cart_empty");
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

    // CSRF token oluştur (her sayfa yüklemesinde veya form gönderilmediyse)
    // if (empty($_POST) && (!isset($_SESSION['csrf_token_checkout']) || empty($_SESSION['csrf_token_checkout']))) {
    //     if (function_exists('random_bytes')) {
    //         $_SESSION['csrf_token_checkout'] = bin2hex(random_bytes(32));
    //     } else {
    //         $_SESSION['csrf_token_checkout'] = bin2hex(openssl_random_pseudo_bytes(32));
    //     }
    // }

} catch (PDOException $e) {
    error_log("checkout.php PDOException: " . $e->getMessage());
    $error_message = "Sayfa yüklenirken bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
} catch (CheckoutException $e) {
    error_log("checkout.php CheckoutException: " . $e->getMessage());
    $error_message = $e->getMessage(); // Özel hata mesajını göster
} catch (Exception $e) {
    error_log("checkout.php Generic Exception: " . $e->getMessage());
    $error_message = "Beklenmedik bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
}

// Formdan gelen değerleri tutmak için (hata durumunda formu tekrar doldurmak için)
$shipping_name = htmlspecialchars($_POST['shipping_name'] ?? $customer_name_default ?? '');
$shipping_address = htmlspecialchars($_POST['shipping_address'] ?? $customer_address_default ?? '');
$shipping_city = htmlspecialchars($_POST['shipping_city'] ?? '');
$shipping_zip = htmlspecialchars($_POST['shipping_zip'] ?? '');
$shipping_phone = htmlspecialchars($_POST['shipping_phone'] ?? $customer_phone_default ?? '');

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
    <title>Sipariş Tamamlama - El Emek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css"> <!-- Genel CSS dosyanız -->
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container.checkout-container {
            margin-top: 30px;
            margin-bottom: 30px;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .cart-summary img {
            max-width: 60px;
            height: auto;
            border-radius: 4px;
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .btn-place-order {
            background-color: rgb(155, 10, 109);
            border-color: rgb(155, 10, 109);
            color: white;
            padding: 10px 25px;
            font-size: 1.1em;
        }
        .btn-place-order:hover {
            background-color: rgb(125, 8, 89);
            border-color: rgb(125, 8, 89);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .alert-checkout {
            border-left: 5px solid #dc3545; /* Hata mesajları için */
        }
    </style>
</head>
<body>

    <?php // İsteğe bağlı: Navigasyon barınızı buraya include edebilirsiniz
        // include_once 'partials/navbar.php';
    ?>

    <div class="container checkout-container">
        <h1 class="text-center mb-4">Sipariş Tamamlama</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-checkout" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Sol Taraf: Sipariş Özeti -->
            <div class="col-lg-5 order-lg-2 mb-4">
                <h4>Sipariş Özeti</h4>
                <?php if (!empty($cart_items)): ?>
                    <ul class="list-group mb-3">
                        <?php foreach ($cart_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between lh-sm">
                                <div>
                                    <h6 class="my-0"><?php echo htmlspecialchars($item['Urun_Adi']); ?></h6>
                                    <small class="text-muted">Miktar: <?php echo (int)$item['Miktar']; ?>
                                        <?php if($item['Boyut']): // Boyut varsa göster ?>
                                            | Boyut: <?php echo htmlspecialchars($item['Boyut']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <span class="text-muted"><?php echo number_format((float)$item['Urun_Fiyati'] * (int)$item['Miktar'], 2, ',', '.'); ?> TL</span>
                            </li>
                        <?php endforeach; ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Toplam (TL)</span>
                            <strong><?php echo number_format($total_cart_amount, 2, ',', '.'); ?> TL</strong>
                        </li>
                    </ul>
                <?php else: ?>
                    <p>Sepetinizde ürün bulunmamaktadır.</p>
                    <a href="../index.php" class="btn btn-outline-primary">Alışverişe Devam Et</a>
                <?php endif; ?>
            </div>

            <!-- Sağ Taraf: Adres ve Ödeme Bilgileri Formu -->
            <div class="col-lg-7 order-lg-1">
                <?php if (!empty($cart_items)): // Sepet boş değilse formu göster ?>
                <form action="order.php" method="POST" id="checkoutForm" novalidate>
                    <div class="form-section">
                        <h4>Teslimat Adresi</h4>
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
                                <input type="tel" class="form-control" id="shipping_phone" name="shipping_phone" value="<?php echo $shipping_phone; ?>" placeholder="05xxxxxxxxx" required>
                                <div class="invalid-feedback">Lütfen geçerli bir telefon numarası girin.</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="billing_same_as_shipping" name="billing_same_as_shipping" <?php if ($billing_same_as_shipping) echo 'checked'; ?>>
                        <label class="form-check-label" for="billing_same_as_shipping">Fatura adresim teslimat adresimle aynı</label>
                    </div>

                    <hr class="my-4">

                    <div class="form-section" id="billingAddressSection" style="<?php if ($billing_same_as_shipping) echo 'display: none;'; ?>">
                        <h4>Fatura Adresi</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="billing_name" class="form-label">Ad Soyad (Fatura)</label>
                                <input type="text" class="form-control" id="billing_name" name="billing_name" value="<?php echo $billing_name; ?>">
                            </div>
                            <div class="col-12">
                                <label for="billing_address" class="form-label">Adres (Fatura)</label>
                                <input type="text" class="form-control" id="billing_address" name="billing_address" placeholder="Mahalle, Cadde, Sokak, No, Daire" value="<?php echo $billing_address; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="billing_city" class="form-label">Şehir (Fatura)</label>
                                <input type="text" class="form-control" id="billing_city" name="billing_city" value="<?php echo $billing_city; ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="billing_zip" class="form-label">Posta Kodu (Fatura)</label>
                                <input type="text" class="form-control" id="billing_zip" name="billing_zip" value="<?php echo $billing_zip; ?>">
                            </div>
                             <div class="col-12">
                                <label for="billing_phone" class="form-label">Telefon Numarası (Fatura)</label>
                                <input type="tel" class="form-control" id="billing_phone" name="billing_phone" value="<?php echo $billing_phone; ?>" placeholder="05xxxxxxxxx">
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">

                    <div class="form-section">
                        <h4>Ödeme Yöntemi</h4>
                        <p class="text-muted">Not: Şu anda sadece "Kapıda Ödeme" seçeneği mevcuttur. Diğer ödeme yöntemleri yakında eklenecektir.</p>
                        <div class="my-3">
                            <div class="form-check">
                                <input id="cod" name="paymentMethod" type="radio" class="form-check-input" checked required value="kapida_odeme">
                                <label class="form-check-label" for="cod">Kapıda Ödeme</label>
                            </div>
                            <!-- Diğer ödeme yöntemleri buraya eklenebilir (örn: kredi kartı) -->
                        </div>
                    </div>

                    <button class="w-100 btn btn-lg btn-place-order" type="submit">Siparişi Ver</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php // İsteğe bağlı: Footer'ınızı buraya include edebilirsiniz
        // include_once 'partials/footer.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fatura adresi checkbox'ı için basit JavaScript
        const billingSameAsShippingCheckbox = document.getElementById('billing_same_as_shipping');
        const billingAddressSection = document.getElementById('billingAddressSection');
        const billingFields = billingAddressSection.querySelectorAll('input');

        function toggleBillingAddress() {
            if (billingSameAsShippingCheckbox.checked) {
                billingAddressSection.style.display = 'none';
                billingFields.forEach(field => field.required = false); // Zorunluluğu kaldır
            } else {
                billingAddressSection.style.display = 'block';
                // İhtiyaç duyulan fatura alanlarını tekrar zorunlu yapabilirsiniz
                // Örneğin: document.getElementById('billing_name').required = true;
            }
        }
        billingSameAsShippingCheckbox.addEventListener('change', toggleBillingAddress);
        // Sayfa yüklendiğinde durumu kontrol et
        toggleBillingAddress();


        // Bootstrap'in varsayılan form validasyonunu etkinleştirme
        (function () {
          'use strict'
          var forms = document.querySelectorAll('.needs-validation, #checkoutForm') // checkoutForm'u da ekledik
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
