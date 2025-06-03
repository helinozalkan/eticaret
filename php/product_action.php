<?php
// product_action.php - Ürün ekleme, düzenleme ve silme işlemleri
session_start(); // Oturumu başlat
include('../database.php'); // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    // Eğer satıcı giriş yapmamışsa veya yetkisi yoksa, giriş sayfasına yönlendir
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id']; // Oturumdan users.id'yi al

// Satıcı ID'sini veritabanından al
try {
    $stmt_seller = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_seller->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_seller->execute();
    $satici_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        // Satıcı kaydı bulunamazsa, hata logla ve yönlendir
        error_log("product_action.php: Satıcı kaydı bulunamadı User_ID: " . $seller_user_id);
        header("Location: seller_dashboard.php?status=seller_not_found");
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("product_action.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    header("Location: seller_dashboard.php?status=db_error");
    exit();
}

// Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Hangi eylemin istendiğini belirle
    // input type="submit" name="add_product" veya "delete_product" gibi
    if (isset($_POST['add_product'])) {
        // Ürün ekleme işlemi
        $urun_adi = trim(htmlspecialchars($_POST['product_name'] ?? ''));
        $urun_fiyati = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
        $stok_adedi = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT);
        $urun_aciklama = trim(htmlspecialchars($_POST['product_description'] ?? ''));
        $aktiflik_durumu = isset($_POST['product_status']) ? 1 : 0;

        // Validasyonlar
        if (empty($urun_adi) || $urun_fiyati === false || $urun_fiyati < 0 || $stok_adedi === false || $stok_adedi < 0) {
            header("Location: manage_product.php?status=add_invalid_input");
            exit();
        }

        $urun_gorseli = null;
        $upload_dir = '../uploads/';

        // Yükleme dizininin varlığını kontrol et ve yoksa oluştur
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                error_log("product_action.php: 'uploads' dizini oluşturulamadı.");
                header("Location: manage_product.php?status=upload_dir_error");
                exit();
            }
        }

        // Dosya yükleme işlemi
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
                header("Location: manage_product.php?status=invalid_file_type");
                exit();
            }

            // Maksimum dosya boyutu (örn: 5MB)
            $max_file_size = 5 * 1024 * 1024;
            if ($file_size > $max_file_size) {
                header("Location: manage_product.php?status=file_too_large");
                exit();
            }

            // Benzersiz dosya adı oluştur
            $new_file_name = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $upload_path)) {
                $urun_gorseli = $new_file_name;
            } else {
                error_log("product_action.php: Dosya yüklenirken hata oluştu: " . $_FILES['product_image']['error']);
                header("Location: manage_product.php?status=upload_failed");
                exit();
            }
        }

        try {
            $conn->beginTransaction(); // İşlemi başlat

            $stmt = $conn->prepare("INSERT INTO Urun (Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Satici_ID) VALUES (:urun_adi, :urun_fiyati, :stok_adedi, :urun_gorseli, :urun_aciklamasi, :aktiflik_durumu, :satici_id)");
            $stmt->bindParam(':urun_adi', $urun_adi);
            $stmt->bindParam(':urun_fiyati', $urun_fiyati);
            $stmt->bindParam(':stok_adedi', $stok_adedi, PDO::PARAM_INT);
            $stmt->bindParam(':urun_gorseli', $urun_gorseli);
            $stmt->bindParam(':urun_aciklamasi', $urun_aciklama);
            $stmt->bindParam(':aktiflik_durumu', $aktiflik_durumu, PDO::PARAM_INT);
            $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $conn->commit(); // İşlemi onayla
                header("Location: manage_product.php?status=product_added");
                exit();
            } else {
                $conn->rollBack(); // Hata oluşursa geri al
                error_log("product_action.php: Ürün eklenirken veritabanı hatası: " . implode(" ", $stmt->errorInfo()));
                header("Location: manage_product.php?status=add_db_error");
                exit();
            }
        } catch (PDOException $e) {
            $conn->rollBack(); // PDO hatası durumunda geri al
            error_log("product_action.php: Ürün ekleme sırasında PDO hatası: " . $e->getMessage());
            header("Location: manage_product.php?status=db_error");
            exit();
        }

    } elseif (isset($_POST['edit_product'])) {
        // Ürün düzenleme sayfasına yönlendirme
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if ($product_id === false || $product_id <= 0) {
            header("Location: manage_product.php?status=edit_invalid_id");
            exit();
        }
        header("Location: edit_product.php?id=" . $product_id); // 'product_id' yerine 'id' kullanıyoruz
        exit();

    } elseif (isset($_POST['delete_product'])) {
        // Ürün silme işlemi
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if ($product_id === false || $product_id <= 0) {
            header("Location: manage_product.php?status=delete_invalid_id");
            exit();
        }

        try {
            $conn->beginTransaction(); // İşlemi başlat

            // Ürün bilgilerini çek (görseli silmek için)
            $stmt_get_image = $conn->prepare("SELECT Urun_Gorseli FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id");
            $stmt_get_image->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_get_image->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
            $stmt_get_image->execute();
            $product_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

            if (!$product_data) {
                $conn->rollBack(); // Ürün bulunamazsa geri al
                header("Location: manage_product.php?status=product_not_found_or_unauthorized");
                exit();
            }

            $product_image = $product_data['Urun_Gorseli'];

            // Ürün silme sorgusu
            $stmt_delete = $conn->prepare("DELETE FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id");
            $stmt_delete->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt_delete->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

            if ($stmt_delete->execute()) {
                // Görseli sil
                if ($product_image && file_exists($upload_dir . $product_image)) {
                    unlink($upload_dir . $product_image);
                }
                $conn->commit(); // İşlemi onayla
                header("Location: manage_product.php?status=product_deleted");
                exit();
            } else {
                $conn->rollBack(); // Hata oluşursa geri al
                error_log("product_action.php: Ürün silinirken hiçbir satır etkilenmedi. Product ID: " . $product_id);
                header("Location: manage_product.php?status=delete_failed");
                exit();
            }
        } catch (PDOException $e) {
            $conn->rollBack(); // PDO hatası durumunda geri al
            error_log("product_action.php: Ürün silme sırasında PDO hatası: " . $e->getMessage());
            header("Location: manage_product.php?status=db_error");
            exit();
        }
    } else {
        // Bilinmeyen POST eylemi
        header("Location: manage_product.php?status=unknown_action");
        exit();
    }
} else {
    // POST dışı istekler için yönlendirme
    header("Location: manage_product.php?status=invalid_request");
    exit();
}
?>
