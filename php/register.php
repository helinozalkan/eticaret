<?php
// register.php - Kullanıcı kayıt sayfası

session_start(); // Oturumu başlat

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya veya paneline yönlendir
if (isset($_SESSION['user_id'])) {
    header("Location: /eticaret/index.php"); // Veya dashboard
    exit();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

// Formdan gelen değerleri tutmak için değişkenler (hata durumunda formu tekrar doldurmak için)
$username_value = "";
$email_value = "";
$role_value = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = trim($_POST['role'] ?? '');

    $username_value = htmlspecialchars($username);
    $email_value = htmlspecialchars($email);
    $role_value = htmlspecialchars($role);

    // Sunucu Tarafı Kapsamlı Form Validasyonu
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm) || empty($role)) {
        $error = "Tüm alanların doldurulması zorunludur.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Geçersiz e-posta formatı. Lütfen doğru bir e-posta adresi girin (örn: kullanici@ornek.com).";
    } elseif (strlen($password) < 8) {
        $error = "Şifre en az 8 karakter uzunluğunda olmalıdır.";
    } elseif ($password !== $password_confirm) {
        $error = "Girilen şifreler birbiriyle eşleşmiyor.";
    } elseif (!in_array($role, ['customer', 'admin'])) {
        $error = "Geçersiz rol seçimi yapıldı.";
    } else {
        try {
            // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt_check_email->execute();

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta deneyin veya giriş yapın.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $conn->beginTransaction();

                $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                $stmt_insert_user->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt_insert_user->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_insert_user->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                $stmt_insert_user->bindParam(':role', $role, PDO::PARAM_STR);

                if ($stmt_insert_user->execute()) {
                    $user_id = $conn->lastInsertId();
                    $insert_specific_role_successful = false;
                    $param_user_id = ':user_id';

                    if ($role == 'admin') {
                        $stmt_role_specific = $conn->prepare("INSERT INTO admin (User_ID) VALUES (" . $param_user_id . ")");
                    } elseif ($role == 'customer') {
                        $stmt_role_specific = $conn->prepare("INSERT INTO musteri (User_ID) VALUES (" . $param_user_id . ")");
                    }
                    
                    if (isset($stmt_role_specific)) {
                        $stmt_role_specific->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
                        $insert_specific_role_successful = $stmt_role_specific->execute();
                    } else {
                        $conn->rollBack();
                        error_log("Register Error: Invalid role detected after validation for User ID: " . $user_id . " and Role: " . $role);
                        $error = "Geçersiz rol nedeniyle kayıt tamamlanamadı.";
                    }

                    if ($insert_specific_role_successful) {
                        $conn->commit();
                        $_SESSION['registration_success'] = "Kayıt başarıyla tamamlandı! Şimdi giriş yapabilirsiniz.";
                        header("Location: login.php");
                        exit();
                    } else {
                        if ($error === "") {
                            $conn->rollBack();
                            error_log("Register Error: Role specific table insertion failed for User ID: " . $user_id . " and Role: " . $role);
                            $error = "Kayıt sırasında bir hata oluştu (rol atama). Lütfen tekrar deneyin.";
                        }
                    }
                } else {
                    $conn->rollBack();
                    error_log("Register Error: User table insertion failed. Email: " . $email);
                    $error = "Kullanıcı kaydı oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Register PDOException: " . $e->getMessage());
            $error = "Kayıt sırasında bir sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.";
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
        .form-container {
            background-color: rgba(255, 255, 255, 0.85);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        h2 {
            margin-bottom: 25px;
            color: #333;
            font-size: 28px;
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
            line-height: 1;
        }
        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: rgb(91, 140, 213);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            margin-top: 10px;
            cursor: pointer;
            font-size: 18px;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        button[type="submit"]:hover {
            background-color: rgb(70, 120, 190);
        }
        .form-link {
            display: block;
            margin-top: 20px;
            color: rgb(91, 140, 213);
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
    <h2>Kayıt Formu</h2>

    <?php if (!empty($error)) : ?>
        <div class="error-message">
            <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="post" id="registerForm" novalidate>
        <div class="form-group">
            <label for="username">Kullanıcı Adı:</label>
            <input type="text" name="username" id="username" value="<?php echo $username_value; ?>" required placeholder="Adınız Soyadınız">
        </div>

        <div class="form-group">
            <label for="email">E-posta Adresi:</label>
            <input type="email" name="email" id="email" value="<?php echo $email_value; ?>" required placeholder="ornek@gmail.com">
        </div>

        <div class="form-group">
            <label for="password">Şifre:</label>
            <input type="password" name="password" id="password" required placeholder="En az 8 karakter">
        </div>

        <div class="form-group">
            <label for="password_confirm">Şifre Tekrar:</label>
            <input type="password" name="password_confirm" id="password_confirm" required placeholder="Şifrenizi tekrar girin">
        </div>

       <div class="form-group">
           <label for="role">Rol Seçin:</label>
           <select id="role" name="role" required>
                <option value="" disabled <?php if (empty($role_value)) echo 'selected'; ?>>-- Rolünüzü Seçin --</option>
                <option value="customer" <?php if ($role_value == 'customer') echo 'selected'; ?>>Müşteri</option>
                <option value="admin" <?php if ($role_value == 'admin') echo 'selected'; ?>>Admin</option>
        </select>
    </div>
    <button type="submit">Kayıt Ol</button>
    </form>
    <a href="login.php" class="form-link">Zaten bir hesabınız var mı? Giriş Yapın</a>
    <a href="seller_register.php" class="form-link">Satıcı olarak mı kayıt olmak istiyorsunuz?</a>
</div>

<script>
    // JavaScript kısmı değişmediği için aynı kalıyor
</script>
</body>
</html>