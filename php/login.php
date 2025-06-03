<?php
// login sayfası
session_start(); // Oturumu başlat

// Veritabanı bağlantısını dahil et
// database.php dosyasının PDO bağlantısını kurduğunu varsayıyoruz
include '../database.php'; // Parantezler kaldırıldı

$error = ""; // Hata mesajlarını tutmak için değişken

// Form gönderimi kontrolü
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Gelen verileri al ve temizle
    // trim: boşlukları temizler
    // htmlspecialchars: HTML etiketlerini özel karakterlere dönüştürerek XSS saldırılarını önler
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? ''; // Şifre hash'leneceği için htmlspecialchars kullanmıyoruz

    // Sunucu tarafı validasyonları
    if (empty($email) || empty($password)) {
        $error = "E-posta ve şifre boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı.";
    } else {
        try {
            // Kullanıcıyı veritabanında hazır sorgu (prepared statement) ile kontrol et
            // Bu, SQL enjeksiyonlarına karşı koruma sağlar
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email); // Parametreyi bağla
            $stmt->execute(); // Sorguyu çalıştır
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Sonucu al

            // Kullanıcı bulunduysa
            if ($user) {
                // Şifre doğrulaması
                // password_verify: hash'lenmiş şifre ile girilen şifreyi güvenli bir şekilde karşılaştırır
                if (password_verify($password, $user['password'])) {
                    // Giriş başarılı, oturum bilgilerini başlat
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role']; // Kullanıcı rolünü oturuma ekle

                    // Rol tabanlı yönlendirme
                    switch ($user['role']) {
                        case 'admin':
                            header("Location: /eticaret/php/admin_dashboard.php");
                            break;
                        case 'seller':
                            header("Location: /eticaret/php/seller_dashboard.php");
                            break;
                        case 'customer':
                            header("Location: /eticaret/index.php");
                            break;
                        default:
                            header("Location: /eticaret/index.php"); // Varsayılan yönlendirme
                            break;
                    }
                    exit(); // Yönlendirmeden sonra betiğin çalışmasını durdur
                } else {
                    // Hatalı şifre
                    $error = "Hatalı e-posta veya şifre. Lütfen tekrar deneyin.";
                }
            } else {
                // Kullanıcı bulunamadı
                $error = "Hatalı e-posta veya şifre. Lütfen tekrar deneyin.";
            }
        } catch (PDOException $e) {
            // Veritabanı hatası durumunda kullanıcıya genel bir hata mesajı göster
            // Gerçek hata mesajını loglamak daha güvenlidir (örn. error_log($e->getMessage());)
            $error = "Giriş sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('../images/index.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .form-container {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 500px;
            height: 400px;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 30px;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 15px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            right: 5px;
            top: 2px;
            cursor: pointer;
            font-weight: bold;
        }

        input[type="text"], input[type="email"], input[type="password"] {
            width: 80%;
            padding: 15px;
            margin: 8px -15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 15px;
        }

        input[type="email"] {
            margin-bottom: 20px;
        }
        input[type="password"] {
            margin-top: 30px;
        }

        button {
            background-color: rgb(155, 10, 109);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            margin-top: 50px;
            cursor: pointer;
            font-size: 20px;
        }

        button:hover {
            background-color: rgb(155, 10, 109);
        }
    </style>

</head>
<body>
    <div class="form-container">
        <h2>Giriş Yap</h2>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <span class="close-btn">&times;</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post" id="loginForm">
            <input type="email" id="email" name="email" placeholder="E-posta" required>
            <input type="password" id="password" name="password" placeholder="Şifre" required>
            <button type="submit">Giriş Yap</button>
        </form>
        <br>
        <a href="register.php">Kayıt Ol</a>
    </div>

    <script>
        // E-posta alanına @gmail.com eklemek için (isteğe bağlı, sunucu tarafı validasyon daha önemlidir)
        document.getElementById("email").addEventListener("input", function () {
            const emailInput = this;
            const gmailSuffix = "gmail.com";

            // Eğer kullanıcı @ işareti koyduysa ve henüz gmail.com eklenmemişse
            if (emailInput.value.includes("@") && !emailInput.value.includes(gmailSuffix)) {
                const parts = emailInput.value.split("@");
                // Kullanıcının girdiği kısmı koru, sadece sonuna gmail.com ekle
                emailInput.value = parts[0] + "@" + gmailSuffix;
            }
        });

        // Hata mesajını kapatmak için çarpı butonu
        document.addEventListener("DOMContentLoaded", function () {
            var closeBtn = document.querySelector(".close-btn");
            if (closeBtn) {
                closeBtn.addEventListener("click", function () {
                    this.parentElement.style.display = "none";
                });
            }
        });
    </script>
</body>
</html>
