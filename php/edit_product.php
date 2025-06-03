<?php
// edit_product.php - Ürün düzenleme sayfası (PDO ile güncellendi)
session_start();
include('../database.php'); // database.php PDO bağlantısını kuruyor

// Kullanıcı oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id']; // users tablosundaki ID

$product = null; // Ürün verilerini tutacak değişken
$error_message = ""; // Hata mesajlarını tutacak değişken
$success_message = ""; // Başarı mesajlarını tutacak değişken

// Satıcı ID'sini users tablosundaki user_id'den al
try {
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        // Satıcı kaydı bulunamazsa, hata logla ve yönlendir
        error_log("edit_product.php: Satıcı kaydı bulunamadı User_ID: " . $seller_user_id);
        header("Location: seller_dashboard.php?status=seller_not_found");
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("edit_product.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    header("Location: seller_dashboard.php?status=db_error");
    exit();
}


// Ürün ID kontrolü - manage_product.php dosyasından id parametresi ile geliyor
// Önceki kodda product_id kullanılıyordu, manage_product.php edit_product.php?id=... gönderiyor
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($product_id === false || $product_id <= 0) {
    header("Location: manage_product.php?status=invalid_product_id");
    exit();
}

// Ürün bilgilerini çekme
try {
    $query = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Onay_Durumu FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT); // Satıcının sadece kendi ürününü düzenleyebilmesi için
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC); // Tek bir ürün beklendiği için fetch

    if (!$product) {
        header("Location: manage_product.php?status=product_not_found_or_unauthorized");
        exit();
    }

} catch (PDOException $e) {
    error_log("edit_product.php: Ürün bilgileri çekilirken veritabanı hatası: " . $e->getMessage());
    header("Location: manage_product.php?status=db_error");
    exit();
}


// Form POST edildiğinde güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gelen verileri al ve doğrula/temizle
    $product_name = trim(htmlspecialchars($_POST['product_name'] ?? ''));
    $product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    $product_stock = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT);
    $product_active = isset($_POST['product_active']) ? 1 : 0; // Aktiflik_Durumu
    $product_description = trim(htmlspecialchars($_POST['product_description'] ?? '')); // Ürün Açıklaması da eklenecek
    // Onay_Durumu'nu burada değiştirmemeliyiz, admin tarafından yönetilmeli. Mevcut değeri koruyalım.
    $onay_durumu = $product['Onay_Durumu'];

    // Validasyonlar
    if (empty($product_name) || $product_price === false || $product_price < 0 || $product_stock === false || $product_stock < 0) {
        $error_message = "Lütfen tüm alanları doldurun ve geçerli değerler girin.";
    } else {
        $upload_dir = "../uploads/";
        $current_image = $product['Urun_Gorseli']; // Mevcut görseli al

        // Yükleme dizininin varlığını kontrol et ve yoksa oluştur
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $error_message = "Yükleme dizini oluşturulamadı.";
            }
        }

        // Görsel güncelleme işlemi
        // Eğer yeni bir dosya yüklendiyse
        if (empty($error_message) && isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_type = $_FILES['product_image']['type'];

            // Güvenli dosya uzantıları ve MIME tipleri kontrolü
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file_ext, $allowed_extensions) || !in_array($file_type, $allowed_mime_types)) {
                $error_message = "Geçersiz dosya tipi. Yalnızca JPG, JPEG, PNG veya GIF yüklenebilir.";
            } elseif ($file_size > $max_file_size) {
                $error_message = "Dosya boyutu çok büyük. Maksimum " . ($max_file_size / (1024 * 1024)) . "MB.";
            } else {
                // Benzersiz dosya adı oluştur
                $new_file_name = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
                $upload_path = $upload_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_path, $upload_path)) {
                    // Eski görseli sil (eğer varsa ve yeni görsel yüklendiyse)
                    if ($current_image && file_exists($upload_dir . $current_image)) {
                        unlink($upload_dir . $current_image);
                    }
                    $current_image = $new_file_name; // Yeni görsel adını ata
                } else {
                    $error_message = "Dosya yüklenirken bir hata oluştu.";
                }
            }
        }

        if (empty($error_message)) {
            try {
                $conn->beginTransaction(); // İşlemi başlat

                // Ürün güncelleme sorgusu
                // Onay_Durumu'nu güncelleme sırasında değiştirmememiz önemli, admin tarafından yönetilmeli
                $update_query = "UPDATE Urun SET Urun_Adi = :product_name, Urun_Fiyati = :product_price, Stok_Adedi = :product_stock, Urun_Gorseli = :product_image, Urun_Aciklamasi = :product_description, Aktiflik_Durumu = :product_active WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
                $update_stmt = $conn->prepare($update_query);

                $update_stmt->bindParam(':product_name', $product_name);
                $update_stmt->bindParam(':product_price', $product_price);
                $update_stmt->bindParam(':product_stock', $product_stock, PDO::PARAM_INT);
                $update_stmt->bindParam(':product_image', $current_image);
                $update_stmt->bindParam(':product_description', $product_description);
                $update_stmt->bindParam(':product_active', $product_active, PDO::PARAM_INT);
                $update_stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $update_stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    $conn->commit(); // İşlemi onayla
                    $success_message = "Ürün başarıyla güncellendi!";
                    // Güncellenmiş verileri HTML formunda göstermek için product objesini tekrar yükleyebiliriz
                    // veya redirect yapıp manage_product.php'ye yönlendirebiliriz.
                    // Şimdilik başarı mesajı gösterip sayfayı yenilemiyoruz.
                    header("Location: manage_product.php?status=product_updated"); // Doğru sayfaya yönlendirme
                    exit();
                } else {
                    $conn->rollBack(); // Hata oluşursa geri al
                    $error_message = "Ürün güncellenirken bir hata oluştu.";
                }

            } catch (PDOException $e) {
                $conn->rollBack(); // PDO hatası durumunda geri al
                error_log("edit_product.php: Ürün güncelleme sırasında PDO hatası: " . $e->getMessage());
                $error_message = "Veritabanı işlemi sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
            }
        }
    }
}
// Ürün verilerini güncelledikten sonra formda göstermek için tekrar çekebiliriz veya POST verilerini kullanabiliriz.
// Şimdilik redirect yaptığımız için bu kısım sadece sayfa ilk yüklendiğinde çalışacak.

