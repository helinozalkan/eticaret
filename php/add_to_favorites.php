<?php
// add_to_favorites.php - Ürünü favorilere ekleme script'i

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit ve değişkenler tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');

// Kullanıcı giriş yapmış mı kontrol et
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Ürünü favorilere eklemek için lütfen giriş yapın.";
    // Giriş yapmamışsa, geldiği sayfaya (veya ürün detayına) yönlendir.
    $redirect_url = isset($_POST['product_id']) ? "product_detail.php?id=" . htmlspecialchars($_POST['product_id']) : "../index.php";
    header(HTTP_HEADER_LOCATION . "login.php?redirect=" . urlencode($redirect_url));
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$product_id_to_add = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id_to_add = (int)$_POST['product_id'];

    if ($product_id_to_add > 0) {
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
            // 1. Ürünün favorilerde olup olmadığını kontrol et
            $stmt_check = $conn->prepare("SELECT Favori_ID FROM Favoriler WHERE Kullanici_ID = :kullanici_id AND Urun_ID = :urun_id");
            $stmt_check->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':urun_id', $product_id_to_add, PDO::PARAM_INT);
            $stmt_check->execute();

            if ($stmt_check->fetch()) {
                // Ürün zaten favorilerde
                $_SESSION['info_message'] = "Bu ürün zaten favorilerinizde.";
            } else {
                // 2. Ürünü favorilere ekle
                $stmt_add = $conn->prepare("INSERT INTO Favoriler (Kullanici_ID, Urun_ID) VALUES (:kullanici_id, :urun_id)");
                $stmt_add->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
                $stmt_add->bindParam(':urun_id', $product_id_to_add, PDO::PARAM_INT);

                if ($stmt_add->execute()) {
                    $_SESSION['success_message'] = "Ürün başarıyla favorilerinize eklendi!";
                } else {
                    $_SESSION['error_message'] = "Ürün favorilere eklenirken bir hata oluştu.";
                }
            }
        } catch (PDOException $e) {
            error_log("add_to_favorites.php: Favoriye ekleme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Favorilere eklenirken teknik bir sorun oluştu.";
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz ürün ID'si.";
    }
} else {
    // POST isteği değilse veya product_id yoksa
    $_SESSION['error_message'] = "Geçersiz istek.";
}

// Kullanıcıyı favoriler sayfasına veya ürünün geldiği sayfaya geri yönlendir
header(HTTP_HEADER_LOCATION . "favourite.php");
exit;
