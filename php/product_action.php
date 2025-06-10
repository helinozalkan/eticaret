<?php
// product_action.php - Ürün ekleme, düzenleme ve silme işlemleri

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Özel İstisna Sınıfı
class ProductActionException extends Exception {}

// PDO Parametre Sabitleri
define('PARAM_USER_ID_PA', ':user_id');
define('PARAM_PRODUCT_ID_PA', ':product_id');
define('PARAM_SATICI_ID_PA', ':satici_id');
define('PARAM_URUN_ADI_PA', ':urun_adi');
define('PARAM_URUN_FIYATI_PA', ':urun_fiyati');
define('PARAM_STOK_ADEDI_PA', ':stok_adedi');
define('PARAM_URUN_GORSELI_PA', ':urun_gorseli');
define('PARAM_URUN_ACIKLAMASI_PA', ':urun_aciklamasi');
define('PARAM_AKTIFLIK_DURUMU_PA', ':aktiflik_durumu');
define('PARAM_URUN_HIKAYESI_PA', ':urun_hikayesi'); // Ürün hikayesi için yeni sabit


// 1. Satıcı Yetki Kontrolü
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized_access");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null;
$upload_dir = '../uploads/'; // Görselin yükleneceği klasörün yolu

// Satıcı ID'sini veritabanından al
try {
    $stmt_seller = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_seller->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_seller->execute();
    $satici_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        throw new ProductActionException("Satıcı profiliniz bulunamadı. Lütfen destek ile iletişime geçin.");
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("product_action.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
    header("Location: manage_product.php");
    exit();
}


// 2. Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_product.php?status=invalid_request_method");
    exit();
}

try {
    if (isset($_POST['add_product'])) {
        // Formdan gelen verileri al ve temizle
        $urun_adi = trim($_POST['product_name'] ?? '');
        $urun_fiyati_str = trim($_POST['product_price'] ?? '');
        $stok_adedi_str = trim($_POST['product_stock'] ?? '');
        $urun_aciklama = trim($_POST['product_description'] ?? '');
        $urun_hikayesi = trim($_POST['product_story'] ?? ''); // Ürün hikayesini al
        $aktiflik_durumu = isset($_POST['product_status']) ? 1 : 0;

        // Validasyonlar
        if (empty($urun_adi) || mb_strlen($urun_adi) > 255) {
            throw new ProductActionException("Ürün adı boş bırakılamaz ve en fazla 255 karakter olabilir.");
        }
        if (!is_numeric($urun_fiyati_str) || (float)$urun_fiyati_str < 0) {
            throw new ProductActionException("Geçerli bir ürün fiyatı girin.");
        }
        $urun_fiyati = (float)$urun_fiyati_str;

        if (!ctype_digit($stok_adedi_str) || (int)$stok_adedi_str < 0) {
            throw new ProductActionException("Geçerli bir stok adedi girin.");
        }
        $stok_adedi = (int)$stok_adedi_str;

        // XSS saldırılarına karşı verileri temizle
        $urun_adi_safe = htmlspecialchars($urun_adi, ENT_QUOTES, 'UTF-8');
        $urun_aciklama_safe = htmlspecialchars($urun_aciklama, ENT_QUOTES, 'UTF-8');
        $urun_hikayesi_safe = htmlspecialchars($urun_hikayesi, ENT_QUOTES, 'UTF-8');

        $urun_gorseli_filename = null; // Başlangıçta dosya adı boş

        // *** DÜZELTME: EKSİK OLAN DOSYA YÜKLEME MANTIĞI EKLENDİ ***
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            
            // uploads klasörü yoksa oluştur
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new ProductActionException("Yükleme klasörü oluşturulamadı. Lütfen klasör izinlerini kontrol edin.");
                }
            }

            $file_tmp_path = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_size = $_FILES['product_image']['size'];
            $file_type = mime_content_type($file_tmp_path);
            
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file_type, $allowed_mime_types)) {
                throw new ProductActionException("Geçersiz dosya türü. Sadece JPG, PNG, GIF formatlarına izin verilmektedir.");
            }
            if ($file_size > $max_file_size) {
                throw new ProductActionException("Dosya boyutu çok büyük. (Maksimum 5MB)");
            }

            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = bin2hex(random_bytes(16)) . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                $urun_gorseli_filename = $new_file_name; // Başarılı olursa, dosya adını değişkene ata
            } else {
                throw new ProductActionException("Dosya yüklenirken bir hata oluştu.");
            }
        }
        // *** DOSYA YÜKLEME MANTIĞI SONU ***

        $conn->beginTransaction();
        
        // Veritabanı sorgusuna Urun_Hikayesi sütunu eklendi
        $stmt = $conn->prepare("INSERT INTO Urun (Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Urun_Hikayesi, Aktiflik_Durumu, Satici_ID) VALUES (:urun_adi, :urun_fiyati, :stok_adedi, :urun_gorseli, :urun_aciklamasi, :urun_hikayesi, :aktiflik_durumu, :satici_id)");
        
        $stmt->bindParam(':urun_adi', $urun_adi_safe, PDO::PARAM_STR);
        $stmt->bindParam(':urun_fiyati', $urun_fiyati);
        $stmt->bindParam(':stok_adedi', $stok_adedi, PDO::PARAM_INT);
        $stmt->bindParam(':urun_gorseli', $urun_gorseli_filename, PDO::PARAM_STR);
        $stmt->bindParam(':urun_aciklamasi', $urun_aciklama_safe, PDO::PARAM_STR);
        $stmt->bindParam(':urun_hikayesi', $urun_hikayesi_safe, PDO::PARAM_STR); // Ürün hikayesi bağlandı
        $stmt->bindParam(':aktiflik_durumu', $aktiflik_durumu, PDO::PARAM_INT);
        $stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['form_success_message'] = "Ürün başarıyla eklendi.";
            header("Location: manage_product.php");
            exit();
        } else {
            $conn->rollBack();
            throw new ProductActionException("Ürün eklenirken veritabanında bir sorun oluştu.");
        }

    }
    // Diğer 'else if' blokları (düzenleme, silme vb.) buraya gelebilir.
    else {
        throw new ProductActionException("Bilinmeyen bir ürün işlemi talep edildi.");
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("product_action.php PDOException: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
    header("Location: manage_product.php");
    exit();
} catch (ProductActionException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("product_action.php ProductActionException: " . $e->getMessage());
    $_SESSION['form_error_message'] = $e->getMessage();
    header("Location: manage_product.php");
    exit();
}

