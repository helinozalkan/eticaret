<?php
// order.php - Sipariş oluşturma ve işleme mantığı

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı (PDO)

// Özel İstisna Sınıfı
class OrderPlacementException extends Exception {}

// PDO Parametre Sabitleri
define('PARAM_USER_ID_OP', ':user_id'); // _OP (Order Placement)
define('PARAM_MUSTERI_ID_OP', ':musteri_id');
define('PARAM_URUN_ID_OP', ':urun_id');
define('PARAM_SIPARIS_ID_OP', ':siparis_id');
define('PARAM_MIKTAR_OP', ':miktar');
define('PARAM_FIYAT_OP', ':fiyat');

// Yönlendirme için varsayılanlar
$redirect_url = 'my_cart.php'; // Hata durumunda genellikle sepet sayfasına dönülür
$redirect_status = 'order_processing_failed';
$redirect_type = 'error';

try {
    // 1. Kullanıcı Giriş ve Rol Kontrolü
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: login.php?status=not_logged_in&return_url=my_cart.php");
        exit();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        error_log("order.php: Yetkisiz sipariş denemesi. User_ID: " . $_SESSION['user_id'] . ", Role: " . ($_SESSION['role'] ?? 'N/A'));
        throw new OrderPlacementException("Sipariş verme yetkiniz bulunmamaktadır.");
    }
    $user_id = $_SESSION['user_id'];

    // 2. CSRF Token Kontrolü (Formdan CSRF token gönderildiğini varsayıyoruz)
    // Bu script genellikle my_cart.php veya checkout.php'den bir POST isteği ile tetiklenir.
    // if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //     if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //         throw new OrderPlacementException("Geçersiz istek (CSRF). Lütfen tekrar deneyin.");
    //     }
    // } else {
    //     throw new OrderPlacementException("Geçersiz istek türü.");
    // }

    // 3. Müşteri ID'sini Al
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . PARAM_USER_ID_OP);
    $stmt_musteri->bindParam(PARAM_USER_ID_OP, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
        error_log("order.php: Müşteri kaydı bulunamadı. User_ID: " . $user_id);
        throw new OrderPlacementException("Sipariş oluşturulurken müşteri profiliniz bulunamadı.");
    }
    $musteri_id = $musteri_data['Musteri_ID'];

    // 4. Sepetteki Ürünleri Çek
    $stmt_cart_items = $conn->prepare(
        "SELECT s.Urun_ID, s.Miktar, u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu
         FROM Sepet s
         JOIN Urun u ON s.Urun_ID = u.Urun_ID
         WHERE s.Musteri_ID = " . PARAM_MUSTERI_ID_OP
    );
    $stmt_cart_items->bindParam(PARAM_MUSTERI_ID_OP, $musteri_id, PDO::PARAM_INT);
    $stmt_cart_items->execute();
    $cart_items = $stmt_cart_items->fetchAll(PDO::FETCH_ASSOC);

    if (empty($cart_items)) {
        throw new OrderPlacementException("Sepetiniz boş. Sipariş oluşturmak için lütfen ürün ekleyin.");
    }

    // 5. Sipariş Detaylarını Hazırla (Fiyat, Stok Kontrolü ve Toplam Tutar)
    $siparis_tutari = 0;
    $urunler_siparis_icin = []; // SiparisUrun tablosuna eklenecek ürünler

    foreach ($cart_items as $item) {
        if ($item['Aktiflik_Durumu'] != 1) {
            throw new OrderPlacementException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün artık satışta değil.");
        }
        if ($item['Stok_Adedi'] < $item['Miktar']) {
            throw new OrderPlacementException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün için yeterli stok bulunmamaktadır (Stok: " . $item['Stok_Adedi'] . ", İstenen: " . $item['Miktar'] . "). Lütfen sepetinizi güncelleyin.");
        }
        // Sipariş anındaki güncel fiyatı kullan
        $urun_fiyati_siparis_aninda = (float)$item['Urun_Fiyati'];
        $siparis_tutari += $urun_fiyati_siparis_aninda * $item['Miktar'];
        $urunler_siparis_icin[] = [
            'urun_id' => $item['Urun_ID'],
            'miktar' => $item['Miktar'],
            'fiyat' => $urun_fiyati_siparis_aninda,
        
        ];
    }

    if ($siparis_tutari <= 0) { // Ek bir kontrol
        throw new OrderPlacementException("Sipariş tutarı hesaplanırken bir sorun oluştu veya sepetiniz boş.");
    }

    // 6. Teslimat ve Fatura Adresleri (Şimdilik varsayılan, checkout.php'den alınmalı)
    // Bu bilgiler normalde checkout sürecinde kullanıcıdan alınır veya kayıtlı adreslerinden seçtirilir.
    // YENİ KOD
    // checkout.php formundan gelen teslimat ve fatura adreslerini alıyoruz.
    // htmlspecialchars ile güvenlik önlemi alıyoruz.
    $shipping_name = htmlspecialchars($_POST['shipping_name'] ?? '');
    $shipping_address = htmlspecialchars($_POST['shipping_address'] ?? '');
    $shipping_city = htmlspecialchars($_POST['shipping_city'] ?? '');
    $shipping_zip = htmlspecialchars($_POST['shipping_zip'] ?? '');

    // Teslimat adresini tek bir metin olarak birleştirelim
    $teslimat_adresi = $shipping_name . ", " . $shipping_address . ", " . $shipping_zip . " " . $shipping_city;

    // Fatura adresi, teslimat ile aynıysa onu kullan, değilse fatura formu verilerini al
    if (isset($_POST['billing_same_as_shipping'])) {
        $fatura_adresi = $teslimat_adresi;
    } else {
        $billing_name = htmlspecialchars($_POST['billing_name'] ?? '');
        $billing_address = htmlspecialchars($_POST['billing_address'] ?? '');
        $billing_city = htmlspecialchars($_POST['billing_city'] ?? '');
        $billing_zip = htmlspecialchars($_POST['billing_zip'] ?? '');
        $fatura_adresi = $billing_name . ", " . $billing_address . ", " . $billing_zip . " " . $billing_city;
    }
    // 7. Veritabanı İşlemleri (Transaction)
    $conn->beginTransaction();

    // Siparis tablosuna ekle
    $siparis_tarihi = date('Y-m-d');
    $siparis_durumu = 'Beklemede'; // Veya 'Yeni Sipariş', 'Hazırlanıyor' vb.

    $stmt_insert_order = $conn->prepare(
        "INSERT INTO Siparis (Musteri_ID, Siparis_Tarihi, Siparis_Tutari, Teslimat_Adresi, Fatura_Adresi, Siparis_Durumu)
         VALUES (:musteri_id, :siparis_tarihi, :siparis_tutari, :teslimat_adresi, :fatura_adresi, :siparis_durumu)"
    );
    $stmt_insert_order->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
    $stmt_insert_order->bindParam(':siparis_tarihi', $siparis_tarihi, PDO::PARAM_STR);
    $stmt_insert_order->bindParam(':siparis_tutari', $siparis_tutari); // PDO ondalık sayıları doğru işler
    $stmt_insert_order->bindParam(':teslimat_adresi', $teslimat_adresi, PDO::PARAM_STR);
    $stmt_insert_order->bindParam(':fatura_adresi', $fatura_adresi, PDO::PARAM_STR);
    $stmt_insert_order->bindParam(':siparis_durumu', $siparis_durumu, PDO::PARAM_STR);

    if (!$stmt_insert_order->execute()) {
        $conn->rollBack();
        throw new OrderPlacementException("Sipariş ana kaydı oluşturulurken bir hata oluştu.");
    }
    $siparis_id = $conn->lastInsertId();

    // SiparisUrun tablosuna ürünleri ekle
    $stmt_insert_order_product = $conn->prepare(
        "INSERT INTO SiparisUrun (Siparis_ID, Urun_ID, Miktar, Fiyat)
         VALUES (" . PARAM_SIPARIS_ID_OP . ", " . PARAM_URUN_ID_OP . ", " . PARAM_MIKTAR_OP . ", " . PARAM_FIYAT_OP . ")"
    );
    foreach ($urunler_siparis_icin as $urun) {
        $stmt_insert_order_product->bindParam(PARAM_SIPARIS_ID_OP, $siparis_id, PDO::PARAM_INT);
        $stmt_insert_order_product->bindParam(PARAM_URUN_ID_OP, $urun['urun_id'], PDO::PARAM_INT);
        $stmt_insert_order_product->bindParam(PARAM_MIKTAR_OP, $urun['miktar'], PDO::PARAM_INT);
        $stmt_insert_order_product->bindParam(PARAM_FIYAT_OP, $urun['fiyat']);

        if (!$stmt_insert_order_product->execute()) {
            $conn->rollBack();
            throw new OrderPlacementException("Sipariş ürünleri kaydedilirken bir hata oluştu.");
        }
    }

    // Stokları güncelle
    $stmt_update_stock = $conn->prepare(
        "UPDATE Urun SET Stok_Adedi = Stok_Adedi - " . PARAM_MIKTAR_OP .
        " WHERE Urun_ID = " . PARAM_URUN_ID_OP
    );
    foreach ($urunler_siparis_icin as $urun) {
        $stmt_update_stock->bindParam(PARAM_MIKTAR_OP, $urun['miktar'], PDO::PARAM_INT);
        $stmt_update_stock->bindParam(PARAM_URUN_ID_OP, $urun['urun_id'], PDO::PARAM_INT);
        if (!$stmt_update_stock->execute()) {
            $conn->rollBack();
            // Stok güncelleme hatası kritik olabilir, siparişi iptal et
            throw new OrderPlacementException("Ürün stokları güncellenirken bir hata oluştu.");
        }
    }

    // Sepeti Temizle
    $stmt_clear_cart = $conn->prepare("DELETE FROM Sepet WHERE Musteri_ID = " . PARAM_MUSTERI_ID_OP);
    $stmt_clear_cart->bindParam(PARAM_MUSTERI_ID_OP, $musteri_id, PDO::PARAM_INT);
    if (!$stmt_clear_cart->execute()) {
        $conn->rollBack();
        // Sepet temizleme hatası siparişi etkilememeli ama loglanmalı.
        // Ancak tutarlılık için rollback yapılabilir.
        error_log("order.php: Sipariş sonrası sepet temizlenirken hata oluştu. Musteri_ID: " . $musteri_id);
        throw new OrderPlacementException("Sipariş sonrası sepetiniz temizlenirken bir sorun oluştu.");
    }

    $conn->commit();

    // Başarılı sipariş sonrası
    $_SESSION['last_order_id'] = $siparis_id; // Sipariş ID'sini başarı sayfasına taşımak için
    $redirect_url = 'order_success.php';
    $redirect_status = 'order_placed_successfully';
    $redirect_type = 'success';

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("order.php PDOException: " . $e->getMessage());
    $_SESSION['order_error_message'] = "Siparişiniz işlenirken bir veritabanı hatası oluştu. Lütfen tekrar deneyin.";
} catch (OrderPlacementException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("order.php OrderPlacementException: " . $e->getMessage());
    $_SESSION['order_error_message'] = $e->getMessage(); // Hata mesajını session'a ata
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("order.php Generic Exception: " . $e->getMessage());
    $_SESSION['order_error_message'] = "Siparişiniz işlenirken beklenmedik bir hata oluştu.";
}

// CSRF token'ını yenile (eğer kullanılıyorsa)
// if (function_exists('random_bytes')) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// } else {
//     $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
// }

// Kullanıcıyı yönlendir
// Hata mesajları session'da taşındığı için, ilgili sayfa (my_cart.php veya checkout.php) bu mesajları göstermeli.
header("Location: " . $redirect_url . "?status=" . $redirect_status . "&type=" . $redirect_type);
exit();
