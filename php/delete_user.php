<?php
// delete_user.php - Kullanıcı silme işlemi

session_start(); // Oturumu başlat

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Özel istisna sınıfı tanımla
class UserDeletionException extends Exception {}

// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// Parametre adı için bir sabit tanımlıyoruz.
$param_user_id = ':user_id';

try {
    // İşlemi başlat (transaction) - Veritabanı tutarlılığı için önemlidir
    $conn->beginTransaction();

    // Silinecek kullanıcının rolünü kontrol et
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
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
                $stmt_delete_seller = $conn->prepare("DELETE FROM satici WHERE User_ID = " . $param_user_id);
                $stmt_delete_seller->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
                $stmt_delete_seller->execute();
                break;
            case 'admin':
                $stmt_delete_admin = $conn->prepare("DELETE FROM admin WHERE User_ID = " . $param_user_id);
                $stmt_delete_admin->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);
                $stmt_delete_admin->execute();
                break;
        }
    }

    // Son olarak, 'users' tablosundan kullanıcıyı sil
    $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = " . $param_user_id);
    $stmt_delete_user->bindParam($param_user_id, $user_id_to_delete, PDO::PARAM_INT);

    if ($stmt_delete_user->execute()) {
        $conn->commit(); // Tüm işlemler başarılıysa commit et
        // Başarılı silme sonrası yönlendirme yaparken session'a bir mesaj bırakabiliriz.
        $_SESSION['user_action_success'] = "Kullanıcı (ID: " . htmlspecialchars($user_id_to_delete) . ") başarıyla silindi.";
        header("Location: admin_user.php");
        exit();
    } else {
        $conn->rollBack(); // Hata oluşursa geri al
        throw new UserDeletionException("Kullanıcı silinemedi veya bulunamadı.");
    }

} catch (UserDeletionException $e) {
    if($conn->inTransaction()) $conn->rollBack();
    $_SESSION['user_action_error'] = $e->getMessage();
    header("Location: admin_user.php");
    exit();
} catch (PDOException $e) {
    if($conn->inTransaction()) $conn->rollBack();
    error_log("delete_user.php: Veritabanı hatası: " . $e->getMessage());
    $_SESSION['user_action_error'] = "Kullanıcı silinirken bir veritabanı hatası oluştu.";
    header("Location: admin_user.php");
    exit();
}

