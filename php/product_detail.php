<?php
// product_detail.php

// Geliştirme aşamasında hataları görmek için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Gerekli Factory ve Model sınıflarını dahil et
include_once __DIR__ . '/models/AbstractProduct.php';
include_once __DIR__ . '/models/GenericProduct.php';
include_once __DIR__ . '/models/CeramicProduct.php';
include_once __DIR__ . '/models/DokumaProduct.php';
include_once __DIR__ . '/factories/ProductFactory.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');

// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null;
$current_user_role = $logged_in ? $_SESSION['role'] : null;
$username_session = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;

// Favorilere Ekleme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_favorites_action'])) {
    // ... (Favori ekleme bloğu değişmeden kalabilir) ...
}

$product = null;
$product_reviews = [];
$message = "";

class ProductDetailException extends Exception {}

try {
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new ProductDetailException("Geçersiz veya eksik ürün ID'si.");
    }
    $product_id_from_url = (int)$_GET['id'];

    // Factory'nin ihtiyacı olan tüm bilgileri (kategori adı ve mağaza adı dahil) çekiyoruz.
    $sql_query_product = "SELECT u.*, s.Magaza_Adi, s.User_ID AS Satici_User_ID, k.Kategori_Adi
                          FROM Urun u
                          LEFT JOIN Satici s ON u.Satici_ID = s.Satici_ID
                          LEFT JOIN KategoriUrun ku ON u.Urun_ID = ku.Urun_ID
                          LEFT JOIN Kategoriler k ON ku.Kategori_ID = k.Kategori_ID
                          WHERE u.Urun_ID = :product_id AND u.Aktiflik_Durumu = 1";
                          
    $statement_product = $conn->prepare($sql_query_product);
    $statement_product->bindParam(':product_id', $product_id_from_url, PDO::PARAM_INT);
    $statement_product->execute();
    
    // Veritabanından gelen ham veriyi bir diziye alıyoruz.
    $productData = $statement_product->fetch(PDO::FETCH_ASSOC);

    if ($productData) {
        // Ham veriyi kullanarak Factory'den uygun nesneyi istiyoruz.
        $product = ProductFactory::create($productData);
    } else {
        $product = null;
    }

    if (!$product) {
        throw new ProductDetailException("Ürün bulunamadı veya şu anda aktif değil.");
    }

    // Yorumları çekme sorgusu (Bu kısım değişmedi)
    $sql_query_reviews = "SELECT r.*, usr.username AS Kullanici_Adi
                          FROM Yorumlar r
                          JOIN users usr ON r.Kullanici_ID = usr.id
                          WHERE r.Urun_ID = :product_id AND r.Onay_Durumu = 1
                          ORDER BY r.Yorum_Tarihi DESC";
    
    $statement_reviews = $conn->prepare($sql_query_reviews);
    $statement_reviews->bindParam(':product_id', $product_id_from_url, PDO::PARAM_INT);
    $statement_reviews->execute();
    $product_reviews = $statement_reviews->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("product_detail.php Hatası: " . $e->getMessage());
    $message = ($e instanceof PDOException) ? "Veritabanı hatası oluştu." : $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $product ? htmlspecialchars($product->getName()) : 'Ürün Detayı'; ?> - ETİCARET</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css">
  <style>
    body { background-color: #f8f9fa; font-family: 'Montserrat', sans-serif; }
    .product-gallery img.main-image { max-height: 450px; object-fit: contain; border-radius: .375rem; background-color: #fff; }
    .product-title { font-family: 'Playfair Display', serif; font-weight: 700; }
    .product-price { font-size: 2.1rem; color: #B12704; font-weight: 700; }
    .nav-tabs .nav-link { color: #495057; }
    .nav-tabs .nav-link.active { color: #000; font-weight: 600; }
    .tab-content { background-color: #fff; padding: 25px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 .375rem .375rem;}
    .review-item { border-bottom: 1px dotted #eee; padding-bottom: 15px; margin-bottom: 15px; }
    .star-rating .bi-star-fill { color: #ffc107; }
  </style>
</head>
<body>
  
  <?php include_once __DIR__ . '/includes/navbar.php'; ?>

  <div class="container my-5">
    <?php if ($product): ?>
    <div class="card shadow-lg border-light">
        <div class="card-body p-lg-5 p-md-4 p-3">
            <div class="row gx-lg-5">
                <div class="col-lg-6 mb-4 mb-lg-0 product-gallery">
                    <img src="../uploads/<?= htmlspecialchars($product->getImageUrl() ?? 'placeholder.jpg'); ?>" alt="<?= htmlspecialchars($product->getName()); ?>" class="img-fluid main-image mb-3">
                </div>
                <div class="col-lg-6 product-details">
                    <h1 class="product-title mb-3"><?= htmlspecialchars($product->getName()); ?></h1>
                    <p class="text-muted">Mağaza: <a href="#"><?= htmlspecialchars($product->getStoreName()); ?></a></p>
                    <div class="product-price my-3"><?= number_format($product->getPrice(), 2, ',', '.'); ?> TL</div>
                    <p class="lead fs-6 mb-4"><?= htmlspecialchars($product->getDescription()); ?></p>
                    <div class="d-grid gap-2 d-sm-flex my-4">

                        <form method="POST" class="d-flex flex-grow-1 gap-3">
                            
                            <input type="hidden" name="product_id" value="<?= $product->getId(); ?>">
                            
                            <input type="hidden" name="miktar" value="1">
                            <input type="hidden" name="boyut" value="1">
                            <button type="submit" formaction="add_to_cart.php" class="btn btn-success btn-lg w-100">Sepete Ekle</button>

                            <button type="submit" formaction="add_to_favorites.php" name="add_to_favorites_action" value="1" class="btn btn-outline-danger btn-lg w-100">Favorilere Ekle</button>
                            
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-4 shadow-sm border-light">
            <div class="card-header bg-light border-bottom-0">
                <ul class="nav nav-tabs card-header-tabs" id="productInfoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#description-tab-pane" type="button">Detaylı Açıklama</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews-tab-pane" type="button">Yorumlar (<?= count($product_reviews); ?>)</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content p-lg-4 p-3" id="productInfoTabsContent">
                <div class="tab-pane fade show active" id="description-tab-pane" role="tabpanel">
                    <h4>Ürün Açıklaması</h4>
                    <p><?= nl2br(htmlspecialchars($product->getDescription() ?? 'Detaylı açıklama mevcut değil.')); ?></p>
                </div>
                <div class="tab-pane fade" id="reviews-tab-pane" role="tabpanel">
                    <h4>Müşteri Yorumları</h4>
                    <?php if (!empty($product_reviews)): ?>
                        <?php foreach ($product_reviews as $review): ?>
                        <div class="review-item">
                            <strong><?= htmlspecialchars($review['Kullanici_Adi']); ?></strong>
                            <div class="star-rating">
                                <?php for($i = 1; $i <= 5; $i++): ?><i class="bi <?= $i <= $review['Puan'] ? 'bi-star-fill' : 'bi-star'; ?>"></i><?php endfor; ?>
                            </div>
                            <p class="m-0"><?= nl2br(htmlspecialchars($review['Yorum_Metni'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Bu ürün için henüz yorum yapılmamış.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning text-center my-5 py-4" role="alert">
            <h2 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i> Ürün Bulunamadı</h2>
            <p class="lead"><?php echo htmlspecialchars($message); ?></p>
            <hr>
            <a href="../index.php" class="alert-link">Ana sayfaya dönmek için tıklayın.</a>
        </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>