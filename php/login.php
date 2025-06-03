<?php
// login sayfası
session_start(); // Oturumu başlat

// Eğer kullanıcı zaten giriş yapmışsa, ilgili panele yönlendir (gereksiz tekrar girişi engelle)
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: /eticaret/php/admin_dashboard.php");
            break;
        case 'seller':
            header("Location: /eticaret/php/seller_dashboard.php");
            break;
        case 'customer':
            header("Location: /eticaret/php/index.php");
            break;
        default:
            header("Location: /eticaret/php/index.php");
            break;
    }
    exit();
}

include '../database.php'; // Veritabanı bağlantısı

$error = ""; // Hata mesajlarını tutmak için değişken
$email_value = ""; // Formda e-posta değerini korumak için

// Form gönderimi kontrolü
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token Kontrolü (ÖNEMLİ: Formunuza CSRF token eklemelisiniz)
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $error = "Geçersiz istek. Lütfen tekrar deneyin.";
    //     // CSRF token'ı loglayabilir veya başka bir işlem yapabilirsiniz.
    //     // exit('CSRF token hatası!'); // Veya daha kullanıcı dostu bir mesaj
    // } else {

        $email = trim($_POST['email'] ?? ''); // POST verisini alırken null birleştirme operatörü kullan
        $password = $_POST['password'] ?? '';
        $email_value = htmlspecialchars($email); // XSS'e karşı e-postayı sakla

        // 1. Sunucu Tarafı Form Validasyonu
        if (empty($email) || empty($password)) {
            $error = "E-posta ve şifre alanları boş bırakılamaz.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçersiz e-posta formatı. Lütfen doğru bir e-posta adresi girin.";
        } elseif (strlen($password) < 6) { // Örnek: Minimum şifre uzunluğu kontrolü
            $error = "Şifre en az 6 karakter olmalıdır.";
        } else {
            try {
                $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = :email");
                $stmt->bindParam(':email', $email, PDO::PARAM_STR); // Veri tipini belirtmek iyi bir pratiktir
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    if (password_verify($password, $user['password'])) {
                        // Giriş başarılı, oturum bilgilerini güvenli bir şekilde yeniden oluştur
                        session_regenerate_id(true); // Oturum sabitleme saldırılarına karşı koruma

                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = htmlspecialchars($user['username']); // XSS'e karşı kullanıcı adını sakla
                        $_SESSION['role'] = $user['role'];
                        // $_SESSION['login_time'] = time(); // Oturum zaman aşımı için kullanılabilir
                        // $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT']; // Oturum kaçırmaya karşı ek bir kontrol

                        // CSRF token'ını yeniden oluştur (giriş sonrası için)
                        // if (function_exists('random_bytes')) {
                        //     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        // } else {
                        //     $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
                        // }


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
                                header("Location: /eticaret/index.php");
                                break;
                        }
                        exit();
                    } else {
                        $error = "Hatalı e-posta veya şifre. Lütfen bilgilerinizi kontrol edin.";
                        // Başarısız giriş denemelerini loglamak ve belirli bir sayıdan sonra hesabı kilitlemek (brute-force saldırılarına karşı) iyi bir pratiktir.
                        // log_failed_login_attempt($email);
                    }
                } else {
                    $error = "Hatalı e-posta veya şifre. Lütfen bilgilerinizi kontrol edin.";
                }
            } catch (PDOException $e) {
                error_log("Login PDOException: " . $e->getMessage()); // Hataları log dosyasına yaz
                $error = "Giriş sırasında bir sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.";
            }
        }
    // } // CSRF token else bloğu sonu
}

