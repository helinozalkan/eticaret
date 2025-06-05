<?php
// product_detail.php

// Geliştirme aşamasında hataları görmek için (üretimde bu ayarlar farklı olmalı)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once '../database.php'; // Veritabanı bağlantısı

// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null;
$current_user_role = $logged_in ? $_SESSION['role'] : null;
$username_session = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;

// Favorilere Ekleme İşlemi (Sayfanın başında, HTML öncesinde)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_favorites_action'])) {
    if (!$logged_in) {
        $_SESSION['error_message'] = "Ürünü favorilere eklemek için lütfen giriş yapın.";
        // Giriş yapmamışsa, geldiği sayfaya (veya ürün detayına) yönlendir.
        $redirect_url = isset($_POST['product_id']) ? "product_detail.php?id=" . htmlspecialchars($_POST['product_id']) : "../index.php";
        header("Location: login.php?redirect=" . urlencode($redirect_url));
        exit;
    }

    if (isset($_POST['product_id'])) {
        $product_id_to_add = (int)$_POST['product_id'];

        if ($product_id_to_add > 0) {
            try {
                // 1. Ürünün favorilerde olup olmadığını kontrol et
                $stmt_check = $conn->prepare("SELECT Favori_ID FROM Favoriler WHERE Kullanici_ID = :kullanici_id AND Urun_ID = :urun_id");
                $stmt_check->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':urun_id', $product_id_to_add, PDO::PARAM_INT);
                $stmt_check->execute();

                if ($stmt_check->fetch()) {
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
                error_log("product_detail.php (favori ekleme): " . $e->getMessage());
                $_SESSION['error_message'] = "Favorilere eklenirken teknik bir sorun oluştu.";
            }
        } else {
            $_SESSION['error_message'] = "Geçersiz ürün ID'si (favori ekleme).";
        }
    } else {
        $_SESSION['error_message'] = "Ürün ID'si belirtilmedi (favori ekleme).";
    }
    // İşlem sonrası kullanıcıyı favoriler sayfasına yönlendir
    header("Location: favourite.php");
    exit;
}
// Favorilere Ekleme İşlemi Sonu


$product_id_from_url = null;
$product = null;
$product_reviews = [];
$message = ""; // Sayfa yüklenirken oluşabilecek hatalar için

$param_product_id_for_sql = ':product_id';

class ProductDetailException extends Exception {}

