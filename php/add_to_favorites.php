<?php
// add_to_favorites.php - Ürünü favorilere ekleme script'i

session_start();

include_once '../database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');

// Kullanıcı giriş yapmış mı kontrol et
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Ürünü favorilere eklemek için lütfen giriş yapın.";
    // Kullanıcıyı, geldiği ürün sayfasına geri yönlendirerek giriş yapmasını sağla
    $redirect_url = isset($_POST['product_id']) ? "product_detail.php?id=" . (int)$_POST['product_id'] : "favourite.php";
    header(HTTP_HEADER_LOCATION . "login.php?redirect=" . urlencode($redirect_url));
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$product_id_to_add = null;
$redirect_to_product_page = 'product_detail.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id_to_add = (int)$_POST['product_id'];
    $redirect_to_product_page .= '?id=' . $product_id_to_add;

    if ($product_id_to_add > 0) {
        try {
            // Ürünün favorilerde olup olmadığını kontrol et
            $stmt_check = $conn->prepare("SELECT Favori_ID FROM Favoriler WHERE Kullanici_ID = :kullanici_id AND Urun_ID = :urun_id");
            $stmt_check->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_check->bindParam(':urun_id', $product_id_to_add, PDO::PARAM_INT);
            $stmt_check->execute();

            if ($stmt_check->fetch()) {
                $_SESSION['info_message'] = "Bu ürün zaten favorilerinizde.";
            } else {
                // Ürünü favorilere ekle
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
    $_SESSION['error_message'] = "Geçersiz istek.";
}

// İşlem sonrası kullanıcıyı favoriler sayfasına yönlendir
header(HTTP_HEADER_LOCATION . 'favourite.php');
exit;