<?php
// update_cart.php - Sepet güncelleme (miktar artırma/azaltma, ürün silme)

// Oturumu başlat ve temel yapılandırmalar
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı (PDO)

// PDO parametreleri için sabitler (SonarQube php:S1192 düzeltmesi)
define('PARAM_MUSTERI_ID', ':musteri_id');
define('PARAM_URUN_ID', ':urun_id');
define('PARAM_USER_ID', ':user_id');
define('PARAM_QUANTITY', ':quantity');
define('LOG_PREFIX_URUN_ID', "(Urun_ID: ");

/**
 * Sepet işlemleri sırasında oluşabilecek özel istisna sınıfı.
 * SonarQube (php:S112) uyarısını gidermek için tanımlanmıştır.
 */
class CartUpdateException extends Exception {}

// 1. Kullanıcı Giriş Kontrolü
if (!isset($_SESSION['user_id'])) {
    // Kullanıcı giriş yapmamışsa, login sayfasına yönlendir
    header("Location: login.php?status=not_logged_in&return_url=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$musteri_id = null;
$message_type = 'error'; // Yönlendirme mesajının türü (error, success, warning)
$redirect_message = 'unexpected_error'; // Yönlendirme mesajı anahtarı

// urun_id_post ve action değişkenlerini try bloğunun dışında tanımlayalım ki catch bloklarında erişilebilir olsun
$urun_id_post = null; 
$action = null;

try {
    // 2. CSRF Token Kontrolü (Formdan CSRF token gönderildiğini varsayıyoruz)
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     throw new CartUpdateException("Geçersiz istek (CSRF token hatası). Lütfen tekrar deneyin.");
    // }

    // 3. Gelen Verilerin Alınması ve Temel Validasyonu
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new CartUpdateException("Geçersiz istek türü. Sadece POST istekleri kabul edilir.");
    }

    $urun_id_post = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    $quantity_set = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($urun_id_post === false || $urun_id_post <= 0) {
        throw new CartUpdateException("Geçersiz ürün kimliği (urun_id).");
    }
    if (empty($action)) {
        throw new CartUpdateException("İşlem türü (action) belirtilmemiş.");
    }

    $allowed_actions = ['increment', 'decrement', 'set_quantity', 'remove'];
    if (!in_array($action, $allowed_actions)) {
        throw new CartUpdateException("Geçersiz işlem türü: " . htmlspecialchars($action));
    }

    if ($action === 'set_quantity' && ($quantity_set === false || $quantity_set < 0)) {
        if ($quantity_set === 0) {
            $action = 'remove';
        } elseif ($quantity_set === false || $quantity_set < 1) {
            throw new CartUpdateException("Geçersiz miktar. Miktar en az 1 olmalıdır.");
        }
    }

    // 4. Müşteri ID'sini Al
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . PARAM_USER_ID);
    $stmt_musteri->bindParam(PARAM_USER_ID, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if (!$musteri_data) {
        error_log("update_cart.php: Musteri kaydı bulunamadı. User_ID: " . $user_id);
        throw new CartUpdateException("Müşteri kaydınız bulunamadı. Lütfen destek ile iletişime geçin.");
    }
    $musteri_id = $musteri_data['Musteri_ID'];

    // 5. Veritabanı İşlemleri (Transaction İçinde)
    $conn->beginTransaction();

    $query_executed = false;
    $rows_affected = 0;

    if ($action === 'decrement') {
        $stmt_check_qty = $conn->prepare("SELECT Miktar FROM Sepet WHERE Musteri_ID = " . PARAM_MUSTERI_ID . " AND Urun_ID = " . PARAM_URUN_ID);
        $stmt_check_qty->bindParam(PARAM_MUSTERI_ID, $musteri_id, PDO::PARAM_INT);
        $stmt_check_qty->bindParam(PARAM_URUN_ID, $urun_id_post, PDO::PARAM_INT);
        $stmt_check_qty->execute();
        $current_item = $stmt_check_qty->fetch(PDO::FETCH_ASSOC);

        if ($current_item && $current_item['Miktar'] <= 1) {
            $action = 'remove';
        }
    }

    if ($action == 'increment') {
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar + 1 WHERE Musteri_ID = " . PARAM_MUSTERI_ID . " AND Urun_ID = " . PARAM_URUN_ID);
        $stmt->bindParam(PARAM_MUSTERI_ID, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_URUN_ID, $urun_id_post, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
        if ($query_executed) $rows_affected = $stmt->rowCount();
    } elseif ($action == 'decrement') {
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar - 1 WHERE Musteri_ID = " . PARAM_MUSTERI_ID . " AND Urun_ID = " . PARAM_URUN_ID . " AND Miktar > 1");
        $stmt->bindParam(PARAM_MUSTERI_ID, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_URUN_ID, $urun_id_post, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
        if ($query_executed) $rows_affected = $stmt->rowCount();
    } elseif ($action == 'set_quantity') {
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = " . PARAM_QUANTITY . " WHERE Musteri_ID = " . PARAM_MUSTERI_ID . " AND Urun_ID = " . PARAM_URUN_ID);
        $stmt->bindParam(PARAM_QUANTITY, $quantity_set, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_MUSTERI_ID, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_URUN_ID, $urun_id_post, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
        if ($query_executed) $rows_affected = $stmt->rowCount();
    } elseif ($action == 'remove') {
        $stmt = $conn->prepare("DELETE FROM Sepet WHERE Musteri_ID = " . PARAM_MUSTERI_ID . " AND Urun_ID = " . PARAM_URUN_ID);
        $stmt->bindParam(PARAM_MUSTERI_ID, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam(PARAM_URUN_ID, $urun_id_post, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
        if ($query_executed) $rows_affected = $stmt->rowCount();
    }

    if ($query_executed) {
        if ($rows_affected > 0) {
            $conn->commit();
            $message_type = 'success';
            switch ($action) {
                case 'increment': $redirect_message = 'quantity_increased'; break;
                case 'decrement': $redirect_message = 'quantity_decreased'; break;
                case 'set_quantity': $redirect_message = 'quantity_updated'; break;
                case 'remove': $redirect_message = 'item_removed'; break;
            }
        } else {
            $conn->rollBack();
            $message_type = 'warning';
            $redirect_message = 'no_change_needed';
            error_log("update_cart.php: No rows affected. Action: $action, Urun_ID: $urun_id_post, Musteri_ID: $musteri_id");
        }
    } else {
        $conn->rollBack();
        // Veritabanı sorgusu başarısız olduysa, daha spesifik bir hata fırlatabiliriz.
        // Ancak, PDO zaten hata modunda exception fırlatacağı için bu throw nadiren çalışır.
        // Genellikle PDOException yakalanır.
        throw new CartUpdateException("Sepet güncellenirken veritabanı sorgusu başarısız oldu.");
    }

} catch (PDOException $e) { // Veritabanı ile ilgili istisnalar
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("update_cart.php PDOException: " . $e->getMessage() . " (Urun_ID: " . ($urun_id_post ?? 'N/A') . ", Action: " . ($action ?? 'N/A') . ")");
    $redirect_message = 'db_error';
} catch (CartUpdateException $e) { // Kendi özel istisnamız
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("update_cart.php CartUpdateException: " . $e->getMessage() . " (Urun_ID: " . ($urun_id_post ?? 'N/A') . ", Action: " . ($action ?? 'N/A') . ")");
    // Hata mesajına göre redirect_message ayarlanabilir
    // Örneğin: if (strpos($e->getMessage(), 'Geçersiz ürün kimliği') !== false) $redirect_message = 'invalid_product_id_on_update';
    $redirect_message = 'cart_processing_error'; // Daha genel bir özel hata mesajı
} catch (Exception $e) { // Diğer tüm genel istisnalar (en sona konulmalı)
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("update_cart.php Generic Exception: " . $e->getMessage() . " (Urun_ID: " . ($urun_id_post ?? 'N/A') . ", Action: " . ($action ?? 'N/A') . ")");
    $redirect_message = 'unexpected_system_error';
}

header("Location: my_cart.php?status=" . $redirect_message . "&type=" . $message_type);
exit();

