<?php
// login.php - Giriş sayfası

// Oturumu başlat
session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
// include_once, dosyanın sadece bir kez dahil edildiğinden emin olur.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
// 1. Database sınıfının tek örneğini (instance) alıyoruz.
$db = Database::getInstance();
// 2. Bu örnek üzerinden PDO bağlantı nesnesini ($conn) alıyoruz.
$conn = $db->getConnection();


$error = ""; // Hata mesajlarını tutmak için değişken

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "E-posta ve şifre boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı.";
    } else {
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkenini
            // artık yeni yöntemle de olsa almış durumdayız.
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (password_verify($password, $user['password'])) {
                    // Giriş başarılı, şimdi role göre ek kontrol yap
                    if ($user['role'] === 'seller') {
                        // Satıcı ise, HesapDurumu'nu kontrol et
                        $stmt_seller_status = $conn->prepare("SELECT HesapDurumu FROM Satici WHERE User_ID = :user_id");
                        $stmt_seller_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                        $stmt_seller_status->execute();
                        $seller_data = $stmt_seller_status->fetch(PDO::FETCH_ASSOC);

                        if (!$seller_data || $seller_data['HesapDurumu'] == 0) {
                            $error = "Hesabınız henüz yönetici onayı almamıştır veya pasif durumdadır. Lütfen daha sonra tekrar deneyin.";
                        } else {
                            // Satıcı hesabı aktif, oturumu başlat ve yönlendir
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            header("Location: /eticaret/php/seller_dashboard.php");
                            exit();
                        }
                    } else {
                        // Diğer roller (admin, customer) için doğrudan yönlendirme
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];

                        switch ($user['role']) {
                            case 'admin':
                                header("Location: /eticaret/php/admin_dashboard.php");
                                break;
                            case 'customer':
                                header("Location: /eticaret/index.php"); // Müşteriyi ana sayfaya yönlendir
                                break;
                            default:
                                header("Location: /eticaret/index.php"); // Varsayılan yönlendirme
                                break;
                        }
                        exit();
                    }
                } else {
                    $error = "Hatalı e-posta veya şifre. Lütfen tekrar deneyin.";
                }
            } else {
                $error = "Hatalı e-posta veya şifre. Lütfen tekrar deneyin.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
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
    <!-- Stil kodları değişmediği için aynı kalıyor -->
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
            background-color: rgb(91, 140, 213);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            margin-top: 50px;
            cursor: pointer;
            font-size: 20px;
        }

        button:hover {
            background-color: rgb(70, 120, 190);
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