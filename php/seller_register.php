<?php
// seller_register.php - Satıcı kayıt sayfası
session_start(); // Oturumu başlat

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya veya paneline yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: /eticaret/index.php"); // Veya satıcı paneline
    exit();
}

include_once '../database.php'; // Veritabanı bağlantısı

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

// Formdan gelen değerleri tutmak için (hata durumunda formu tekrar doldurmak için)
$store_name_value = "";
$name_value = "";
$email_value = "";
$phone_value = "";
$address_value = "";
// TCKN/VKN alanı için de bir değer tutucu eklenebilir, şimdilik opsiyonel.
// $tckn_vkn_value = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Token Kontrolü
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $error = "Geçersiz istek. Lütfen formu tekrar gönderin.";
    // } else {

        $store_name = trim($_POST['store_name'] ?? '');
        $name = trim($_POST['name'] ?? ''); // Ad Soyad
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? ''; // Şifre tekrarı
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        // $tckn_vkn = trim($_POST['tckn_vkn'] ?? ''); // Opsiyonel TCKN/VKN alanı

        // Hata durumunda form alanlarını doldurmak için değerleri sakla
        $store_name_value = htmlspecialchars($store_name);
        $name_value = htmlspecialchars($name);
        $email_value = htmlspecialchars($email);
        $phone_value = htmlspecialchars($phone);
        $address_value = htmlspecialchars($address);

        // Kapsamlı Sunucu Tarafı Validasyonları
        if (empty($store_name) || empty($name) || empty($email) || empty($password) || empty($password_confirm) || empty($phone) || empty($address)) {
            $error = "Tüm zorunlu alanlar doldurulmalıdır.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçersiz e-posta formatı. (örn: kullanici@ornek.com)";
        } elseif (strlen($password) < 8) {
            $error = "Şifre en az 8 karakter uzunluğunda olmalıdır.";
        } elseif ($password !== $password_confirm) {
            $error = "Girilen şifreler eşleşmiyor.";
        } elseif (!preg_match('/^\+?[0-9\s\-()]{10,15}$/', $phone)) { // Daha esnek telefon formatı (uluslararası da olabilir)
            // Veya daha katı bir Türkiye formatı: /^0?5\d{9}$/ (05xxxxxxxxx veya 5xxxxxxxxx)
            $error = "Geçersiz telefon numarası formatı. (örn: 05xxxxxxxxx)";
        } elseif (strlen($store_name) > 100 || strlen($name) > 100 || strlen($address) > 255) { // Alan uzunluk kontrolleri
            $error = "Alanlardan biri çok uzun. Lütfen kısaltın.";
        }
        // Gerekirse TCKN/VKN için de validasyon eklenebilir.
        // elseif (!empty($tckn_vkn) && !preg_match('/^\d{10,11}$/', $tckn_vkn)) {
        //     $error = "Geçersiz TCKN/VKN formatı.";
        // }
        else {
            try {
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
                $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_check_email->execute();

                if ($stmt_check_email->rowCount() > 0) {
                    $error = "Bu e-posta adresi zaten başka bir hesap tarafından kullanılıyor.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Satıcılar için hesap durumu başlangıçta doğrulanmamış (0) olmalı. Admin onayı sonrası 1 yapılabilir.
                    $hesap_durumu_satici = 0; // 0: Beklemede/Doğrulanmamış, 1: Aktif

                    $conn->beginTransaction();

                    // 1. `users` tablosuna ekle
                    $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'seller')");
                    // `users` tablosundaki `username` için satıcının Ad Soyad bilgisini kullanıyoruz.
                    // Eğer mağaza adı veya farklı bir kullanıcı adı isteniyorsa bu kısım düzenlenebilir.
                    $stmt_insert_user->bindParam(':username', $name, PDO::PARAM_STR);
                    $stmt_insert_user->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt_insert_user->bindParam(':password', $hashed_password, PDO::PARAM_STR);

                    if ($stmt_insert_user->execute()) {
                        $user_id = $conn->lastInsertId(); // Yeni eklenen kullanıcının ID'si

                        // 2. `satici` tablosuna ekle
                        // TCKN_VKN şimdilik null olarak ayarlanabilir veya formdan alınabilir.
                        $tckn_vkn_satici = null; // Eğer formdan alınıyorsa: $tckn_vkn;

                        $stmt_insert_seller = $conn->prepare(
                            "INSERT INTO satici (User_ID, TCKN_VKN, Magaza_Adi, Ad_Soyad, Tel_No, Eposta, Adres, HesapDurumu)
                             VALUES (:user_id, :tckn_vkn, :store_name, :ad_soyad, :tel_no, :eposta, :adres, :hesap_durumu)"
                        );
                        $stmt_insert_seller->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt_insert_seller->bindParam(':tckn_vkn', $tckn_vkn_satici, PDO::PARAM_STR); // Eğer null ise PARAM_NULL
                        $stmt_insert_seller->bindParam(':store_name', $store_name, PDO::PARAM_STR);
                        $stmt_insert_seller->bindParam(':ad_soyad', $name, PDO::PARAM_STR);
                        $stmt_insert_seller->bindParam(':tel_no', $phone, PDO::PARAM_STR);
                        $stmt_insert_seller->bindParam(':eposta', $email, PDO::PARAM_STR); // Satıcının e-postası users tablosundaki ile aynı
                        $stmt_insert_seller->bindParam(':adres', $address, PDO::PARAM_STR);
                        $stmt_insert_seller->bindParam(':hesap_durumu', $hesap_durumu_satici, PDO::PARAM_INT);

                        if ($stmt_insert_seller->execute()) {
                            $conn->commit();
                            $_SESSION['registration_success'] = "Satıcı kaydınız başarıyla oluşturuldu. Hesabınız admin onayı sonrası aktifleşecektir. Giriş yapabilirsiniz.";
                            header("Location: login.php");
                            exit();
                        } else {
                            $conn->rollBack();
                            error_log("Seller Register Error: Failed to insert into satici table for User ID: " . $user_id);
                            $error = "Satıcı bilgileri kaydedilirken bir hata oluştu. Lütfen tekrar deneyin.";
                        }
                    } else {
                        $conn->rollBack();
                        error_log("Seller Register Error: Failed to insert into users table for email: " . $email);
                        $error = "Kullanıcı (satıcı) kaydı oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.";
                    }
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Seller Register PDOException: " . $e->getMessage());
                $error = "Kayıt sırasında bir sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.";
            } catch (Exception $e) {
                 if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Seller Register Exception: " . $e->getMessage());
                $error = "Beklenmedik bir hata oluştu. Lütfen sistem yöneticisi ile iletişime geçin.";
            }
        }
    // } // CSRF token else bloğu sonu
}

