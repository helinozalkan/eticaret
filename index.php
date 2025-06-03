<?php
session_start();
include_once 'database.php'; // include_once kullanıldı

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']); // Kullanıcı giriş yapmış mı kontrol et
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null; // Kullanıcı adını al ve temizle

// Aktif ürünleri veri tabanından çek
$products = []; // Ürünleri tutacak dizi
try {
    $query = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Indirimli_Fiyat FROM Urun WHERE Aktiflik_Durumu = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("index.php: Ürünler çekilirken veritabanı hatası: " . $e->getMessage());
    // Hata durumunda ürün listesi boş kalır, kullanıcıya genel bir mesaj gösterilebilir.
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ana Sayfa</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

<body>
  <nav class="navbar  navbar-expand-lg navbar-dark" style="background-color:rgb(155, 10, 109) ; ">
    <div class="container-fluid">

      <a class="navbar-brand d-flex ms-4" href="#" style="margin-left: 5px;">
        <img src="images/logo.png" alt="Logo" width="35" height="35" class="align-text-top">

        <div class="baslik fs-3">
          <a class="dropdown-item" href="index.php">
            EL EMEK
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
            <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
              Ana sayfa
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Girişimci Sayısı</a>
              <a class="dropdown-item" href="#">Yeni Ürünlerimiz</a>
              <a class="dropdown-item" href="#">Eticaret Ekibi</a>
              <a class="dropdown-item" href="#">indirim Haberleri</a>
              <a class="dropdown-item" href="#">Popüler Ürünler</a>
              <a class="dropdown-item" href="#">Başarılı Satıcılar</a>
            </div>
          </li>

          
          <li class="nav-item dropdown ps-3">
            <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
              Siparişlerim
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="php/customer_orders.php">Sipariş Detay</a>
              <a class="dropdown-item" href="#">Sipariş Listem</a>
              <a class="dropdown-item" href="#">Geçmiş Siparişlerim</a>
            </div>
          </li>


          <li class="nav-item dropdown ps-3">
            <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
              Mağazalar
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="php/seller_register.php">Satıcı oluştur</a>
              <a class="dropdown-item" href="php/motivation.php">Eticaret Ekibinden Mesaj Var</a>
              <a class="dropdown-item" href="#">Kategoriler</a>
              <a class="dropdown-item" href="#">Özellikler</a>
              <a class="dropdown-item" href="#">Satıcı Profili</a>
            </div>
          </li>

          <li class="nav-item dropdown ps-3">
            <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
              Yorumlar
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Yorum Yaz</a>
              <a class="dropdown-item" href="#">Yorum Oku</a>
              <a class="dropdown-item" href="#">Yorum Analizi</a>
              <a class="dropdown-item" href="#">Favori Yorumlar</a>

            </div>
          </li>



          <li class="nav-item dropdown ps-3">
            <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
              Hakkımızda
            </button>
            <div class="dropdown-menu" aria-labelledby="navbarDropdown">
              <a class="dropdown-item" href="#">Hakkımızda</a>
              <a class="dropdown-item" href="#">Gizlilik</a>
            </div>
          </li>


        </ul>

        <div style="margin-left: 0px;">
          <i class="bi bi-search text-white fs-5"></i>

          <a href="php/favourite.php">
          <i class="bi bi-heart text-white fs-5" style="margin-left: 20px;"></i>
          </a>

          <a href="php/my_cart.php">
            <i class="bi bi-cart3 text-white fs-5" style="margin-left: 20px;"></i>
          </a>
        </div>

        <div class="d-flex me-3" style="margin-left: 145px;">
    <i class="bi bi-person-circle text-white fs-4"></i>
    <?php if (isset($_SESSION['username'])): ?>
        <a href="php/logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            <?php echo htmlspecialchars($_SESSION['username']); ?> </a>
    <?php else: ?>
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











  <div class="container-fluid ">
    <div class="row  position-relative">
      <div class="slideshow">
        <img src="images/index.jpg" class="img-fluid w-100 responsive-img slide-img">
        <img src="images/indexx.jpg" class="img-fluid w-100 responsive-img slide-img">
      </div>
      <div class="position-absolute top-50  start-50 translate-middle w-50" style="margin-top: -70px;">
        <div class="text-center">
          <div class="text-center fw-bold mb-3" style="color: black; font-size: 1vw;">Düşle, İnan , Başar</div>
          <div class="baslik2" style="color: black; font-size: 4vw;">El İşçiliğiyle Üretilmiş, Emeğe Saygı Duyanların Tercihi</div>
          
        </div>
      </div>
    </div>
  </div>
  <section id="tranding">

    <div class="container-fluid " style="padding: 0px; margin-top: 50px;">
      <div class="swiper tranding-slider">
        <div class="swiper-wrapper">
          
        <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/salca.jpg" alt="Tranding">
            </div>
          </div>

          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/orgu.jpg" alt="Tranding">
            </div>

          </div>

          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/seramik.jpg" alt="Tranding">
            </div>

          </div>
          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/kekik.jpg" alt="Tranding">
            </div>
          </div>

          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/sarma.jpg" alt="Tranding">
            </div>
          </div>

          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/kozmetik.jpg" alt="Tranding">
            </div>

          </div>

          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/orgucanta.jpg" alt="Tranding">
            </div>

          </div>
          <div class="swiper-slide tranding-slide">
            <div class="tranding-slide-img">
              <img src="images/taki.jpg" alt="Tranding">
            </div>
          </div>



        </div>

      </div>
    </div>
  </section>






  <div class="wrapper  ">
  <div class="container border-end border-warning col-12 col-lg-3 text-center">
  <div class="sayac d-flex" style="padding-left: 115px;">
    <span class="num" data-value="15">00</span>
    <span style="margin-top: 40px;">+</span>
  </div>
  <span class="text">Yıllık Deneyim</span>
</div>
<div class="container border-end border-warning col-12 col-lg-3 text-center">
  <div class="sayac d-flex" style="padding-left: 130px;">
    <span class="num" data-value="50">00</span>
    <span style="margin-top: 40px;">+</span>
  </div>

  <span class="text">Girişimci</span>

</div>
<div class="container border-end border-warning col-12 col-lg-3 text-center">
  <div class="sayac d-flex" style="padding-left: 100px;">
    <span class="num" data-value="200">00</span>
    <span style="margin-top: 40px;">+</span>
  </div>
  <span class="text">Günlük Ziyaretçi</span>
</div>
<div class="container col-12 col-lg-3 text-center">
  <div class="sayac d-flex" style="padding-left: 110px;">
    <span class="num" data-value="35">00</span>
    <span style="margin-top: 40px;">+</span>
  </div>
  <span class="text">Başarılar</span>
</div>
  </div>
  <div class="container-fluid mt-5 bg-light py-5 ms-4">
  <div class="row">
    <div class="col-12 col-md-5 text-center py-5">
      <div class="text-start" style="color:rgb(155, 10, 109) ;">El Emeği Ürünlerin Hikayesine Ortak Olun</div>
      <div class="baslik3 text-start text-black fw-bold" style="font-size: 3vw;">Yeni Ürünlerimizi Deneyin</div>

      <div class="text-start">Geleneksel değerlerle modern tasarım anlayışını bir araya getiren bu e-ticaret deneyimi, sadece alışveriş yapmakla kalmayıp, zanaatın ruhunu keşfetmenize olanak tanır. Keşfedin, ilham alın ve emeğe değer katın.</div>
    </div>
    <div class="col-12 col-md-7">
      <div class="swiper ilk">
        <div class="swiper-wrapper mb-5">
          <div class="k swiper-slide iki"><img class="img" src="images/taki.jpg">
            <div class="text-overlay">Seramik ve Çini</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/kozmetik.jpg">
            <div class="text-overlay">Ahşap Ürünler</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/dogalsabun.jpg">
            <div class="text-overlay">Dokuma Ürünler</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/orgu.jpg">
            <div class="text-overlay">Takılar ve Aksesuarlar</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/recel.jpg">
            <div class="text-overlay">Deri Ürünler</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/orgucanta.jpg">
            <div class="text-overlay">Metal İşleri</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/bakim.jpg">
            <div class="text-overlay">Doğal Cilt Bakım Ürünleri</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/dekormum.jpg">
            <div class="text-overlay">El Yapımı Sabun ve Kozmetik Ürünleri</div>
          </div>
          <div class="k swiper-slide iki"><img class="img" src="images/gingercookie.jpg">
            <div class="text-overlay">Organik Gıda Ürünleri</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="container-fluid  mt-5">
    <div class="text-center">
      <div style="color:rgb(155, 10, 109) ;">
        Satışta Olan Ürünlerimiz
      </div>
      <div class="baslik3 " style="font-size: 50px;">
        Popüler Ürünler
      </div>
    </div>
  </div>


  <div class="container bg-light mt-5">
    <div class="row px-5">
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $urun): ?>
          <div class="col-6">
            <div class="a container bg-white mb-3" style="border-radius: 5%;">
              <div class="row mt-5 mb-5">
                <div class="col-6 text-center">
                  <img src="uploads/<?= htmlspecialchars($urun['Urun_Gorseli']) ?>" class="img-grow"
                    style="border-radius:5%; height: 230px; width: 230px;">
                </div>
                <div class="col-6">
                  <div class="baslik3 fw-bold" style="font-size: 21px;"><?= htmlspecialchars($urun['Urun_Adi']) ?></div>
                  <div class="starts" style="color:rgb(155, 10, 109) ;">
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                    <i class="bi bi-star-fill"></i>
                  </div>
                  <div style="font-size: 15px;margin-top: 10px;">
                    <?= htmlspecialchars($urun['Urun_Aciklamasi']) ?>
                  </div>
                  <div class="baslik3 fw-bold d-inline-block" style="font-size:30px;margin-top: 15px;">
                    <?= htmlspecialchars($urun['Urun_Fiyati']) ?> TL
                  </div>
                  <?php if (!empty($urun['Indirimli_Fiyat'])): ?>
                    <div class="baslik3 fw-bold d-inline-block"
                      style="font-size:20px; color: rgb(182, 182, 182);text-decoration: line-through; margin-left: 10px;">
                      <?= htmlspecialchars($urun['Indirimli_Fiyat']) ?> TL
                    </div>
                  <?php endif; ?>
                  <div>
                  <form action="php/add_to_cart.php" method="POST">
      <input type="hidden" name="urun_id" value="<?= $urun['Urun_ID'] ?>">
      <input type="hidden" name="boyut" value="1"> <input type="hidden" name="miktar" value="1"> <button type="submit" class="btn ms-2 text-white" style="background-color:rgb(155, 10, 109) ;border-radius: 20; height: 40px; width: 120px;margin-top: 13px;">Sepete Ekle</button>
       </form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-center">Şu anda satışta olan ürün bulunmamaktadır.</p>
      <?php endif; ?>
    </div>
  </div>
  <div class="container-fluid mt-5 bg-light">
  <div class="row">
    <div class="col-12 col-md-6">
      <div class="d-flex" style="margin-left: 70px;">
        <img src="images/dogalsabun.jpg" style="width: 400px; height: 450px; border-radius: 5%;">
        <img src="images/orgucanta.jpg"
          style="width: 260px; height: 300px; border-radius: 5%; margin-left: -180px; margin-top: 100px;">
      </div>
    </div>
    <div class="col-12 col-md-6 px-5 mt-4">
      <div class="text-start" style="color:rgb(155, 10, 109) ;">Hakkımızda</div>
      <div class="baslik3 text-start text-black fw-bold" style="font-size: 3vw;">Başarıya Giden Yolculuğumuz: Emekçilerimizin Özverisi.</div>
      <div class="text-start">Emekçi girişimcilerimizin azmi ve yaratıcılığı ile dolu bir yolculuk. Her biri kendi alanında fark yaratan Türkiye'nin dört bir yanındaki emekçinin hikayeleri.</div>
      <div class="row">
        <div class="col-6 border-end" style="margin-top: 20px;">
          <div class="mb-2">
            <i class="bi bi-check-circle" style="color:rgb(155, 10, 109) ;"></i> Sıcak ve Samimi Ortam
          </div>
          <div class="mb-4">
            <i class="bi bi-check-circle" style="color:rgb(155, 10, 109) ;"></i> Girişimciler İçin İlham Verici Hikayeler
          </div>
          <div>
            <button type="button" class="btn ms-2 text-white"
              style="background-color:rgb(155, 10, 109) ;border-radius: 20; height: 40px; width: 150px;margin-top: 50px;">Daha Fazla Bilgi</button>
          </div>
        </div>

        <div class="col-6 d-flex align-items-center mb-5 mt-0">
          <img src="images/zey.jpeg" alt="Ünlü Kadın"
            style="border-radius: 50%; height: 70px; width: 70px; margin-left: 10px;">
          <div class="ms-3">

            <div>Zeynep Nuriye Tekin</div>

            <div style="color: rgb(105, 101, 101); font-weight: bold; font-size: 12px;">Eticaret Ekibi</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<div class="container-fluid p-0 bg-dark mt-5" style="min-height: 200px; max-height: 50vh; height: auto;">
  <div class="row">
    <div class="baslik3 col-6 text-white p-5" style="font-weight:bold; font-size: 45px;">
      Girişimcilerden %50'den Fazla İndirim
      <div>
        <button type="button" class="btn ms-2 text-white"
          style="background-color:rgb(155, 10, 109) ;border-radius: 20; height: 40px; width: 120px;margin-top: 0px;">Hemen Al</button>
      </div>
    </div>
    <div class="col-6 text-white p-5">
      <div class="countdown">
        <div>
          <div id="day" style="display: none;">00</div>
        </div>
        <div class="border-danger p-5">
          <div class="border fixed-size" style="border-radius: 50%;padding: 20px;" id="hour">00</div>
          <div class="baslik3 fs-3 mt-3 ms-1">
            Saat
          </div>
        </div>
        <div class="border-danger p-5">
          <div class="border fixed-size" style="border-radius: 50%;padding: 20px;" id="minute">00</div>
          <div class="baslik3 fs-3 mt-3">
            Dakika
          </div>
        </div>
        <div class="p-5">
          <div class="border fixed-size" style="border-radius: 50%;padding: 20px;" id="second">00</div>
          <div class="baslik3 fs-3 mt-3">
            Saniye
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="container-fluid  mt-5">
    <div class="text-center">
      <div style="color:rgb(155, 10, 109) ;">
        Emekçi Kadınlarımız
      </div>
      <div class="baslik3 " style="font-size: 50px;">
        Yılın Girişimcileri
      </div>
    </div>
  </div>

  <div class=" container ">
    <div class="row bg-light px-5 ">
      <div class="col-4 mt-4 ">
        <div class=" b bg-light rounded-4 bg-white " style=" height: 410px; width: 350px;">
          <img src="images/kevser.jpeg " class="img-b rounded-top-4" style=" height: 300px; width: 350px;">
          <div class="baslik3 text-center fs-4 fw-bold mt-3">
            Ayşe Türkmen, Muğla
          </div>
          <div class="text-center" style="font-size: 13px; color:rgb(155, 10, 109) ;">
            Ev Hanımı
          </div>
          <div class="text-center mt-2" style="color:rgb(155, 10, 109) ;">
            <i class="bi bi-facebook  mx-2"></i>
            <i class="bi bi-linkedin mx-2"></i>
            <i class="bi bi-instagram mx-2"></i>
          </div>
        </div>
      </div>
      <div class="col-4 mt-4 ">
        <div class=" b bg-light rounded-4 bg-white" style=" height: 410px; width: 350px;">
          <img src="images/zey.jpeg " class="img-b rounded-top-4" style=" height: 300px; width: 350px;">
          <div class="baslik3 text-center fs-4 fw-bold mt-3">
            Zeynep Nuriye Tekin, Bilecik
          </div>
          <div class="text-center" style="font-size: 13px; color:rgb(155, 10, 109) ;">
            Yazılım Mühendisi
          </div>
          <div class="text-center mt-2 " style="color:rgb(155, 10, 109) ;">
            <i class="bi bi-facebook  mx-2"></i>
            <i class="bi bi-linkedin mx-2"></i>
            <i class="bi bi-instagram mx-2"></i>
          </div>
        </div>
      </div>
      <div class="col-4 mt-4 mb-5">
        <div class=" b bg-light rounded-4 bg-white" style=" height: 410px; width: 350px;">
          <img src="images/fatma.png " class="img-b rounded-top-4" style=" height: 300px; width: 350px;">
          <div class="baslik3 text-center fs-4 fw-bold mt-3">
            Seher Yılmaz, Burdur
          </div>
          <div class="text-center" style="font-size: 13px; color:rgb(155, 10, 109) ;">
            Takı Tasarımcısı
          </div>
          <div class="text-center mt-2" style="color:rgb(155, 10, 109) ;">
            <i class="bi bi-facebook  mx-2"></i>
            <i class="bi bi-linkedin mx-2"></i>
            <i class="bi bi-instagram mx-2"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="container text-center">
    <button type="button" class="btn ms-2 mt-5 mb-5 "
      style="border-color:rgb(155, 10, 109) ;border-radius: 20; height: 40px; width: 120px;margin-top: 13px;color:rgb(155, 10, 109) ;"> Daha fazla</button>
  </div>
  <div class="container p-0 mt-5">
  <div class="text-center">
    <div style="color:rgb(155, 10, 109) ;">
      Yorumlar
    </div>
    <div class="baslik3" style="font-size: 50px;">
      Girişimcilerde Gelen Yorumlar
    </div>
  </div>