// Eğer POST sonrası redirect yapılmazsa, güncel product objesi burada tekrar çekilmeli
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message) && !empty($success_message)) {
//     $query = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Onay_Durumu FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
//     $stmt = $conn->prepare($query);
//     $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
//     $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
//     $stmt->execute();
//     $product = $stmt->fetch(PDO::FETCH_ASSOC);
// }

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles.css"> <style>
        /* HTML'deki stil kısmını buraya taşıyabilir veya ayrı bir CSS dosyası oluşturabilirsin */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; /* Ekran yüksekliğini kaplasın */
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        form {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 450px; /* Genişliği artır */
            box-sizing: border-box;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input[type="file"] {
            padding-top: 5px;
        }
        input[type="checkbox"] {
            margin-right: 10px;
        }
        button {
            width: auto;
            padding: 10px 20px;
            background-color: #007bff; /* Mavi tonu */
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-right: 10px;
        }
        button:hover {
            background-color: #0056b3;
        }
        a.btn { /* İptal butonu için stil */
            display: inline-block;
            padding: 10px 20px;
            background-color: #6c757d; /* Gri tonu */
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 18px;
            transition: background-color 0.3s ease;
        }
        a.btn:hover {
            background-color: #5a6268;
        }
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
    <form action="" method="POST" enctype="multipart/form-data">
        <h1>Ürün Düzenle</h1>

        <?php if (!empty($error_message)) : ?>
            <div class="message-container error-message">
                <span class="close-btn">&times;</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)) : ?>
            <div class="message-container success-message">
                <span class="close-btn">&times;</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div>
            <label for="product_name">Ürün Adı:</label>
            <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($product['Urun_Adi'] ?? '') ?>" required>
        </div>
        <div>
            <label for="product_price">Ürün Fiyatı (₺):</label>
            <input type="number" step="0.01" id="product_price" name="product_price" value="<?= htmlspecialchars($product['Urun_Fiyati'] ?? '') ?>" required>
        </div>
        <div>
            <label for="product_stock">Stok Adedi:</label>
            <input type="number" id="product_stock" name="product_stock" value="<?= htmlspecialchars($product['Stok_Adedi'] ?? '') ?>" required>
        </div>
        <div>
            <label for="product_description">Ürün Açıklaması:</label>
            <textarea id="product_description" name="product_description"><?= htmlspecialchars($product['Urun_Aciklamasi'] ?? '') ?></textarea>
        </div>
        <div>
            <label for="product_image">Ürün Görseli:</label>
            <input type="file" id="product_image" name="product_image">
            <?php if (!empty($product['Urun_Gorseli'])): ?>
                <p>Mevcut Görsel: <img src="../uploads/<?= htmlspecialchars($product['Urun_Gorseli']) ?>" alt="Ürün Görseli" width="100"></p>
            <?php else: ?>
                <p>Mevcut Görsel Yok</p>
            <?php endif; ?>
        </div>
        <div>
            <label for="product_active">Aktiflik Durumu:</label>
            <input type="checkbox" id="product_active" name="product_active" <?= (isset($product['Aktiflik_Durumu']) && $product['Aktiflik_Durumu'] == 1) ? 'checked' : '' ?>>
        </div>
        <button type="submit">Güncelle</button>
        <a href="manage_product.php" class="btn">İptal</a>
    </form> 

    <script>
        // Mesaj kutularını kapatma işlevi
        document.addEventListener("DOMContentLoaded", function () {
            var closeBtns = document.querySelectorAll(".close-btn");
            closeBtns.forEach(function(btn) {
                btn.addEventListener("click", function () {
                    this.parentElement.style.display = "none";
                });
            });
        });
    </script>
</body>
</html>