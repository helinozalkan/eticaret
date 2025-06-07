<?php
// favourite.php - Favorilerim Sayfası

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');
$redirect_page = 'favourite.php';


// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null;
$username_session = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;
$current_user_role = $logged_in && isset($_SESSION['role']) ? $_SESSION['role'] : null;


$favorite_products = []; // Favori ürünleri tutacak dizi
$message = "";           // Mesajları tutacak değişken

// Sadece giriş yapmış kullanıcılar favorilerini görebilir
if (!$logged_in) {
    $_SESSION['error_message'] = "Favorilerinizi görmek için lütfen giriş yapın.";
    header(HTTP_HEADER_LOCATION . "login.php?redirect=" . $redirect_page); // Giriş sonrası favorilere yönlendir
    exit;
}

// Favorilerden ürün çıkarma işlemi (POST ile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_favorites'])) {
    if (isset($_POST['urun_id']) && $current_user_id) {
        $urun_id_to_remove = (int)$_POST['urun_id'];
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
            $stmt_remove = $conn->prepare("DELETE FROM Favoriler WHERE Kullanici_ID = :kullanici_id AND Urun_ID = :urun_id");
            $stmt_remove->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_remove->bindParam(':urun_id', $urun_id_to_remove, PDO::PARAM_INT);
            if ($stmt_remove->execute()) {
                $_SESSION['success_message'] = "Ürün favorilerden başarıyla çıkarıldı.";
            } else {
                $_SESSION['error_message'] = "Ürün favorilerden çıkarılırken bir hata oluştu.";
            }
            header(HTTP_HEADER_LOCATION . $redirect_page); // Sayfayı yenile
            exit;
        } catch (PDOException $e) {
            error_log("favourite.php: Favori silme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Favori silinirken teknik bir sorun oluştu.";
            header(HTTP_HEADER_LOCATION . $redirect_page);
            exit;
        }
    }
}


// Kullanıcının favori ürünlerini çek
if ($current_user_id) {
    try {
        $sql_favorites = "SELECT u.Urun_ID, u.Urun_Adi, u.Urun_Fiyati, u.Urun_Gorseli
                          FROM Favoriler f
                          JOIN Urun u ON f.Urun_ID = u.Urun_ID
                          WHERE f.Kullanici_ID = :kullanici_id AND u.Aktiflik_Durumu = 1
                          ORDER BY f.Ekleme_Tarihi DESC";

        $stmt_favorites = $conn->prepare($sql_favorites);
        $stmt_favorites->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
        $stmt_favorites->execute();
        $favorite_products = $stmt_favorites->fetchAll(PDO::FETCH_ASSOC);

        if (empty($favorite_products) && !isset($_SESSION['success_message']) && !isset($_SESSION['error_message'])) {
            $message = "Henüz favorilerinize eklediğiniz bir ürün bulunmuyor.";
        }

    } catch (PDOException $e) {
        error_log("favourite.php: Favori ürünleri çekme hatası: " . $e->getMessage());
        $message = "Favori ürünleriniz yüklenirken teknik bir sorun oluştu.";
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Favorilerim - ETİCARET</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css">
  <!-- Diğer link ve stil etiketleri aynı kalıyor -->
  <style>
    body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
    .custom-container { width: 90%; max-width: 1200px; margin: 30px auto; background-color: #fff; padding: 30px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); border-radius: 8px; }
    .page-title { text-align: center; font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 30px; color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; display: inline-block; }
    .page-title-container { text-align: center; margin-bottom: 30px; }
    .favorites { display: flex; flex-wrap: wrap; gap: 25px; justify-content: center; }
    .favorite-item { background-color: #fff; padding: 20px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08); width: calc(50% - 12.5px); box-sizing: border-box; display: flex; align-items: center; border-radius: 8px; transition: transform 0.2s ease-out, box-shadow 0.2s ease-out; }
    .favorite-item:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1); }
    .favorite-image { width: 100%; height: 100%; border-radius: 8px; object-fit: cover; }
    .favorite-info { flex-grow: 1; display: flex; flex-direction: column; }
    .remove-button { padding: 8px 15px; background-color: #dc3545; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-size: 0.9rem; transition: background-color 0.2s ease; align-self: flex-start; }
    .remove-button:hover { background-color: #c82333; }
    @media (max-width: 768px) { .favorite-item { width: 100%; flex-direction: column; align-items: flex-start; } .favorite-image-link { margin-bottom: 15px; } }
  </style>
</head>
<body>
  <!-- Sayfanın HTML içeriği (navbar, favori ürünler listesi vb.) aynı kalıyor -->
</body>
</html>
