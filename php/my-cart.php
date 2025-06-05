<?php
// my_cart.php - Sepetim Sayfası

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include_once '../database.php'; // Veritabanı bağlantısı (eğer ürün detay linkleri için gerekirse)

// Giriş yapmış kullanıcı bilgilerini al
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;
$current_user_role = $logged_in && isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Sepet session'ını al
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$message = ""; // Genel mesajlar için

// Sepet güncelleme veya ürün çıkarma işlemleri (POST ile)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ürün adedini güncelleme
    if (isset($_POST['update_quantity']) && isset($_POST['product_id']) && isset($_POST['quantity'])) {
        $product_id_to_update = (int)$_POST['product_id'];
        $new_quantity = (int)$_POST['quantity'];

        if (isset($cart_items[$product_id_to_update])) {
            if ($new_quantity > 0) {
                // Stok kontrolü (veritabanından güncel stok çekilmeli)
                try {
                    $stmt_stock = $conn->prepare("SELECT Stok_Adedi FROM Urun WHERE Urun_ID = :urun_id");
                    $stmt_stock->bindParam(':urun_id', $product_id_to_update, PDO::PARAM_INT);
                    $stmt_stock->execute();
                    $product_stock_data = $stmt_stock->fetch(PDO::FETCH_ASSOC);

                    if ($product_stock_data && $product_stock_data['Stok_Adedi'] >= $new_quantity) {
                        $_SESSION['cart'][$product_id_to_update]['quantity'] = $new_quantity;
                        $_SESSION['success_message'] = "Sepet güncellendi.";
                    } else {
                        $_SESSION['error_message'] = "Stokta yeterli ürün yok. Maksimum adet: " . ($product_stock_data ? $product_stock_data['Stok_Adedi'] : 0);
                    }
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Stok kontrolü sırasında bir hata oluştu.";
                    error_log("my_cart.php: Stok kontrol hatası: " . $e->getMessage());
                }
            } else { // Adet 0 veya daha az ise ürünü çıkar
                unset($_SESSION['cart'][$product_id_to_update]);
                $_SESSION['success_message'] = "Ürün sepetten çıkarıldı.";
            }
        }
        header("Location: my_cart.php"); // Sayfayı yenile
        exit;
    }

    // Ürünü sepetten çıkarma
    if (isset($_POST['remove_item']) && isset($_POST['product_id'])) {
        $product_id_to_remove = (int)$_POST['product_id'];
        if (isset($_SESSION['cart'][$product_id_to_remove])) {
            unset($_SESSION['cart'][$product_id_to_remove]);
            $_SESSION['success_message'] = "Ürün sepetten başarıyla çıkarıldı.";
        }
        header("Location: my_cart.php"); // Sayfayı yenile
        exit;
    }

    // Sepeti boşaltma
    if (isset($_POST['clear_cart'])) {
        unset($_SESSION['cart']);
        $_SESSION['success_message'] = "Sepetiniz başarıyla boşaltıldı.";
        header("Location: my_cart.php");
        exit;
    }
}
// Sepet session'ını tekrar al (güncellemeler sonrası)
$cart_items = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sepetim - ETİCARET</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="../css/css.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; font-family: 'Montserrat', sans-serif; }
    .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: .25rem; }
    .quantity-input { width: 70px; text-align: center; }
    .page-title { font-family: 'Playfair Display', serif; color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; display: inline-block; }
    .page-title-container { text-align: center; margin-bottom: 30px; }
    .cart-summary { background-color: #e9ecef; padding: 20px; border-radius: .375rem; }
    .table th, .table td { vertical-align: middle; }
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
          <a href="my_cart.php" class="text-white me-3"><i class="bi bi-cart-check-fill fs-5"></i></a> <!-- Aktif sayfa ikonu -->
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
    <div class="page-title-container">
        <h1 class="page-title">Sepetim</h1>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="alert alert-info text-center">
            <i class="bi bi-cart-x-fill me-2 fs-4"></i>Sepetinizde henüz ürün bulunmuyor.
            <a href="../index.php" class="alert-link">Hemen alışverişe başlayın!</a>
        </div>
    <?php else: ?>
        <div class="table-responsive shadow-sm bg-white p-3 rounded">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width: 10%;">Görsel</th>
                        <th scope="col" style="width: 35%;">Ürün Adı</th>
                        <th scope="col" class="text-center" style="width: 15%;">Fiyat</th>
                        <th scope="col" class="text-center" style="width: 15%;">Adet</th>
                        <th scope="col" class="text-center" style="width: 15%;">Ara Toplam</th>
                        <th scope="col" class="text-center" style="width: 10%;">Kaldır</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $id => $item): ?>
                        <tr class="cart-item">
                            <td>
                                <a href="product_detail.php?id=<?php echo $id; ?>">
                                    <img src="<?php echo $item['image'] ? '../uploads/' . htmlspecialchars($item['image']) : 'https://placehold.co/80x80/EFEFEF/AAAAAA?text=Görsel'; ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </a>
                            </td>
                            <td>
                                <a href="product_detail.php?id=<?php echo $id; ?>" class="text-dark text-decoration-none fw-medium">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </td>
                            <td class="text-center"><?php echo number_format($item['price'], 2, ',', '.'); ?> TL</td>
                            <td class="text-center">
                                <form action="my_cart.php" method="POST" class="d-inline-flex align-items-center">
                                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="form-control form-control-sm quantity-input me-2">
                                    <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </form>
                            </td>
                            <td class="text-center fw-bold"><?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?> TL</td>
                            <td class="text-center">
                                <form action="my_cart.php" method="POST" class="d-inline">
                                    <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                    <button type="submit" name="remove_item" class="btn btn-sm btn-outline-danger" title="Ürünü Kaldır">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row mt-4">
            <div class="col-md-6 mb-3 mb-md-0">
                <a href="../index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle me-2"></i>Alışverişe Devam Et</a>
                <form action="my_cart.php" method="POST" class="d-inline ms-2">
                    <button type="submit" name="clear_cart" class="btn btn-danger" onclick="return confirm('Sepetinizi boşaltmak istediğinizden emin misiniz?');">
                        <i class="bi bi-cart-x me-2"></i>Sepeti Boşalt
                    </button>
                </form>
            </div>
            <div class="col-md-6">
                <div class="cart-summary">
                    <h4 class="mb-3">Sepet Özeti</h4>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Ara Toplam:</span>
                        <span><?php echo number_format($total_price, 2, ',', '.'); ?> TL</span>
                    </div>
                    <!-- Kargo ücreti, indirim vb. eklenebilir -->
                    <hr>
                    <div class="d-flex justify-content-between fw-bold fs-5">
                        <span>Genel Toplam:</span>
                        <span><?php echo number_format($total_price, 2, ',', '.'); ?> TL</span>
                    </div>
                    <div class="d-grid mt-3">
                        <a href="checkout.php" class="btn btn-primary btn-lg">
                           <i class="bi bi-credit-card-fill me-2"></i> Siparişi Tamamla
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
  </div>

  <footer class="text-white pt-5 pb-4" style="background-color: #343a40;">
    <div class="container text-center text-md-start">
        <div class="row text-center text-md-start">
            <div class="col-md-3 col-lg-3 col-xl-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">ETİCARET</h5>
                <p>El emeği göz nuru ürünlerin, zanaatkarların hikayeleriyle buluştuğu online pazar yeriniz.</p>
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">Linkler</h5>
                <p><a href="../index.php" class="text-white" style="text-decoration: none;">Ana Sayfa</a></p>
                <p><a href="favourite.php" class="text-white" style="text-decoration: none;">Favorilerim</a></p>
            </div>
            <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">Destek</h5>
                <p><a href="#" class="text-white" style="text-decoration: none;">Yardım & SSS</a></p>
                <p><a href="#" class="text-white" style="text-decoration: none;">İletişim</a></p>
            </div>
            <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3">
                <h5 class="text-uppercase mb-4 fw-bold text-warning">İletişim</h5>
                <p><i class="bi bi-geo-alt-fill me-3"></i> İstanbul, TR</p>
                <p><i class="bi bi-envelope-fill me-3"></i> destek@eticaret.com</p>
            </div>
        </div>
        <hr class="mb-4">
        <div class="text-center"><p>© <?php echo date("Y"); ?> ETİCARET Projesi</p></div>
    </div>
  </footer>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
