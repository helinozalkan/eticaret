<?php
// order_manage.php - Satıcı Sipariş Yönetimi Sayfası
session_start();
include('../database.php'); // database.php PDO bağlantısını kuruyor

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id']; // users tablosundaki ID

$orders = []; // Sipariş verilerini tutacak dizi
$error_message = ""; // Hata mesajlarını tutacak değişken
$satici_id = null; // Satıcı ID'sini burada tanımla

// update_order_status.php'den gelen mesajları al
if (isset($_SESSION['order_update_success'])) {
    $success_message = $_SESSION['order_update_success'];
    unset($_SESSION['order_update_success']);
}
// Hata mesajını session'dan alırken, mevcut $error_message'ı ezmemesi için kontrol ekleyelim
if (isset($_SESSION['order_update_error'])) {
    $error_message = empty($error_message) ? $_SESSION['order_update_error'] : $error_message . " | " . $_SESSION['order_update_error'];
    unset($_SESSION['order_update_error']);
}


try {
    // 1. Kullanıcının Satici_ID'sini al
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        $error_message = "Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturduğunuzdan emin olun.";
    } else {
        $satici_id = $satici_data['Satici_ID'];

        // 2. Bu satıcıya ait ürünlerin olduğu siparişleri çek
        // Müşteri adını (username) users tablosundan alacak şekilde sorgu güncellendi.
        $query_orders = "SELECT
                            S.Siparis_ID,
                            S.Siparis_Tarihi,
                            S.Siparis_Durumu,
                            S.Siparis_Tutari,
                            GROUP_CONCAT(DISTINCT U.Urun_Adi SEPARATOR ', ') AS Urun_Adlari,
                            SUM(SU.Miktar) AS Toplam_Urun_Miktari,
                            USR.username AS Musteri_Adi, /* users tablosundan username alındı */
                            M.User_ID AS Musteri_User_ID,
                            S.Teslimat_Adresi
                         FROM
                            Siparis S
                         JOIN
                            SiparisUrun SU ON S.Siparis_ID = SU.Siparis_ID
                         JOIN
                            Urun U ON SU.Urun_ID = U.Urun_ID
                         JOIN
                            Musteri M ON S.Musteri_ID = M.Musteri_ID
                         JOIN
                            users USR ON M.User_ID = USR.id /* Musteri.User_ID ile users.id birleştirildi */
                         WHERE
                            U.Satici_ID = :satici_id
                         GROUP BY S.Siparis_ID
                         ORDER BY
                            S.Siparis_Tarihi DESC";

        $stmt_orders = $conn->prepare($query_orders);
        $stmt_orders->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("order_manage.php: Veritabanı hatası: " . $e->getMessage());
    $error_message = "Siparişler çekilirken bir veritabanı hatası oluştu. Detay: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css"> <!-- Ana CSS dosyanız -->
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
        }
        .navbar-custom {
             background-color: rgb(91, 140, 213);
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
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        .orders-table th, .orders-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        .orders-table th {
            background-color: #e9ecef;
            font-weight: 600;
            color: #495057;
        }
        .orders-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            padding: 0.4em 0.7em;
            border-radius: 0.3rem;
            color: white;
            font-weight: 500;
            font-size: 0.85em;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        /* Veritabanı ENUM'una uygun durumlar */
        .status-beklemede { background-color: #ffc107; color: #212529;} /* Bootstrap warning */
        .status-kargoda { background-color: #0dcaf0; } /* Bootstrap info */
        .status-teslim-edildi { background-color: #198754; } /* Bootstrap success */
        /* Uygulamada kullanılan ama DB ENUM'unda olmayanlar için varsayılan (veya kaldırılmalı) */
        .status-odeme-bekleniyor { background-color: #fd7e14; } /* Turuncu */
        .status-hazirlaniyor { background-color: #6f42c1; } /* Mor */
        .status-iptal-edildi { background-color: #dc3545; } /* Kırmızı */
        .status-iade-edildi { background-color: #6c757d; } /* Gri */


        .update-status-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .update-status-form .form-select {
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            border-radius: 0.25rem;
            flex-grow: 1;
            min-width: 150px;
        }
        .update-status-form .btn-update {
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            background-color: rgb(91, 140, 213);
            border-color: rgb(91, 140, 213);
            color: white;
        }
        .btn-update:hover {
            background-color: rgb(70, 120, 190);
            border-color: rgb(70, 120, 190);
        }
        .no-orders-message {
            text-align: center;
            padding: 40px;
            background-color: #e9ecef;
            border-radius: 10px;
            color: #6c757d;
            font-size: 1.1rem;
        }
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
                <li class="nav-item ps-3"><a class="nav-link" href="seller_manage.php">Mağaza Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="manage_product.php">Ürün Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link active" href="order_manage.php">Sipariş Yönetimi</a></li>
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
    <h1 class="page-title"><i class="bi bi-box-seam-fill me-2"></i>Sipariş Yönetimi</h1>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-custom alert-success-custom alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message) && !isset($success_message)): ?>
        <div class="alert alert-danger alert-custom alert-danger-custom alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <?php if (empty($orders) && empty($error_message)): ?>
        <div class="no-orders-message">
            <i class="bi bi-cart-x fs-1 mb-3 d-block"></i>
            Şu anda yönetilecek herhangi bir sipariş bulunmamaktadır.
        </div>
    <?php elseif (!empty($orders)): ?>
        <div class="table-responsive">
            <table class="table orders-table">
                <thead>
                    <tr>
                        <th>Sipariş ID</th>
                        <th>Müşteri</th>
                        <th>Ürünler</th>
                        <th>Tarih</th>
                        <th>Tutar</th>
                        <th>Durum</th>
                        <th>Durumu Güncelle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['Siparis_ID']) ?></td>
                            <td><?= htmlspecialchars($order['Musteri_Adi']) ?></td>
                            <td><?= htmlspecialchars($order['Urun_Adlari']) ?> (x<?= htmlspecialchars($order['Toplam_Urun_Miktari']) ?>)</td>
                            <td><?= htmlspecialchars(date("d.m.Y", strtotime($order['Siparis_Tarihi']))) ?></td> {/* Saat bilgisi kaldırıldı (DATE tipi için) */}
                            <td><?= number_format(htmlspecialchars($order['Siparis_Tutari']), 2, ',', '.') ?> TL</td>
                            <td>
                                <?php
                                $status_class = 'status-' . strtolower(str_replace([' ', 'ş', 'ı', 'ö', 'ü', 'ğ', 'ç'], ['-', 's', 'i', 'o', 'u', 'g', 'c'], $order['Siparis_Durumu']));
                                ?>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= htmlspecialchars($order['Siparis_Durumu']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="update_order_status.php" method="post" class="update-status-form">
                                    <input type="hidden" name="siparis_id" value="<?= htmlspecialchars($order['Siparis_ID']) ?>">
                                    <select name="siparis_durumu" class="form-select form-select-sm">
                                        {/* Veritabanı ENUM değerlerine göre seçenekler güncellendi */}
                                        <option value="Beklemede" <?= ($order['Siparis_Durumu'] === 'Beklemede') ? 'selected' : '' ?>>Beklemede</option>
                                        <option value="Kargoda" <?= ($order['Siparis_Durumu'] === 'Kargoda') ? 'selected' : '' ?>>Kargoda</option>
                                        <option value="Teslim Edildi" <?= ($order['Siparis_Durumu'] === 'Teslim Edildi') ? 'selected' : '' ?>>Teslim Edildi</option>
                                        {/* Şimdilik diğer durumlar kaldırıldı, DB ENUM'a eklenince açılabilir.
                                        <option value="Ödeme Bekleniyor" <?= ($order['Siparis_Durumu'] === 'Ödeme Bekleniyor') ? 'selected' : '' ?>>Ödeme Bekleniyor</option>
                                        <option value="Hazırlanıyor" <?= ($order['Siparis_Durumu'] === 'Hazırlanıyor') ? 'selected' : '' ?>>Hazırlanıyor</option>
                                        <option value="İptal Edildi" <?= ($order['Siparis_Durumu'] === 'İptal Edildi') ? 'selected' : '' ?>>İptal Edildi</option>
                                        <option value="İade Edildi" <?= ($order['Siparis_Durumu'] === 'İade Edildi') ? 'selected' : '' ?>>İade Edildi</option>
                                        */}
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-update"><i class="bi bi-arrow-repeat"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