try {
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new ProductDetailException("Geçersiz ürün ID'si. URL'de 'id' parametresi eksik veya hatalı.");
    }
    $product_id_from_url = (int)$_GET['id'];

    // Satici tablosundaki mağaza adı sütununun 'Magaza_Adi' (alt tire ile) olduğunu varsayıyoruz.
    $sql_query_product = "SELECT u.*, s.Magaza_Adi AS Zanaatkar_Adi, s.User_ID AS Satici_User_ID
                          FROM Urun u
                          LEFT JOIN Satici s ON u.Satici_ID = s.Satici_ID
                          WHERE u.Urun_ID = " . $param_product_id_for_sql . " AND u.Aktiflik_Durumu = 1";

    $statement_product = $conn->prepare($sql_query_product);

    if (!$statement_product) {
        $errorInfo = $conn->errorInfo();
        error_log("product_detail.php: SQL prepare hatası (ürün): " . implode(";", $errorInfo));
        throw new PDOException("SQL sorgusu hazırlanamadı (ürün). Hata: " . htmlspecialchars(implode(";", $errorInfo)));
    }

    $statement_product->bindParam($param_product_id_for_sql, $product_id_from_url, PDO::PARAM_INT);
    $execution_result = $statement_product->execute();

    if (!$execution_result) {
        $errorInfo = $statement_product->errorInfo();
        error_log("product_detail.php: SQL execute hatası (ürün): " . implode(";", $errorInfo));
        throw new PDOException("Ürün bilgileri çekilirken bir sorun oluştu (SQL execute ürün). Hata: " . htmlspecialchars(implode(";", $errorInfo)) );
    }

    $product = $statement_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new ProductDetailException("Ürün bulunamadı (ID: " . htmlspecialchars($product_id_from_url) . ") veya ürün aktif değil.");
    }

    // Kullanıcı tablosu: 'users', ID sütunu: 'id', kullanıcı adı sütunu: 'username'
    $sql_query_reviews = "SELECT r.*, usr.username AS Kullanici_Adi
                          FROM Yorumlar r
                          JOIN users usr ON r.Kullanici_ID = usr.id
                          WHERE r.Urun_ID = :product_id
                          ORDER BY r.Yorum_Tarihi DESC";
    // Sadece onaylanmış yorumları göstermek için: AND r.Onay_Durumu = 1

    $statement_reviews = $conn->prepare($sql_query_reviews);
    if (!$statement_reviews) {
        $errorInfo = $conn->errorInfo();
        error_log("product_detail.php: SQL prepare hatası (yorumlar): " . implode(";", $errorInfo));
        throw new PDOException("SQL sorgusu hazırlanamadı (yorumlar). Hata: " . htmlspecialchars(implode(";", $errorInfo)));
    }
    $statement_reviews->bindParam(':product_id', $product_id_from_url, PDO::PARAM_INT);
    $execution_reviews_result = $statement_reviews->execute();

    if (!$execution_reviews_result) {
        $errorInfo = $statement_reviews->errorInfo();
        error_log("product_detail.php: SQL execute hatası (yorumlar): " . implode(";", $errorInfo));
        throw new PDOException("Yorumlar çekilirken bir sorun oluştu (SQL execute yorumlar). Hata: " . htmlspecialchars(implode(";", $errorInfo)) );
    }
    $product_reviews = $statement_reviews->fetchAll(PDO::FETCH_ASSOC);

} catch (ProductDetailException $e) {
    $message = htmlspecialchars($e->getMessage());
} catch (PDOException $e) {
    error_log("product_detail.php: Veritabanı Bağlantı/Sorgu Hatası: " . $e->getMessage());
    $message = "<strong>Veritabanı Hatası Oluştu:</strong><br>" . htmlspecialchars($e->getMessage()) . "<br><br><strong>SQL Sorgusu (eğer çalıştırıldıysa):</strong><br><pre>" . (isset($sql_query_product) ? htmlspecialchars($sql_query_product) : (isset($sql_query_reviews) ? htmlspecialchars($sql_query_reviews) : "Sorgu oluşturulamadı.")) . "</pre>";
    if (isset($statement_product) && $statement_product && $statement_product->errorInfo()[0] !== '00000') {
        $message .= "<br><strong>PDO Hata Bilgisi (statement_product->errorInfo):</strong><pre>" . htmlspecialchars(print_r($statement_product->errorInfo(), true)) . "</pre>";
    } elseif (isset($statement_reviews) && $statement_reviews && $statement_reviews->errorInfo()[0] !== '00000') {
        $message .= "<br><strong>PDO Hata Bilgisi (statement_reviews->errorInfo):</strong><pre>" . htmlspecialchars(print_r($statement_reviews->errorInfo(), true)) . "</pre>";
    } elseif(isset($conn) && $conn->errorInfo()[0] !== '00000') {
         $message .= "<br><strong>PDO Bağlantı Hata Bilgisi (conn->errorInfo):</strong><pre>" . htmlspecialchars(print_r($conn->errorInfo(), true)) . "</pre>";
    }
} catch (Exception $e) {
    error_log("product_detail.php: Genel Hata: " . $e->getMessage());
    $message = "Beklenmedik bir genel hata oluştu: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $product ? htmlspecialchars($product['Urun_Adi']) : 'Ürün Detayı'; ?> - ETİCARET</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css">
  <link href="https://fonts.googleapis.com/css2?family=Courgette&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <style>
    body { background-color: #f8f9fa; }
    .product-gallery img.main-image { max-height: 450px; object-fit: contain; border: 1px solid #dee2e6; border-radius: .375rem; background-color: #fff; display: block; margin-left: auto; margin-right: auto; }
    .product-gallery .thumbnail-images img { width: 70px; height: 70px; object-fit: cover; cursor: pointer; border: 2px solid transparent; border-radius: .25rem; margin-right: 8px; padding: 2px; transition: border-color 0.2s ease-in-out; }
    .product-gallery .thumbnail-images img.active, .product-gallery .thumbnail-images img:hover { border-color: #0d6efd; }
    .product-title { font-family: 'Playfair Display', serif; font-weight: 700; color: #343a40; font-size: 2.2rem; }
    .product-price { font-size: 2.1rem; color: #B12704; font-weight: 700; }
    .artisan-name { font-size: 0.95rem; }
    .artisan-name a { color: #0d6efd; text-decoration: none; font-weight: 500; }
    .artisan-name a:hover { text-decoration: underline; }
    .nav-tabs .nav-link { color: #495057; border-radius: .375rem .375rem 0 0; }
    .nav-tabs .nav-link.active { color: #000; font-weight: 600; background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; }
    .tab-content { background-color: #fff; padding: 25px; border: 1px solid #dee2e6; border-top: none; border-radius: 0 0 .375rem .375rem;}
    .review-item { border-bottom: 1px dotted #eee; padding-bottom: 15px; margin-bottom: 15px; }
    .review-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .star-rating .bi-star-fill { color: #ffc107; }
    .star-rating .bi-star { color: #d1d1d1; }
    .btn-add-to-cart { background-color: #198754; border-color: #198754; font-size: 1.1rem; padding: 0.7rem 1.2rem;}
    .btn-add-to-favorites { font-size: 1.1rem; padding: 0.7rem 1.2rem; }
    .customization-form { background-color: #f8f9fa; padding: 15px; border-radius: .375rem; margin-top:15px;}
    .customization-form .form-label { font-weight: 500; font-size: 0.9rem; }
    .section-title { font-family: 'Montserrat', sans-serif; font-weight: 600; margin-bottom: 1rem; font-size:1.4rem; color:#444;}
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(91, 140, 213) ; ">
    <div class="container-fluid">
      <a class="navbar-brand d-flex ms-4" href="../index.php" style="margin-left: 5px;">
        <img src="../images/ana_logo.png" alt="Logo" width="40" height="40" class="align-text-top">
        <div class="baslik fs-3" style="color:white; text-decoration:none;">ETİCARET</div>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 " style="margin-left: 110px;">
          <li class="nav-item dropdown ps-3">
            <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: none; border: none; color: inherit; font: inherit; cursor: pointer;">
             Ana Sayfa
            </button>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownHome">
             <li><a class="dropdown-item" href="girisimciler.php">Girişimci Sayısı</a></li>
             <li><a class="dropdown-item" href="../index.php#newProducts">Yeni Ürünlerimiz</a></li>
             <li><a class="dropdown-item" href="eticaret-ekibi.php">Eticaret Ekibi</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown ps-3">
            <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: none; border: none; color: inherit; font: inherit; cursor: pointer;">
             Siparişlerim
            </button>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownOrders">
              <li><a class="dropdown-item" href="customer_orders.php">Sipariş Detay</a></li>
            </ul>
          </li>
          <li class="nav-item dropdown ps-3">
            <button class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: none; border: none; color: inherit; font: inherit; cursor: pointer;">
             Mağazalar
            </button>
            <ul class="dropdown-menu" aria-labelledby="navbarDropdownStores">
              <li><a class="dropdown-item" href="seller_register.php">Satıcı Ol</a></li>
              <li><a class="dropdown-item" href="motivation.php">Eticaret Ekibinden Mesaj Var</a></li>
            </ul>
          </li>
           <?php if ($logged_in && $current_user_role === 'seller'): ?>
            <li class="nav-item ps-3"><a class="nav-link" href="manage_product.php">Ürün Yönetimi</a></li>
            <li class="nav-item ps-3"><a class="nav-link" href="seller_dashboard.php">Satıcı Paneli</a></li>
           <?php elseif ($logged_in && $current_user_role === 'customer'): ?>
             <li class="nav-item ps-3"><a class="nav-link" href="customer_dashboard.php">Müşteri Paneli</a></li>
           <?php endif; ?>
        </ul>
        <div class="d-flex align-items-center ms-auto me-3">
          <a href="#" class="text-white me-3"><i class="bi bi-search fs-5"></i></a>
          <a href="favourite.php" class="text-white me-3"><i class="bi bi-heart fs-5"></i></a>
          <a href="my_cart.php" class="text-white me-3"><i class="bi bi-cart3 fs-5"></i></a>
          <div class="d-flex align-items-center">
            <i class="bi bi-person-circle text-white fs-4 me-2"></i>
            <?php if ($logged_in && $username_session): ?>
              <div class="dropdown">
                <a href="#" class="text-white dropdown-toggle" data-bs-toggle="dropdown" style="font-size: 15px; text-decoration: none;">
                  <?php echo $username_session; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li><a class="dropdown-item" href="<?php echo ($current_user_role === 'seller' ? 'seller_dashboard.php' : ($current_user_role === 'customer' ? 'customer_dashboard.php' : '#')); ?>">Panelim</a></li>
                  <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                </ul>
              </div>
            <?php else: ?>
              <a href="login.php" class="text-white" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </nav>

  <div class="container my-5">
    <?php if (!empty($message) && !$product): // Eğer ürün yüklenirken bir hata oluştuysa ve $product boşsa, hata mesajını göster ?>
        <div class="alert alert-warning text-center my-5 py-4" role="alert">
            <h2 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i> Bilgilendirme</h2>
            <p class="lead"><?php echo $message; ?></p>
            <hr>
            <p class="mb-0">Lütfen geçerli bir ürün ID'si girdiğinizden emin olun veya <a href="../index.php" class="alert-link">ana sayfaya</a> dönerek diğer ürünlerimize göz atın.</p>
        </div>
    <?php elseif ($product): // Ürün başarıyla çekildiyse ?>
    <div class="card shadow-lg border-light">
        <div class="card-body p-lg-5 p-md-4 p-3">
            <div class="row gx-lg-5">
                <div class="col-lg-6 mb-4 mb-lg-0 product-gallery">
                    <img src="<?php echo $product['Urun_Gorseli'] ? '../uploads/' . htmlspecialchars($product['Urun_Gorseli']) : 'https://placehold.co/600x450/EFEFEF/AAAAAA?text=Görsel+Yok'; ?>"
                         alt="<?php echo htmlspecialchars($product['Urun_Adi']); ?>" class="img-fluid main-image mb-3" id="mainProductImage">
                </div>
                <div class="col-lg-6 product-details">
                    <h1 class="product-title mb-3"><?php echo htmlspecialchars($product['Urun_Adi']); ?></h1>
                    <?php if (!empty($product['Zanaatkar_Adi'])): ?>
                        <p class="mb-2 artisan-name">
                            Zanaatkar: <a href="seller_profile.php?id=<?php echo htmlspecialchars($product['Satici_User_ID'] ?? $product['Satici_ID']); ?>"><?php echo htmlspecialchars($product['Zanaatkar_Adi']); ?></a>
                        </p>
                    <?php endif; ?>
                    <div class="product-price my-3"><?php echo number_format($product['Urun_Fiyati'], 2, ',', '.'); ?> TL</div>
                    <p class="lead fs-6 mb-4" style="color: #555;">
                        <?php
                        $kisa_aciklama = !empty($product['Urun_Aciklamasi_Kisa']) ? $product['Urun_Aciklamasi_Kisa'] : (isset($product['Urun_Aciklamasi']) ? substr(strip_tags($product['Urun_Aciklamasi']), 0, 200) . (strlen(strip_tags($product['Urun_Aciklamasi'])) > 200 ? '...' : '') : 'Bu ürün için kısa bir açıklama bulunmamaktadır.');
                        echo htmlspecialchars($kisa_aciklama);
                        ?>
                    </p>
                    <?php if ($product['Stok_Adedi'] > 0): ?>
                        <p class="text-success fw-bold mb-3"><i class="bi bi-patch-check-fill"></i> Stokta Mevcut (<?php echo htmlspecialchars($product['Stok_Adedi']); ?> adet)</p>
                    <?php else: ?>
                        <p class="text-danger fw-bold mb-3"><i class="bi bi-x-octagon-fill"></i> Maalesef Stokta Yok</p>
                    <?php endif; ?>
                    <div class="customization-form my-4">
                        <h5 class="section-title fs-6"><i class="bi bi-palette2"></i> Ürünü Kişiselleştir</h5>
                        <form id="customizationForm">
                            <div class="mb-2">
                                <label for="custom_size" class="form-label">Beden:</label>
                                <select name="custom_options[size]" id="custom_size" class="form-select form-select-sm">
                                    <option value="">Beden Seçin</option>
                                    <option value="s">Small</option>
                                    <option value="m">Medium</option>
                                    <option value="l">Large</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="d-grid gap-2 d-sm-flex my-4">
                        <form action="add_to_cart.php" method="POST" class="flex-grow-1 mb-2 mb-sm-0">
                            <input type="hidden" name="urun_id" value="<?php echo htmlspecialchars($product_id_from_url); ?>">
                            <input type="hidden" name="miktar" value="1">
                            <button type="submit" class="btn btn-success btn-lg w-100 btn-add-to-cart" <?php echo $product['Stok_Adedi'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="bi bi-cart-plus-fill me-2"></i>Sepete Ekle
                            </button>
                        </form>
                        <!-- Favorilere Ekleme Formu Güncellendi -->
                        <form action="product_detail.php?id=<?php echo htmlspecialchars($product_id_from_url); ?>" method="POST" class="ms-md-2">
                             <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id_from_url); ?>">
                             <input type="hidden" name="add_to_favorites_action" value="1"> <!-- Bu action'ı PHP'de yakalayacağız -->
                            <button type="submit" class="btn btn-outline-danger btn-lg w-100 btn-add-to-favorites">
                                <i class="bi bi-heart-fill me-2"></i>Favorilere Ekle
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-4 shadow-sm border-light">
            <div class="card-header bg-light border-bottom-0">
                <ul class="nav nav-tabs card-header-tabs" id="productInfoTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description-tab-pane" type="button" role="tab" aria-controls="description-tab-pane" aria-selected="true">Detaylı Açıklama</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="artisan-story-tab" data-bs-toggle="tab" data-bs-target="#artisan-story-tab-pane" type="button" role="tab" aria-controls="artisan-story-tab-pane" aria-selected="false">Zanaatkar Hikayesi</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews-tab-pane" type="button" role="tab" aria-controls="reviews-tab-pane" aria-selected="false">Yorumlar (<?php echo count($product_reviews); ?>)</button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content p-lg-4 p-3" id="productInfoTabsContent">
                <div class="tab-pane fade show active" id="description-tab-pane" role="tabpanel" aria-labelledby="description-tab" tabindex="0">
                    <h4 class="section-title">Ürün Açıklaması</h4>
                    <p><?php echo nl2br(htmlspecialchars($product['Urun_Aciklamasi'] ?? 'Bu ürün için detaylı açıklama henüz eklenmemiştir.')); ?></p>
                </div>
                <div class="tab-pane fade" id="artisan-story-tab-pane" role="tabpanel" aria-labelledby="artisan-story-tab" tabindex="0">
                    <h4 class="section-title">Zanaatkar: <?php echo htmlspecialchars($product['Zanaatkar_Adi'] ?? 'Belirtilmemiş'); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($product['Zanaatkar_Hikayesi'] ?? 'Zanaatkarımız hakkında daha fazla bilgi yakında eklenecektir. Her bir ürün, onların tutkusu ve emeğiyle hayat bulmaktadır.')); ?></p>
                </div>
                <div class="tab-pane fade" id="reviews-tab-pane" role="tabpanel" aria-labelledby="reviews-tab" tabindex="0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="section-title mb-0">Müşteri Yorumları</h4>
                        <?php if ($logged_in && $current_user_role === 'customer'): ?>
                            <a href="customer_review.php?product_id=<?php echo htmlspecialchars($product_id_from_url); ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil-square me-1"></i> Yorum Yap
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($product_reviews)): ?>
                        <?php foreach ($product_reviews as $review): ?>
                        <div class="review-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <strong class="text-dark"><?php echo htmlspecialchars($review['Kullanici_Adi'] ?? 'Anonim'); ?></strong>
                                <span class="star-rating">
                                    <?php $rating = (int)($review['Puan'] ?? 0); ?>
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?php echo $i <= $rating ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <small class="text-muted d-block mb-1"><?php echo isset($review['Yorum_Tarihi']) ? date("d F Y, H:i", strtotime($review['Yorum_Tarihi'])) : ''; ?></small>
                            <p class="m-0" style="color: #555;"><?php echo nl2br(htmlspecialchars($review['Yorum_Metni'] ?? '')); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Bu ürün için henüz yorum yapılmamış.
                        <?php if ($logged_in && $current_user_role === 'customer'): ?>
                             İlk yorumu siz yapın!
                         <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif (!empty($message)): // Ürün yüklenirken bir hata oluştuysa ve $product boşsa, hata mesajını göster ?>
        <div class="alert alert-warning text-center my-5 py-4" role="alert">
            <h2 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i> Bilgilendirme</h2>
            <p class="lead"><?php echo $message; ?></p>
            <hr>
            <p class="mb-0">Lütfen geçerli bir ürün ID'si girdiğinizden emin olun veya <a href="../index.php" class="alert-link">ana sayfaya</a> dönerek diğer ürünlerimize göz atın.</p>
        </div>
    <?php else: // Beklenmedik bir durum ?>
        <div class="alert alert-danger text-center my-5 py-4" role="alert">
            <h2 class="alert-heading"><i class="bi bi-bug-fill me-2"></i> Bir Sorun Oluştu</h2>
            <p class="lead">Ürün bilgileri şu anda yüklenemiyor. Teknik bir aksaklık olabilir.</p>
            <hr>
            <p class="mb-0">Lütfen daha sonra tekrar deneyin veya <a href="../index.php" class="alert-link">ana sayfaya</a> dönün.</p>
        </div>
    <?php endif; ?>
  </div>

  <footer class="text-white pt-5 pb-4" style="background-color: #343a40;">
    <div class="container text-center text-md-start">
        <div class="row text-center text-md-start">
            <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">ETİCARET</h5>
                <p>El emeği göz nuru ürünlerin, zanaatkarların hikayeleriyle buluştuğu online pazar yeriniz. Kalite ve özgünlük bir tık uzağınızda.</p>
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">Ürünler</h5>
                <p><a href="#" class="text-white" style="text-decoration: none;">Seramik & Çini</a></p>
                <p><a href="#" class="text-white" style="text-decoration: none;">Ahşap İşleri</a></p>
            </div>
            <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">Faydalı Linkler</h5>
                <p><a href="customer_dashboard.php" class="text-white" style="text-decoration: none;">Hesabım</a></p>
                <p><a href="seller_register.php" class="text-white" style="text-decoration: none;">Satıcı Ol</a></p>
            </div>
            <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">İletişim</h5>
                <p><i class="bi bi-house-door-fill me-3"></i> İstanbul, TR</p>
                <p><i class="bi bi-envelope-fill me-3"></i> bilgi@eticaretprojesi.com</p>
            </div>
        </div>
        <hr class="mb-4">
        <div class="row align-items-center">
            <div class="col-md-7 col-lg-8">
                <p class="text-center text-md-start">© <?php echo date("Y"); ?> ETİCARET Projesi - Tüm Hakları Saklıdır.
                    <a href="#" style="text-decoration: none;"><strong class="text-warning">Helin Ö.</strong></a>
                </p>
            </div>
             <div class="col-md-5 col-lg-4">
                <div class="text-center text-md-end">
                    <ul class="list-unstyled list-inline">
                        <li class="list-inline-item"><a href="#" class="btn-floating btn-sm text-white" style="font-size: 23px;"><i class="bi bi-facebook"></i></a></li>
                        <li class="list-inline-item"><a href="#" class="btn-floating btn-sm text-white" style="font-size: 23px;"><i class="bi bi-instagram"></i></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
        const mainImage = document.getElementById('mainProductImage');
        var triggerTabList = [].slice.call(document.querySelectorAll('#productInfoTabs button'))
        triggerTabList.forEach(function (triggerEl) {
          var tabTrigger = new bootstrap.Tab(triggerEl)
          triggerEl.addEventListener('click', function (event) {
            event.preventDefault()
            tabTrigger.show()
          })
        })
    });
  </script>
</body>
</html>
