<?php
// seller_manage.php - Satıcı Mağaza Yönetimi (İstatistikler ve Grafik)
session_start();
include_once '../database.php'; // include_once kullanıldı

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']); // Kullanıcı giriş yapmış mı kontrol et
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null; // Kullanıcı adını al ve temizle

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized"); // Yetkisiz erişim durumunda yönlendir
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null; // Satıcı ID'sini tutacak değişken
$message = ""; // Mesajları tutacak değişken

// Özel istisna sınıfı tanımla
class SellerManageException extends Exception {}

try {
    // Satıcı ID'sini al
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        throw new SellerManageException("Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturun.");
    }
    $satici_id = $satici_data['Satici_ID'];

    // Ürünleri listele (istatistikler için)
    $products = [];
    if ($satici_id !== null) {
        $query_products = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Aktiflik_Durumu FROM Urun WHERE Satici_ID = :satici_id";
        $stmt_products = $conn->prepare($query_products);
        $stmt_products->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

        // Dashboard kartları için sayılar
        $total_products = count($products);
        $active_products = 0;
        $removed_products = 0; // Pasif ürünler için

        foreach ($products as $product) {
            if ($product['Aktiflik_Durumu'] == 1) {
                $active_products++;
            } else {
                $removed_products++;
            }
        }

    } else {
        $total_products = 0;
        $active_products = 0;
        $removed_products = 0;
    }

} catch (SellerManageException $e) {
    error_log("seller_manage.php: Satıcı Yönetim Hatası: " . $e->getMessage());
    $message = htmlspecialchars($e->getMessage());
    $total_products = 0; $active_products = 0; $removed_products = 0; // Hata durumunda sıfırla
} catch (PDOException $e) {
    error_log("seller_manage.php: Veritabanı Hatası: " . $e->getMessage());
    $message = "Veritabanı işlemi sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
    $total_products = 0; $active_products = 0; $removed_products = 0; // Hata durumunda sıfırla
} catch (Exception $e) {
    error_log("seller_manage.php: Beklenmedik Hata: " . $e->getMessage());
    $message = "Beklenmedik bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
    $total_products = 0; $active_products = 0; $removed_products = 0; // Hata durumunda sıfırla
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Yönetim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="css/css.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
/>
 <script src="https://code.jquery.com/jquery-1.8.2.min.js" integrity="sha256-9VTS8JJyxvcUR+v+RTLTsd0ZWbzmafmlzMmeZO9RFyk=" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
    <style>
       body {
    font-family: 'Montserrat', sans-serif; /* Fontu güncelledim */
    background-color: #f4f4f9; /* Hafif bir arka plan rengi */
}

.navbar {
    background-color: #5b8cd5; /* Navbar rengi */
}
.navbar-brand .baslik {
    font-family: 'Playfair Display', serif; /* Marka fontu */
}

.container {
    width: 80%;
    margin: 20px auto;
    background-color: #fff;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px; /* Köşeleri yuvarla */
}

h1 {
    color: #333;
    text-align: center;
    margin-bottom: 30px;
}

.row {
    margin-top: 20px;
}

.card {
    transition: transform 0.3s ease-in-out;
    min-height: 150px; /* Kart yüksekliğini ayarladım */
    border-radius: 8px; /* Kart köşelerini yuvarla */
    overflow: hidden; /* İçerik taşmasını engelle */
}
.card:hover {
    transform: translateY(-5px); /* Hafif yukarı kaydırma efekti */
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); /* Daha belirgin gölge */
}
.card-header {
    background-color: rgba(0,0,0,0.05); /* Header için hafif arka plan */
    border-bottom: 1px solid rgba(0,0,0,0.125);
    font-weight: bold;
}
.card-body {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
}
.card-title {
    font-size: 2.5em; /* Başlık font boyutunu büyüttüm */
    margin-bottom: 0.5rem;
}
.card-text {
    font-size: 0.9em;
    color: rgba(255,255,255,0.8);
}

/* Renkli kartlar */
.card.bg-primary {
    background-color: #007bff !important; /* Bootstrap primary rengi */
}
.card.bg-success {
    background-color: #28a745 !important; /* Bootstrap success rengi */
}
.card.bg-danger {
    background-color: #dc3545 !important; /* Bootstrap danger rengi */
}

.chart-container {
    width: 100%;
    height: 400px;
    margin-top: 50px;
    background-color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Mesaj kutuları */
.message-container {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 15px;
    position: relative;
    text-align: left;
    font-size: 14px;
}
.error-message {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.success-message {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.close-btn {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-weight: bold;
    font-size: 1.2em;
}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(91, 140, 213);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php" style="margin-left: 5px;">
            <div class="baslik fs-3"> ELEMEK</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_dashboard.php">Satıcı Paneli</a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_manage.php">Mağaza Yönetimi</a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="manage_product.php">Ürün Yönetimi</a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="customer_orders.php">Sipariş Yönetimi</a>
                </li>
            </ul>
            <div class="d-flex me-3" style="margin-left: 145px;">
                <i class="bi bi-person-circle text-white fs-4"></i>
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-5 fade-in">
    <h1>Satıcı Yönetim Paneli</h1>
    <?php if (!empty($message)): ?>
        <div class="message-container <?php echo strpos($message, 'başarı') !== false ? 'success-message' : 'error-message'; ?>">
            <span class="close-btn">&times;</span>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Toplam Ürün</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $total_products ?></h5>
                    <p class="card-text">Sistemde kayıtlı toplam ürün sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Aktif Ürünler</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $active_products ?></h5>
                    <p class="card-text">Sistemde aktif olarak satış yapan ürün sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Pasif Ürünler</div>
                <div class="card-body">
                    <h5 class="card-title"><?= $removed_products ?></h5>
                    <p class="card-text">Sistemde pasif durumda olan ürün sayısı.</p>
                </div>
            </div>
        </div>
    </div>

     <div class="chart-container">
        <canvas id="productChart"></canvas>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" xintegrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Mesaj kutularını kapatma işlevi
    document.addEventListener("DOMContentLoaded", function() {
        var closeBtns = document.querySelectorAll(".close-btn");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.parentElement.style.display = "none";
            });
        });
    });

    const ctx = document.getElementById('productChart').getContext('2d');
    const productChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Toplam Ürün', 'Aktif Ürünler', 'Pasif Ürünler'],
            // PHP'den gelen verileri buraya dinamik olarak aktar
            datasets: [{
                label: 'Ürün Sayısı',
                data: [<?= $total_products ?>, <?= $active_products ?>, <?= $removed_products ?>],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(255, 99, 132, 0.6)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 2,
                borderRadius: 10,
                borderSkipped: false
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: 'Ürün Grafiği',
                    color: '#333',
                    font: {
                        size: 25,
                        family: 'Montserrat'
                    }
                },
                legend: {
                    display: true,
                    labels: {
                        color: '#333',
                        font: {
                            size: 14,
                            family: 'Montserrat'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    titleFont: {
                        family: 'Montserrat',
                        size: 16
                    },
                    bodyFont: {
                        family: 'Montserrat',
                        size: 14
                    },
                    cornerRadius: 5
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#333',
                        font: {
                            size: 12,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#333',
                        font: {
                            size: 12,
                            family: 'Montserrat'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            }
        }
    });
</script>
</body>
</html>
