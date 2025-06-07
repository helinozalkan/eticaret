<?php
// seller_manage.php - Satıcı Mağaza Yönetimi (İstatistikler, Grafik VE Profil Yönetimi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


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
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Satıcı yetki kontrolü
if (!$logged_in || !isset($_SESSION['role']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null;

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
} catch (SellerManageException $e) {
    error_log("seller_manage.php: Satıcı profil bilgileri alınırken SellerManageException: " . $e->getMessage());
    $profile_update_error = $e->getMessage();
}


// Profil Güncelleme Formu İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_store_name = trim($_POST['store_name'] ?? '');
    $new_store_address = trim($_POST['store_address'] ?? '');
    $new_owner_name = trim($_POST['owner_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $current_password_for_change = $_POST['current_password_for_security'] ?? '';

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

            $users_update_sql_parts = [];
            $users_bind_params = [];

            if ($email_changed) {
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
                    $stmt_update_users->bindValue($key, $value, PDO::PARAM_STR);
                }
                $stmt_update_users->bindParam(PARAM_USER_ID_SM, $seller_user_id, PDO::PARAM_INT);
                $stmt_update_users->execute();
                $users_updated = $stmt_update_users->rowCount() > 0;
            }

            if ($satici_updated || $users_updated) {
                $conn->commit();
                $_SESSION['profile_update_success'] = "Profil başarıyla güncellendi.";
                if ($email_changed || $password_changed) {
                    $_SESSION['profile_update_success'] .= " E-posta veya şifre değişikliği yaptınız.";
                }
            } elseif (!$email_changed && !$password_changed && !$satici_updated && empty($_SESSION['profile_update_error'])) {
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
    header("Location: seller_manage.php");
    exit();
}

$products = [];
$total_products = 0;
$active_products = 0;
$removed_products = 0;
$stats_message = '';

if ($satici_id) {
    try {
        $query_products = "SELECT Aktiflik_Durumu FROM Urun WHERE Satici_ID = " . PARAM_SATICI_ID_SM;
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
} elseif (empty($profile_update_error) && empty($stats_message)) {
     $stats_message = "Satıcıya ait ürün bulunamadı veya satıcı profili henüz tamamlanmamış.";
}

// Formun hata durumunda eski değerleri koruması için
$form_store_name = htmlspecialchars($_POST['store_name'] ?? $current_store_name);
$form_store_address = htmlspecialchars($_POST['store_address'] ?? $current_store_address);
$form_owner_name = htmlspecialchars($_POST['owner_name'] ?? $current_owner_name);
$form_email = htmlspecialchars($_POST['email'] ?? $current_email);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satıcı Mağaza Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background-color: rgb(91, 140, 213); }
        .main-container { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-top: 20px; margin-bottom: 20px; }
        .page-title { font-family: 'Playfair Display', serif; color: #2c3e50; text-align: center; margin-bottom: 40px; font-size: 2.5rem; font-weight: 700; }
        .section-title { font-family: 'Playfair Display', serif; color: #34495e; text-align: center; margin-top: 50px; margin-bottom: 30px; font-size: 2rem; font-weight: 600; }
        .stat-card { background: linear-gradient(145deg, #ffffff, #e6e9ed); border: none; border-radius: 10px; padding: 25px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.07); transition: transform 0.3s ease, box-shadow 0.3s ease; height: 100%; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .stat-card .card-header { background-color: transparent; border-bottom: 1px solid #dee2e6; font-size: 1.1rem; font-weight: 600; color: #495057; padding-bottom: 10px; margin-bottom: 15px; }
        .stat-card .card-title { font-size: 3rem; font-weight: 700; color: rgb(91, 140, 213); margin-bottom: 10px; }
        .stat-card .card-text { font-size: 0.95rem; color: #6c757d; }
        .chart-container { width: 100%; max-width: 750px; height: 400px; margin: 40px auto; padding: 25px; background-color: #fff; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
        .profile-form-section { background-color: #fdfdff; padding: 35px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
        .form-label { font-weight: 600; color: #495057; margin-bottom: 0.3rem; }
        .form-control { border-radius: 6px; border: 1px solid #ced4da; padding: 0.6rem 0.9rem; }
        .form-control:focus { border-color: rgb(91, 140, 213); box-shadow: 0 0 0 0.2rem rgba(91, 140, 213, 0.25); }
        .btn-update-profile { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); color: white; padding: 0.7rem 1.5rem; font-size: 1.05rem; font-weight: 600; border-radius: 6px; transition: background-color 0.2s ease, border-color 0.2s ease; }
        .btn-update-profile:hover { background-color: rgb(70, 120, 190); border-color: rgb(70, 120, 190); }
        .alert-custom { border-left-width: 5px; border-radius: 6px; padding: 1rem 1.25rem; }
        .alert-danger-custom { border-left-color: #dc3545; }
        .alert-success-custom { border-left-color: #198754; }
        .alert-info-custom { border-left-color: #0dcaf0; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
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

<div class="container main-container">
    <h1 class="page-title">Mağaza ve Ürün Yönetimi</h1>

    <section id="product-stats">
        <h2 class="section-title">Ürün İstatistikleri</h2>
        <?php if (!empty($stats_message)): ?>
            <div class="alert alert-info alert-custom alert-info-custom" role="alert">
                <?php echo htmlspecialchars($stats_message); ?>
            </div>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="card-header">Toplam Ürün</div>
                    <div class="card-body"><h5 class="card-title"><?= $total_products ?></h5><p class="card-text">Mağazanızdaki toplam ürün sayısı.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="card-header">Aktif Ürünler</div>
                    <div class="card-body"><h5 class="card-title"><?= $active_products ?></h5><p class="card-text">Şu anda satışta olan ürünler.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="card-header">Pasif Ürünler</div>
                    <div class="card-body"><h5 class="card-title"><?= $removed_products ?></h5><p class="card-text">Satışta olmayan veya kaldırılmış ürünler.</p></div>
                </div>
            </div>
        </div>

        <?php if ($total_products > 0): ?>
        <div class="chart-container">
            <canvas id="productChart"></canvas>
        </div>
        <?php endif; ?>
    </section>

    <hr class="my-5">

    <section id="profile-management">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-md-12">
                <div class="profile-form-section">
                    <h2 class="section-title mt-0 mb-4">Mağaza Profil Bilgileri</h2>

                    <?php if (!empty($profile_update_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show alert-custom alert-danger-custom" role="alert">
                            <?php echo htmlspecialchars($profile_update_error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($profile_update_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show alert-custom alert-success-custom" role="alert">
                            <?php echo htmlspecialchars($profile_update_success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="seller_manage.php" method="POST" id="profileUpdateForm" class="needs-validation" novalidate>
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="store_name" class="form-label">Mağaza Adı:</label>
                                <input type="text" class="form-control" id="store_name" name="store_name" value="<?php echo $form_store_name; ?>" required maxlength="100">
                                <div class="invalid-feedback">Lütfen mağaza adını girin.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="owner_name" class="form-label">Mağaza Sahibi Adı Soyadı:</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" value="<?php echo $form_owner_name; ?>" required maxlength="100">
                                <div class="invalid-feedback">Lütfen sahip adını ve soyadını girin.</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="store_address" class="form-label">Mağaza Adresi:</label>
                                <textarea class="form-control" id="store_address" name="store_address" rows="3" required maxlength="255"><?php echo $form_store_address; ?></textarea>
                                <div class="invalid-feedback">Lütfen mağaza adresini girin.</div>
                            </div>
                        </div>
                        <hr class="my-4">
                        <h5 class="mb-3 fw-semibold" style="color: #34495e;">Giriş Bilgileri (Değiştirmek İsterseniz)</h5>
                        <div class="alert alert-warning p-2 small">
                            <i class="bi bi-exclamation-triangle-fill"></i> E-posta veya şifrenizi değiştirmek için <strong>Mevcut Şifrenizi</strong> girmeniz gerekmektedir.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">E-posta Adresi:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $form_email; ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="current_password_for_security" class="form-label">Mevcut Şifreniz:</label>
                                <input type="password" class="form-control" id="current_password_for_security" name="current_password_for_security">
                                <div class="form-text small">E-posta veya yeni şifre alanlarını doldurduysanız bu alanı girmeniz zorunludur.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="new_password" class="form-label">Yeni Şifre:</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                                <div class="invalid-feedback">Yeni şifre en az 8 karakter olmalıdır.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Yeni Şifre Tekrar:</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <div class="invalid-feedback" id="confirmPasswordFeedback">Yeni şifreler eşleşmiyor.</div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                             <button type="submit" class="btn btn-update-profile">Profili Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var closeBtns = document.querySelectorAll(".alert .btn-close");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.closest('.alert').style.display = 'none';
            });
        });

        <?php if ($total_products > 0 && $satici_id): ?>
        const ctx = document.getElementById('productChart').getContext('2d');
        const productChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Aktif Ürünler', 'Pasif Ürünler'],
                datasets: [{
                    label: 'Ürün Durumu',
                    data: [<?= $active_products ?>, <?= $removed_products ?>],
                    backgroundColor: ['rgb(91, 140, 213)', '#fd7e14'],
                    borderColor: ['#ffffff', '#ffffff'],
                    borderWidth: 3,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { display: true, text: 'Mağaza Ürün Dağılımı', font: { size: 18, family: 'Playfair Display', weight: 'bold' }, color: '#34495e', padding: { top: 10, bottom: 20 } },
                    legend: { position: 'bottom', labels: { font: { size: 14, family: 'Montserrat' }, color: '#495057', padding: 20 } },
                }
            }
        });
        <?php endif; ?>

        var forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPasswordInput = document.getElementById('confirm_password');
                const confirmPasswordFeedback = document.getElementById('confirmPasswordFeedback');

                if (newPassword !== '' && newPassword !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity('Yeni şifreler eşleşmiyor.');
                    confirmPasswordFeedback.style.display = 'block';
                } else {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordFeedback.style.display = 'none';
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
