<?php
// register.php - Kullanıcı kayıt sayfası
session_start(); // Oturumu başlat

// Eğer kullanıcı zaten giriş yapmışsa, ana sayfaya veya paneline yönlendir
if (isset($_SESSION['user_id'])) {
    // Rolüne göre uygun sayfaya yönlendirme yapılabilir
    header("Location: /eticaret/index.php"); // Veya dashboard
    exit();
}

include_once '../database.php'; // Veritabanı bağlantısı

$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken

// Formdan gelen değerleri tutmak için değişkenler (hata durumunda formu tekrar doldurmak için)
$username_value = "";
$email_value = "";
$role_value = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Token Kontrolü (ÖNEMLİ: Formunuza CSRF token eklemelisiniz)
    // if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    //     $error = "Geçersiz kayıt isteği. Lütfen sayfayı yenileyip tekrar deneyin.";
    //     // exit('CSRF token hatası!');
    // } else {

        // Gelen verileri al ve temizle
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? ''; // Şifre tekrarı alanı
        $role = trim($_POST['role'] ?? '');

        // Hata durumunda form alanlarını doldurmak için değerleri sakla
        $username_value = htmlspecialchars($username);
        $email_value = htmlspecialchars($email);
        $role_value = htmlspecialchars($role);

        // 1. Sunucu Tarafı Kapsamlı Form Validasyonu
        if (empty($username) || empty($email) || empty($password) || empty($password_confirm) || empty($role)) {
            $error = "Tüm alanların doldurulması zorunludur.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçersiz e-posta formatı. Lütfen doğru bir e-posta adresi girin (örn: kullanici@ornek.com).";
        } elseif (strlen($password) < 8) { // Daha güçlü şifre politikası örneği
            $error = "Şifre en az 8 karakter uzunluğunda olmalıdır.";
        } elseif ($password !== $password_confirm) {
            $error = "Girilen şifreler birbiriyle eşleşmiyor.";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            // Şifre karmaşıklığı kontrolü (en az bir harf ve bir rakam) - isteğe bağlı
            // $error = "Şifre en az bir harf ve bir rakam içermelidir.";
        } elseif (!in_array($role, ['customer', 'admin'])) { // SATICI ROLÜ BURADAN KALDIRILDI
            $error = "Geçersiz rol seçimi yapıldı.";
        } else {
            try {
                $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email");
                $stmt_check_email->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt_check_email->execute();

                if ($stmt_check_email->rowCount() > 0) {
                    $error = "Bu e-posta adresi zaten kayıtlı. Lütfen farklı bir e-posta deneyin veya giriş yapın.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Güçlü hash algoritması

                    $conn->beginTransaction(); // Veritabanı işlemlerini transaction ile yönet

                    $stmt_insert_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)");
                    $stmt_insert_user->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt_insert_user->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt_insert_user->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                    $stmt_insert_user->bindParam(':role', $role, PDO::PARAM_STR);

                    if ($stmt_insert_user->execute()) {
                        $user_id = $conn->lastInsertId();
                        $insert_specific_role_successful = false;
                        $param_user_id = ':user_id'; // Tekrar eden string için değişken

                        if ($role == 'admin') {
                            $stmt_role_specific = $conn->prepare("INSERT INTO admin (User_ID) VALUES (".$param_user_id.")");
                        } elseif ($role == 'customer') {
                            $stmt_role_specific = $conn->prepare("INSERT INTO musteri (User_ID) VALUES (".$param_user_id.")");
                        }
                        // elseif ($role == 'seller') { // BU BLOK ARTIK BU FORM ÜZERİNDEN ÇAĞRILMAYACAK
                        //     $store_name_default = "Yeni Mağaza - " . $username;
                        //     $ad_soyad_default = $username;
                        //     $hesap_durumu_default = 0;
                        //     $stmt_role_specific = $conn->prepare("INSERT INTO satici (User_ID, Magaza_Adi, Ad_Soyad, Eposta, HesapDurumu)
                        //                                           VALUES (:user_id, :magaza_adi, :ad_soyad, :eposta, :hesap_durumu)");
                        //     $stmt_role_specific->bindParam(':magaza_adi', $store_name_default, PDO::PARAM_STR);
                        //     $stmt_role_specific->bindParam(':ad_soyad', $ad_soyad_default, PDO::PARAM_STR);
                        //     $stmt_role_specific->bindParam(':eposta', $email, PDO::PARAM_STR);
                        //     $stmt_role_specific->bindParam(':hesap_durumu', $hesap_durumu_default, PDO::PARAM_INT);
                        // }

                        if (isset($stmt_role_specific)) {
                            $stmt_role_specific->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
                            $insert_specific_role_successful = $stmt_role_specific->execute();
                        } else {
                            // Eğer rol admin veya customer değilse (ki validasyon bunu engellemeli)
                            // Bu durumun oluşmaması gerekir, ama bir güvenlik katmanı olarak eklenebilir.
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
                            // $insert_specific_role_successful false ise veya $stmt_role_specific hiç set edilmemişse (yukarıdaki ek else bloğu)
                            if ($error === "") { // Eğer daha önce bir hata set edilmemişse
                                $conn->rollBack(); // $stmt_role_specific->execute() başarısız olduysa veya hiç çalışmadıysa
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
                if ($conn->inTransaction()){
                    $conn->rollBack();
                }
                error_log("Register PDOException: " . $e->getMessage());
                $error = "Kayıt sırasında bir sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.";
            } catch (Exception $e) { 
                if ($conn->inTransaction()){
                    $conn->rollBack();
                }
                error_log("Register Exception: " . $e->getMessage());
                $error = "Beklenmedik bir hata oluştu. Lütfen sistem yöneticisi ile iletişime geçin.";
            }
        }
    // } // CSRF token else bloğu sonu
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
        /* CSS stilleri önceki yanıttaki gibidir, değişiklik yok */
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
        select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007CB2%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
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
            background-color: rgb(91, 140, 213);
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
    <a href="seller_register.php" class="form-link">Satıcı olarak mı kayıt olmak istiyorsunuz?</a> </div>

<script>
    // JavaScript kodu önceki yanıttaki gibidir
    const registerForm = document.getElementById('registerForm');
    registerForm.addEventListener('submit', function (event) {
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        const role = document.getElementById('role').value;
        let messages = [];

        if (username === '') messages.push('Kullanıcı adı boş bırakılamaz.');
        if (email === '') {
            messages.push('E-posta adresi boş bırakılamaz.');
        } else {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                messages.push('Lütfen geçerli bir e-posta adresi girin.');
            }
        }
        if (password === '') {
            messages.push('Şifre boş bırakılamaz.');
        } else if (password.length < 8) {
            messages.push('Şifre en az 8 karakter olmalıdır.');
        }
        if (passwordConfirm === '') {
            messages.push('Şifre tekrarı boş bırakılamaz.');
        } else if (password !== passwordConfirm) {
            messages.push('Şifreler eşleşmiyor.');
        }
        if (role === '') messages.push('Lütfen bir rol seçin.');
        // Client-side'da da rol validasyonunu güncelleyelim
        else if (role !== 'customer' && role !== 'admin') {
             messages.push('Geçersiz rol seçimi.');
        }


        if (messages.length > 0) {
            event.preventDefault();
            let errorDiv = document.querySelector('.error-message');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                registerForm.insertBefore(errorDiv, registerForm.firstChild);
            }
            errorDiv.innerHTML = '<span class="close-btn" onclick="this.parentElement.style.display=\'none\';">&times;</span>' + messages.join('<br>');
            errorDiv.style.display = 'block';
        }
    });

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
