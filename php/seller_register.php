<?php
// seller_register.php - Satıcı kayıt sayfası

session_start(); // Oturumu başlat

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya veya paneline yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: /eticaret/index.php"); // Veya satıcı paneline
    exit();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

// Formdan gelen değerleri tutmak için (hata durumunda formu tekrar doldurmak için)
$store_name_value = "";
$name_value = "";
$email_value = "";
$phone_value = "";
$address_value = "";


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $store_name = trim($_POST['store_name'] ?? '');
    $name = trim($_POST['name'] ?? ''); // Ad Soyad
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? ''; // Şifre tekrarı
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

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
    } elseif (!preg_match('/^\+?[0-9\s\-()]{10,15}$/', $phone)) {
        $error = "Geçersiz telefon numarası formatı. (örn: 05xxxxxxxxx)";
    } elseif (strlen($store_name) > 100 || strlen($name) > 100 || strlen($address) > 255) {
        $error = "Alanlardan biri çok uzun. Lütfen kısaltın.";
    } else {
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt_check_email->execute();

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Bu e-posta adresi zaten başka bir hesap tarafından kullanılıyor.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $hesap_durumu_satici = 0; // 0: Beklemede/Doğrulanmamış

                $conn->beginTransaction();

                // 1. `users` tablosuna ekle
                $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, 'seller')");
                $stmt_insert_user->bindParam(':username', $name, PDO::PARAM_STR);
                $stmt_insert_user->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_insert_user->bindParam(':password', $hashed_password, PDO::PARAM_STR);

                if ($stmt_insert_user->execute()) {
                    $user_id = $conn->lastInsertId();

                    // 2. `satici` tablosuna ekle
                    $tckn_vkn_satici = null;

                    $stmt_insert_seller = $conn->prepare(
                        "INSERT INTO satici (User_ID, TCKN_VKN, Magaza_Adi, Ad_Soyad, Tel_No, Eposta, Adres, HesapDurumu)
                         VALUES (:user_id, :tckn_vkn, :store_name, :ad_soyad, :tel_no, :eposta, :adres, :hesap_durumu)"
                    );
                    $stmt_insert_seller->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt_insert_seller->bindParam(':tckn_vkn', $tckn_vkn_satici, PDO::PARAM_STR);
                    $stmt_insert_seller->bindParam(':store_name', $store_name, PDO::PARAM_STR);
                    $stmt_insert_seller->bindParam(':ad_soyad', $name, PDO::PARAM_STR);
                    $stmt_insert_seller->bindParam(':tel_no', $phone, PDO::PARAM_STR);
                    $stmt_insert_seller->bindParam(':eposta', $email, PDO::PARAM_STR);
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
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Kayıt Formu</title>
    <!-- Stil kodları değişmediği için aynı kalıyor -->
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
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 550px;
            box-sizing: border-box;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 25px;
            font-size: 26px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-weight: bold;
            text-align: left;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 15px;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color:rgb(91, 140, 213);
            border: none;
            border-radius: 5px;
            color: #fff;
            font-size: 17px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button[type="submit"]:hover {
            background-color:rgb(70, 120, 190);
        }
        .error-message {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            position: relative;
            text-align: left;
            font-size: 0.9em;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            
            <button type="submit">Satıcı Olarak Kayıt Ol</button>
        </form>
        <a href="login.php" class="form-link">Zaten bir hesabınız var mı? Giriş Yapın</a>
        <a href="register.php" class="form-link">Müşteri olarak mı kayıt olmak istiyorsunuz?</a>
        <a href="../index.php" class="form-link">Ana Sayfaya Dön</a>
    </div>
</body>
</html>
