<?php
// update_order_status.php - Güvenli Sipariş Durumu Güncelleme İşlemi

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit ve değişkenler tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');
$redirect_page = 'order_manage.php';


// 1. Satıcı Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header(HTTP_HEADER_LOCATION . "login.php?status=unauthorized");
    exit();
}

// 2. Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(HTTP_HEADER_LOCATION . $redirect_page . "?status=invalid_request");
    exit();
}

// 3. Gelen verileri al ve doğrula
$siparis_id = filter_input(INPUT_POST, 'siparis_id', FILTER_VALIDATE_INT);
$siparis_durumu = filter_input(INPUT_POST, 'siparis_durumu', FILTER_SANITIZE_STRING);

// İzin verilen durumların bir listesini tutmak, güvenliği artırır.
$allowed_statuses = ['Beklemede', 'Kargoda', 'Teslim Edildi', 'İptal Edildi'];

if ($siparis_id === false || $siparis_id <= 0 || !in_array($siparis_durumu, $allowed_statuses)) {
    error_log("update_order_status.php: Geçersiz sipariş ID veya durum. ID: $siparis_id, Durum: $siparis_durumu");
    $_SESSION['order_update_error'] = "Geçersiz veri gönderildi.";
    header(HTTP_HEADER_LOCATION . $redirect_page . "?status=invalid_data");
    exit();
}

try {
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
    $conn->beginTransaction();

    // 4. Oturum açmış olan satıcının Satici_ID'sini al
    $seller_user_id = $_SESSION['user_id'];
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        throw new Exception("Güncelleme yetkisi için satıcı profili bulunamadı.");
    }
    $satici_id = $satici_data['Satici_ID'];

    // 5. GÜVENLİ GÜNCELLEME SORGUSU:
    // Siparişi sadece, içinde bu satıcıya ait en az bir ürün varsa güncelle.
    $sql = "UPDATE Siparis
            SET Siparis_Durumu = :siparis_durumu
            WHERE Siparis_ID = :siparis_id
            AND EXISTS (
                SELECT 1
                FROM SiparisUrun su
                JOIN Urun u ON su.Urun_ID = u.Urun_ID
                WHERE su.Siparis_ID = :siparis_id_check AND u.Satici_ID = :satici_id
            )";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':siparis_durumu', $siparis_durumu, PDO::PARAM_STR);
    $stmt->bindParam(':siparis_id', $siparis_id, PDO::PARAM_INT);
    $stmt->bindParam(':siparis_id_check', $siparis_id, PDO::PARAM_INT);
    $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

    $stmt->execute();

    // 6. Güncellemenin başarılı olup olmadığını kontrol et
    if ($stmt->rowCount() > 0) {
        $conn->commit();
        $_SESSION['order_update_success'] = "Sipariş #" . $siparis_id . " durumu başarıyla '" . htmlspecialchars($siparis_durumu) . "' olarak güncellendi.";
    } else {
        throw new Exception("Sipariş güncellenemedi. Sipariş bulunamadı veya bu siparişi düzenleme yetkiniz yok.");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("update_order_status.php: Hata: " . $e->getMessage());
    $_SESSION['order_update_error'] = $e->getMessage();
}

// 7. İşlem sonrası sipariş yönetimi sayfasına geri yönlendir
header(HTTP_HEADER_LOCATION . $redirect_page);
exit();
