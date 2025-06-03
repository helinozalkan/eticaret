<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favorilerim</title>
     <!-- !BOOTSTRAP'S CSS-->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- !BOOTSTRAP'S CSS-->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="css/css.css">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
  <script src="https://code.jquery.com/jquery-1.8.2.min.js"
    integrity="sha256-9VTS8JJyxvcUR+v+RTLTsd0ZWbzmafmlzMmeZO9RFyk=" crossorigin="anonymous">
    </script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  </head>
    <style>
       body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f4f4f4;
}

.container {
    width: 80%;
    margin: 20px auto;
    background-color: #fff;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.page-title {
    text-align: center;
    font-size: 32px;
    margin-bottom: 20px;
}

.favorites {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.favorite-item {
    background-color: #fff;
    padding: 20px; /* Padding değerini artırdım */
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: calc(50% - 20px);
    box-sizing: border-box;
    display: flex;
    align-items: center;
}

.favorite-image {
    width: 150px; /* Genişliği artırdım */
    height: 150px; /* Yüksekliği artırdım */
    border-radius: 10px;
    margin-right: 20px;
}

.favorite-info {
    flex-grow: 1;
}

.favorite-name {
    font-size: 24px; /* Yazı boyutunu artırdım */
    margin: 0 0 10px;
}

.favorite-price {
    font-size: 22px; /* Yazı boyutunu artırdım */
    color:rgb(155, 10, 109) ;
    margin: 0 0 10px;
}

.remove-button {
    padding: 10px 20px;
    background-color:rgb(155, 10, 109) ;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.remove-button:hover {
    background-color:rgb(155, 10, 109) ;
}
    </style>

<body>
<nav class="navbar  navbar-expand-lg navbar-dark" style="background-color:rgb(155, 10, 109) ;">
    <div class="container-fluid">

      <a class="navbar-brand d-flex ms-4" href="#" style="margin-left: 5px;">
        <img src="../images/logo.png" alt="Logo" width="35" height="35" class="align-text-top">

        <div class="baslik fs-3">
          <a class="dropdown-item" href="../index.php">
            ELEMEK
          </a>
        </div>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
        <ul class="navbar-nav  me-auto mb-2 mb-lg-0 " style="margin-left: 110px;">
          <li class="nav-item dropdown ps-3">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Ana sayfa
            </a>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Pizza Palace</a>
              <a class="dropdown-item" href="#">Coffee House</a>
              <a class="dropdown-item" href="#">Gourmet Gateway</a>
              <a class="dropdown-item" href="#">Flavor Fiesta</a>
              <a class="dropdown-item" href="#">Epicurean Explorer</a>
              <a class="dropdown-item" href="#">Epicurean Explorer Dark</a>
            </div>
          </li>
          <li class="nav-item dropdown ps-3">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Satıcı
            </a>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="php/seller_register.php">Satıcı oluştur</a>
              <a class="dropdown-item" href="php/motivation.php">Girişimci Kadınlarımız</a>
              <a class="dropdown-item" href="#">Shop Details Coffee</a>
              <a class="dropdown-item" href="#">Cart</a>
              <a class="dropdown-item" href="#">Checkout</a>
            </div>
          </li>
          <li class="nav-item dropdown ps-3">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Blog
            </a>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Blog Grid One</a>
              <a class="dropdown-item" href="#">Blog Grid Two</a>
              <a class="dropdown-item" href="#">Blog Standard</a>
              <a class="dropdown-item" href="#">Blog Deails</a>

            </div>
          </li>
          <li class="nav-item dropdown ps-3">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Siparişlerim
            </a>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="php/customer_orders.php">Sipariş Detay</a>
              <a class="dropdown-item" href="#">Chefs</a>
              <a class="dropdown-item" href="#">Faq</a>
              <a class="dropdown-item" href="#">Reservation</a>
              <a class="dropdown-item" href="#">Food Menu</a>
            </div>
          </li>
          <li class="nav-item dropdown ps-3">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              Hakkımızda
            </a>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Contact</a>
              <a class="dropdown-item" href="#">Contact With Map</a>
            </div>
          </li>
        </ul>
        <!--SEARCH/FAVORİTES/CART-->
        <div style="margin-left: 0px;">
          <i class="bi bi-search text-white fs-5"></i>

          <a href="favourite.php">
          <i class="bi bi-heart text-white fs-5" style="margin-left: 20px;"></i>
          </a>

          <a href="my_cart.php">
            <i class="bi bi-cart3 text-white fs-5" style="margin-left: 20px;"></i>
          </a>
        </div>

        <div class="d-flex me-3" style="margin-left: 145px;">
    <i class="bi bi-person-circle text-white fs-4"></i>
    <?php if (isset($_SESSION['username'])): ?>
        <!-- Kullanıcı giriş yaptıysa -->
        <a href="php/logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            <?php echo htmlspecialchars($_SESSION['username']); ?> <!-- Kullanıcı adı gösteriliyor -->
        </a>
    <?php else: ?>
        <!-- Kullanıcı giriş yapmamışsa -->
        <a href="php/login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            Giriş Yap
        </a>
    <?php endif; ?>
</div>
      </div>
    </div>
    </div>
    </div>
  </nav>


    <div class="container">
        <h1 class="page-title">Favorilerim</h1>
        <div class="favorites">
            <div class="favorite-item">
                <img src="../images/recel.jpg" alt="Ürün 1" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Vişne Reçeli</h2>
                    <p class="favorite-price">₺100</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>

            <div class="favorite-item">
                <img src="../images/sarma.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Yaprak Sarma</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/seramik.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Seramik Kupa</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/borek.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Börek</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/kozmetik.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Kozmetik Ürün</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/salca.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Domates Salçası</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/sutreceli.jpeg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Süt Reçeli</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/orgu.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Örgü Bebek</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>
            <div class="favorite-item">
                <img src="../images/orgucanta.jpg" alt="Ürün 2" class="favorite-image">
                <div class="favorite-info">
                    <h2 class="favorite-name">Örgü Çanta</h2>
                    <p class="favorite-price">₺200</p>
                    <button class="remove-button">Favorilerden Kaldır</button>
                </div>
            </div>

            <!-- Daha fazla favori ürün ekleyebilirsiniz -->
        </div>

    </div>
</body>
</html>