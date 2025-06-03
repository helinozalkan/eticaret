<?php
// manage_product.php - Ürün Yönetimi (Ürün Ekleme ve Listeleme)
session_start();
include_once '../database.php'; // include_once kullanıldı

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null;
$message = ""; // Mesajları tutacak değişken

// Özel istisna sınıfı tanımla
class ProductManageException extends Exception {}

// SonarQube S1192 hatasını gidermek için parametre adı sabitlerini tanımla
$param_satici_id = ':satici_id';
$param_product_id = ':product_id'; // Product ID için de sabit tanımladım

try {
    // Satıcı ID'sini al
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        throw new ProductManageException("Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturun.");
    }
    $satici_id = $satici_data['Satici_ID'];

    // Ürün ekleme işlemi
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product_form'])) { // Formun adını kontrol et
        $urun_adi = trim(htmlspecialchars($_POST['product_name'] ?? ''));
        $urun_fiyati = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
        $stok_adedi = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT);
        $urun_aciklama = trim(htmlspecialchars($_POST['product_description'] ?? ''));
        $aktiflik_durumu = isset($_POST['product_status']) ? 1 : 0;

        // Validasyonlar
        if (empty($urun_adi) || $urun_fiyati === false || $urun_fiyati < 0 || $stok_adedi === false || $stok_adedi < 0) {
            $message = "Ürün eklemek için lütfen tüm alanları doldurun ve geçerli değerler girin.";
        } else {
            $urun_gorseli = null;
            $upload_dir = "../uploads/";
            $max_file_size = 5 * 1024 * 1024; // 5MB

            // Yükleme dizininin varlığını kontrol et ve yoksa oluştur
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new ProductManageException("Yükleme dizini oluşturulamadı.");
                }
            }

            // Dosya yükleme işlemi (isteğe bağlı)
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES['product_image']['tmp_name'];
                $file_name = $_FILES['product_image']['name'];
                $file_size = $_FILES['product_image']['size'];
                $file_type = $_FILES['product_image']['type'];

                // Güvenli dosya uzantıları ve MIME tipleri kontrolü
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_extensions) || !in_array($file_type, $allowed_mime_types)) {
                    $message = "Geçersiz dosya tipi. Yalnızca JPG, JPEG, PNG veya GIF yüklenebilir.";
                } elseif ($file_size > $max_file_size) {
                    $message = "Dosya boyutu çok büyük. Maksimum " . ($max_file_size / (1024 * 1024)) . "MB.";
                } else {
                    // Benzersiz dosya adı oluştur
                    $new_file_name = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp_path, $upload_path)) {
                        $urun_gorseli = $new_file_name;
                    } else {
                        throw new ProductManageException("Dosya yüklenirken bir hata oluştu.");
                    }
                }
            }

            if (empty($message)) { // Önceki dosya yükleme hataları yoksa devam et
                $conn->beginTransaction(); // İşlemi başlat

                $stmt_insert_product = $conn->prepare("INSERT INTO Urun (Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Satici_ID) VALUES (:urun_adi, :urun_fiyati, :stok_adedi, :urun_gorseli, :urun_aciklamasi, :aktiflik_durumu, :satici_id)");
                $stmt_insert_product->bindParam(':urun_adi', $urun_adi);
                $stmt_insert_product->bindParam(':urun_fiyati', $urun_fiyati);
                $stmt_insert_product->bindParam(':stok_adedi', $stok_adedi, PDO::PARAM_INT);
                $stmt_insert_product->bindParam(':urun_gorseli', $urun_gorseli);
                $stmt_insert_product->bindParam(':urun_aciklamasi', $urun_aciklama);
                $stmt_insert_product->bindParam(':aktiflik_durumu', $aktiflik_durumu, PDO::PARAM_INT);
                $stmt_insert_product->bindParam($param_satici_id, $satici_id, PDO::PARAM_INT);

                if ($stmt_insert_product->execute()) {
                    $conn->commit(); // İşlemi onayla
                    $message = "Ürün başarıyla eklendi.";
                } else {
                    $conn->rollBack(); // Hata oluşursa geri al
                    throw new ProductManageException("Ürün eklenirken veritabanı hatası oluştu.");
                }
            }
        }
    }

    // Ürün silme işlemi (GET ile tetiklenir)
    if (isset($_GET['delete'])) {
        $product_id_to_delete = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);

        if ($product_id_to_delete === false || $product_id_to_delete <= 0) {
            throw new ProductManageException("Geçersiz ürün ID'si.");
        }

        $conn->beginTransaction(); // İşlemi başlat

        // Ürün bilgilerini çek (görseli silmek için)
        $stmt_get_image = $conn->prepare("SELECT Urun_Gorseli FROM Urun WHERE Urun_ID = " . $param_product_id . " AND Satici_ID = " . $param_satici_id);
        $stmt_get_image->bindParam($param_product_id, $product_id_to_delete, PDO::PARAM_INT);
        $stmt_get_image->bindParam($param_satici_id, $satici_id, PDO::PARAM_INT);
        $stmt_get_image->execute();
        $product_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

        if (!$product_data) {
            $conn->rollBack();
            throw new ProductManageException("Ürün bulunamadı veya silme yetkiniz yok.");
        }

        $product_image = $product_data['Urun_Gorseli'];

        // Ürün silme sorgusu
        $stmt_delete_product = $conn->prepare("DELETE FROM Urun WHERE Urun_ID = " . $param_product_id . " AND Satici_ID = " . $param_satici_id);
        $stmt_delete_product->bindParam($param_product_id, $product_id_to_delete, PDO::PARAM_INT);
        $stmt_delete_product->bindParam($param_satici_id, $satici_id, PDO::PARAM_INT);

        if ($stmt_delete_product->execute()) {
            // Görseli sil
            if ($product_image && file_exists($upload_dir . $product_image)) {
                unlink($upload_dir . $product_image);
            }
            $conn->commit(); // İşlemi onayla
            $message = "Ürün başarıyla silindi.";
            // header("Location: manage_product.php"); // Yönlendirme yapmıyoruz, mesaj gösteriyoruz
            // exit();
        } else {
            $conn->rollBack();
            throw new ProductManageException("Ürün silinirken bir hata oluştu.");
        }
    }

} catch (ProductManageException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("manage_product.php: Ürün Yönetim Hatası: " . $e->getMessage());
    $message = htmlspecialchars($e->getMessage());
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("manage_product.php: Veritabanı Hatası: " . $e->getMessage());
    $message = "Veritabanı işlemi sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("manage_product.php: Beklenmedik Hata: " . $e->getMessage());
    $message = "Beklenmedik bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
}

