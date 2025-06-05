<?php
//admin panel sayfası
session_start();
include('../database.php');

// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null; // Session'daki kullanıcı adı

try {
    // Tüm kullanıcıları listele
    $stmt_users = $conn->prepare("SELECT id, username, email, role FROM users ORDER BY id DESC"); // Son eklenenler üste gelsin
    $stmt_users->execute();
    $all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("admin_user.php - Veritabanı hatası: " . $e->getMessage());
    $all_users = [];
    $error_message = "Kullanıcılar listelenirken bir veritabanı hatası oluştu.";
}

// Diğer sayfalardan gelen durum mesajlarını al
if (isset($_SESSION['user_action_success'])) {
    $success_message = $_SESSION['user_action_success'];
    unset($_SESSION['user_action_success']);
}
if (isset($_SESSION['user_action_error'])) {
    $error_message = isset($error_message) ? $error_message . " | " . $_SESSION['user_action_error'] : $_SESSION['user_action_error'];
    unset($_SESSION['user_action_error']);
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Kullanıcı Yönetimi</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css"> <!-- ../css/css.css olarak düzeltildi -->
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-admin {
            background-color: rgb(34, 132, 17); /* Yeşil renk */
        }
        .main-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 35px;
            font-size: 2.5rem;
            font-weight: 700;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        .users-table th, .users-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .users-table th {
            background-color: #e9ecef;
            font-weight: 600;
            color: #495057;
        }
        .users-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .btn-action {
            padding: 0.35rem 0.7rem; /* Biraz daha büyük butonlar */
            font-size: 0.9rem;
            margin-right: 6px;
            border-radius: 0.25rem; /* Standart Bootstrap radius */
        }
        .btn-edit { background-color: #ffc107; border-color: #ffc107; color: #212529;}
        .btn-edit:hover { background-color: #e0a800; border-color: #d39e00;}
        .btn-delete { background-color: #dc3545; border-color: #dc3545; color:white;}
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130;}

        .status-badge {
            padding: 0.4em 0.7em;
            font-size: 0.85em;
            font-weight: 500;
            border-radius: 0.3rem;
            display: inline-block;
            min-width: 90px; /* Biraz daha geniş */
            text-align: center;
        }
        .role-customer { background-color: #cfe2ff; color: #052c65; } /* Mavi tonu */
        .role-seller { background-color: #d2f4ea; color: #0a3622; } /* Yeşil tonu */
        .role-admin { background-color: #fdebd0; color: #593202; } /* Turuncu/Sarı tonu */

        .status-aktif { background-color: #d1e7dd; color: #0f5132; }
        .status-pasif { background-color: #f8d7da; color: #842029; }
        .status-bilgi-yok { background-color: #e2e3e5; color: #495057; }
        .status-satici-bilgisi-eksik { background-color: #fff3cd; color: #664d03;}

        .alert-custom {
            border-left-width: 5px;
            border-radius: 6px;
            padding: 0.9rem 1.1rem;
            font-size: 0.95rem;
        }
        .alert-danger-custom { border-left-color: #dc3545; }
        .alert-success-custom { border-left-color: #198754; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="../index.php">
            <div class="baslik fs-3"> Admin Paneli</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
            <li class="nav-item ps-3">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Kontrol Paneli
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a class="nav-link active" href="admin_user.php">
                        <i class="bi bi-people-fill me-1"></i>Kullanıcı Yönetimi
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a class="nav-link" href="seller_verification.php">
                        <i class="bi bi-patch-check-fill me-1"></i>Satıcı Doğrulama
                    </a>
                </li>
                <!-- Ürün Doğrulama linki kaldırıldı -->
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
    <h1 class="page-title"><i class="bi bi-person-lines-fill me-2"></i>Kullanıcı Yönetim Sistemi</h1>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-custom alert-success-custom alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($error_message) && !isset($success_message)): ?>
        <div class="alert alert-danger alert-custom alert-danger-custom alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kullanıcı Adı</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Durum</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_users)): ?>
                <?php foreach ($all_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <?php
                            $role_text = ucfirst(htmlspecialchars($user['role']));
                            $role_class = 'role-' . htmlspecialchars(strtolower($user['role']));
                        ?>
                        <span class="status-badge <?= $role_class ?>"><?= $role_text ?></span>
                    </td>
                    <td>
                        <?php
                        // admin_dashboard.php'deki durum belirleme mantığı buraya da eklendi.
                        $user_status = 'Bilgi Yok';
                        $status_class = 'status-bilgi-yok';
                        try {
                            if ($user['role'] === 'seller') {
                                $stmt_seller_status = $conn->prepare("SELECT HesapDurumu FROM Satici WHERE User_ID = :user_id");
                                $stmt_seller_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                                $stmt_seller_status->execute();
                                $seller_data = $stmt_seller_status->fetch(PDO::FETCH_ASSOC);
                                if ($seller_data) {
                                    if ($seller_data['HesapDurumu'] == 1) {
                                        $user_status = 'Aktif'; $status_class = 'status-aktif';
                                    } else {
                                        $user_status = 'Pasif/Doğrulanmamış'; $status_class = 'status-pasif';
                                    }
                                } else {
                                    $user_status = 'Satıcı Kaydı Yok'; $status_class = 'status-satici-bilgisi-eksik';
                                }
                            } elseif ($user['role'] === 'customer') {
                                $stmt_customer_status = $conn->prepare("SELECT Uyelik_Durumu FROM Musteri WHERE User_ID = :user_id");
                                $stmt_customer_status->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
                                $stmt_customer_status->execute();
                                $customer_data = $stmt_customer_status->fetch(PDO::FETCH_ASSOC);
                                if ($customer_data) {
                                    if($customer_data['Uyelik_Durumu'] == 1){
                                        $user_status = 'Aktif'; $status_class = 'status-aktif';
                                    } else {
                                        $user_status = 'Pasif'; $status_class = 'status-pasif';
                                    }
                                } else {
                                     $user_status = 'Müşteri Kaydı Yok'; $status_class = 'status-satici-bilgisi-eksik'; // Benzer stil
                                }
                            } elseif ($user['role'] === 'admin') {
                                $user_status = 'Aktif'; $status_class = 'status-aktif';
                            }
                        } catch (PDOException $e) {
                            error_log("admin_user.php - Kullanıcı durumu çekilirken hata (ID: ".$user['id']."): " . $e->getMessage());
                            $user_status = 'Hata'; $status_class = 'status-pasif';
                        }
                        echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($user_status) . '</span>';
                        ?>
                    </td>
                    <td>
                        <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-warning btn-action" title="Düzenle"><i class="bi bi-pencil-square"></i></a>
                        <?php if ($_SESSION['user_id'] != $user['id']): // Adminin kendi kendini silmesini engelle ?>
                        <a href="delete_user.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-danger btn-action" title="Sil" onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')"><i class="bi bi-trash3-fill"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <i class="bi bi-person-x fs-3 d-block mb-2"></i>
                        Sistemde kayıtlı kullanıcı bulunmamaktadır.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mesaj kutularını kapatma işlevi
    document.addEventListener("DOMContentLoaded", function() {
        var closeBtns = document.querySelectorAll(".alert .btn-close");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.closest('.alert').style.display = 'none';
            });
        });
    });
</script>
</body>
</html>
