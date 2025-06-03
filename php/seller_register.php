<?php
// seller_register.php - Satıcı kayıt sayfası
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz) - include_once kullanıldı, parantezler kaldırıldı

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Gelen verileri al ve temizle
    $store_name = trim(htmlspecialchars($_POST['store_name'] ?? ''));
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
    $address = trim(htmlspecialchars($_POST['address'] ?? ''));
    $status = 1; // HesapDurumu için varsayılan aktif durumu

    // Sunucu tarafı validasyonları
    if (empty($store_name) || empty($name) || empty($email) || empty($password) || empty($phone) || empty($address)) {
        $error = "Tüm alanlar doldurulmalıdır.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı.";
    } elseif (strlen($password) < 6) { // Minimum şifre uzunluğu kontrolü
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif (!preg_match("/^\d{10,11}$/", $phone)) { // Telefon numarası formatı kontrolü (10 veya 11 rakam) - [0-9] yerine \d kullanıldı
        $error = "Geçersiz telefon numarası formatı. Sadece rakam giriniz (örn: 5xxxxxxxxx).";
    } else {
        try {
            // E-posta adresinin zaten kullanılıp kullanılmadığını kontrol et
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt_check_email->bindParam(':email', $email);
            $stmt_check_email->execute();

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Bu e-posta adresi zaten kullanılıyor.";
            } else {
                // Şifreyi güvenli bir şekilde hashle
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // İşlemi başlat (transaction) - Veritabanı tutarlılığı için önemlidir
                $conn->beginTransaction();

                // `users` tablosuna ekle ve role'ü seller olarak ayarla
                $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'seller')");
                $stmt_insert_user->bindParam(':username', $name); // Kullanıcı adı olarak Ad Soyad kullanılıyor
                $stmt_insert_user->bindParam(':email', $email);
                $stmt_insert_user->bindParam(':password', $hashed_password);

                if ($stmt_insert_user->execute()) {
                    $user_id = $conn->lastInsertId(); // Yeni eklenen kullanıcının ID'sini al

                    // `satici` tablosuna ekleme
                    // TCKN_VKN şimdilik NULL olarak bırakıldı, ileride eklenebilir
                    $tckn_vkn = null;
                    $stmt_insert_seller = $conn->prepare("INSERT INTO satici (User_ID, TCKN_VKN, Magaza_Adi, Ad_Soyad, Tel_No, Eposta, Adres, HesapDurumu)
                                                        VALUES (:user_id, :tckn_vkn, :store_name, :ad_soyad, :tel_no, :eposta, :adres, :hesap_durumu)");
                    $stmt_insert_seller->bindParam(':user_id', $user_id);
                    $stmt_insert_seller->bindParam(':tckn_vkn', $tckn_vkn);
                    $stmt_insert_seller->bindParam(':store_name', $store_name);
                    $stmt_insert_seller->bindParam(':ad_soyad', $name);
                    $stmt_insert_seller->bindParam(':tel_no', $phone);
                    $stmt_insert_seller->bindParam(':eposta', $email);
                    $stmt_insert_seller->bindParam(':adres', $address);
                    $stmt_insert_seller->bindParam(':hesap_durumu', $status, PDO::PARAM_INT); // Integer olarak bağla

                    if ($stmt_insert_seller->execute()) {
                        $conn->commit(); // Tüm işlemler başarılıysa commit et
                        $success = "Satıcı kaydınız başarıyla tamamlandı. Giriş yapabilirsiniz.";
                        // header("Location: login.php"); // Başarılı kayıt sonrası login sayfasına yönlendirme
                        // exit(); // Yönlendirmeden sonra betiğin çalışmasını durdur
                    } else {
                        $conn->rollBack(); // Hata oluşursa geri al
                        $error = "Satıcı bilgileri kaydedilirken bir hata oluştu. Lütfen tekrar deneyin.";
                    }
                } else {
                    $conn->rollBack(); // Hata oluşarsa geri al
                    $error = "Kullanıcı kaydı sırasında bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        } catch (PDOException $e) {
            $conn->rollBack(); // Herhangi bir PDO hatasında geri al
            error_log("Satıcı kayıt hatası: " . $e->getMessage()); // Hatayı logla
            $error = "Kayıt sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Kayıt</title>
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
        .container {
            background-color: rgba(255, 255, 255, 0.8);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color:rgb(155, 10, 109) ;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color:rgb(155, 10, 109) ;
        }
        /* Hata ve başarı mesajları için stil */
        .error-message, .success-message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            position: relative;
            text-align: left;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .close-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Satıcı Kayıt Formu</h1>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <span class="close-btn">&times;</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)) : ?>
            <div class="success-message">
                <span class="close-btn">&times;</span>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form action="seller_register.php" method="POST">
            <label for="store_name">Mağaza Adı:</label>
            <input type="text" name="store_name" id="store_name" required value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>">

            <label for="name">Ad Soyad:</label>
            <input type="text" name="name" id="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

            <label for="phone">Telefon Numarası:</label>
            <input type="text" name="phone" id="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

            <label for="email">E-posta:</label>
            <input type="email" name="email" id="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label for="password">Şifre:</label>
            <input type="password" name="password" id="password" required>

            <label for="address">Adres:</label>
            <textarea name="address" id="address" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

            <button type="submit">Kayıt Ol</button>
        </form>
    </div>

    <script>
        // Hata/başarı mesajını kapatmak için çarpı butonu
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
