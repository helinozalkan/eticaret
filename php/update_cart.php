<?php
// Sepetimdeki ürünü güncelleme, silme veya sayısını arttırma
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isset($_SESSION['user_id'])) {
    // Eğer kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
    header("Location: login.php?status=not_logged_in");
    exit();
}

$user_id = $_SESSION['user_id']; // Oturumdan users.id'yi al

// POST verilerini güvenli bir şekilde al ve temizle/doğrula
// filter_input ile inputları filtrelemek daha güvenlidir
$urun_id = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT); // 'set_quantity' eylemi için yeni parametre

// Gerekli verilerin gelip gelmediğini ve geçerliliğini kontrol et
if ($urun_id === false || $urun_id <= 0 || empty($action)) {
    // Geçersiz veya eksik girdi durumunda hata logla ve kullanıcıyı yönlendir
    error_log("update_cart.php: Geçersiz veya eksik girdi. Urun_ID: " . ($urun_id === false ? 'false' : $urun_id) . ", Action: " . ($action === false ? 'false' : $action));
    header("Location: my_cart.php?status=invalid_input");
    exit();
}

try {
    // Kullanıcının Musteri_ID'sini veritabanından al
    // Bu, users tablosundaki ID ile musteri tablosundaki Musteri_ID arasındaki eşleşmeyi sağlar
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
    $stmt_musteri->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    // Eğer müşteri kaydı bulunamazsa (ki bu normalde olmamalı, add_to_cart'ta oluşturulur)
    if (!$musteri_data) {
        error_log("update_cart.php: Musteri kaydı bulunamadı User_ID: " . $user_id);
        header("Location: my_cart.php?status=no_customer_record");
        exit();
    }
    $musteri_id = $musteri_data['Musteri_ID'];

    // SonarQube S1192 hatalarını gidermek için parametre adı sabitlerini tanımla
    $param_musteri_id = ':musteri_id';
    $param_urun_id = ':urun_id';

    // Veritabanı işlemlerinde tutarlılık için transaction başlat
    $conn->beginTransaction();

    $query_executed = false; // Sorgunun başarılı olup olmadığını takip etmek için bayrak

    // Gelen 'action' parametresine göre farklı işlemler yap
    if ($action == 'increment') {
        // Ürün miktarını 1 artır
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar + 1 WHERE Musteri_ID = :musteri_id AND Urun_ID = :urun_id");
        $stmt->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
    } elseif ($action == 'decrement') {
        // Ürün miktarını 1 azalt, ancak miktar 1'den az olamaz
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = Miktar - 1 WHERE Musteri_ID = :musteri_id AND Urun_ID = :urun_id AND Miktar > 1");
        $stmt->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
    } elseif ($action == 'set_quantity') {
        // Ürün miktarını doğrudan belirli bir değere ayarla
        if ($quantity === false || $quantity < 1) {
            $conn->rollBack(); // Geçersiz miktar durumunda işlemi geri al
            error_log("update_cart.php: Geçersiz miktar değeri: quantity=" . ($quantity === false ? 'false' : $quantity));
            header("Location: my_cart.php?status=invalid_quantity");
            exit();
        }
        $stmt = $conn->prepare("UPDATE Sepet SET Miktar = :quantity WHERE Musteri_ID = :musteri_id AND Urun_ID = :urun_id");
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
    } elseif ($action == 'remove') {
        // Ürünü sepetten sil
        $stmt = $conn->prepare("DELETE FROM Sepet WHERE Musteri_ID = :musteri_id AND Urun_ID = :urun_id");
        $stmt->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        $stmt->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
        $query_executed = $stmt->execute();
    } else {
        // Bilinmeyen bir eylem gelirse işlemi geri al
        $conn->rollBack();
        error_log("update_cart.php: Bilinmeyen eylem: " . $action);
        header("Location: my_cart.php?status=unknown_action");
        exit();
    }

    // Sorgu başarılı olduysa ve en az bir satır etkilendiyse
    if ($query_executed && $stmt->rowCount() > 0) {
        $conn->commit(); // İşlemi onayla
        header("Location: my_cart.php?status=success");
        exit();
    } else {
        // Sorgu çalıştı ama hiçbir satır etkilenmediyse (örn. ürün sepette yoksa) veya sorgu başarısız olduysa
        $conn->rollBack(); // İşlemi geri al
        error_log("update_cart.php: Sepet güncellenirken hiçbir satır etkilenmedi veya sorgu başarısız oldu. Action: " . $action . ", Urun_ID: " . $urun_id . ", Musteri_ID: " . $musteri_id);
        header("Location: my_cart.php?status=error");
        exit();
    }

} catch (PDOException $e) {
    $conn->rollBack(); // PDO hatası durumunda işlemi geri al
    // Hata mesajını logla, kullanıcıya gösterme
    error_log("update_cart.php: Veritabanı hatası: " . $e->getMessage());
    header("Location: my_cart.php?status=db_error");
    exit();
}
