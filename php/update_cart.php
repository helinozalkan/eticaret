<?php
// update_cart.php - Sepet güncelleme (miktar artırma/azaltma, ürün silme)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı

// 1. Kullanıcı Giriş Kontrolü
if (!isset($_SESSION['user_id'])) {
    // API tarzı bir yanıt döndürmek daha iyi olabilir ama şimdilik yönlendirme yapalım.
    header("Location: login.php?status=not_logged_in");
    exit();
}

// 2. Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my_cart.php?status=invalid_request");
    exit();
}

// 3. Gerekli verileri al ve doğrula
$sepet_id = filter_input(INPUT_POST, 'sepet_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$sepet_id || !$action) {
    header("Location: my_cart.php?status=missing_data");
    exit();
}

try {
    // 4. Müşteri ID'sini al (güvenlik için: kullanıcı sadece kendi sepetini güncelleyebilmeli)
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
    $stmt_musteri->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
        throw new Exception("Müşteri profili bulunamadı.");
    }
    $musteri_id = $musteri_data['Musteri_ID'];
    
    $conn->beginTransaction();

    // 5. Eyleme (action) göre veritabanı işlemini yap
    switch ($action) {
        case 'increment':
            // Stok kontrolü burada yapılabilir (opsiyonel ama önerilir)
            $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar + 1 WHERE Sepet_ID = :sepet_id AND Musteri_ID = :musteri_id");
            break;

        case 'decrement':
            // Miktar 1'den büyükse azalt, değilse işlem yapma (veya sil)
            $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar - 1 WHERE Sepet_ID = :sepet_id AND Musteri_ID = :musteri_id AND Miktar > 1");
            break;
            
        case 'remove':
             $stmt = $conn->prepare("DELETE FROM Sepet WHERE Sepet_ID = :sepet_id AND Musteri_ID = :musteri_id");
            break;

        default:
            throw new Exception("Geçersiz işlem türü.");
    }

    $stmt->bindParam(':sepet_id', $sepet_id, PDO::PARAM_INT);
    $stmt->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $conn->commit();

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Sepet Güncelleme Hatası: " . $e->getMessage());
    // Hata durumunda kullanıcıya bir mesaj göstermek için session kullanılabilir.
    $_SESSION['cart_error'] = "Sepet güncellenirken bir hata oluştu.";
}

// 6. İşlem sonrası sepet sayfasına geri yönlendir
header("Location: my_cart.php");
exit();

?>