<?php
/*Sepeti onaylayıp veri tabanına sipariş kaydet
session_start();
include('../database.php');

// Kullanıcı kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$musteri_id = $_SESSION['user_id'];

// Sepetteki ürünleri çekmek için doğru sorguyu yazalım
$query = "SELECT m.Sepet_ID, m.Miktar, u.Urun_ID, u.Urun_Fiyati, u.Satici_ID 
          FROM Sepet m
          JOIN Urun u ON m.Urun_ID = u.Urun_ID 
          WHERE m.Musteri_ID = ?";  // `Musteri_ID`'yi kullanarak doğru müşteri bilgilerini alıyoruz

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $musteri_id);
$stmt->execute();
$result = $stmt->get_result();

// Sepet boşsa kullanıcıyı uyarmak için kontrol
if ($result->num_rows == 0) {
    echo "Sepetiniz boş.";
    exit();  // Çıkış yaparak sipariş işlemi durdurulur.
}

// Sipariş bilgilerini oluştur
$siparis_tutari = 0;
$urunler = [];
while ($row = $result->fetch_assoc()) {
    $subtotal = $row['Urun_Fiyati'] * $row['Miktar'];
    $siparis_tutari += $subtotal;

    $urunler[] = [
        'urun_id' => $row['Urun_ID'],
        'miktar' => $row['Miktar'],
        'fiyat' => $row['Urun_Fiyati'],
        'satici_id' => $row['Satici_ID']
    ];
}

// Eğer sipariş tutarı 0'dan büyükse, siparişi veritabanına kaydedelim
if ($siparis_tutari > 0) {
    $siparis_tarihi = date('Y-m-d');
    $teslimat_adresi = "Varsayılan Teslimat Adresi"; // Burayı dinamik olarak düzenleyebilirsiniz
    $fatura_adresi = "Varsayılan Fatura Adresi"; // Burayı dinamik olarak düzenleyebilirsiniz
    $teslimat_suresi = 7; // Örnek olarak 7 gün
    $siparis_durumu = "Beklemede";

    // Sipariş tablosuna ekle
    $query = "INSERT INTO Siparis (Siparis_Tarihi, Siparis_Tutari, Musteri_ID, Teslimat_Adresi, Fatura_Adresi, Teslimat_Suresi, Siparis_Durumu) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sdissis", $siparis_tarihi, $siparis_tutari, $musteri_id, $teslimat_adresi, $fatura_adresi, $teslimat_suresi, $siparis_durumu);

    if ($stmt->execute()) {
        $siparis_id = $stmt->insert_id;

        // Sipariş ürünlerini ekle
        $query = "INSERT INTO SiparisUrun (Siparis_ID, Urun_ID, Miktar, Fiyat) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);

        foreach ($urunler as $urun) {
            $stmt->bind_param("iiid", $siparis_id, $urun['urun_id'], $urun['miktar'], $urun['fiyat']);
            $stmt->execute();
        }

        // Sepeti temizle
        $query = "DELETE FROM Sepet WHERE Musteri_ID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $musteri_id);
        $stmt->execute();

        // Başarılı işlem sonrası yönlendirme
        header("Location: order_success.php");
    } else {
        echo "Sipariş kaydedilirken bir hata oluştu.";
    }
} else {
    echo "Sepetinizdeki ürünlerin toplam tutarı 0 olamaz. Sepetinizi kontrol edin.";
}*/
?>

