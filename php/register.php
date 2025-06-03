<?php
// register.php - Kullanıcı kayıt sayfası
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz) - include_once kullanıldı

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Gelen verileri al ve temizle
    $username = trim(htmlspecialchars($_POST['username'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = trim(htmlspecialchars($_POST['role'] ?? ''));

    // Sunucu tarafı validasyonları
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = "Tüm alanlar doldurulmalıdır.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı.";
    } elseif (strlen($password) < 6) { // Minimum şifre uzunluğu kontrolü
        $error = "Şifre en az 6 karakter olmalıdır.";
    } elseif (!in_array($role, ['customer', 'seller', 'admin'])) { // Geçerli rol kontrolü
        $error = "Geçersiz rol seçimi.";
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

                // `users` tablosuna ekle
                $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                $stmt_insert_user->bindParam(':username', $username);
                $stmt_insert_user->bindParam(':email', $email);
                $stmt_insert_user->bindParam(':password', $hashed_password);
                $stmt_insert_user->bindParam(':role', $role);

                if ($stmt_insert_user->execute()) {
                    $user_id = $conn->lastInsertId(); // Yeni eklenen kullanıcının ID'sini al

                    // SonarQube S1192 hatasını gidermek için parametre adı sabitini tanımla
                    $param_user_id = ':user_id';

                    // Role göre ilgili tabloya ekleme
                    $insert_specific_role_successful = false;
                    if ($role == 'admin') {
                        $stmt_admin = $conn->prepare("INSERT INTO admin (User_ID) VALUES (:user_id)");
                        $stmt_admin->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
                        $insert_specific_role_successful = $stmt_admin->execute();
                    } elseif ($role == 'customer') {
                        $stmt_customer = $conn->prepare("INSERT INTO musteri (User_ID) VALUES (:user_id)");
                        $stmt_customer->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
                        $insert_specific_role_successful = $stmt_customer->execute();
                    } elseif ($role == 'seller') {
                        // Satıcı rolü için satici tablosuna ekleme yap
                        // Magaza_Adi, Ad_Soyad, Tel_No, Eposta, Adres, HesapDurumu gibi alanları şimdilik varsayılan/boş değerlerle doldurabiliriz.
                        // Bu alanlar daha sonra seller_manage.php veya seller_dashboard.php üzerinden güncellenebilir.
                        $store_name_default = "Yeni Mağaza " . $username; // Varsayılan mağaza adı
                        $ad_soyad_default = $username; // Ad Soyad için kullanıcı adını kullan
                        $tel_no_default = null; // Telefon numarası varsayılan boş
                        $eposta_default = $email; // E-posta varsayılan olarak kullanıcının e-postası
                        $adres_default = null; // Adres varsayılan boş
                        $hesap_durumu_default = 0; // Doğrulama bekliyor olabilir, 0 olarak ayarlandı (aktiflik durumu için 1'di)

                        $stmt_seller = $conn->prepare("INSERT INTO satici (User_ID, Magaza_Adi, Ad_Soyad, Tel_No, Eposta, Adres, HesapDurumu)
                                                      VALUES (:user_id, :magaza_adi, :ad_soyad, :tel_no, :eposta, :adres, :hesap_durumu)");
                        $stmt_seller->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
                        $stmt_seller->bindParam(':magaza_adi', $store_name_default);
                        $stmt_seller->bindParam(':ad_soyad', $ad_soyad_default);
                        $stmt_seller->bindParam(':tel_no', $tel_no_default);
                        $stmt_seller->bindParam(':eposta', $eposta_default);
                        $stmt_seller->bindParam(':adres', $adres_default);
                        $stmt_seller->bindParam(':hesap_durumu', $hesap_durumu_default, PDO::PARAM_INT); // HesapDurumu integer

                        $insert_specific_role_successful = $stmt_seller->execute();
                    }
                    
                    if ($insert_specific_role_successful) {
                        $conn->commit(); // Tüm işlemler başarılıysa commit et
                        $success = "Kayıt başarılı! Hoş geldiniz, " . htmlspecialchars($username) . ".";
                        header("Location: login.php"); // Başarılı kayıt sonrası login sayfasına yönlendirme
                        exit();
                    } else {
                        // Kullanıcı eklendi ama role özel tabloya eklenirken hata oluştuysa users tablosundan sil
                        $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = :user_id");
                        $stmt_delete_user->bindParam($param_user_id, $user_id, PDO::PARAM_INT); // Sabit kullanıldı
                        $stmt_delete_user->execute();
                        $conn->rollBack(); // Hata oluşursa geri al
                        $error = "Kayıt sırasında role özel tabloya eklenirken bir hata oluştu. Lütfen tekrar deneyin.";
                    }
                } else {
                    $conn->rollBack(); // Hata oluşursa geri al
                    $error = "Kullanıcı kaydı sırasında bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        } catch (PDOException $e) {
            // Veritabanı hatası durumunda kullanıcıya genel bir hata mesajı göster
            $conn->rollBack(); // Herhangi bir PDO hatasında geri al
            error_log("Kayıt hatası: " . $e->getMessage()); // Hatayı logla
            $error = "Kayıt sırasında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
        } catch (Exception $e) { // Genel istisnaları yakala
            $conn->rollBack();
            error_log("Kayıt sırasında beklenmedik hata: " . $e->getMessage());
            $error = "Beklenmedik bir hata oluştu. Lütfen daha sonra tekrar deneyin.";
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
    <title>Kayıt Ol</title>

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
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 30px;
        }

        .error-message, .success-message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            position: relative;
            text-align: left; /* Mesajın sol hizalı olması için */
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

        input[type="text"], input[type="email"], input[type="password"], select {
            width: 80%;
            padding: 15px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 15px;
            box-sizing: border-box;
        }

        select {
            appearance: none;
            background-color: #fff;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg xmlns%3D%27http://www.w3.org/2000/svg%27 viewBox%3D%270 0 4 5%27%3E%3Cpath fill%3D%27%23444%27 d%3D%27M2 0L0 2h4zm0 5L0 3h4z%27/%3E%3C/svg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 10px 10px;
            padding-right: 30px;
        }

        button {
            background-color: rgb(155, 10, 109);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            margin-top: 25px;
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
    <h2>Kayıt Formu</h2>

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

    <form action="register.php" method="post">
       <input type="text" name="username" id="username" placeholder="Kullanıcı Adı" required>
       <input type="email" name="email" id="email" placeholder="E-posta" required>
       <input type="password" name="password" id="password" placeholder="Şifre" required>
       <select id="role" name="role" required>
            <option value="" disabled selected>Rol Seçin</option>
            <option value="customer">Müşteri</option>
            <option value="seller">Satıcı</option>
            <option value="admin">Admin</option>
       </select>
       <button type="submit">Kayıt Ol</button>
    </form>
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
