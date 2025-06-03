<?php
// product_action.php - Ürün ekleme, düzenleme ve silme işlemleri
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('../database.php'); // Veritabanı bağlantısı

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
$upload_dir = '../uploads/'; // Yükleme dizini (scriptin çalıştığı yere göre göreceli)

// Satıcı ID'sini veritabanından al
try {
    $stmt_seller = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = " . PARAM_USER_ID_PA);
    $stmt_seller->bindParam(PARAM_USER_ID_PA, $seller_user_id, PDO::PARAM_INT);
    $stmt_seller->execute();
    $satici_data = $stmt_seller->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        error_log("product_action.php: Satıcı kaydı bulunamadı. User_ID: " . $seller_user_id);
        // Yönlendirme için session mesajı kullanılabilir manage_product.php'de göstermek üzere
        $_SESSION['form_error_message'] = "Satıcı profiliniz bulunamadı. Lütfen destek ile iletişime geçin.";
        header("Location: seller_dashboard.php?status=seller_profile_not_found"); // veya manage_product.php
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("product_action.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
    header("Location: seller_dashboard.php?status=db_error_seller_fetch"); // veya manage_product.php
    exit();
}


// 2. Sadece POST isteklerini işle
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manage_product.php?status=invalid_request_method");
    exit();
}

// 3. CSRF Token Kontrolü (Formlarınıza CSRF token eklemelisiniz)
// if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
//     error_log("product_action.php: CSRF token mismatch for User_ID: " . $seller_user_id);
//     $_SESSION['form_error_message'] = "Geçersiz istek (CSRF). Lütfen formu tekrar gönderin.";
//     header("Location: manage_product.php?status=csrf_error");
//     exit();
// }


