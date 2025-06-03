<?php
// add_to_cart.php - Sepete ürün ekleme fonksiyonu

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı (PDO)

// PDO parametreleri için sabitler
define('PARAM_USER_ID_ATC', ':user_id'); // _ATC (Add To Cart) ekiyle diğer dosyalardaki sabitlerden ayırmak için
define('PARAM_MUSTERI_ID_ATC', ':musteri_id');
define('PARAM_URUN_ID_ATC', ':urun_id');
define('PARAM_BOYUT_ATC', ':boyut');
define('PARAM_MIKTAR_ATC', ':miktar');
define('PARAM_EKLENME_TARIHI_ATC', ':eklenme_tarihi');
define('PARAM_NEW_MIKTAR_ATC', ':new_miktar');
define('PARAM_SEPET_ID_ATC', ':sepet_id');

/**
 * Sepete ekleme işlemleri sırasında oluşabilecek özel istisna sınıfı.
 */
class AddToCartException extends Exception {}

// Yönlendirme için varsayılanlar
$redirect_url = 'my_cart.php'; // Varsayılan olarak sepet sayfasına yönlendir
$redirect_status = 'unexpected_error_adding_to_cart';
$redirect_type = 'error';

// Değişkenleri try bloğu dışında tanımla
$urun_id_posted = null;
$boyut_posted = null;
$miktar_posted = null;

