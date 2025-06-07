<?php
// seller_dashboard.php - Satıcı panel sayfası (Factory Pattern ile güncellendi)

session_start();

// Gerekli tüm dosyaları dahil et
include_once '../database.php';
include_once __DIR__ . '/models/AbstractProduct.php';
include_once __DIR__ . '/models/GenericProduct.php';
include_once __DIR__ . '/models/CeramicProduct.php';
include_once __DIR__ . '/models/DokumaProduct.php';
include_once __DIR__ . '/factories/ProductFactory.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Admin veya customer rolündeki kullanıcıların seller_dashboard'a erişimini engelle
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$store_name = "Mağaza Adı Bulunamadı";
$seller_name = "Satıcı Adı Bulunamadı";
$satici_id = null;
$products = []; // Ürün nesnelerini tutacak dizi

try {
    // Satıcı bilgilerini çek
    $seller_info_query = "SELECT Satici_ID, Magaza_Adi, Ad_Soyad FROM satici WHERE User_ID = :user_id";
    $stmt_seller_info = $conn->prepare($seller_info_query);
    $stmt_seller_info->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_seller_info->execute();
    $seller_info = $stmt_seller_info->fetch(PDO::FETCH_ASSOC);

    if ($seller_info) {
        $satici_id = $seller_info['Satici_ID'];
        $store_name = htmlspecialchars($seller_info['Magaza_Adi']);
        $seller_name = htmlspecialchars($seller_info['Ad_Soyad']);
    } else {
        error_log("seller_dashboard.php: Satıcı bilgisi bulunamadı User_ID: " . $user_id);
    }
} catch (PDOException $e) {
    error_log("seller_dashboard.php: Satıcı bilgisi çekilirken veritabanı hatası: " . $e->getMessage());
}

// Satıcının ürünlerini çekmek ve nesnelere dönüştürmek
if ($satici_id !== null) {
    try {
        // Kategori adını da alacak şekilde sorguyu güncelle
        $product_query = "SELECT u.*, k.Kategori_Adi 
                          FROM Urun u
                          LEFT JOIN KategoriUrun ku ON u.Urun_ID = ku.Urun_ID
                          LEFT JOIN Kategoriler k ON ku.Kategori_ID = k.Kategori_ID
                          WHERE u.Satici_ID = :satici_id AND u.Aktiflik_Durumu = 1
                          GROUP BY u.Urun_ID";

        $stmt_product = $conn->prepare($product_query);
        $stmt_product->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_product->execute();
        $productsFromDb = $stmt_product->fetchAll(PDO::FETCH_ASSOC);

        // Gelen veriyi Product nesnelerine dönüştür
        foreach ($productsFromDb as $productData) {
            $products[] = ProductFactory::create($productData);
        }

    } catch (PDOException $e) {
        error_log("seller_dashboard.php: Ürünler çekilirken veritabanı hatası: " . $e->getMessage());
        $products = []; // Hata durumunda dizi boş kalır
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Paneli - <?php echo $store_name; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-custom {
             background-color: rgb(91, 140, 213);
        }
        .seller-header {
            background: linear-gradient(135deg, rgb(91, 140, 213) 0%, rgb(120, 160, 230) 100%);
            color: white;
            padding: 40px 20px;
            border-radius: 0 0 25px 25px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .store-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        .seller-subtitle {
            font-size: 1.3rem;
            font-weight: 400;
            opacity: 0.9;
        }
        .search-bar {
            display: flex;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        #search-input {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 20px 0 0 20px;
            font-size: 1rem;
        }
        .search-bar button {
            padding: 10px 20px;
            background-color: rgb(91, 140, 213);
            color: #fff;
            border: none;
            border-radius: 0 20px 20px 0;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        .search-bar button:hover {
            background-color: rgb(70, 120, 190);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .product-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        .product-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        .product-card-body {
            padding: 20px;
            text-align: center;
        }
        .product-name {
            font-size: 1.15rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            min-height: 44px;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Eski/WebKit tarayıcılar için */
            line-clamp: 2;         /* Yeni ve standart tarayıcılar için */
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-price {
            color: rgb(91, 140, 213);
            font-size: 1.25rem;
            font-weight: 700;
        }
        .no-products-message {
            text-align: center;
            padding: 40px;
            background-color: #e9ecef;
            border-radius: 10px;
            color: #6c757d;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3">ELEMEK</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3"><a class="nav-link active" href="seller_dashboard.php">Satıcı Paneli</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="seller_manage.php">Mağaza Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="manage_product.php">Ürün Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="order_manage.php">Sipariş Yönetimi</a></li>
            </ul>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4 me-2"></i>
                <?php if ($logged_in && $username): ?>
                    <a href="logout.php" class="text-white" style="font-size: 15px; text-decoration: none;"><?php echo $username; ?> (Çıkış Yap)</a>
                <?php else: ?>
                    <a href="login.php" class="text-white" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="seller-header">
    <h1 class="store-title"><?php echo $store_name; ?></h1>
    <p class="seller-subtitle">Satıcı: <?php echo $seller_name; ?></p>
</div>

<div class="container mt-4">
    <div class="search-bar">
        <input type="text" id="search-input" placeholder="Mağazanızda ürün ara...">
        <button onclick="searchProducts()">
            <i class="bi bi-search"></i> Ara
        </button>
    </div>

    <h2 class="mb-4 text-center" style="font-family: 'Playfair Display', serif; color: #333;">Mağaza Ürünleri</h2>
    <div class="products-grid">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <a href="edit_product.php?id=<?= htmlspecialchars($product->getId()); ?>" style="text-decoration:none;">
                        <img src="../uploads/<?= htmlspecialchars($product->getImageUrl() ?: 'placeholder.jpg'); ?>" alt="<?= htmlspecialchars($product->getName()); ?>" class="product-image">
                        <div class="product-card-body">
                            <p class="product-name"><?= htmlspecialchars($product->getName()); ?></p>
                            <p class="product-price">₺<?= number_format($product->getPrice(), 2, ',', '.'); ?></p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                 <div class="no-products-message">
                    <i class="bi bi-emoji-frown fs-1 mb-3 d-block"></i>
                    Bu mağazada henüz sergilenecek ürün bulunmamaktadır.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // JavaScript arama fonksiyonu, HTML yapısı korunduğu için değişmeden çalışır.
    function searchProducts() {
        const input = document.getElementById('search-input').value.toLowerCase();
        const products = document.getElementsByClassName('product-card');

        for (let i = 0; i < products.length; i++) {
            const productNameElement = products[i].getElementsByClassName('product-name')[0];
            if (productNameElement) {
                const productName = productNameElement.innerText.toLowerCase();
                if (productName.includes(input)) {
                    products[i].style.display = '';
                } else {
                    products[i].style.display = 'none';
                }
            }
        }
    }
</script>
</body>
</html>