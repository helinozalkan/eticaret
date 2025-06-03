<?php
// Veritabanı bağlantı dosyası - PDO Kullanımı ile güvenli bağlantı

// Veritabanı erişim bilgileri
$servername = "localhost";
$username   = "root";
$password   = ""; // Geliştirme ortamında boş olabilir; production ortamda güçlü şifre kullanılmalı
$dbname     = "eticaret";

try {
    // PDO ile bağlantıyı oluştur
    $conn = new PDO(
        "mysql:host=$servername;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // PDO hata modunu exception olarak ayarla
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Varsayılan fetch modunu ilişkisel dizi olarak ayarla
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "Veritabanı bağlantısı başarılı!"; // Test amaçlı; canlı ortamda kullanılmamalı
} catch (PDOException $e) {
    // Geliştirme ortamı için detaylı hata mesajı gösterilebilir:
    // die("Veritabanı bağlantı hatası: " . $e->getMessage());

    // Production ortam için daha kullanıcı dostu ve güvenli mesaj
    error_log("PDO Hatası: " . $e->getMessage()); // Log dosyasına kaydet
    die("Veritabanı bağlantısı hatası: Lütfen daha sonra tekrar deneyin.");
}