// CSRF token oluşturma
// if (empty($_POST) && (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token']))) {
//     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Kayıt Formu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('../images/index.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 0;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.9); /* Arka plan opaklığı biraz artırıldı */
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px; /* Form genişliği artırıldı */
            box-sizing: border-box;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
            font-size: 26px; /* Başlık boyutu ayarlandı */
        }
        label {
            display: block;
            margin-bottom: 6px; /* Label alt boşluğu azaltıldı */
            color: #555;
            font-weight: bold;
            text-align: left; /* Labellar sola dayalı */
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px; /* Padding biraz azaltıldı */
            margin-bottom: 15px; /* Alanlar arası boşluk ayarlandı */
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 15px; /* Font boyutu ayarlandı */
        }
        textarea {
            min-height: 80px; /* Textarea minimum yükseklik */
            resize: vertical; /* Sadece dikeyde boyutlandırma */
        }
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color:rgb(91, 140, 213);
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 17px; /* Buton font boyutu */
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button[type="submit"]:hover {
            background-color:rgb(91, 140, 213);
        }
        .error-message, .success-message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            position: relative;
            text-align: left;
            font-size: 0.9em;
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
            font-size: 1.1em;
        }
        .form-link {
            display: block;
            margin-top: 20px;
            color: rgb(91, 140, 213);
            text-decoration: none;
            font-size: 0.9em;
            text-align: center;
        }
        .form-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Satıcı Kayıt Formu</h1>

        <?php if (!empty($error)) : ?>
            <div class="error-message">
                <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php /* Başarı mesajı login sayfasında gösterilecek
        <?php endif; */?>

        <form action="seller_register.php" method="POST" id="sellerRegisterForm" novalidate>
            <label for="store_name">Mağaza Adı:</label>
            <input type="text" name="store_name" id="store_name" required value="<?php echo $store_name_value; ?>" placeholder="Mağazanızın Adı">

            <label for="name">Ad Soyad (Yetkili Kişi):</label>
            <input type="text" name="name" id="name" required value="<?php echo $name_value; ?>" placeholder="Adınız Soyadınız">

            <label for="email">E-posta Adresiniz:</label>
            <input type="email" name="email" id="email" required value="<?php echo $email_value; ?>" placeholder="iletisim@magaza.com">

            <label for="password">Şifre:</label>
            <input type="password" name="password" id="password" required placeholder="En az 8 karakter">

            <label for="password_confirm">Şifre Tekrar:</label>
            <input type="password" name="password_confirm" id="password_confirm" required placeholder="Şifrenizi tekrar girin">

            <label for="phone">Telefon Numarası:</label>
            <input type="text" name="phone" id="phone" required value="<?php echo $phone_value; ?>" placeholder="05xxxxxxxxx">

            <label for="address">Mağaza Adresi:</label>
            <textarea name="address" id="address" required placeholder="Açık adresiniz"><?php echo $address_value; ?></textarea>

            <!--
            <label for="tckn_vkn">TCKN / VKN (Opsiyonel):</label>
            <input type="text" name="tckn_vkn" id="tckn_vkn" value="<?php ?>" placeholder="TC Kimlik veya Vergi Numaranız">
            -->

            <form action="php/seller_register.php" method="post">
  <!-- Form alanları buraya gelecek -->
  <button type="submit">Satıcı Olarak Kayıt Ol</button>
