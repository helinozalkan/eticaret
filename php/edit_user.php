<?php
// edit_user.php - Admin paneli kullanıcı düzenleme sayfası

session_start(); // Oturumu başlat

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$user = null; // Kullanıcı verilerini tutacak değişken
$error = ""; // Hata mesajlarını tutmak için değişken
$success = ""; // Başarı mesajlarını tutmak için değişken
$redirect_page = 'admin_user.php'; // Tekrar eden yönlendirme adresini bir değişkene atadık.

// GET ile gelen kullanıcı ID'sini al ve doğrula
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($user_id === false || $user_id <= 0) {
    error_log("edit_user.php: Geçersiz kullanıcı ID'si: " . ($user_id === false ? 'false' : $user_id));
    header("Location: " . $redirect_page . "?status=invalid_user_id");
    exit();
}

// Parametre adı için bir sabit tanımlıyoruz.
$param_user_id = ':user_id';

try {
    // Kullanıcı bilgilerini veritabanından çek
    // Buradan sonraki kodlar aynı kalıyor, çünkü $conn değişkeni doğru şekilde alındı.
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = " . $param_user_id);
    $stmt->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Eğer kullanıcı bulunamazsa
    if (!$user) {
        header("Location: " . $redirect_page . "?status=user_not_found");
        exit();
    }

    // Form POST edildiğinde güncelleme işlemini yap
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_username = trim(htmlspecialchars($_POST['username'] ?? ''));
        $new_email = trim(htmlspecialchars($_POST['email'] ?? ''));
        $new_role = trim(htmlspecialchars($_POST['role'] ?? ''));

        if (empty($new_username) || empty($new_email) || empty($new_role)) {
            $error = "Tüm alanlar doldurulmalıdır.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçersiz e-posta formatı.";
        } elseif (!in_array($new_role, ['customer', 'seller', 'admin'])) {
            $error = "Geçersiz rol seçimi.";
        } else {
            // E-posta adresinin başka bir kullanıcı tarafından kullanılıp kullanılmadığını kontrol et
            $stmt_check_email = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != " . $param_user_id);
            $stmt_check_email->bindParam(':email', $new_email);
            $stmt_check_email->bindParam($param_user_id, $user_id, PDO::PARAM_INT);
            $stmt_check_email->execute();

            if ($stmt_check_email->rowCount() > 0) {
                $error = "Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.";
            } else {
                $stmt_update = $conn->prepare("UPDATE users SET username = :username, email = :email, role = :role WHERE id = " . $param_user_id);
                $stmt_update->bindParam(':username', $new_username);
                $stmt_update->bindParam(':email', $new_email);
                $stmt_update->bindParam(':role', $new_role);
                $stmt_update->bindParam($param_user_id, $user_id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    // Başarılı güncelleme sonrası admin kullanıcı yönetimi sayfasına yönlendir
                    $_SESSION['user_action_success'] = "Kullanıcı (ID: " . htmlspecialchars($user_id) . ") başarıyla güncellendi.";
                    header("Location: " . $redirect_page);
                    exit();
                } else {
                    $error = "Kullanıcı güncellenirken bir hata oluştu. Lütfen tekrar deneyin.";
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("edit_user.php: Veritabanı hatası: " . $e->getMessage());
    $error = "Bir sorun oluştu. Lütfen daha sonra tekrar deneyin.";
}

// Eğer $user hala null ise (hata oluştuysa), boş değerler ata
if (!$user) {
    $user = ['id' => '', 'username' => '', 'email' => '', 'role' => ''];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Düzenle</title>
    <!-- Stil kodları değişmediği için aynı kalıyor -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        h1 {
            color: #333;
            text-align: center;
        }
        form {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            width: 350px;
            box-sizing: border-box;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
        }
        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: rgb(34, 132, 17);
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: rgb(28, 109, 14);
        }
        .error-message, .success-message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            position: relative;
            text-align: left;
            font-size: 14px;
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
    <form action="edit_user.php?id=<?php echo htmlspecialchars($user_id); ?>" method="POST">
        <h1>Kullanıcı Düzenle</h1>
        
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

        <label for="username">Kullanıcı Adı:</label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label for="email">E-posta:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

        <label for="role">Rol:</label>
        <select name="role" id="role" required>
            <option value="customer" <?php echo ($user['role'] == 'customer') ? 'selected' : ''; ?>>Müşteri</option>
            <option value="seller" <?php echo ($user['role'] == 'seller') ? 'selected' : ''; ?>>Satıcı</option>
            <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
        </select>

        <button type="submit">Güncelle</button>
    </form>

    <script>
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