try {
    // 1. Kullanıcı Giriş ve Rol Kontrolü
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        // Kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
        // return_url ile sepete eklemeye çalıştığı sayfaya geri dönebilir
        $product_page_url = $_SERVER['HTTP_REFERER'] ?? '../index.php'; // Geldiği sayfayı al, yoksa ana sayfa
        header("Location: login.php?status=not_logged_in&return_url=" . urlencode($product_page_url));
        exit();
    }

    // Sadece 'customer' rolündeki kullanıcılar sepete ekleyebilir
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        error_log("add_to_cart.php: Yetkisiz erişim denemesi. User_ID: " . $_SESSION['user_id'] . ", Role: " . ($_SESSION['role'] ?? 'N/A'));
        throw new AddToCartException("Sadece müşteriler sepete ürün ekleyebilir.");
    }
    $user_id = $_SESSION['user_id'];

    // 2. CSRF Token Kontrolü (Formdan CSRF token gönderildiğini varsayıyoruz)
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     throw new AddToCartException("Geçersiz istek (CSRF). Lütfen tekrar deneyin.");
    // }

    // 3. Gelen Verilerin Alınması ve Kapsamlı Validasyonu
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new AddToCartException("Geçersiz istek türü. Sadece POST istekleri kabul edilir.");
    }

    $urun_id_posted = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
    $boyut_posted = filter_input(INPUT_POST, 'boyut', FILTER_VALIDATE_INT); // Boyutun integer olduğunu varsayıyoruz
    $miktar_posted = filter_input(INPUT_POST, 'miktar', FILTER_VALIDATE_INT);

    if ($urun_id_posted === false || $urun_id_posted <= 0) {
        throw new AddToCartException("Geçersiz ürün kimliği (urun_id).");
    }
    // Boyut zorunlu değilse veya bazı ürünlerde yoksa bu kontrol esnetilebilir.
    // Şimdilik zorunlu ve pozitif bir tamsayı olduğunu varsayıyoruz.
    if ($boyut_posted === false || $boyut_posted <= 0) {
        // Eğer boyut her ürün için geçerli değilse veya farklı türlerdeyse (örn: S, M, L stringleri)
        // bu validasyon ve veritabanı şeması (Sepet.Boyut) ona göre güncellenmeli.
        // Şimdilik varsayılan bir boyut (örn: 1) atanabilir veya hata verilebilir.
        // Varsayılan olarak 1 atayalım, eğer ürünün boyutu yoksa.
        // $boyut_posted = 1; // VEYA hata fırlat:
        throw new AddToCartException("Geçersiz veya eksik boyut bilgisi.");
    }
    if ($miktar_posted === false || $miktar_posted <= 0) {
        throw new AddToCartException("Geçersiz miktar. Miktar en az 1 olmalıdır.");
    }
    if ($miktar_posted > 100) { // Maksimum miktar sınırı (örnek)
        throw new AddToCartException("Tek seferde en fazla 100 adet ürün ekleyebilirsiniz.");
    }

    // 4. Ürün Mevcudiyeti ve Stok Kontrolü
    $stmt_check_product = $conn->prepare("SELECT Stok_Adedi, Aktiflik_Durumu FROM Urun WHERE Urun_ID = " . PARAM_URUN_ID_ATC);
    $stmt_check_product->bindParam(PARAM_URUN_ID_ATC, $urun_id_posted, PDO::PARAM_INT);
    $stmt_check_product->execute();
    $product_data = $stmt_check_product->fetch(PDO::FETCH_ASSOC);

    if (!$product_data) {
        throw new AddToCartException("Eklenmek istenen ürün bulunamadı.");
    }
    if ($product_data['Aktiflik_Durumu'] != 1) {
        throw new AddToCartException("Bu ürün şu anda satışta değil.");
    }
    if ($product_data['Stok_Adedi'] < $miktar_posted) {
        throw new AddToCartException("Yetersiz stok. Bu üründen en fazla " . $product_data['Stok_Adedi'] . " adet ekleyebilirsiniz.");
    }


    // 5. Müşteri ID'sini Al veya Oluştur
    $musteri_id = null;
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . PARAM_USER_ID_ATC);
    $stmt_musteri->bindParam(PARAM_USER_ID_ATC, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_fetch_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if ($musteri_fetch_data) {
        $musteri_id = $musteri_fetch_data['Musteri_ID'];
    } else {
        // Müşteri kaydı yoksa oluştur (Bu senaryo register.php'de ele alınıyor olmalı)
        // Ancak bir güvenlik katmanı olarak burada da kontrol edilebilir veya hata verilebilir.
        // Şimdilik, musteri tablosunda User_ID'ye karşılık gelen bir kayıt olması gerektiğini varsayıyoruz.
        error_log("add_to_cart.php: Müşteri tablosunda User_ID için kayıt bulunamadı: " . $user_id);
        throw new AddToCartException("Müşteri profilinizle ilgili bir sorun oluştu. Lütfen destek ile iletişime geçin.");
    }

    // 6. Veritabanı İşlemleri (Transaction İçinde)
    $conn->beginTransaction();

    // Ürünün sepette olup olmadığını kontrol et (aynı ürün ve aynı boyut için)
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
        // Aynı ürün (ve boyut) sepette varsa miktarı güncelle
        $new_miktar = $cart_item['Miktar'] + $miktar_posted;
        // Yeni miktarın stoğu aşıp aşmadığını tekrar kontrol et
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
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("add_to_cart.php PDOException: " . $e->getMessage() . "(Urun_ID: " . ($urun_id_posted ?? 'N/A') . ", Miktar: " . ($miktar_posted ?? 'N/A') . ")");
    $redirect_status = 'db_error_on_add_to_cart';
} catch (AddToCartException $e) { // Kendi özel istisnamız
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("add_to_cart.php AddToCartException: " . $e->getMessage() . " (Urun_ID: " . ($urun_id_posted ?? 'N/A') . ", Miktar: " . ($miktar_posted ?? 'N/A') . ")");
    // Hata mesajına göre redirect_status ayarlanabilir
    // Örneğin: if (strpos($e->getMessage(), 'Yetersiz stok') !== false) $redirect_status = 'insufficient_stock';
    $redirect_status = 'cart_operation_failed'; // Daha genel bir hata
    $_SESSION['cart_error_message'] = $e->getMessage(); // Detaylı hata mesajını session'a ata
} catch (Exception $e) { // Diğer tüm genel istisnalar
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("add_to_cart.php Generic Exception: " . $e->getMessage() . " (Urun_ID: " . ($urun_id_posted ?? 'N/A') . ", Miktar: " . ($miktar_posted ?? 'N/A') . ")");
    $redirect_status = 'unexpected_system_error_on_add';
}

// 7. Kullanıcıyı Yönlendir
// CSRF token'ını her istek sonrası yenilemek iyi bir pratiktir
// if (function_exists('random_bytes')) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// } else {
//     $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
// }

// Genellikle kullanıcıyı sepet sayfasına veya geldiği ürün sayfasına geri yönlendirmek iyi bir UX'dir.
// Şimdilik sepet sayfasına yönlendirelim.
// Eğer hata mesajı varsa, my_cart.php bunu session'dan okuyup gösterebilir.
header("Location: " . $redirect_url . "?status=" . $redirect_status . "&type=" . $redirect_type);
exit();

