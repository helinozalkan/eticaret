<?php
// order.php - Sipariş oluşturma ve işleme mantığı

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

// Özel İstisna Sınıfı
class OrderPlacementException extends Exception {}

// PDO Parametre Sabitleri
define('PARAM_USER_ID_OP', ':user_id');
define('PARAM_MUSTERI_ID_OP', ':musteri_id');
define('PARAM_URUN_ID_OP', ':urun_id');
define('PARAM_SIPARIS_ID_OP', ':siparis_id');
define('PARAM_MIKTAR_OP', ':miktar');
define('PARAM_FIYAT_OP', ':fiyat');

// Yönlendirme için varsayılanlar
$redirect_url = 'checkout.php'; // Hata durumunda checkout sayfasına dön
$redirect_status = 'order_processing_failed';

try {
    // 1. Kullanıcı Giriş ve Rol Kontrolü
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header(HTTP_HEADER_LOCATION . "login.php?status=not_logged_in&return_url=my_cart.php");
        exit();
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        throw new OrderPlacementException("Sipariş verme yetkiniz bulunmamaktadır.");
    }
    $user_id = $_SESSION['user_id'];
    
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
    // 3. Müşteri ID'sini Al
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . PARAM_USER_ID_OP);
    $stmt_musteri->bindParam(PARAM_USER_ID_OP, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
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
    $urunler_siparis_icin = [];

    foreach ($cart_items as $item) {
        if ($item['Aktiflik_Durumu'] != 1) throw new OrderPlacementException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün artık satışta değil.");
        if ($item['Stok_Adedi'] < $item['Miktar']) throw new OrderPlacementException("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün için yeterli stok bulunmamaktadır.");
        
        $urun_fiyati_siparis_aninda = (float)$item['Urun_Fiyati'];
        $siparis_tutari += $urun_fiyati_siparis_aninda * $item['Miktar'];
        $urunler_siparis_icin[] = ['urun_id' => $item['Urun_ID'], 'miktar' => $item['Miktar'], 'fiyat' => $urun_fiyati_siparis_aninda];
    }

    // 6. Teslimat ve Fatura Adresleri
    $shipping_name = htmlspecialchars($_POST['shipping_name'] ?? '');
    $shipping_address = htmlspecialchars($_POST['shipping_address'] ?? '');
    $shipping_city = htmlspecialchars($_POST['shipping_city'] ?? '');
    $shipping_zip = htmlspecialchars($_POST['shipping_zip'] ?? '');
    $teslimat_adresi = $shipping_name . ", " . $shipping_address . ", " . $shipping_zip . " " . $shipping_city;
    $fatura_adresi = isset($_POST['billing_same_as_shipping']) ? $teslimat_adresi : (htmlspecialchars($_POST['billing_name'] ?? '') . ", " . htmlspecialchars($_POST['billing_address'] ?? ''));

    // 7. Veritabanı İşlemleri (Transaction)
    $conn->beginTransaction();

    // Siparis tablosuna ekle
    $stmt_insert_order = $conn->prepare("INSERT INTO Siparis (Musteri_ID, Siparis_Tarihi, Siparis_Tutari, Teslimat_Adresi, Fatura_Adresi, Siparis_Durumu) VALUES (:musteri_id, CURDATE(), :siparis_tutari, :teslimat_adresi, :fatura_adresi, 'Beklemede')");
    $stmt_insert_order->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
    $stmt_insert_order->bindParam(':siparis_tutari', $siparis_tutari);
    $stmt_insert_order->bindParam(':teslimat_adresi', $teslimat_adresi, PDO::PARAM_STR);
    $stmt_insert_order->bindParam(':fatura_adresi', $fatura_adresi, PDO::PARAM_STR);
    if (!$stmt_insert_order->execute()) throw new OrderPlacementException("Sipariş ana kaydı oluşturulurken bir hata oluştu.");
    $siparis_id = $conn->lastInsertId();

    // SiparisUrun tablosuna ürünleri ekle ve stokları güncelle
    $stmt_insert_order_product = $conn->prepare("INSERT INTO SiparisUrun (Siparis_ID, Urun_ID, Miktar, Fiyat) VALUES (" . PARAM_SIPARIS_ID_OP . ", " . PARAM_URUN_ID_OP . ", " . PARAM_MIKTAR_OP . ", " . PARAM_FIYAT_OP . ")");
    $stmt_update_stock = $conn->prepare("UPDATE Urun SET Stok_Adedi = Stok_Adedi - " . PARAM_MIKTAR_OP . " WHERE Urun_ID = " . PARAM_URUN_ID_OP);
    
    foreach ($urunler_siparis_icin as $urun) {
        $stmt_insert_order_product->execute([PARAM_SIPARIS_ID_OP => $siparis_id, PARAM_URUN_ID_OP => $urun['urun_id'], PARAM_MIKTAR_OP => $urun['miktar'], PARAM_FIYAT_OP => $urun['fiyat']]);
        $stmt_update_stock->execute([PARAM_MIKTAR_OP => $urun['miktar'], PARAM_URUN_ID_OP => $urun['urun_id']]);
    }

    // Sepeti Temizle
    $stmt_clear_cart = $conn->prepare("DELETE FROM Sepet WHERE Musteri_ID = " . PARAM_MUSTERI_ID_OP);
    $stmt_clear_cart->bindParam(PARAM_MUSTERI_ID_OP, $musteri_id, PDO::PARAM_INT);
    if (!$stmt_clear_cart->execute()) throw new OrderPlacementException("Sipariş sonrası sepetiniz temizlenirken bir sorun oluştu.");

    $conn->commit();

    $_SESSION['last_order_id'] = $siparis_id;
    header(HTTP_HEADER_LOCATION . "order_success.php");
    exit();

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("order.php Exception: " . $e->getMessage());
    $_SESSION['order_error_message'] = $e->getMessage();
    header(HTTP_HEADER_LOCATION . $redirect_url . "?status=" . $redirect_status);
    exit();
}