// Ürünleri listele (her zaman güncel listeyi göster)
$products = [];
if ($satici_id !== null) {
    try {
        $query_products = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Aktiflik_Durumu FROM Urun WHERE Satici_ID = :satici_id";
        $stmt_products = $conn->prepare($query_products);
        $stmt_products->bindParam($param_satici_id, $satici_id, PDO::PARAM_INT);
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("manage_product.php: Ürün listesi çekilirken veritabanı hatası: " . $e->getMessage());
        $message = "Ürünler listelenirken bir sorun oluştu.";
        $products = [];
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    background-color: #f4f4f4;
    font-family: Arial, sans-serif;
}

.container {
    width: 80%;
    margin: 20px auto;
    background-color: #fff;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.product-form {
    margin-bottom: 30px;
}

.form-group {
    display: flex; /* Flexbox kullanarak label ve input'u aynı satıra getir */
    align-items: center; /* Dikeyde ortala */
    margin-bottom: 15px;
}

.form-group label {
    flex: 0 0 150px; /* Label için sabit genişlik */
    margin-bottom: 0; /* Margin'i sıfırla */
    font-weight: bold;
    padding-right: 10px; /* Label ve input arasına boşluk */
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea,
.form-group input[type="file"] {
    flex: 1; /* Geri kalan alanı doldur */
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
}

.form-group input[type="file"] {
    padding: 3px;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-left: 0; /* Checkbox için varsayılan margin'i sıfırla */
}

.btn-primary {
    padding: 10px 20px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.product-table img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

/* Hata ve başarı mesajları için stil */
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
        <a class="navbar-brand d-flex ms-4" href="#" style="margin-left: 5px;">
         
            <div class="baslik fs-3"> ELEMEK</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
            <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_dashboard.php">
                        Satıcı Paneli
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_manage.php">
                        Mağaza Yönetimi
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="manage_product.php">
                        Ürün Yönetimi
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="customer_orders.php">
                        Sipariş Yönetimi
                    </a>
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


    <div class="container">
        <h1>Ürün Yönetimi</h1>
        <?php if (!empty($message)): ?>
            <div class="message-container <?php echo strpos($message, 'başarı') !== false ? 'success-message' : 'error-message'; ?>">
                <span class="close-btn">&times;</span>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form action="manage_product.php" method="POST" enctype="multipart/form-data" class="product-form">
            <input type="hidden" name="add_product_form" value="1">
            <div class="form-group">
                <label for="product_name">Ürün Adı:</label>
                <input type="text" name="product_name" id="product_name" required>
            </div>
            <div class="form-group">
                <label for="product_price">Fiyat:</label>
                <input type="number" name="product_price" id="product_price" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="product_stock">Stok Adedi:</label>
                <input type="number" name="product_stock" id="product_stock" required>
            </div>
            <div class="form-group">
                <label for="product_image">Ürün Görseli:</label>
                <input type="file" name="product_image" id="product_image">
            </div>
            <div class="form-group">
                <label for="product_description">Ürün Açıklaması:</label>
                <textarea name="product_description" id="product_description"></textarea>
            </div>
            <div class="form-group">
                <label for="product_status">Aktiflik Durumu:</label>
                <input type="checkbox" name="product_status" id="product_status" checked>
            </div>
            <button type="submit" class="btn btn-primary">Ürün Ekle</button>
        </form>

        <hr>

        <h2>Mevcut Ürünler</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>id</th>
                    <th>Ürün Adı</th>
                    <th>Fiyat</th>
                    <th>Stok</th>
                    <th>Durum</th>
                    <th>Görsel</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= htmlspecialchars($product['Urun_ID']) ?></td>
                            <td><?= htmlspecialchars($product['Urun_Adi']) ?></td>
                            <td><?= htmlspecialchars($product['Urun_Fiyati']) ?> TL</td>
                            <td><?= htmlspecialchars($product['Stok_Adedi']) ?></td>
                            <td><?= $product['Aktiflik_Durumu'] ? 'Aktif' : 'Pasif' ?></td>
                            <td>
                                <?php if ($product['Urun_Gorseli']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($product['Urun_Gorseli']) ?>" alt="Ürün Görseli" width="50">
                                <?php else: ?>
                                    Görsel Yok
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_product.php?id=<?= $product['Urun_ID'] ?>" class="btn btn-warning">Düzenle</a>
                                <a href="manage_product.php?delete=<?= $product['Urun_ID'] ?>" class="btn btn-danger" onclick="return confirm('Bu ürünü silmek istediğinizden emin misiniz?')">Sil</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Henüz ürün eklenmedi.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" xintegrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>

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
</script>
</body>
</html>
