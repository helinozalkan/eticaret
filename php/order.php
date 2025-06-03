<?php
// order.php - Sipariş sayfası
session_start();
include('../database.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sepet verisi alınacak
    $musteri_id = $_SESSION['user_id']; // Müşteri ID'si session'dan alınacak
    $siparis_tutari = 0; // Sipariş tutarı
    $urunler = $_POST['urunler']; // Sepet ürünleri

    // Sepetteki her ürün için sipariş veritabanına ekleme
    foreach ($urunler as $urun) {
        $urun_id = $urun['id'];
        $urun_fiyat = $urun['fiyat'];
        $siparis_tutari += $urun_fiyat;

        // Sipariş tablosuna veri ekle
        $query = "INSERT INTO Siparis (Musteri_ID, Siparis_Tutari, Siparis_Tarihi) VALUES ('$musteri_id', '$siparis_tutari', NOW())";
        mysqli_query($conn, $query);

        // Sepet ürünleri Sipariş tablosuna aktarılacak
        $siparis_id = mysqli_insert_id($conn);
        $siparis_urun_query = "INSERT INTO SiparisUrun (Siparis_ID, Urun_ID) VALUES ('$siparis_id', '$urun_id')";
        mysqli_query($conn, $siparis_urun_query);
    }

    // Ödeme işlemi ve teslimat adresi ekleme
    // Sipariş ve ödeme bilgilerini kaydet
    header("Location: /El-Emek/order_summary.php");
    exit();
}

// Sipariş sayfası içeriği
?>

<form action="order.php" method="POST">
    <!-- Sepetteki ürünler gösterilecek ve ödeme işlemi yapılacak -->
    <input type="submit" value="Siparişi Ver">
</form>
