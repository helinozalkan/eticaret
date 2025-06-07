<?php
// customer_review.php

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


// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? $_SESSION['user_id'] : null;
$current_user_role = $logged_in ? $_SESSION['role'] : null;
$username = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;

// Yorum yapılacak ürünün ID'sini URL'den al
$product_to_review_id = null;
$product_name_to_review = "Bilinmeyen Ürün";
$message = ""; // Form işleme mesajları için

if (isset($_GET['product_id']) && filter_var($_GET['product_id'], FILTER_VALIDATE_INT)) {
    $product_to_review_id = (int)$_GET['product_id'];

    // Ürün adını çek
    try {
        // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
        $stmt_product_name = $conn->prepare("SELECT Urun_Adi FROM Urun WHERE Urun_ID = :urun_id");
        $stmt_product_name->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
        $stmt_product_name->execute();
        $product_data = $stmt_product_name->fetch(PDO::FETCH_ASSOC);
        if ($product_data) {
            $product_name_to_review = htmlspecialchars($product_data['Urun_Adi']);
        } else {
            $message = "Yorum yapılacak ürün bulunamadı.";
        }
    } catch (PDOException $e) {
        error_log("customer_review.php: Ürün adı çekme hatası: " . $e->getMessage());
        $message = "Ürün bilgileri yüklenirken bir sorun oluştu.";
    }
} else {
    $message = "Yorum yapılacak ürün belirtilmemiş.";
}

// Sadece giriş yapmış ve 'customer' rolündeki kullanıcılar yorum yapabilir
if (!$logged_in || $current_user_role !== 'customer') {
    $_SESSION['review_error_message'] = "Yorum yapabilmek için müşteri olarak giriş yapmanız gerekmektedir.";
    header(HTTP_HEADER_LOCATION . "login.php?redirect=product_detail.php?id=" . $product_to_review_id);
    exit;
}

// Form gönderildi mi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment_text = isset($_POST['comment_text']) ? trim(htmlspecialchars($_POST['comment_text'])) : '';

    if ($product_to_review_id && $current_user_id && $rating >= 1 && $rating <= 5 && !empty($comment_text)) {
        try {
            // Yorumu veritabanına ekle
            $sql_insert_review = "INSERT INTO Yorumlar (Urun_ID, Kullanici_ID, Puan, Yorum_Metni, Yorum_Tarihi, Onay_Durumu)
                                  VALUES (:urun_id, :kullanici_id, :puan, :yorum_metni, NOW(), 0)";

            $stmt_insert = $conn->prepare($sql_insert_review);
            $stmt_insert->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':puan', $rating, PDO::PARAM_INT);
            $stmt_insert->bindParam(':yorum_metni', $comment_text, PDO::PARAM_STR);

            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Yorumunuz başarıyla gönderildi ve onay bekliyor.";
                header(HTTP_HEADER_LOCATION . "product_detail.php?id=" . $product_to_review_id);
                exit;
            } else {
                $message = "Yorumunuz kaydedilirken bir hata oluştu.";
            }
        } catch (PDOException $e) {
            error_log("customer_review.php: Yorum kaydetme hatası: " . $e->getMessage());
            $message = "Yorumunuz kaydedilirken teknik bir sorun oluştu.";
        }
    } else {
        $message = "Lütfen puan seçin ve yorumunuzu yazın.";
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ürün Yorumu Yap - <?php echo $product_name_to_review; ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css">
  <!-- Stil kodları aynı kalıyor -->
  <style>
    body { background-color: #f8f9fa; }
    .rating-stars .bi-star, .rating-stars .bi-star-fill { font-size: 2rem; color: #ffc107; cursor: pointer; margin-right: 5px; }
    .form-label { font-weight: 500; }
  </style>
</head>
<body>
  <!-- NAVİGASYON ÇUBUĞU HTML KISMI AYNI KALIYOR -->
  
  <!-- YORUM FORMU İÇERİĞİ BAŞLANGICI -->
  <div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Ürün Değerlendirmesi Yap</h4>
                </div>
                <div class="card-body p-4">
                    <!-- Form ve mesajların HTML kısmı aynı kalıyor -->
                </div>
            </div>
        </div>
    </div>
  </div>
  <!-- YORUM FORMU İÇERİĞİ SONU -->

  <!-- FOOTER HTML KISMI AYNI KALIYOR -->

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- JavaScript kısmı aynı kalıyor -->
</body>
</html>
