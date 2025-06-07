<?php
// update_cart.php - Sepet güncelleme (miktar artırma/azaltma, ürün silme)

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
$cart_page = 'my_cart.php';


// 1. Kullanıcı Giriş Kontrolü
if (!isset($_SESSION['user_id'])) {
    // Bu bir arka plan scripti olduğu için genellikle JSON yanıtı döndürmek daha iyidir,
    // ancak mevcut yapıda yönlendirme kullanılıyor.
    header(HTTP_HEADER_LOCATION . "login.php?status=not_logged_in");
    exit();
}

// 2. Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header(HTTP_HEADER_LOCATION . $cart_page . "?status=invalid_request");
    exit();
}

// 3. Gerekli verileri al ve doğrula
$sepet_id = filter_input(INPUT_POST, 'sepet_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$sepet_id || !$action) {
    header(HTTP_HEADER_LOCATION . $cart_page . "?status=missing_data");
    exit();
}

try {
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
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
header(HTTP_HEADER_LOCATION . $cart_page);
exit();