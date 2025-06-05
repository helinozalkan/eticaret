<?php
// customer_review.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once '../database.php'; // Veritabanı bağlantısı

// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? $_SESSION['user_id'] : null;
$current_user_role = $logged_in ? $_SESSION['role'] : null; // Rol kontrolü için
$username = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;

// Yorum yapılacak ürünün ID'sini URL'den al
$product_to_review_id = null;
$product_name_to_review = "Bilinmeyen Ürün";
$message = ""; // Form işleme mesajları için

if (isset($_GET['product_id']) && filter_var($_GET['product_id'], FILTER_VALIDATE_INT)) {
    $product_to_review_id = (int)$_GET['product_id'];

    // Ürün adını çek (opsiyonel, sayfada göstermek için)
    try {
        $stmt_product_name = $conn->prepare("SELECT Urun_Adi FROM Urun WHERE Urun_ID = :urun_id");
        $stmt_product_name->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
        $stmt_product_name->execute();
        $product_data = $stmt_product_name->fetch(PDO::FETCH_ASSOC);
        if ($product_data) {
            $product_name_to_review = htmlspecialchars($product_data['Urun_Adi']);
        } else {
            $message = "Yorum yapılacak ürün bulunamadı.";
            // Ürün bulunamazsa formu göstermeyebilir veya kullanıcıyı yönlendirebilirsiniz.
        }
    } catch (PDOException $e) {
        error_log("customer_review.php: Ürün adı çekme hatası: " . $e->getMessage());
        $message = "Ürün bilgileri yüklenirken bir sorun oluştu.";
    }
} else {
    $message = "Yorum yapılacak ürün belirtilmemiş.";
    // product_id yoksa, kullanıcıyı ana sayfaya veya ürün listesine yönlendirebilirsiniz.
    // header("Location: ../index.php");
    // exit;
}

// Sadece giriş yapmış ve 'customer' rolündeki kullanıcılar yorum yapabilir
if (!$logged_in || $current_user_role !== 'customer') {
    // Kullanıcıyı giriş sayfasına yönlendir veya bir hata mesajı göster
    $_SESSION['review_error_message'] = "Yorum yapabilmek için müşteri olarak giriş yapmanız gerekmektedir.";
    header("Location: login.php?redirect=product_detail.php?id=" . $product_to_review_id); // Giriş sonrası geri dönmek için
    exit;
}

