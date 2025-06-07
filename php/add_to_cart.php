<?php
// add_to_cart.php - Sepete ürün ekleme fonksiyonu

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit ve değişkenler tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');

// PDO parametreleri için sabitler
define('PARAM_USER_ID_ATC', ':user_id');
define('PARAM_MUSTERI_ID_ATC', ':musteri_id');
define('PARAM_URUN_ID_ATC', ':urun_id');
define('PARAM_BOYUT_ATC', ':boyut');
define('PARAM_MIKTAR_ATC', ':miktar');
define('PARAM_EKLENME_TARIHI_ATC', ':eklenme_tarihi');
define('PARAM_NEW_MIKTAR_ATC', ':new_miktar');
define('PARAM_SEPET_ID_ATC', ':sepet_id');


class AddToCartException extends Exception {}

// Yönlendirme için varsayılanlar
$redirect_url = 'my_cart.php';
$redirect_status = 'unexpected_error_adding_to_cart';
$redirect_type = 'error';

$urun_id_posted = null;
$miktar_posted = null;

try {
    // 1. Kullanıcı Giriş ve Rol Kontrolü
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        $product_page_url = $_SERVER['HTTP_REFERER'] ?? '../index.php';
        header(HTTP_HEADER_LOCATION . "login.php?status=not_logged_in&return_url=" . urlencode($product_page_url));
        exit();
    }

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        error_log("add_to_cart.php: Yetkisiz erişim denemesi. User_ID: " . $_SESSION['user_id'] . ", Role: " . ($_SESSION['role'] ?? 'N/A'));
        throw new AddToCartException("Sadece müşteriler sepete ürün ekleyebilir.");
    }
    $user_id = $_SESSION['user_id'];

    // 3. Gelen Verilerin Alınması ve Kapsamlı Validasyonu
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new AddToCartException("Geçersiz istek türü. Sadece POST istekleri kabul edilir.");
    }

    $urun_id_posted = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
    $boyut_posted = filter_input(INPUT_POST, 'boyut', FILTER_VALIDATE_INT);
    $miktar_posted = filter_input(INPUT_POST, 'miktar', FILTER_VALIDATE_INT);

    if ($urun_id_posted === false || $urun_id_posted <= 0) throw new AddToCartException("Geçersiz ürün kimliği (urun_id).");
    if ($boyut_posted === false || $boyut_posted <= 0) throw new AddToCartException("Geçersiz veya eksik boyut bilgisi.");
    if ($miktar_posted === false || $miktar_posted <= 0) throw new AddToCartException("Geçersiz miktar. Miktar en az 1 olmalıdır.");
    

    // 4. Ürün Mevcudiyeti ve Stok Kontrolü
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
    $stmt_check_product = $conn->prepare("SELECT Stok_Adedi, Aktiflik_Durumu FROM Urun WHERE Urun_ID = " . PARAM_URUN_ID_ATC);
    $stmt_check_product->bindParam(PARAM_URUN_ID_ATC, $urun_id_posted, PDO::PARAM_INT);
    $stmt_check_product->execute();
    $product_data = $stmt_check_product->fetch(PDO::FETCH_ASSOC);

    if (!$product_data) throw new AddToCartException("Eklenmek istenen ürün bulunamadı.");
    if ($product_data['Aktiflik_Durumu'] != 1) throw new AddToCartException("Bu ürün şu anda satışta değil.");
    if ($product_data['Stok_Adedi'] < $miktar_posted) throw new AddToCartException("Yetersiz stok. Bu üründen en fazla " . $product_data['Stok_Adedi'] . " adet ekleyebilirsiniz.");


    // 5. Müşteri ID'sini Al veya Oluştur
    $musteri_id = null;
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . PARAM_USER_ID_ATC);
    $stmt_musteri->bindParam(PARAM_USER_ID_ATC, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_fetch_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if ($musteri_fetch_data) {
        $musteri_id = $musteri_fetch_data['Musteri_ID'];
    } else {
        error_log("add_to_cart.php: Müşteri tablosunda User_ID için kayıt bulunamadı: " . $user_id);
        throw new AddToCartException("Müşteri profilinizle ilgili bir sorun oluştu. Lütfen destek ile iletişime geçin.");
    }

    // 6. Veritabanı İşlemleri (Transaction İçinde)
    $conn->beginTransaction();

    // Ürünün sepette olup olmadığını kontrol et
    $stmt_check_cart = $conn->prepare(
        "SELECT Sepet_ID, Miktar FROM sepet WHERE Musteri_ID = " . PARAM_MUSTERI_ID_ATC .
        " AND Urun_ID = " . PARAM_URUN_ID_ATC . " AND Boyut = " . PARAM_BOYUT_ATC
    );
    $stmt_check_cart->bindParam(PARAM_MUSTERI_ID_ATC, $musteri_id, PDO::PARAM_INT);
    $stmt_check_cart->bindParam(PARAM_URUN_ID_ATC, $urun_id_posted, PDO::PARAM_INT);
    $stmt_check_cart->bindParam(PARAM_BOYUT_ATC, $boyut_posted, PDO::PARAM_INT);
    $stmt_check_cart->execute();
    $cart_item = $stmt_check_cart->fetch(PDO::FETCH_ASSOC);

    $eklenme_tarihi = date('Y-m-d H:i:s');
    $query_executed = false;

    if ($cart_item) {
        // Miktarı güncelle
        $new_miktar = $cart_item['Miktar'] + $miktar_posted;
        if ($product_data['Stok_Adedi'] < $new_miktar) {
            throw new AddToCartException("Sepetinizdeki bu ürünle birlikte toplam miktar stoğu aşıyor. En fazla " . ($product_data['Stok_Adedi'] - $cart_item['Miktar']) . " adet daha ekleyebilirsiniz.");
        }

        $stmt_update_cart = $conn->prepare(
            "UPDATE sepet SET Miktar = " . PARAM_NEW_MIKTAR_ATC . ", Eklenme_Tarihi = " . PARAM_EKLENME_TARIHI_ATC .
            " WHERE Sepet_ID = " . PARAM_SEPET_ID_ATC
        );
        $stmt_update_cart->bindParam(PARAM_NEW_MIKTAR_ATC, $new_miktar, PDO::PARAM_INT);
        $stmt_update_cart->bindParam(PARAM_EKLENME_TARIHI_ATC, $eklenme_tarihi, PDO::PARAM_STR);
        $stmt_update_cart->bindParam(PARAM_SEPET_ID_ATC, $cart_item['Sepet_ID'], PDO::PARAM_INT);
        $query_executed = $stmt_update_cart->execute();
    } else {
        // Yeni ürün ekle
        $stmt_insert_cart = $conn->prepare(
            "INSERT INTO sepet (Boyut, Miktar, Eklenme_Tarihi, Urun_ID, Musteri_ID) VALUES (" .
            PARAM_BOYUT_ATC . ", " . PARAM_MIKTAR_ATC . ", " . PARAM_EKLENME_TARIHI_ATC . ", " .
            PARAM_URUN_ID_ATC . ", " . PARAM_MUSTERI_ID_ATC . ")"
        );
        $stmt_insert_cart->bindParam(PARAM_BOYUT_ATC, $boyut_posted, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam(PARAM_MIKTAR_ATC, $miktar_posted, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam(PARAM_EKLENME_TARIHI_ATC, $eklenme_tarihi, PDO::PARAM_STR);
        $stmt_insert_cart->bindParam(PARAM_URUN_ID_ATC, $urun_id_posted, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam(PARAM_MUSTERI_ID_ATC, $musteri_id, PDO::PARAM_INT);
        $query_executed = $stmt_insert_cart->execute();
    }

    if ($query_executed) {
        $conn->commit();
        $redirect_status = 'item_added_to_cart';
        $redirect_type = 'success';
    } else {
        $conn->rollBack();
        throw new AddToCartException("Ürün sepete eklenirken bir veritabanı hatası oluştu.");
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("add_to_cart.php PDOException: " . $e->getMessage());
    $redirect_status = 'db_error_on_add_to_cart';
} catch (AddToCartException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("add_to_cart.php AddToCartException: " . $e->getMessage());
    $redirect_status = 'cart_operation_failed';
    $_SESSION['cart_error_message'] = $e->getMessage();
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("add_to_cart.php Generic Exception: " . $e->getMessage());
    $redirect_status = 'unexpected_system_error_on_add';
}


// 7. Kullanıcıyı Yönlendir
header(HTTP_HEADER_LOCATION . $redirect_url . "?status=" . $redirect_status . "&type=" . $redirect_type);
exit();