// Her sayfa yüklendiğinde yeni bir CSRF token oluştur (veya form gönderilmediyse)
// if (empty($_POST) && (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token']))) {
//     if (function_exists('random_bytes')) {
//         $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
//     } else {
//         // Fallback for older PHP versions
//         $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
//     }
// }
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
            background-color: rgba(255, 255, 255, 0.85); /* Biraz daha opak yaptım */
            padding: 30px; /* Padding artırıldı */
            border-radius: 10px; /* Köşeler daha yuvarlak */
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25); /* Gölge belirginleştirildi */
            width: 100%;
            max-width: 450px; /* Maksimum genişlik artırıldı */
            text-align: center;
        }
        h2 {
            margin-bottom: 25px; /* Boşluk artırıldı */
            color: #333;
            font-size: 28px; /* Boyut biraz küçültüldü */
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px; /* Padding artırıldı */
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px; /* Boşluk artırıldı */
            position: relative;
            text-align: left; /* Hata mesajları sola dayalı */
            font-size: 0.9em;
        }
        .close-btn {
            position: absolute;
            right: 10px; /* Konum ayarlandı */
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.1em;
            line-height: 1;
        }
        .form-group {
            margin-bottom: 20px; /* Form grupları arasına boşluk */
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="email"], input[type="password"] {
            width: 100%; /* Tam genişlik */
            padding: 12px; /* Padding artırıldı */
            border: 1px solid #ccc;
            border-radius: 5px; /* Köşeler daha yuvarlak */
            font-size: 16px; /* Font boyutu */
            box-sizing: border-box; /* Padding ve border'ı genişliğe dahil et */
        }
        button {
            background-color: rgb(155, 10, 109);
            color: white;
            padding: 12px 20px; /* Padding ayarlandı */
            border: none;
            border-radius: 5px;
            margin-top: 10px; /* Üst boşluk azaltıldı */
            cursor: pointer;
            font-size: 18px; /* Font boyutu */
            width: 100%; /* Tam genişlik */
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: rgb(125, 8, 89); /* Hover rengi koyulaştırıldı */
        }
        .form-link {
            display: block;
            margin-top: 15px;
            color: rgb(155, 10, 109);
            text-decoration: none;
            font-size: 0.9em;
        }
        .form-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Giriş Yap</h2>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
                <?php echo htmlspecialchars($error); // Hata mesajını güvenli göster ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post" id="loginForm" novalidate>
            <div class="form-group">
                <label for="email">E-posta Adresi:</label>
                <input type="email" id="email" name="email" value="<?php echo $email_value; // E-posta değerini formda tut ?>" required placeholder="ornek@gmail.com">
            </div>

            <div class="form-group">
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required placeholder="Şifreniz">
            </div>

            <button type="submit">Giriş Yap</button>
        </form>
        <a href="register.php" class="form-link">Hesabınız yok mu? Kayıt Olun</a>
        </div>

    <script>
        // Basit bir client-side validasyon örneği (isteğe bağlı, sunucu tarafı şart)
        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', function(event) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            let messages = [];

            if (email.trim() === '') {
                messages.push('E-posta adresi boş bırakılamaz.');
            } else {
                // Basit e-posta format kontrolü
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email)) {
                    messages.push('Lütfen geçerli bir e-posta adresi girin.');
                }
            }

            if (password.trim() === '') {
                messages.push('Şifre boş bırakılamaz.');
            } else if (password.length < 6) {
                // messages.push('Şifre en az 6 karakter olmalıdır.'); // Sunucu tarafında bu kontrol var
            }

            if (messages.length > 0) {
                event.preventDefault(); // Formun gönderilmesini engelle
                // Hata mesajlarını göstermek için bir alan oluşturulabilir veya mevcut hata alanı güncellenebilir.
                // Örneğin:
                let errorDiv = document.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    loginForm.insertBefore(errorDiv, loginForm.firstChild);
                    const closeBtn = document.createElement('span');
                    closeBtn.className = 'close-btn';
                    closeBtn.innerHTML = '&times;';
                    closeBtn.onclick = function() { this.parentElement.style.display='none'; };
                    errorDiv.appendChild(closeBtn);
                }
                 // innerHTML yerine textContent kullanmak XSS riskini azaltır
                errorDiv.innerHTML = '<span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>' + messages.join('<br>');
                errorDiv.style.display = 'block';
            }
        });

        // Hata mesajını kapatmak için çarpı butonu (dinamik olarak eklenenler için de çalışır)
        document.addEventListener("DOMContentLoaded", function () {
            var closeBtns = document.querySelectorAll(".close-btn");
            closeBtns.forEach(function(btn) {
                btn.addEventListener("click", function () {
                    this.parentElement.style.display = "none";
                });
            });
        });
    </script>
</body>
</html>