</form>

        </form>
        <a href="login.php" class="form-link">Zaten bir hesabınız var mı? Giriş Yapın</a>
        <a href="register.php" class="form-link">Müşteri olarak mı kayıt olmak istiyorsunuz?</a>
        <a href="../index.php" class="form-link">Ana Sayfaya Dön</a>

    </div>

    <script>
        // Client-side validasyon (isteğe bağlı, sunucu tarafı esastır)
        const sellerRegisterForm = document.getElementById('sellerRegisterForm');
        sellerRegisterForm.addEventListener('submit', function (event) {
            let messages = [];
            // Alanların boş olup olmadığını kontrol et
            const requiredFields = ['store_name', 'name', 'email', 'password', 'password_confirm', 'phone', 'address'];
            requiredFields.forEach(function(fieldId) {
                const field = document.getElementById(fieldId);
                if (field.value.trim() === '') {
                    messages.push(field.previousElementSibling.innerText.replace(':', '') + ' boş bırakılamaz.');
                }
            });

            const email = document.getElementById('email').value.trim();
            if (email !== '') {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email)) messages.push('Geçerli bir e-posta adresi girin.');
            }

            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            if (password !== '' && password.length < 8) messages.push('Şifre en az 8 karakter olmalıdır.');
            if (password !== '' && passwordConfirm !== '' && password !== passwordConfirm) messages.push('Şifreler eşleşmiyor.');

            const phone = document.getElementById('phone').value.trim();
            // Basit bir telefon formatı kontrolü (daha karmaşık regex'ler kullanılabilir)
            // const phonePattern = /^(0?5\d{9})$/; // Örnek Türkiye formatı
            // if (phone !== '' && !phonePattern.test(phone)) messages.push('Geçerli bir telefon numarası girin (05xxxxxxxxx).');


            if (messages.length > 0) {
                event.preventDefault();
                let errorDiv = document.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    sellerRegisterForm.insertBefore(errorDiv, sellerRegisterForm.firstChild);
                }
                errorDiv.innerHTML = '<span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>' + messages.join('<br>');
                errorDiv.style.display = 'block';
            }
        });

        // Hata mesajını kapatmak için
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
