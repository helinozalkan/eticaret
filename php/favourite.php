<?php
// favourite.php - Favorilerim Sayfası

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once '../database.php'; // Veritabanı bağlantısı

// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null; // Kullanıcı ID'sini integer olarak al
$username_session = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;
$current_user_role = $logged_in && isset($_SESSION['role']) ? $_SESSION['role'] : null;


$favorite_products = []; // Favori ürünleri tutacak dizi
$message = "";           // Mesajları tutacak değişken

// Sadece giriş yapmış kullanıcılar favorilerini görebilir
if (!$logged_in) {
    $_SESSION['error_message'] = "Favorilerinizi görmek için lütfen giriş yapın.";
    header("Location: login.php?redirect=favourite.php"); // Giriş sonrası favorilere yönlendir
    exit;
}

// Favorilerden ürün çıkarma işlemi (POST ile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_favorites'])) {
    if (isset($_POST['urun_id']) && $current_user_id) {
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
            header("Location: favourite.php"); // Sayfayı yenile
            exit;
        } catch (PDOException $e) {
            error_log("favourite.php: Favori silme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Favori silinirken teknik bir sorun oluştu.";
            header("Location: favourite.php");
            exit;
        }
    }
}


// Kullanıcının favori ürünlerini çek
if ($current_user_id) {
    try {
        // Favoriler tablosunu Urun tablosu ile JOIN yaparak ürün bilgilerini alıyoruz.
        // Urun tablosundaki sütun adlarının (Urun_Adi, Urun_Fiyati, Urun_Gorseli) doğru olduğundan emin olun.
        $sql_favorites = "SELECT u.Urun_ID, u.Urun_Adi, u.Urun_Fiyati, u.Urun_Gorseli
                          FROM Favoriler f
                          JOIN Urun u ON f.Urun_ID = u.Urun_ID
                          WHERE f.Kullanici_ID = :kullanici_id AND u.Aktiflik_Durumu = 1
                          ORDER BY f.Ekleme_Tarihi DESC";

        $stmt_favorites = $conn->prepare($sql_favorites);
        $stmt_favorites->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
        $stmt_favorites->execute();
        $favorite_products = $stmt_favorites->fetchAll(PDO::FETCH_ASSOC);

        if (empty($favorite_products) && !isset($_SESSION['success_message']) && !isset($_SESSION['error_message'])) { // Mesaj yoksa ve ürün de yoksa bu mesajı ayarla
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
  <!-- !BOOTSTRAP'S CSS-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- !BOOTSTRAP'S CSS-->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css"> <!-- Ana CSS dosyanızın yolu, php klasöründen bir üst dizine çıkıyor -->
  <link href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <style>
    body {
        font-family: 'Montserrat', sans-serif; /* Daha modern bir font */
        margin: 0;
        padding: 0;
        background-color: #f8f9fa; /* Bootstrap'in hafif gri arka planı */
    }
    .custom-container { /* Bootstrap container yerine özel bir isim */
        width: 90%;     /* Biraz daha geniş */
        max-width: 1200px; /* Maksimum genişlik */
        margin: 30px auto; /* Üst ve alttan daha fazla boşluk */
        background-color: #fff;
        padding: 30px;  /* Daha fazla iç boşluk */
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); /* Daha yumuşak gölge */
        border-radius: 8px; /* Köşeleri yuvarlat */
    }
    .page-title {
        text-align: center;
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem; /* Biraz daha büyük */
        margin-bottom: 30px;
        color: #333;
        border-bottom: 2px solid #0d6efd; /* Bootstrap primary rengi */
        padding-bottom: 10px;
        display: inline-block; /* Border'ın sadece metin altında kalması için */
    }
    .page-title-container { /* Başlığı ortalamak için */
        text-align: center;
        margin-bottom: 30px;
    }
    .favorites {
        display: flex;
        flex-wrap: wrap;
        gap: 25px; /* Biraz daha fazla aralık */
        justify-content: center; /* Ortalamak için */
    }
    .favorite-item {
        background-color: #fff;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
        width: calc(50% - 12.5px); /* gap'i hesaba katarak 2 sütun */
        box-sizing: border-box;
        display: flex;
        align-items: center;
        border-radius: 8px;
        transition: transform 0.2s ease-out, box-shadow 0.2s ease-out;
    }
    .favorite-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    }
    .favorite-image-link {
        display: block; /* Resmin tamamının link olması için */
        width: 150px;
        height: 150px;
        margin-right: 20px;
        flex-shrink: 0; /* Resmin küçülmesini engelle */
    }
    .favorite-image {
        width: 100%;
        height: 100%;
        border-radius: 8px; /* Daha yumuşak köşeler */
        object-fit: cover; /* Resmin orantısını koru */
    }
    .favorite-info {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .favorite-name {
        font-size: 1.25rem; /* Bootstrap h5 boyutuna yakın */
        font-weight: 600;
        margin: 0 0 8px;
        color: #333;
    }
    .favorite-name a {
        color: inherit;
        text-decoration: none;
    }
    .favorite-name a:hover {
        color: #0d6efd;
    }
    .favorite-price {
        font-size: 1.15rem; /* Biraz daha büyük */
        color: #B12704; /* Amazon fiyat rengi */
        font-weight: bold;
        margin: 0 0 15px; /* Butonla arasına boşluk */
    }
    .remove-button {
        padding: 8px 15px;
        background-color: #dc3545; /* Bootstrap danger rengi */
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: background-color 0.2s ease;
        align-self: flex-start; /* Butonu sola yasla */
    }
    .remove-button:hover {
        background-color: #c82333; /* Daha koyu danger */
    }
    .alert-custom { /* Mesajlar için */
        margin-bottom: 20px;
    }
    @media (max-width: 768px) { /* Mobil uyumluluk */
        .favorite-item {
            width: 100%; /* Mobilde tek sütun */
            flex-direction: column; /* Mobilde resmi üste al */
            align-items: flex-start; /* İçeriği sola yasla */
        }
        .favorite-image-link {
            width: 100%;
            height: 200px; /* Mobilde resim daha büyük olabilir */
            margin-right: 0;
            margin-bottom: 15px;
        }
        .favorite-info {
            width: 100%;
        }
        .remove-button {
            align-self: stretch; /* Mobilde butonu tam genişlik yap */
            text-align: center;
        }
    }
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
          <a href="favourite.php" class="text-white me-3"><i class="bi bi-heart-fill fs-5"></i></a> <!-- Aktif sayfa ikonu -->
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

  <div class="custom-container">
    <div class="page-title-container">
        <h1 class="page-title">Favorilerim</h1>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show alert-custom" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-custom" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($message) && empty($favorite_products)): ?>
        <div class="alert alert-info text-center alert-custom">
            <i class="bi bi-info-circle-fill me-2"></i><?php echo $message; ?>
        </div>
    <?php elseif (empty($favorite_products) && empty($message)): ?>
         <div class="alert alert-info text-center alert-custom">
            <i class="bi bi-emoji-frown me-2"></i>Henüz favorilerinize eklediğiniz bir ürün bulunmuyor. <a href="../index.php" class="alert-link">Alışverişe başlayın!</a>
        </div>
    <?php else: ?>
        <div class="favorites">
            <?php foreach ($favorite_products as $fav_product): ?>
                <div class="favorite-item">
                    <a href="product_detail.php?id=<?php echo htmlspecialchars($fav_product['Urun_ID']); ?>" class="favorite-image-link">
                        <img src="<?php echo $fav_product['Urun_Gorseli'] ? '../uploads/' . htmlspecialchars($fav_product['Urun_Gorseli']) : 'https://placehold.co/150x150/EFEFEF/AAAAAA?text=Görsel+Yok'; ?>"
                             alt="<?php echo htmlspecialchars($fav_product['Urun_Adi']); ?>" class="favorite-image">
                    </a>
                    <div class="favorite-info">
                        <h2 class="favorite-name">
                            <a href="product_detail.php?id=<?php echo htmlspecialchars($fav_product['Urun_ID']); ?>">
                                <?php echo htmlspecialchars($fav_product['Urun_Adi']); ?>
                            </a>
                        </h2>
                        <p class="favorite-price"><?php echo number_format($fav_product['Urun_Fiyati'], 2, ',', '.'); ?> TL</p>
                        <form action="favourite.php" method="POST" class="mt-auto">
                            <input type="hidden" name="urun_id" value="<?php echo htmlspecialchars($fav_product['Urun_ID']); ?>">
                            <button type="submit" name="remove_from_favorites" class="remove-button">
                                <i class="bi bi-trash3 me-1"></i> Favorilerden Kaldır
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
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
                <p class="text-center text-md-start">© <?php echo date("Y"); ?> ETİCARET Projesi - Tüm Hakları Saklıdır.</p>
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
</body>
</html>
