<?php
// add_to_cart.php - Ürünü sepete ekleme script'i

session_start();
include_once '../database.php'; // Veritabanı bağlantısı

// Kullanıcı giriş yapmışsa ID'sini al, yapmamışsa sepet session tabanlı olacak
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['urun_id'])) {
    $product_id = (int)$_POST['urun_id'];
    $quantity = isset($_POST['miktar']) ? (int)$_POST['miktar'] : 1; // index.php'den miktar geliyor

    if ($product_id > 0 && $quantity > 0) {
        try {
            // Ürün bilgilerini veritabanından çek (fiyat, ad, stok vb.)
            $stmt_product = $conn->prepare("SELECT Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli FROM Urun WHERE Urun_ID = :urun_id AND Aktiflik_Durumu = 1");
            $stmt_product->bindParam(':urun_id', $product_id, PDO::PARAM_INT);
            $stmt_product->execute();
            $product_data = $stmt_product->fetch(PDO::FETCH_ASSOC);

            if ($product_data) {
                if ($product_data['Stok_Adedi'] >= $quantity) {
                    // Sepet session'ını başlat (eğer yoksa)
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    // Ürün sepette varsa adedini artır, yoksa yeni ekle
                    if (isset($_SESSION['cart'][$product_id])) {
                        // Stok kontrolü: Mevcut adet + eklenecek adet <= stok
                        if (($product_data['Stok_Adedi'] >= ($_SESSION['cart'][$product_id]['quantity'] + $quantity))) {
                             $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                             $_SESSION['success_message'] = htmlspecialchars($product_data['Urun_Adi']) . " sepetinize eklendi (adet güncellendi).";
                        } else {
                            $_SESSION['error_message'] = "Stokta yeterli sayıda '" . htmlspecialchars($product_data['Urun_Adi']) . "' bulunmuyor. Sepetinize ekleyebileceğiniz maksimum adet: " . ($product_data['Stok_Adedi'] - $_SESSION['cart'][$product_id]['quantity']);
                        }
                    } else {
                        $_SESSION['cart'][$product_id] = [
                            'name' => $product_data['Urun_Adi'],
                            'price' => (float)$product_data['Urun_Fiyati'],
                            'quantity' => $quantity,
                            'image' => $product_data['Urun_Gorseli']
                        ];
                        $_SESSION['success_message'] = htmlspecialchars($product_data['Urun_Adi']) . " başarıyla sepetinize eklendi.";
                    }
                } else {
                    $_SESSION['error_message'] = "Maalesef, '" . htmlspecialchars($product_data['Urun_Adi']) . "' için stokta yeterli ürün bulunmuyor.";
                }
            } else {
                $_SESSION['error_message'] = "Sepete eklenecek ürün bulunamadı veya aktif değil.";
            }
        } catch (PDOException $e) {
            error_log("add_to_cart.php: Sepete ekleme hatası: " . $e->getMessage());
            $_SESSION['error_message'] = "Sepete ekleme sırasında teknik bir sorun oluştu.";
        }
    } else {
        $_SESSION['error_message'] = "Geçersiz ürün ID'si veya miktar.";
    }
} else {
    $_SESSION['error_message'] = "Geçersiz istek.";
}

// Kullanıcıyı geldiği sayfaya veya sepet sayfasına yönlendir
// HTTP_REFERER genellikle ürünün eklendiği sayfadır.
$redirect_page = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';
header("Location: " . $redirect_page);
exit;
?>
