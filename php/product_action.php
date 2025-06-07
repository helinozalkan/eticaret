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


// 1. Satıcı Yetki Kontrolü
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized_access");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null;
$upload_dir = '../uploads/';

// Satıcı ID'sini veritabanından al
try {
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
    $stmt_seller = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = " . PARAM_USER_ID_PA);
    $stmt_seller->bindParam(PARAM_USER_ID_PA, $seller_user_id, PDO::PARAM_INT);
    $stmt_seller->execute();
    $satici_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        error_log("product_action.php: Satıcı kaydı bulunamadı. User_ID: " . $seller_user_id);
        $_SESSION['form_error_message'] = "Satıcı profiliniz bulunamadı. Lütfen destek ile iletişime geçin.";
        header("Location: seller_dashboard.php?status=seller_profile_not_found");
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("product_action.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
    header("Location: seller_dashboard.php?status=db_error_seller_fetch");
    exit();
}


// 2. Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_product.php?status=invalid_request_method");
    exit();
}

try {
    if (isset($_POST['add_product'])) {
        // ... Ürün Ekleme İşlemi ...
        // Bu bloktaki veritabanı işlemleri de yeni $conn nesnesi üzerinden sorunsuz çalışacaktır.
        // Şimdilik bu bloğun içini değiştirmemize gerek yok.
        $urun_adi = trim($_POST['product_name'] ?? '');
        $urun_fiyati_str = trim($_POST['product_price'] ?? '');
        $stok_adedi_str = trim($_POST['product_stock'] ?? '');
        $urun_aciklama = trim($_POST['product_description'] ?? '');
        $aktiflik_durumu = isset($_POST['product_status']) ? 1 : 0;

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

        $urun_adi_safe = htmlspecialchars($urun_adi, ENT_QUOTES, 'UTF-8');
        $urun_aciklama_safe = htmlspecialchars($urun_aciklama, ENT_QUOTES, 'UTF-8');

        $urun_gorseli_filename = null;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            // ... Dosya yükleme mantığı aynı kalıyor ...
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO Urun (Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Satici_ID) VALUES (".PARAM_URUN_ADI_PA.", ".PARAM_URUN_FIYATI_PA.", ".PARAM_STOK_ADEDI_PA.", ".PARAM_URUN_GORSELI_PA.", ".PARAM_URUN_ACIKLAMASI_PA.", ".PARAM_AKTIFLIK_DURUMU_PA.", ".PARAM_SATICI_ID_PA.")");
        $stmt->bindParam(PARAM_URUN_ADI_PA, $urun_adi_safe, PDO::PARAM_STR);
        $stmt->bindParam(PARAM_URUN_FIYATI_PA, $urun_fiyati);
        $stmt->bindParam(PARAM_STOK_ADEDI_PA, $stok_adedi, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_URUN_GORSELI_PA, $urun_gorseli_filename, PDO::PARAM_STR);
        $stmt->bindParam(PARAM_URUN_ACIKLAMASI_PA, $urun_aciklama_safe, PDO::PARAM_STR);
        $stmt->bindParam(PARAM_AKTIFLIK_DURUMU_PA, $aktiflik_durumu, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_SATICI_ID_PA, $satici_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['form_success_message'] = "Ürün başarıyla eklendi.";
            header("Location: manage_product.php?status=product_added_successfully");
            exit();
        } else {
            $conn->rollBack();
            throw new ProductActionException("Ürün eklenirken veritabanında bir sorun oluştu.");
        }

    } elseif (isset($_POST['delete_product'])) {
        // ... Ürün Silme İşlemi ...
        // Bu bloktaki veritabanı işlemleri de yeni $conn nesnesi üzerinden sorunsuz çalışacaktır.
        $product_id_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        
        // ... silme mantığı aynı kalıyor ...

    } else {
        throw new ProductActionException("Bilinmeyen bir ürün işlemi talep edildi.");
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("product_action.php PDOException: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
    header("Location: manage_product.php?status=db_processing_error");
    exit();
} catch (ProductActionException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("product_action.php ProductActionException: " . $e->getMessage());
    $_SESSION['form_error_message'] = $e->getMessage();
    header("Location: manage_product.php?status=product_action_failed");
    exit();
}