try {
    if (isset($_POST['add_product'])) {
        // Ürün Ekleme İşlemi
        $urun_adi = trim($_POST['product_name'] ?? '');
        $urun_fiyati_str = trim($_POST['product_price'] ?? '');
        $stok_adedi_str = trim($_POST['product_stock'] ?? '');
        $urun_aciklama = trim($_POST['product_description'] ?? '');
        $aktiflik_durumu = isset($_POST['product_status']) ? 1 : 0; // Checkbox işaretliyse 1, değilse 0

        // Detaylı Validasyonlar
        if (empty($urun_adi) || mb_strlen($urun_adi) > 255) { // mb_strlen multibyte karakterler için
            throw new ProductActionException("Ürün adı boş bırakılamaz ve en fazla 255 karakter olabilir.");
        }
        if (!is_numeric($urun_fiyati_str) || (float)$urun_fiyati_str < 0 || (float)$urun_fiyati_str > 9999999.99) {
            throw new ProductActionException("Geçerli bir ürün fiyatı girin (0 - 9999999.99 arası).");
        }
        $urun_fiyati = (float)$urun_fiyati_str;

        if (!ctype_digit($stok_adedi_str) || (int)$stok_adedi_str < 0 || (int)$stok_adedi_str > 99999) { // ctype_digit sadece rakam kontrolü
            throw new ProductActionException("Geçerli bir stok adedi girin (0 - 99999 arası).");
        }
        $stok_adedi = (int)$stok_adedi_str;

        if (mb_strlen($urun_aciklama) > 1000) { // Örnek bir sınır
            throw new ProductActionException("Ürün açıklaması en fazla 1000 karakter olabilir.");
        }
        // htmlspecialchars ürün adı ve açıklama için veritabanına kaydetmeden hemen önce veya gösterirken yapılabilir.
        // Güvenlik için, veritabanına ham veri kaydedip gösterirken htmlspecialchars kullanmak daha yaygındır.
        // Şimdilik, veritabanına eklerken htmlspecialchars ile ekleyelim, gösterirken tekrar edilmemesine dikkat edin.
        $urun_adi_safe = htmlspecialchars($urun_adi, ENT_QUOTES, 'UTF-8');
        $urun_aciklama_safe = htmlspecialchars($urun_aciklama, ENT_QUOTES, 'UTF-8');


        $urun_gorseli_filename = null;
        // Yükleme dizininin varlığını kontrol et ve yoksa oluştur (Daha güvenli izinler)
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) { // 0777 yerine 0755
                error_log("product_action.php: 'uploads' dizini oluşturulamadı. Kontrol edin: " . $upload_dir);
                throw new ProductActionException("Dosya yükleme dizini oluşturulamadı. Sistem yöneticisine başvurun.");
            }
        }

        // Dosya Yükleme İşlemi
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            if (!is_uploaded_file($_FILES['product_image']['tmp_name'])) { // Ek güvenlik kontrolü
                throw new ProductActionException("Geçersiz dosya yükleme denemesi.");
            }

            $file_tmp_path = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']); // basename ile path traversal saldırılarını engelle
            $file_size = $_FILES['product_image']['size'];
            $file_type = mime_content_type($file_tmp_path); // Daha güvenilir MIME type tespiti

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_extensions) || !in_array($file_type, $allowed_mime_types)) {
                throw new ProductActionException("Geçersiz dosya türü. Sadece JPG, JPEG, PNG, GIF izinlidir.");
            }

            $max_file_size = 5 * 1024 * 1024; // 5MB
            if ($file_size > $max_file_size) {
                throw new ProductActionException("Dosya boyutu çok büyük (Maksimum 5MB).");
            }

            // Benzersiz ve güvenli dosya adı oluştur
            $urun_gorseli_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
            $upload_path = $upload_dir . $urun_gorseli_filename;

            if (!move_uploaded_file($file_tmp_path, $upload_path)) {
                error_log("product_action.php: Dosya yüklenirken hata. Kaynak: $file_tmp_path, Hedef: $upload_path");
                throw new ProductActionException("Dosya yüklenirken bir hata oluştu.");
            }
        }

        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO Urun (Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Satici_ID) VALUES (".PARAM_URUN_ADI_PA.", ".PARAM_URUN_FIYATI_PA.", ".PARAM_STOK_ADEDI_PA.", ".PARAM_URUN_GORSELI_PA.", ".PARAM_URUN_ACIKLAMASI_PA.", ".PARAM_AKTIFLIK_DURUMU_PA.", ".PARAM_SATICI_ID_PA.")");
        $stmt->bindParam(PARAM_URUN_ADI_PA, $urun_adi_safe, PDO::PARAM_STR);
        $stmt->bindParam(PARAM_URUN_FIYATI_PA, $urun_fiyati); // PDO::PARAM_STR olarak gönderilip DB'de float'a dönüşür
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
            error_log("product_action.php: Ürün eklenirken veritabanı hatası: " . implode(" ", $stmt->errorInfo()));
            throw new ProductActionException("Ürün eklenirken veritabanında bir sorun oluştu.");
        }

    } elseif (isset($_POST['edit_product_action'])) { // edit_product.php'den gelen güncelleme isteği
        // Bu kısım edit_product.php içinde olmalı.
        // product_action.php genellikle sadece add/delete/status change gibi doğrudan aksiyonları yönetir.
        // Eğer edit_product.php formunu buraya POST ediyorsanız, mantık buraya eklenebilir.
        // Şimdilik, edit_product.php'ye yönlendirme bloğu (aşağıdaki) daha mantıklı.
        // Eğer tüm update logic'i burada olacaksa, product_id, urun_adi vb. tüm alanlar alınmalı,
        // validasyon yapılmalı ve UPDATE sorgusu çalıştırılmalı.
        // Örnek:
        // $product_id_edit = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        // ... diğer alanlar ...
        // ... validasyonlar ...
        // ... $stmt = $conn->prepare("UPDATE Urun SET Urun_Adi = ..., WHERE Urun_ID = ... AND Satici_ID = ...");
        // ... execute, commit/rollback ...
        // header("Location: manage_product.php?status=product_updated");
        // exit();
        $_SESSION['form_info_message'] = "Ürün düzenleme işlemi için lütfen ilgili ürünü düzenle sayfasını kullanın.";
        header("Location: manage_product.php?status=edit_via_form_only");
        exit();


    } elseif (isset($_POST['delete_product'])) {
        $product_id_delete = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

        if ($product_id_delete === false || $product_id_delete <= 0) {
            throw new ProductActionException("Silinecek ürün için geçersiz ID.");
        }

        $conn->beginTransaction();
        $stmt_get_image = $conn->prepare("SELECT Urun_Gorseli FROM Urun WHERE Urun_ID = ".PARAM_PRODUCT_ID_PA." AND Satici_ID = ".PARAM_SATICI_ID_PA);
        $stmt_get_image->bindParam(PARAM_PRODUCT_ID_PA, $product_id_delete, PDO::PARAM_INT);
        $stmt_get_image->bindParam(PARAM_SATICI_ID_PA, $satici_id, PDO::PARAM_INT);
        $stmt_get_image->execute();
        $product_image_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

        if (!$product_image_data) {
            $conn->rollBack();
            throw new ProductActionException("Silinecek ürün bulunamadı veya bu ürünü silme yetkiniz yok.");
        }
        $product_image_to_delete = $product_image_data['Urun_Gorseli'];

        $stmt_delete = $conn->prepare("DELETE FROM Urun WHERE Urun_ID = ".PARAM_PRODUCT_ID_PA." AND Satici_ID = ".PARAM_SATICI_ID_PA);
        $stmt_delete->bindParam(PARAM_PRODUCT_ID_PA, $product_id_delete, PDO::PARAM_INT);
        $stmt_delete->bindParam(PARAM_SATICI_ID_PA, $satici_id, PDO::PARAM_INT);

        if ($stmt_delete->execute() && $stmt_delete->rowCount() > 0) {
            if ($product_image_to_delete && file_exists($upload_dir . $product_image_to_delete)) {
                if (!unlink($upload_dir . $product_image_to_delete)) {
                    error_log("product_action.php: Ürün görseli silinemedi: " . $upload_dir . $product_image_to_delete);
                     // Bu kritik bir hata değil, ürün silindi ama görseli kaldı. Loglamak yeterli olabilir.
                }
            }
            $conn->commit();
            $_SESSION['form_success_message'] = "Ürün başarıyla silindi.";
            header("Location: manage_product.php?status=product_deleted_successfully");
            exit();
        } else {
            $conn->rollBack();
            error_log("product_action.php: Ürün silinirken hiçbir satır etkilenmedi veya hata. Product ID: " . $product_id_delete);
            throw new ProductActionException("Ürün silinirken bir sorun oluştu veya ürün bulunamadı.");
        }
    } elseif (isset($_POST['edit_product_redirect'])) { // Bu, manage_product.php'deki "Düzenle" butonu için olabilir
        // Bu kısım sizin mevcut `edit_product` bloğunuzla aynı işlevi görüyor, sadece POST name farklı.
        // Genellikle "Düzenle" butonu GET ile ID gönderir veya form içinde POST ile ID ve bir action gönderir.
        // Sizin kodunuzda manage_product.php'den gelen POST['edit_product'] vardı, bu GET olmalı.
        // Eğer manage_product.php'deki düzenle butonu bir form içindeyse ve POST ile 'edit_product_redirect' gönderiyorsa bu blok çalışır.
        $product_id_redirect = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if ($product_id_redirect === false || $product_id_redirect <= 0) {
            throw new ProductActionException("Düzenlenecek ürün için geçersiz ID.");
        }
        header("Location: edit_product.php?id=" . $product_id_redirect);
        exit();
    }
    else {
        throw new ProductActionException("Bilinmeyen bir ürün işlemi talep edildi.");
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("product_action.php PDOException: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı işlemi sırasında bir hata oluştu. Lütfen tekrar deneyin.";
    header("Location: manage_product.php?status=db_processing_error");
    exit();
} catch (ProductActionException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("product_action.php ProductActionException: " . $e->getMessage());
    $_SESSION['form_error_message'] = $e->getMessage(); // Özel hata mesajını session'a ata
    header("Location: manage_product.php?status=product_action_failed");
    exit();
} catch (Exception $e) { // Diğer tüm genel istisnalar
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("product_action.php Generic Exception: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Beklenmedik bir sistem hatası oluştu.";
    header("Location: manage_product.php?status=unexpected_system_error_product_action");
    exit();
}

// Herhangi bir POST işlemi tanımlanamadıysa veya bir hata oluştuysa (catch bloklarından çıkılırsa)
// Bu noktaya normalde gelinmemeli, tüm yollar exit() ile bitiyor.
// Ama bir fallback olarak.
if (!headers_sent()) { // Henüz header gönderilmediyse
    $_SESSION['form_error_message'] = $_SESSION['form_error_message'] ?? "Tanımsız bir işlem durumu oluştu.";
    header("Location: manage_product.php?status=unknown_product_action_state");
    exit();
}

