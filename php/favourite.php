<?php
// favourite.php - Favorilerim Sayfası (Son Hali)

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Gerekli tüm dosyaları dahil et
include_once '../database.php';
include_once __DIR__ . '/models/AbstractProduct.php';
include_once __DIR__ . '/models/GenericProduct.php';
include_once __DIR__ . '/models/CeramicProduct.php';
include_once __DIR__ . '/models/DokumaProduct.php';
include_once __DIR__ . '/factories/ProductFactory.php';

$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');
$redirect_page = 'favourite.php';

$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null;

if (!$logged_in) {
    $_SESSION['error_message'] = "Favorilerinizi görmek için lütfen giriş yapın.";
    header(HTTP_HEADER_LOCATION . "login.php?redirect=" . urlencode($redirect_page));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_favorites'])) {
    $urun_id_to_remove = (int)$_POST['urun_id'];
    try {
        $stmt_remove = $conn->prepare("DELETE FROM Favoriler WHERE Kullanici_ID = :kullanici_id AND Urun_ID = :urun_id");
        $stmt_remove->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
        $stmt_remove->bindParam(':urun_id', $urun_id_to_remove, PDO::PARAM_INT);
        if ($stmt_remove->execute()) {
            $_SESSION['success_message'] = "Ürün favorilerden başarıyla çıkarıldı.";
        } else {
            $_SESSION['error_message'] = "Ürün favorilerden çıkarılırken bir hata oluştu.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Favori silinirken teknik bir sorun oluştu.";
    }
    header(HTTP_HEADER_LOCATION . $redirect_page);
    exit;
}

$favorite_products = [];
$message = "";

try {
    $sql_favorites = "SELECT u.*, k.Kategori_Adi
                      FROM Favoriler f
                      JOIN Urun u ON f.Urun_ID = u.Urun_ID
                      LEFT JOIN KategoriUrun ku ON u.Urun_ID = ku.Urun_ID
                      LEFT JOIN Kategoriler k ON ku.Kategori_ID = k.Kategori_ID
                      WHERE f.Kullanici_ID = :kullanici_id AND u.Aktiflik_Durumu = 1
                      GROUP BY u.Urun_ID
                      ORDER BY f.Ekleme_Tarihi DESC";

    $stmt_favorites = $conn->prepare($sql_favorites);
    $stmt_favorites->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
    $stmt_favorites->execute();
    $favoritesDataFromDb = $stmt_favorites->fetchAll(PDO::FETCH_ASSOC);

    foreach ($favoritesDataFromDb as $productData) {
        $favorite_products[] = ProductFactory::create($productData);
    }

    if (empty($favorite_products) && !isset($_SESSION['success_message']) && !isset($_SESSION['error_message'])) {
        $message = "Henüz favorilerinize eklediğiniz bir ürün bulunmuyor.";
    }

} catch (PDOException $e) {
    $message = "Favori ürünleriniz yüklenirken teknik bir sorun oluştu.";
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
<style>
    body { 
        background-color: #f8f9fa; /* Sayfa arkaplanını hafif gri yapalım */
        font-family: 'Montserrat', sans-serif; 
    }
    .custom-container { 
        max-width: 960px; 
        margin: 40px auto; 
    }
    .page-title-container {
        margin-top: 40px; /* Başlığın üstündeki boşluk */
        margin-bottom: 40px; /* Başlığın altındaki boşluk */
    }
    .page-title {
        font-family: 'Playfair Display', serif;
        font-weight: 700;
    }

    /* Favori Ürün Kartı Stilleri */
    .favorite-item {
        display: flex;
        align-items: center;
        gap: 20px; /* Elemanlar arası boşluk */
        padding: 20px;
        background-color: #ffffff;
        border: 1px solid #dee2e6; /* İnce bir çerçeve */
        border-radius: 12px; /* Daha yuvarlak köşeler */
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: all 0.3s ease; /* Yumuşak geçiş efekti */
        margin-bottom: 20px;
    }
    .favorite-item:hover {
        transform: translateY(-5px); /* Hafif yukarı kalkma efekti */
        box-shadow: 0 8px 16px rgba(0,0,0,0.1); /* Daha belirgin gölge */
        border-color: #0d6efd; /* Mavi çerçeve */
    }
    .favorite-image {
        width: 120px; /* Resim boyutunu biraz büyütelim */
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
    }
    .favorite-info {
        flex-grow: 1; /* Ortadaki alanın tüm boşluğu kaplamasını sağlar */
    }
    .favorite-info h5 {
        margin-bottom: 0.25rem;
        font-weight: 600;
    }
    .favorite-info a {
        text-decoration: none;
        color: #212529;
    }
    .favorite-info a:hover {
        color: #0d6efd;
    }
    .favorite-store {
        font-size: 0.9rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }
    .favorite-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: #B12704;
    }

    /* Buton Grubu Stilleri */
    .actions-group {
        display: flex;
        gap: 10px; /* Butonlar arası boşluk */
        align-items: center;
    }
</style>
</head>
<body>
    <?php include_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="container custom-container" style="max-width: 960px;">
        <div class="page-title-container text-center mb-4">
            <h1 class="page-title"><i class="bi bi-heart-fill me-2"></i>Favorilerim</h1>
        </div>
        
        <?php if(isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $_SESSION['error_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
         <?php if(isset($_SESSION['info_message'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert"><?= $_SESSION['info_message'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <div class="favorites-container">
            <?php if (!empty($favorite_products)): ?>
                <?php foreach ($favorite_products as $product): ?>
                    <div class="d-flex align-items-center p-3 mb-3 bg-white rounded shadow-sm">
                        <a href="product_detail.php?id=<?= $product->getId() ?>">
                            <img src="../uploads/<?= htmlspecialchars($product->getImageUrl()) ?>" alt="<?= htmlspecialchars($product->getName()) ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;">
                        </a>
                        <div class="flex-grow-1 ms-3">
                            <h5><a href="product_detail.php?id=<?= $product->getId() ?>" class="text-decoration-none text-dark"><?= htmlspecialchars($product->getName()) ?></a></h5>
                            <p class="mb-0 text-danger fw-bold fs-5"><?= number_format($product->getPrice(), 2, ',', '.') ?> TL</p>
                        </div>
                        <div class="d-flex flex-column gap-2">
                             <form action="add_to_cart.php" method="POST" class="d-inline">
                                <input type="hidden" name="urun_id" value="<?= $product->getId() ?>">
                                <input type="hidden" name="miktar" value="1">
                                <input type="hidden" name="boyut" value="1">
                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-cart-plus-fill"></i> Sepete Ekle</button>
                            </form>
                            <form action="favourite.php" method="POST" class="d-inline">
                                <input type="hidden" name="urun_id" value="<?= $product->getId() ?>">
                                <button type="submit" name="remove_from_favorites" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash3-fill"></i> Kaldır</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-secondary text-center">
                    <i class="bi bi-info-circle fs-3 d-block mb-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="../index.php" class="btn btn-primary">Alışverişe Devam Et</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>