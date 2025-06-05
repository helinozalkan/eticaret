<?php
// update_order_status.php - Güvenli Sipariş Durumu Güncelleme İşlemi
session_start();
include('../database.php'); // Veritabanı bağlantısı

// 1. Satıcı Yetki Kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

// 2. Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: order_manage.php?status=invalid_request");
    exit();
}

// 3. Gelen verileri al ve doğrula
$siparis_id = filter_input(INPUT_POST, 'siparis_id', FILTER_VALIDATE_INT);
$siparis_durumu = filter_input(INPUT_POST, 'siparis_durumu', FILTER_SANITIZE_STRING);

$allowed_statuses = ['Beklemede', 'Kargoda', 'Teslim Edildi', 'İptal Edildi'];

if ($siparis_id === false || $siparis_id <= 0 || !in_array($siparis_durumu, $allowed_statuses)) {
    // Geçersiz veri durumunda hata günlüğü tut ve yönlendir
    error_log("update_order_status.php: Geçersiz sipariş ID veya durum. ID: $siparis_id, Durum: $siparis_durumu");
    header("Location: order_manage.php?status=invalid_data");
    exit();
}

try {
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
    $stmt->bindParam(':siparis_id_check', $siparis_id, PDO::PARAM_INT); // EXISTS içindeki kontrol için
    $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

    $stmt->execute();

    // 6. Güncellemenin başarılı olup olmadığını kontrol et
    if ($stmt->rowCount() > 0) {
        // Başarılı güncelleme
        $conn->commit();
        header("Location: order_manage.php?status=success&order_id=" . $siparis_id);
    } else {
        // Sipariş bulunamadı veya satıcının bu siparişi güncelleme yetkisi yok
        throw new Exception("Sipariş güncellenemedi. Sipariş bulunamadı veya bu siparişi düzenleme yetkiniz yok.");
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("update_order_status.php: Hata: " . $e->getMessage());
    // Kullanıcıya daha genel bir hata mesajı göstermek için session kullanılabilir
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: order_manage.php?status=error");
}
exit();
?>