</div>

<div class="swiper my">
  <div class="x swiper-wrapper">
    <div class="z swiper-slide">
      <div class="text-center text-dark fw-normal fs-6">
        <img src="images/58.png" alt="Ünlü Kadın"
          style="border-radius: 50%; height: 100px; width: 100px; margin-left: 350px;margin-top: 30px;">
        <div class="fs-6 px-5 mt-3">
          Türkiye'nin her tarafından tüm ülkeye emek dağıtan girişimciler, yaratıcılığınız ve kararlılığınızla dünyayı değiştiriyorsunuz. Başarılarınızla gurur duyuyoruz.
        </div>
        <div class="baslik3 fw-bold fs-4 mt-4">
          Ahmet Gerçek
        </div>
        <div class="px-5 mt-1">
          Öğretmen
        </div>
        <div class="starts mx-3 mt-1" style="color:rgb(155, 10, 109) ;">
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
        </div>
      </div>
    </div>
    <div class="z swiper-slide">
      <div class="text-center text-dark fw-normal fs-6">
        <img src="images/yorum.jpg" alt="Ünlü Kadın"
          style="border-radius: 50%; height: 100px; width: 100px; margin-left: 350px;margin-top: 30px;">
        <div class="fs-6 px-5 mt-3">
          Kendi işini kuran girişimci kadınlar, sizler ilham kaynağısınız. Azminiz ve çalışkanlığınızla geleceğe yön veriyorsunuz.
        </div>
        <div class="baslik3 fw-bold fs-4 mt-4">
          Ela Erdem
        </div>
        <div class="px-5 mt-1">
          CEO, Tech Innovators
        </div>
        <div class="starts mx-3 mt-1" style="color:rgb(155, 10, 109) ;">
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
        </div>
      </div>
    </div>
    <div class="z swiper-slide">
      <div class="text-center text-dark fw-normal fs-6">
        <img src="images/yorum2.jpg" alt="Ünlü Kadın"
          style="border-radius: 50%; height: 100px; width: 100px; margin-left: 350px;margin-top: 30px;">
        <div class="fs-6 px-5 mt-3">
          Değerli girişimciler, cesaretiniz ve yenilikçi ruhunuzla gurur duyuyoruz. Sizler, geleceğin liderlerisiniz.
        </div>
        <div class="baslik3 fw-bold fs-4 mt-4">
          Ali Yıldız
        </div>
        <div class="px-5 mt-1">
          Çiftçi
        </div>
        <div class="starts mx-3 mt-1" style="color:rgb(155, 10, 109) ;">
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
          <i class="bi bi-star-fill"></i>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="container-fluid text-white p-0 mt-5" style="width: 100%;">
    <div class="row p-0 position-relative">
      <img src="images/62.png" class="img-fluid w-100 position-absolute"
        style="top: 0; left: 0; z-index: -1; height: 100%;">
      <div class="container d-flex flex-column flex-lg-row text-white border-bottom border-white"
        style="z-index: 2; background-color: transparent; padding: 20px; margin-top: 20px; width: 90%;">
        <div class="col-lg-3 mb-4">
          <h4>Ürünler</h4>
          <p>Seramik ve Çini</p>
          <p>Organik Kozmetik</p>
          <p>Ahşap Ürünler</p>
          <p>El Örgüsü Ürünler</p>
          <p>Metal İşleri</p>
          <p>El Yapımı Sabun ve Kozmetik Ürünleri</p>
        </div>
        <div class="col-lg-3 mb-4">
          <h4>Bilgi</h4>
          <p>SSS</p>
          <p>Blog</p>
          <p>Destek</p>
        </div>
        <div class="col-lg-3 mb-4">
          <h4>Şirket</h4>
          <p>Hakkımızda</p>
          <p>Ürünlerimiz</p>
          <p>İletişim</p>
          <p>Başarı Hikayeleri</p>
        </div>
        <div class="col-lg-3 mb-4">
          <div class="container rounded-4 text-center p-4"
            style="background-color: rgba(255, 255, 255, 0.2); height: 100%; width: 400px;">
            <img class="rounded-4 mb-3" src="images/63.png" style="width: 100%; height: auto;">
            <div class="d-flex justify-content-center">
              <input type="email" class="rounded-3 border-0" placeholder="E-posta adresinizi girin"
                style="height: 50px; padding-left: 10px; width: 60%;">
              <button class="rounded-3 border-0 bg-danger text-white ml-2" type="button"
                style="height: 50px; width: 35%;">Abone Ol</button>
            </div>
          </div>
        </div>
      </div>
      <div class="container d-flex flex-column flex-lg-row justify-content-between align-items-center text-white"
        style="z-index: 2; background-color: transparent; margin-top: 20px; padding: 20px; width: 90%;">
        <div class="d-flex align-items-center mb-3 mb-lg-0">
          <img src="images/chef.png" alt="Logo" width="60" height="60" class="d-inline-block align-text-top">
          <div class="baslik ml-3" style="font-size: 40px;">El Emek</div>
        </div>
        <div class="d-flex flex-column flex-lg-row align-items-center mb-3 mb-lg-0">
          <p class="mb-0">Şartlar</p>
          <p class="mb-0 ml-lg-3">Gizlilik</p>
          <p class="mb-0 ml-lg-3">Çerezler</p>
        </div>
        <div class="d-flex align-items-center">
          <i class="icon bi bi-facebook mr-3"></i>
          <i class="icon bi bi-linkedin mr-3"></i>
          <i class="icon bi bi-instagram"></i>
        </div>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    var swiper = new Swiper(".my", {
      effect: "cards",
      grabCursor: true,
    });
  </script>
  <script>
    const countDate = new Date('August 24,2024 00:00:00').getTime();
    function newYear() {
      const now = new Date().getTime();
      let gap = countDate - now;

      let second = 1000;
      let minute = second * 60;
      let hour = minute * 60;
      let day = hour * 24;

      let d = Math.floor(gap / (day));
      let h = Math.floor((gap % (day)) / (hour));
      let m = Math.floor((gap % (hour)) / (minute));
      let s = Math.floor((gap % (minute)) / (second));

      document.getElementById('day').innerText = d;
      document.getElementById('hour').innerText = h;
      document.getElementById('minute').innerText = m;
      document.getElementById('second').innerText = s;
    }
    setInterval(function () {
      newYear()
    }, 1000)
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const scrollContainer = document.querySelector('.scroll-container');

      scrollContainer.addEventListener('wheel', (evt) => {
        evt.preventDefault();
        scrollContainer.scrollLeft += evt.deltaY;
      });
    });
  </script>
  <script>
    var TrandingSlider = new Swiper('.tranding-slider', {
      effect: 'coverflow',
      grabCursor: true,
      centeredSlides: true,
      loop: true,
      loopedSlides: 50,
      slidesPerView: 5,// Ekranda kaç görselin görüneceğini ayarlar
      spaceBetween: 40, // Görseller arasındaki boşluğu ayarlar (px cinsinden)
      coverflowEffect: {
        rotate: 0,
        stretch: 0,
        depth: 200,
        modifier: 0.5,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
    });
  </script>
  <script>
    let valueDisplays = document.querySelectorAll(".num");
    let interval = 1000;

    let startCounter = (valueDisplay) => {
      let startValue = 0;
      let endValue = parseInt(valueDisplay.getAttribute("data-value"));
      let duration = Math.floor(interval / endValue);
      let counter = setInterval(function () {
        startValue += 1;
        valueDisplay.textContent = startValue;
        if (startValue == endValue) {
          clearInterval(counter);
        }
      }, duration);
    };

    let observerOptions = {
      root: null, // Viewport as root
      threshold: 0.1 // Trigger when 10% of the element is visible
    };

    let observer = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          startCounter(entry.target);
          observer.unobserve(entry.target); // Stop observing once counter starts
        }
      });
    }, observerOptions);

    valueDisplays.forEach((valueDisplay) => {
      observer.observe(valueDisplay);
    });
  </script>
  <script>
    var swiper = new Swiper(".ilk", {
      slidesPerView: 3,
      spaceBetween: 30,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },
    });
  </script>




  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    xintegrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
    crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
  <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
</body>

</html>