// Form gönderildi mi kontrolü (Bu kısım daha sonra doldurulacak)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // Form işleme mantığı buraya gelecek
    // Puanı ve yorumu al
    // Veritabanına kaydet
    // Başarı veya hata mesajı ayarla
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment_text = isset($_POST['comment_text']) ? trim(htmlspecialchars($_POST['comment_text'])) : '';

    if ($product_to_review_id && $current_user_id && $rating >= 1 && $rating <= 5 && !empty($comment_text)) {
        try {
            // Yorumu veritabanına ekle
            // Yorumlar tablonuzun adını ve sütunlarını kontrol edin
            $sql_insert_review = "INSERT INTO Yorumlar (Urun_ID, Kullanici_ID, Puan, Yorum_Metni, Yorum_Tarihi, Onay_Durumu)
                                  VALUES (:urun_id, :kullanici_id, :puan, :yorum_metni, NOW(), 0)"; // Onay_Durumu 0 = onay bekliyor

            $stmt_insert = $conn->prepare($sql_insert_review);
            $stmt_insert->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':puan', $rating, PDO::PARAM_INT);
            $stmt_insert->bindParam(':yorum_metni', $comment_text, PDO::PARAM_STR);

            if ($stmt_insert->execute()) {
                $_SESSION['success_message'] = "Yorumunuz başarıyla gönderildi ve onay bekliyor.";
                header("Location: product_detail.php?id=" . $product_to_review_id);
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
  <link rel="stylesheet" href="../css/css.css"> <!-- Ana CSS dosyanızın yolu -->
  <style>
    body { background-color: #f8f9fa; }
    .rating-stars .bi-star, .rating-stars .bi-star-fill {
        font-size: 2rem; /* Yıldız boyutu */
        color: #ffc107; /* Yıldız rengi */
        cursor: pointer;
        margin-right: 5px;
    }
    .rating-stars .bi-star-fill.hover, .rating-stars .bi-star.hover {
        transform: scale(1.1); /* Hover efekti */
    }
    .form-label { font-weight: 500; }
  </style>
</head>
<body>
  <!-- NAVİGASYON ÇUBUĞU (index.php veya product_detail.php'deki gibi, yollar ayarlandı) -->
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
            <?php if ($logged_in && $username): ?>
              <div class="dropdown">
                <a href="#" class="text-white dropdown-toggle" data-bs-toggle="dropdown" style="font-size: 15px; text-decoration: none;">
                  <?php echo $username; ?>
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
  <!-- NAVİGASYON ÇUBUĞU SONU -->

  <!-- YORUM FORMU İÇERİĞİ BAŞLANGICI -->
  <div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Ürün Değerlendirmesi Yap</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($message) && strpos($message, "belirtilmemiş") === false && strpos($message, "bulunamadı") === false ): // Sadece form gönderim hatalarını göster ?>
                        <div class="alert alert-danger"><?php echo $message; ?></div>
                    <?php elseif (isset($_SESSION['review_error_message'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['review_error_message']; unset($_SESSION['review_error_message']); ?></div>
                    <?php endif; ?>

                    <?php if ($product_to_review_id && empty($message) || (!empty($message) && (strpos($message, "belirtilmemiş") !== false || strpos($message, "bulunamadı") !== false )) ): ?>
                        <h5 class="card-title mb-3">Değerlendirilen Ürün: <?php echo $product_name_to_review; ?></h5>
                        <form action="customer_review.php?product_id=<?php echo htmlspecialchars($product_to_review_id); ?>" method="POST" id="reviewForm">
                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_to_review_id); ?>">
                            <input type="hidden" name="submit_review" value="1">

                            <div class="mb-3">
                                <label for="rating" class="form-label">Puanınız:</label>
                                <div class="rating-stars d-flex" id="ratingStars">
                                    <i class="bi bi-star" data-value="1"></i>
                                    <i class="bi bi-star" data-value="2"></i>
                                    <i class="bi bi-star" data-value="3"></i>
                                    <i class="bi bi-star" data-value="4"></i>
                                    <i class="bi bi-star" data-value="5"></i>
                                </div>
                                <input type="hidden" name="rating" id="ratingInput" value="0" required>
                            </div>

                            <div class="mb-3">
                                <label for="comment_text" class="form-label">Yorumunuz:</label>
                                <textarea class="form-control" id="comment_text" name="comment_text" rows="5" placeholder="Ürün hakkındaki düşüncelerinizi buraya yazın..." required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Yorumu Gönder</button>
                        </form>
                    <?php elseif(empty($product_to_review_id) || !empty($message)): ?>
                         <div class="alert alert-danger"><?php echo $message; ?></div>
                         <a href="../index.php" class="btn btn-secondary">Ana Sayfaya Dön</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
  </div>
  <!-- YORUM FORMU İÇERİĞİ SONU -->

  <!-- FOOTER (index.php'deki gibi, yollar ayarlandı) -->
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
  <!-- FOOTER SONU -->

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Yıldız puanlama sistemi için JavaScript
    document.addEventListener('DOMContentLoaded', function () {
        const starsContainer = document.getElementById('ratingStars');
        const ratingInput = document.getElementById('ratingInput');
        if (starsContainer && ratingInput) {
            const stars = starsContainer.querySelectorAll('.bi-star, .bi-star-fill');

            stars.forEach(star => {
                star.addEventListener('mouseover', function () {
                    resetStars();
                    const currentValue = parseInt(this.dataset.value);
                    highlightStars(currentValue);
                });

                star.addEventListener('mouseout', function () {
                    resetStars();
                    const selectedValue = parseInt(ratingInput.value);
                    if (selectedValue > 0) {
                        highlightStars(selectedValue);
                    }
                });

                star.addEventListener('click', function () {
                    const selectedValue = parseInt(this.dataset.value);
                    ratingInput.value = selectedValue;
                    highlightStars(selectedValue); // Kalıcı olarak seçili göster
                });
            });

            function highlightStars(value) {
                stars.forEach(s => {
                    if (parseInt(s.dataset.value) <= value) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill');
                    } else {
                        s.classList.remove('bi-star-fill');
                        s.classList.add('bi-star');
                    }
                });
            }

            function resetStars() {
                stars.forEach(s => {
                    s.classList.remove('bi-star-fill');
                    s.classList.add('bi-star');
                });
            }
        }
    });
  </script>
</body>
</html>