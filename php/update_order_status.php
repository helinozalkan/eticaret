<?php
// update_order_status.php - Sipariş durumu güncelleme işlemi
session_start(); // Oturumu başlat
include('database.php'); // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    // Eğer satıcı giriş yapmamışsa veya yetkisi yoksa, giriş sayfasına yönlendir
    header("Location: login.php?status=unauthorized");
    exit();
}

// Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gelen verileri al ve doğrula
    $siparis_id = filter_input(INPUT_POST, 'siparis_id', FILTER_VALIDATE_INT);
    $siparis_durumu = filter_input(INPUT_POST, 'siparis_durumu', FILTER_SANITIZE_STRING);

    // Geçerli sipariş durumları listesi
    $allowed_statuses = ['Beklemede', 'Kargoda', 'Teslim Edildi', 'İptal Edildi']; // "İptal Edildi" ekledim, ihtiyaca göre düzenlenebilir

    // Giriş validasyonu
    if ($siparis_id === false || $siparis_id <= 0) {
        error_log("update_order_status.php: Geçersiz sipariş ID'si: " . ($siparis_id === false ? 'false' : $siparis_id));
        header("Location: order_manage.php?status=invalid_order_id");
        exit();
    }

    if (!in_array($siparis_durumu, $allowed_statuses)) {
        error_log("update_order_status.php: Geçersiz sipariş durumu: " . $siparis_durumu);
        header("Location: order_manage.php?status=invalid_status");
        exit();
    }

    try {
        // Satıcının kendi siparişlerini güncellediğinden emin olmak için ek kontrol (isteğe bağlı ama önerilir)
        // Bu, siparişin ilgili satıcıya ait ürünleri içerip içermediğini kontrol etmeyi gerektirir.
        // Mevcut yapıda Siparis tablosunda Satici_ID yok, bu yüzden bu kontrolü doğrudan yapamayız.
        // Eğer Siparis tablosuna Satici_ID eklenirse bu kontrol yapılabilir.
        // Alternatif olarak, SiparisUrun tablosu üzerinden ürünün satıcısına ulaşılabilir.

        // Örnek: Eğer Siparis tablosunda Satici_ID olsaydı:
        // $stmt_check_ownership = $conn->prepare("SELECT COUNT(*) FROM Siparis WHERE Siparis_ID = :siparis_id AND Satici_ID = :satici_id");
        // $stmt_check_ownership->bindParam(':siparis_id', $siparis_id, PDO::PARAM_INT);
        // $stmt_check_ownership->bindParam(':satici_id', $_SESSION['user_id'], PDO::PARAM_INT); // Assuming Satici_ID is User_ID
        // $stmt_check_ownership->execute();
        // if ($stmt_check_ownership->fetchColumn() == 0) {
        //     header("Location: order_manage.php?status=not_authorized_to_update");
        //     exit();
        // }

        // Sipariş durumu güncelleme
        $stmt = $conn->prepare("UPDATE Siparis SET Siparis_Durumu = :siparis_durumu WHERE Siparis_ID = :siparis_id");
        $stmt->bindParam(':siparis_durumu', $siparis_durumu);
        $stmt->bindParam(':siparis_id', $siparis_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Başarılı güncelleme
            header("Location: order_manage.php?status=success");
            exit();
        } else {
            // Sorgu çalıştı ama hiçbir satır etkilenmediyse (örn. sipariş ID'si bulunamadıysa)
            error_log("update_order_status.php: Sipariş güncellenirken hiçbir satır etkilenmedi. Siparis ID: " . $siparis_id);
            header("Location: order_manage.php?status=error");
            exit();
        }

    } catch (PDOException $e) {
        // Veritabanı hatası durumunda hata logla ve kullanıcıyı yönlendir
        error_log("update_order_status.php: Veritabanı hatası: " . $e->getMessage());
        header("Location: order_manage.php?status=db_error");
        exit();
    }
} else {
    // POST dışı istekler için yönlendirme
    header("Location: order_manage.php?status=invalid_request");
    exit();
}
?>
