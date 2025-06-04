<?php
// seller_manage.php - Satıcı Mağaza Yönetimi (İstatistikler, Grafik VE Profil Yönetimi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once '../database.php'; // Veritabanı bağlantısı

// Özel İstisna Sınıfı
class SellerManageException extends Exception {}

// PDO Parametre Sabitleri
define('PARAM_USER_ID_SM', ':user_id');
define('PARAM_SATICI_ID_SM', ':satici_id');
define('PARAM_MAGAZA_ADI_SM', ':magaza_adi');
define('PARAM_ADRES_SM', ':adres');
define('PARAM_AD_SOYAD_SM', ':ad_soyad');
define('PARAM_EMAIL_SM', ':email');
define('PARAM_PASSWORD_SM', ':password');

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null; // Session'daki kullanıcı adı

// Satıcı yetki kontrolü
if (!$logged_in || !isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id']; // Bu, users.id'dir
$satici_id = null; // Bu, Satici.Satici_ID'dir

// Profil güncelleme mesajları için session değişkenlerini kontrol et ve temizle
$profile_update_error = $_SESSION['profile_update_error'] ?? '';
$profile_update_success = $_SESSION['profile_update_success'] ?? '';
unset($_SESSION['profile_update_error'], $_SESSION['profile_update_success']);

// Satıcı ID'sini ve mevcut profil bilgilerini al
$current_store_name = '';
$current_store_address = '';
$current_owner_name = '';
$current_email = '';

try {
    $stmt_satici_info = $conn->prepare(
        "SELECT s.Satici_ID, s.Magaza_Adi, s.Adres, s.Ad_Soyad, u.email
         FROM Satici s
         JOIN users u ON s.User_ID = u.id
         WHERE s.User_ID = " . PARAM_USER_ID_SM
    );
    $stmt_satici_info->bindParam(PARAM_USER_ID_SM, $seller_user_id, PDO::PARAM_INT);
    $stmt_satici_info->execute();
    $satici_profile_data = $stmt_satici_info->fetch(PDO::FETCH_ASSOC);

    if (!$satici_profile_data) {
        throw new SellerManageException("Satıcı profili bulunamadı. Lütfen bir satıcı hesabı oluşturduğunuzdan emin olun veya destek ile iletişime geçin.");
    }
    $satici_id = $satici_profile_data['Satici_ID'];
    $current_store_name = $satici_profile_data['Magaza_Adi'];
    $current_store_address = $satici_profile_data['Adres'];
    $current_owner_name = $satici_profile_data['Ad_Soyad'];
    $current_email = $satici_profile_data['email'];

} catch (PDOException $e) {
    error_log("seller_manage.php: Satıcı profil bilgileri alınırken PDOException: " . $e->getMessage());
    $profile_update_error = "Profil bilgileri yüklenirken bir veritabanı hatası oluştu.";
    // Bu durumda sayfanın devam etmesi anlamsız olabilir, kritik bir hata.
} catch (SellerManageException $e) {
    error_log("seller_manage.php: Satıcı profil bilgileri alınırken SellerManageException: " . $e->getMessage());
    $profile_update_error = $e->getMessage();
}


// Profil Güncelleme Formu İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // CSRF Token Kontrolü (Formunuza CSRF token eklemelisiniz)
    // if (!isset($_POST['csrf_token_profile']) || !hash_equals($_SESSION['csrf_token_profile'], $_POST['csrf_token_profile'])) {
    //     $_SESSION['profile_update_error'] = "Geçersiz istek. Lütfen tekrar deneyin.";
    //     header("Location: seller_manage.php"); exit();
    // }

    $new_store_name = trim($_POST['store_name'] ?? '');
    $new_store_address = trim($_POST['store_address'] ?? '');
    $new_owner_name = trim($_POST['owner_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $current_password_for_change = $_POST['current_password_for_security'] ?? ''; // İsim değiştirildi

    // Validasyonlar
    if (empty($new_store_name) || empty($new_store_address) || empty($new_owner_name) || empty($new_email)) {
        $_SESSION['profile_update_error'] = "Mağaza adı, adres, sahip adı ve e-posta boş bırakılamaz.";
    } elseif (mb_strlen($new_store_name) > 100 || mb_strlen($new_owner_name) > 100 || mb_strlen($new_store_address) > 255) {
        $_SESSION['profile_update_error'] = "Alanlardan biri çok uzun. Lütfen karakter sınırlarına uyun.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_update_error'] = "Geçersiz e-posta formatı.";
    } elseif (!empty($new_password) && strlen($new_password) < 8) {
        $_SESSION['profile_update_error'] = "Yeni şifre en az 8 karakter olmalıdır.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $_SESSION['profile_update_error'] = "Yeni şifreler eşleşmiyor.";
    } else {
        try {
            $email_changed = ($new_email !== $current_email);
            $password_changed = !empty($new_password);

            if ($email_changed || $password_changed) {
                if (empty($current_password_for_change)) {
                    throw new SellerManageException("E-posta veya şifrenizi değiştirmek için mevcut şifrenizi girmelisiniz.");
                }
                // Mevcut şifreyi doğrula
                $stmt_user_pass = $conn->prepare("SELECT password FROM users WHERE id = " . PARAM_USER_ID_SM);
                $stmt_user_pass->bindParam(PARAM_USER_ID_SM, $seller_user_id, PDO::PARAM_INT);
                $stmt_user_pass->execute();
                $user_pass_data = $stmt_user_pass->fetch(PDO::FETCH_ASSOC);
                if (!$user_pass_data || !password_verify($current_password_for_change, $user_pass_data['password'])) {
                    throw new SellerManageException("Mevcut şifreniz yanlış. E-posta veya şifre değişikliği yapılamadı.");
                }
            }

            $conn->beginTransaction();
            $satici_updated = false;
            $users_updated = false;

            // Satici tablosunu güncelle (Mağaza Adı, Adres, Ad_Soyad)
            if ($new_store_name !== $current_store_name || $new_store_address !== $current_store_address || $new_owner_name !== $current_owner_name) {
                $stmt_update_satici = $conn->prepare(
                    "UPDATE Satici SET Magaza_Adi = " . PARAM_MAGAZA_ADI_SM . ", Adres = " . PARAM_ADRES_SM . ", Ad_Soyad = " . PARAM_AD_SOYAD_SM .
                    " WHERE Satici_ID = " . PARAM_SATICI_ID_SM
                );
                $stmt_update_satici->bindParam(PARAM_MAGAZA_ADI_SM, $new_store_name, PDO::PARAM_STR);
                $stmt_update_satici->bindParam(PARAM_ADRES_SM, $new_store_address, PDO::PARAM_STR);
                $stmt_update_satici->bindParam(PARAM_AD_SOYAD_SM, $new_owner_name, PDO::PARAM_STR);
                $stmt_update_satici->bindParam(PARAM_SATICI_ID_SM, $satici_id, PDO::PARAM_INT);
                $stmt_update_satici->execute();
                $satici_updated = $stmt_update_satici->rowCount() > 0;
            }

            // Users tablosunu güncelle (email, password)
            $users_update_sql_parts = [];
            $users_bind_params = [];

            if ($email_changed) {
                // Yeni e-postanın benzersizliğini kontrol et
                $stmt_check_new_email = $conn->prepare("SELECT id FROM users WHERE email = " . PARAM_EMAIL_SM . " AND id != " . PARAM_USER_ID_SM);
                $stmt_check_new_email->bindParam(PARAM_EMAIL_SM, $new_email, PDO::PARAM_STR);
                $stmt_check_new_email->bindParam(PARAM_USER_ID_SM, $seller_user_id, PDO::PARAM_INT);
                $stmt_check_new_email->execute();
                if ($stmt_check_new_email->rowCount() > 0) {
                    throw new SellerManageException("Girdiğiniz yeni e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.");
                }
                $users_update_sql_parts[] = "email = " . PARAM_EMAIL_SM;
                $users_bind_params[PARAM_EMAIL_SM] = $new_email;
            }

            if ($password_changed) {
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $users_update_sql_parts[] = "password = " . PARAM_PASSWORD_SM;
                $users_bind_params[PARAM_PASSWORD_SM] = $hashed_new_password;
            }

            if (!empty($users_update_sql_parts)) {
                $sql_users_update = "UPDATE users SET " . implode(", ", $users_update_sql_parts) . " WHERE id = " . PARAM_USER_ID_SM;
                $stmt_update_users = $conn->prepare($sql_users_update);
                foreach ($users_bind_params as $key => $value) {
                    $stmt_update_users->bindValue($key, $value, PDO::PARAM_STR); // Şifre de string olarak bağlanır
                }
                $stmt_update_users->bindParam(PARAM_USER_ID_SM, $seller_user_id, PDO::PARAM_INT);
                $stmt_update_users->execute();
                $users_updated = $stmt_update_users->rowCount() > 0;
            }

            if ($satici_updated || $users_updated) {
                $conn->commit();
                $_SESSION['profile_update_success'] = "Profil başarıyla güncellendi.";
                if ($email_changed || $password_changed) {
                    // Güvenlik için e-posta veya şifre değiştiğinde oturumu sonlandırıp tekrar giriş yapmasını isteyebiliriz.
                    // Veya sadece bir uyarı mesajı verebiliriz.
                    $_SESSION['profile_update_success'] .= " E-posta veya şifre değişikliği yaptınız.";
                     // Oturumu sonlandırıp login'e yönlendirme:
                     // unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
                     // session_destroy();
                     // header("Location: login.php?status=profile_updated_relogin");
                     // exit();
                }
            } elseif (!$email_changed && !$password_changed && !$satici_updated && empty($_SESSION['profile_update_error'])) {
                 // Hiçbir şey değişmediyse, yine de bir "değişiklik yok" mesajı verilebilir.
                $_SESSION['profile_update_success'] = "Profilinizde herhangi bir değişiklik yapılmadı.";
            }


        } catch (SellerManageException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $_SESSION['profile_update_error'] = $e->getMessage();
        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("seller_manage.php Profile Update PDOException: " . $e->getMessage());
            $_SESSION['profile_update_error'] = "Profil güncellenirken bir veritabanı hatası oluştu.";
        }
    }
    // Sayfayı yeniden yönlendirerek POST verilerinin tekrar gönderilmesini engelle
    header("Location: seller_manage.php");
    exit();
}


// Ürün İstatistikleri (Mevcut kodunuz)
$products = [];
$total_products = 0;
$active_products = 0;
$removed_products = 0;
$stats_message = ''; // İstatistikler için ayrı mesaj

if ($satici_id) { // $satici_id doluysa istatistikleri çek
    try {
        $query_products = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Aktiflik_Durumu FROM Urun WHERE Satici_ID = " . PARAM_SATICI_ID_SM;
        $stmt_products = $conn->prepare($query_products);
        $stmt_products->bindParam(PARAM_SATICI_ID_SM, $satici_id, PDO::PARAM_INT);
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

        $total_products = count($products);
        foreach ($products as $product) {
            if ($product['Aktiflik_Durumu'] == 1) {
                $active_products++;
            } else {
                $removed_products++;
            }
        }
    } catch (PDOException $e) {
        error_log("seller_manage.php: Ürün istatistikleri çekilirken PDOException: " . $e->getMessage());
        $stats_message = "Ürün istatistikleri yüklenirken bir sorun oluştu.";
    }
} elseif (empty($profile_update_error) && empty($stats_message)) { // $satici_id yoksa ve başka bir hata da yoksa
     $stats_message = "Satıcıya ait ürün bulunamadı veya satıcı profili henüz tamamlanmamış.";
}

// Formda gösterilecek güncel değerler (POST sonrası veya ilk yükleme)
$form_store_name = htmlspecialchars($new_store_name ?? $current_store_name);
$form_store_address = htmlspecialchars($new_store_address ?? $current_store_address);
$form_owner_name = htmlspecialchars($new_owner_name ?? $current_owner_name);
$form_email = htmlspecialchars($new_email ?? $current_email);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Mağaza Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css"> <!-- Ana CSS dosyanız -->
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f4f9;
        }
        .navbar { background-color: #5b8cd5; }
        .navbar-brand .baslik { font-family: 'Playfair Display', serif; }
        .container.main-container { /* Ana içerik container'ı için yeni bir class */
            width: 90%; /* Daha geniş */
            max-width: 1200px; /* Maksimum genişlik */
            margin: 20px auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h1, h2 { text-align: center; margin-bottom: 30px; color: #333; }
        .card {
            transition: transform 0.2s ease-in-out;
            border-radius: 8px;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .chart-container {
            width: 100%;
            max-width: 700px; /* Grafik için maksimum genişlik */
            height: auto; /* Yüksekliği otomatik ayarla */
            margin: 40px auto; /* Ortala */
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .profile-form-section {
            background-color: #fdfdff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .form-label { font-weight: 600; }
        .btn-update-profile {
            background-color: #5b8cd5;
            border-color: #5b8cd5;
            color:white;
        }
        .btn-update-profile:hover {
            background-color: #4a75b3;
            border-color: #4a75b3;
        }
         /* Mesaj kutuları için stiller (mevcut css.css dosyanızda olabilir) */
        .message-container { padding: 10px; border-radius: 5px; margin-bottom: 15px; position: relative; text-align: left; font-size: 14px; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info-message { background-color: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; }
        .close-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3">ELEMEK</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3"><a class="nav-link" href="seller_dashboard.php">Satıcı Paneli</a></li>
                <li class="nav-item ps-3"><a class="nav-link active" href="seller_manage.php">Mağaza Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="manage_product.php">Ürün Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="order_manage.php">Sipariş Yönetimi</a></li>
            </ul>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4 me-2"></i>
                <?php if ($logged_in): ?>
                    <a href="logout.php" class="text-white" style="font-size: 15px; text-decoration: none;"><?php echo $username_session; ?> (Çıkış Yap)</a>
                <?php else: ?>
                    <a href="login.php" class="text-white" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container main-container mt-4">
    <h1>Satıcı Mağaza Yönetimi</h1>

    <!-- Ürün İstatistikleri Bölümü -->
    <h2 class="mt-5">Ürün İstatistikleri</h2>
    <?php if (!empty($stats_message)): ?>
        <div class="alert alert-info info-message"><?php echo htmlspecialchars($stats_message); ?></div>
    <?php endif; ?>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-header">Toplam Ürün</div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <h5 class="card-title display-4"><?= $total_products ?></h5>
                    <p class="card-text">Sistemde kayıtlı toplam ürün sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-success h-100">
                <div class="card-header">Aktif Ürünler</div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <h5 class="card-title display-4"><?= $active_products ?></h5>
                    <p class="card-text">Satışta olan aktif ürün sayısı.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card text-white bg-danger h-100">
                <div class="card-header">Pasif Ürünler</div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                    <h5 class="card-title display-4"><?= $removed_products ?></h5>
                    <p class="card-text">Satışta olmayan pasif ürün sayısı.</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($total_products > 0): // Sadece ürün varsa grafiği göster ?>
    <div class="chart-container">
        <canvas id="productChart"></canvas>
    </div>
    <?php endif; ?>

    <hr class="my-5">

    <!-- Mağaza Profili Yönetimi Bölümü -->
    <div class="row justify-content-center">
        <div class="col-lg-9 col-md-11">
            <div class="profile-form-section">
                <h2 class="mb-4">Mağaza Profili Bilgileri</h2>

                <?php if (!empty($profile_update_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show error-message" role="alert">
                        <?php echo htmlspecialchars($profile_update_error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($profile_update_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show success-message" role="alert">
                        <?php echo htmlspecialchars($profile_update_success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="seller_manage.php" method="POST" id="profileUpdateForm" class="needs-validation" novalidate>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="mb-3">
                        <label for="store_name" class="form-label">Mağaza Adı:</label>
                        <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo $form_store_name; ?>" required maxlength="100">
                        <div class="invalid-feedback">Lütfen mağaza adını girin.</div>
                    </div>

                    <div class="mb-3">
                        <label for="owner_name" class="form-label">Mağaza Sahibi Adı Soyadı:</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo $form_owner_name; ?>" required maxlength="100">
                        <div class="invalid-feedback">Lütfen sahip adını ve soyadını girin.</div>
                    </div>

                    <div class="mb-3">
                        <label for="store_address" class="form-label">Mağaza Adresi:</label>
                        <textarea class="form-control" id="store_address" name="store_address" rows="3" required maxlength="255"><?php echo $form_store_address; ?></textarea>
                        <div class="invalid-feedback">Lütfen mağaza adresini girin.</div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Giriş Bilgileri (Değiştirmek İsterseniz)</h5>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta Adresi:</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $form_email; ?>" required>
                        <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                    </div>
                    
                    <div class="alert alert-warning p-2 small">
                        <i class="bi bi-exclamation-triangle-fill"></i> E-posta veya şifrenizi değiştirmek için <strong>Mevcut Şifrenizi</strong> girmeniz gerekmektedir.
                    </div>
                    <div class="mb-3">
                        <label for="current_password_for_security" class="form-label">Mevcut Şifreniz:</label>
                        <input type="password" class="form-control" id="current_password_for_security" name="current_password_for_security">
                        <div class="form-text">E-posta veya yeni şifre alanlarını doldurduysanız bu alanı girmeniz zorunludur.</div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">Yeni Şifre (Değiştirmek istemiyorsanız boş bırakın):</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8">
                        <div class="invalid-feedback">Yeni şifre en az 8 karakter olmalıdır.</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Yeni Şifre Tekrar:</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        <div class="invalid-feedback" id="confirmPasswordFeedback">Yeni şifreler eşleşmiyor.</div>
                    </div>

                    <button type="submit" class="btn btn-update-profile w-100 mt-3">Profili Güncelle</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Mesaj kutularını kapatma işlevi
        var closeBtns = document.querySelectorAll(".message-container .close-btn, .alert .btn-close");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.closest('.alert, .message-container').style.display = 'none';
            });
        });

        // Ürün Grafiği
        <?php if ($total_products > 0 && $satici_id): ?>
        const ctx = document.getElementById('productChart').getContext('2d');
        const productChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Toplam Ürün', 'Aktif Ürünler', 'Pasif Ürünler'],
                datasets: [{
                    label: 'Ürün Sayısı',
                    data: [<?= $total_products ?>, <?= $active_products ?>, <?= $removed_products ?>],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1,
                    borderRadius: 5,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Mağaza Ürün Dağılımı', font: { size: 18 } },
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
        <?php endif; ?>

        // Bootstrap form validasyonunu etkinleştir
        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPasswordInput = document.getElementById('confirm_password');
                const confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');

                if (newPassword !== '' && newPassword !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Yeni şifreler eşleşmiyor.');
                    confirmPasswordFeedback.style.display = 'block'; // Hata mesajını göster
                } else {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordFeedback.style.display = 'none'; // Hata mesajını gizle
                }

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    });
</script>
</body>
</html>
