<?php
// Sepete ürün ekleme fonksiyonu
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Özel istisna sınıfı tanımla
// Bu, SonarQube'un S112 uyarısını gidermek için özel bir istisna kullanmamızı sağlar.
class CartOperationException extends Exception {}

// Oturum kontrolü ve kullanıcı giriş kontrolü
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Eğer kullanıcı giriş yapmamışsa, giriş sayfasına yönlendir
    header("Location: login.php?status=not_logged_in");
    exit();
}

$user_id = $_SESSION['user_id']; // Oturumdan users.id'yi al

// POST verilerini güvenli bir şekilde al ve temizle/doğrula
// filter_input ile inputları filtrelemek daha güvenlidir
$urun_id = filter_input(INPUT_POST, 'urun_id', FILTER_VALIDATE_INT);
$boyut = filter_input(INPUT_POST, 'boyut', FILTER_VALIDATE_INT); // Boyutun integer olduğunu varsayıyoruz
$miktar = filter_input(INPUT_POST, 'miktar', FILTER_VALIDATE_INT);

// Gerekli verilerin gelip gelmediğini ve geçerliliğini kontrol et
if ($urun_id === false || $urun_id <= 0 || $boyut === false || $boyut <= 0 || $miktar === false || $miktar <= 0) {
    error_log("add_to_cart.php: Geçersiz veya eksik girdi. Urun_ID: " . ($urun_id === false ? 'false' : $urun_id) . ", Boyut: " . ($boyut === false ? 'false' : $boyut) . ", Miktar: " . ($miktar === false ? 'false' : $miktar));
    header("Location: my_cart.php?status=invalid_input");
    exit();
}

try {
    // İşlemi başlat (transaction) - Veritabanı tutarlılığı için önemlidir
    $conn->beginTransaction();

    // SonarQube S1192 hatalarını gidermek için parametre adı sabitlerini tanımla
    $param_user_id = ':user_id';
    $param_musteri_id = ':musteri_id';
    $param_urun_id = ':urun_id';
    $param_boyut = ':boyut';
    $param_miktar = ':miktar';
    $param_eklenme_tarihi = ':eklenme_tarihi';
    $param_new_miktar = ':new_miktar';
    $param_sepet_id = ':sepet_id';

    // Kullanıcının Musteri_ID'sini veritabanından al veya oluştur
    $musteri_id = null;
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = " . $param_user_id);
    $stmt_musteri->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if ($musteri_data) {
        $musteri_id = $musteri_data['Musteri_ID'];
    } else {
        // Müşteri kaydı yoksa oluştur
        $stmt_insert_musteri = $conn->prepare("INSERT INTO musteri (User_ID) VALUES (" . $param_user_id . ")");
        $stmt_insert_musteri->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
        if ($stmt_insert_musteri->execute()) {
            $musteri_id = $conn->lastInsertId(); // Yeni eklenen müşteri ID'sini al
        } else {
            // Özel istisna fırlat
            throw new CartOperationException("Müşteri kaydı eklenirken bir hata oluştu.");
        }
    }

    // Ürünün sepette olup olmadığını kontrol et
    $stmt_check_cart = $conn->prepare("SELECT Sepet_ID, Miktar FROM sepet WHERE Musteri_ID = " . $param_musteri_id . " AND Urun_ID = " . $param_urun_id . " AND Boyut = " . $param_boyut);
    $stmt_check_cart->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
    $stmt_check_cart->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
    $stmt_check_cart->bindParam($param_boyut, $boyut, PDO::PARAM_INT);
    $stmt_check_cart->execute();
    $cart_item = $stmt_check_cart->fetch(PDO::FETCH_ASSOC);

    $eklenme_tarihi = date('Y-m-d H:i:s'); // Tarih ve saati de kaydetmek daha iyi

    if ($cart_item) {
        // Aynı ürün (ve boyut) sepette varsa miktarı güncelle
        $new_miktar = $cart_item['Miktar'] + $miktar;
        $stmt_update_cart = $conn->prepare("UPDATE sepet SET Miktar = " . $param_new_miktar . ", Eklenme_Tarihi = " . $param_eklenme_tarihi . " WHERE Sepet_ID = " . $param_sepet_id);
        $stmt_update_cart->bindParam($param_new_miktar, $new_miktar, PDO::PARAM_INT);
        $stmt_update_cart->bindParam($param_eklenme_tarihi, $eklenme_tarihi);
        $stmt_update_cart->bindParam($param_sepet_id, $cart_item['Sepet_ID'], PDO::PARAM_INT);
        $query_executed = $stmt_update_cart->execute();
    } else {
        // Yeni ürün ekle
        $stmt_insert_cart = $conn->prepare("INSERT INTO sepet (Boyut, Miktar, Eklenme_Tarihi, Urun_ID, Musteri_ID) VALUES (" . $param_boyut . ", " . $param_miktar . ", " . $param_eklenme_tarihi . ", " . $param_urun_id . ", " . $param_musteri_id . ")");
        $stmt_insert_cart->bindParam($param_boyut, $boyut, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam($param_miktar, $miktar, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam($param_eklenme_tarihi, $eklenme_tarihi);
        $stmt_insert_cart->bindParam($param_urun_id, $urun_id, PDO::PARAM_INT);
        $stmt_insert_cart->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        $query_executed = $stmt_insert_cart->execute();
    }

    // Sorgu başarılı olduysa
    if ($query_executed) {
        $conn->commit(); // İşlemi onayla
        header("Location: my_cart.php?status=added_to_cart");
        exit();
    } else {
        // Sorgu çalıştı ama başarısız olduysa
        // Özel istisna fırlat
        throw new CartOperationException("Sepete ürün eklenirken bir sorun oluştu.");
    }

} catch (CartOperationException $e) { // Özel istisnayı yakala
    // Herhangi bir hata durumunda işlemi geri al
    $conn->rollBack();
    // Hata mesajını logla, kullanıcıya gösterme
    error_log("add_to_cart.php: Kart İşlemi Hatası: " . $e->getMessage());
    header("Location: my_cart.php?status=error_adding_to_cart");
    exit();
} catch (PDOException $e) { // PDO istisnasını yakala
    // PDO hatası durumunda işlemi geri al
    $conn->rollBack();
    // Hata mesajını logla, kullanıcıya gösterme
    error_log("add_to_cart.php: Veritabanı hatası: " . $e->getMessage());
    header("Location: my_cart.php?status=db_error");
    exit();
} catch (Exception $e) { // Diğer genel istisnaları yakala
    $conn->rollBack();
    error_log("add_to_cart.php: Beklenmedik Hata: " . $e->getMessage());
    header("Location: my_cart.php?status=unexpected_error");
    exit();
}
