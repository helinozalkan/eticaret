<?php
// Veritabanı bağlantısı - PDO Kullanımı
$servername = "localhost";
$username = "root";
// php:S2115 uyarısı: Hassas bilgiler (veritabanı şifresi gibi) doğrudan kod içine yazılmamalıdır.
// Bu uyarı, şifre boş bırakılsa bile, şifrenin kod içinde hardcoded olması nedeniyle verilir.
// Canlı (production) ortamda bu şifre, sunucu ortam değişkenleri (environment variables)
// veya web kök dizininin dışında saklanan güvenli bir yapılandırma dosyası aracılığıyla alınmalıdır.
// Geliştirme ortamı için şimdilik boş bırakılabilir, ancak MySQL root kullanıcınız için güçlü bir şifre
// belirlemeniz ve buraya o şifreyi yazmanız şiddetle tavsiye edilir.
$password = "";
$dbname = "eticaret";

try {
    // PDO ile bağlantıyı oluştur
    // charset=utf8mb4 kullanmak, emoji gibi daha geniş karakter setlerini destekler
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Hata modunu istisnalara ayarla. Bu, veritabanı hatalarında PDOException fırlatır.
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Varsayılan fetch modunu ayarla: İlişkisel dizi olarak döndür (FETCH_ASSOC)
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // echo "Veritabanı bağlantısı başarılı!"; // Bağlantıyı test etmek için kullanılabilir, canlıda kaldırılmalı
} catch(PDOException $e) {
    // Bağlantı hatası durumunda güvenli bir hata mesajı göster ve betiği durdur
    // Gerçek hata mesajını kullanıcıya göstermek yerine loglamak daha güvenlidir (örn. error_log($e->getMessage());)
    die("Veritabanı bağlantısı hatası: Lütfen daha sonra tekrar deneyin. (" . $e->getMessage() . ")");
}


