<?php
// delete_user.php - Kullanıcı silme işlemi
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Özel istisna sınıfı tanımla
class UserDeletionException extends Exception {}

// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Eğer admin giriş yapmamışsa veya yetkisi yoksa, giriş sayfasına yönlendir
    header("Location: login.php?status=unauthorized");
    exit();
}

// GET ile gelen kullanıcı ID'sini al ve doğrula
$user_id_to_delete = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Kullanıcı ID'si geçerli değilse veya kendi hesabını silmeye çalışıyorsa engelle
if ($user_id_to_delete === false || $user_id_to_delete <= 0) {
    error_log("delete_user.php: Geçersiz kullanıcı ID'si: " . ($user_id_to_delete === false ? 'false' : $user_id_to_delete));
    header("Location: admin_user.php?status=invalid_user_id");
    exit();
}

// Adminin kendi hesabını silmesini engelle
if ($user_id_to_delete == $_SESSION['user_id']) {
    header("Location: admin_user.php?status=cannot_delete_self");
    exit();
}

// SonarQube S1192 hatasını gidermek için parametre adı sabitini tanımla
$param_user_id = ':user_id';

try {
    // İşlemi başlat (transaction) - Veritabanı tutarlılığı için önemlidir
    $conn->beginTransaction();

    // Silinecek kullanıcının rolünü kontrol et
    $stmt_get_role = $conn->prepare("SELECT role FROM users WHERE id = " . $param_user_id);
    $stmt_get_role->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
    $stmt_get_role->execute();
    $user_role = $stmt_get_role->fetchColumn();

    if ($user_role) {
        switch ($user_role) {
            case 'customer':
                $stmt_delete_customer = $conn->prepare("DELETE FROM musteri WHERE User_ID = " . $param_user_id);
                $stmt_delete_customer->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
                $stmt_delete_customer->execute();
                break;
            case 'seller':
                // Satıcıya ait ürünleri de silmek gerekebilir (veya ürünleri başka bir satıcıya aktarmak)
                // Eğer veritabanı şemanızda CASCADE DELETE ayarı yoksa, ilgili ürünleri de silmeniz gerekebilir.
                // Örn: DELETE FROM Urun WHERE Satici_ID IN (SELECT Satici_ID FROM satici WHERE User_ID = :user_id);
                $stmt_delete_seller = $conn->prepare("DELETE FROM satici WHERE User_ID = " . $param_user_id);
                $stmt_delete_seller->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
                $stmt_delete_seller->execute();
                break;
            case 'admin':
                $stmt_delete_admin = $conn->prepare("DELETE FROM admin WHERE User_ID = " . $param_user_id);
                $stmt_delete_admin->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
                $stmt_delete_admin->execute();
                break;
            default :
            
        }
    }

    // Son olarak, 'users' tablosundan kullanıcıyı sil
    $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = " . $param_user_id);
    $stmt_delete_user->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);

    if ($stmt_delete_user->execute()) {
        $conn->commit(); // Tüm işlemler başarılıysa commit et
        header("Location: admin_user.php?status=user_deleted"); // Başarılı silme sonrası yönlendir
        exit();
    } else {
        $conn->rollBack(); // Hata oluşursa geri al
        error_log("delete_user.php: Kullanıcı silinirken hiçbir satır etkilenmedi. User ID: " . $user_id_to_delete);
        // Özel istisna fırlat
        throw new UserDeletionException("Kullanıcı silinemedi veya bulunamadı.");
    }

} catch (UserDeletionException $e) { // Özel istisnayı yakala
    $conn->rollBack(); // İşlemi geri al
    error_log("delete_user.php: Kullanıcı Silme Hatası: " . $e->getMessage());
    header("Location: admin_user.php?status=delete_failed");
    exit();
} catch (PDOException $e) { // PDO istisnasını yakala
    $conn->rollBack(); // PDO hatası durumunda işlemi geri al
    error_log("delete_user.php: Veritabanı hatası: " . $e->getMessage());
    header("Location: admin_user.php?status=db_error");
    exit();
} catch (Exception $e) { // Diğer genel istisnaları yakala
    $conn->rollBack(); // Genel hata durumunda işlemi geri al
    error_log("delete_user.php: Beklenmedik Hata: " . $e->getMessage());
    header("Location: admin_user.php?status=unexpected_error");
    exit();
}

