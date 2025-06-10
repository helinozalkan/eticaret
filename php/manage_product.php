<?php
// manage_product.php - Ürün Yönetimi (Ürün Ekleme ve Listeleme)

session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();


// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$satici_id = null;
$message = "";
$message_type = "error";

// Özel istisna sınıfı tanımla
class ProductManageException extends Exception {}

// Parametre sabitleri
$param_satici_id_pm = ':satici_id';
$param_product_id_pm = ':product_id';

// Diğer sayfalardan gelen mesajları al
if (isset($_SESSION['form_success_message'])) {
    $message = $_SESSION['form_success_message'];
    $message_type = "success";
    unset($_SESSION['form_success_message']);
}
if (isset($_SESSION['form_error_message'])) {
    $message = $_SESSION['form_error_message'];
    $message_type = "error";
    unset($_SESSION['form_error_message']);
}
if (isset($_SESSION['form_info_message'])) {
    $message = $_SESSION['form_info_message'];
    $message_type = "info";
    unset($_SESSION['form_info_message']);
}


// Mesaj türüne göre gösterilecek CSS sınıfını burada belirliyoruz.
$alert_class = '';
if (!empty($message)) {
    switch ($message_type) {
        case 'success':
            $alert_class = 'alert-success-custom alert-success';
            break;
        case 'info':
            $alert_class = 'alert-info-custom alert-info';
            break;
        default:
            $alert_class = 'alert-danger-custom alert-danger';
            break;
    }
}


try {
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        throw new ProductManageException("Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturun.");
    }
    $satici_id = $satici_data['Satici_ID'];

    // Ürün silme işlemi (Bu mantık product_action.php'ye taşınmalı, ancak mevcut yapı korunuyor)
    if (isset($_GET['delete'])) {
        $product_id_to_delete = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);

        if ($product_id_to_delete === false || $product_id_to_delete <= 0) {
            throw new ProductManageException("Geçersiz ürün ID'si.");
        }

        $conn->beginTransaction();

        $stmt_get_image = $conn->prepare("SELECT Urun_Gorseli FROM Urun WHERE Urun_ID = " . $param_product_id_pm . " AND Satici_ID = " . $param_satici_id_pm);
        $stmt_get_image->bindParam($param_product_id_pm, $product_id_to_delete, PDO::PARAM_INT);
        $stmt_get_image->bindParam($param_satici_id_pm, $satici_id, PDO::PARAM_INT);
        $stmt_get_image->execute();
        $product_image_data = $stmt_get_image->fetch(PDO::FETCH_ASSOC);

        if (!$product_image_data) {
            $conn->rollBack();
            throw new ProductManageException("Silinecek ürün bulunamadı veya bu ürünü silme yetkiniz yok.");
        }
        $product_image_to_delete_path = $product_image_data['Urun_Gorseli'] ? "../uploads/" . $product_image_data['Urun_Gorseli'] : null;

        $stmt_delete_product = $conn->prepare("DELETE FROM Urun WHERE Urun_ID = " . $param_product_id_pm . " AND Satici_ID = " . $param_satici_id_pm);
        $stmt_delete_product->bindParam($param_product_id_pm, $product_id_to_delete, PDO::PARAM_INT);
        $stmt_delete_product->bindParam($param_satici_id_pm, $satici_id, PDO::PARAM_INT);

        if ($stmt_delete_product->execute() && $stmt_delete_product->rowCount() > 0) {
            if ($product_image_to_delete_path && file_exists($product_image_to_delete_path)) {
                unlink($product_image_to_delete_path);
            }
            $conn->commit();
            $_SESSION['form_success_message'] = "Ürün başarıyla silindi.";
        } else {
            $conn->rollBack();
            $_SESSION['form_error_message'] = "Ürün silinirken bir sorun oluştu veya ürün bulunamadı.";
        }
        header("Location: manage_product.php");
        exit();
    }

} catch (ProductManageException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    $message = htmlspecialchars($e->getMessage());
    $message_type = "error";
    $alert_class = 'alert-danger-custom alert-danger';
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("manage_product.php: PDOException: " . $e->getMessage());
    $message = "Veritabanı işlemi sırasında bir sorun oluştu.";
    $message_type = "error";
    $alert_class = 'alert-danger-custom alert-danger';
}

// Ürünleri listele
$products = [];
if ($satici_id !== null) {
    try {
        // SQL sorgusundan Onay_Durumu kaldırıldı
        $query_products = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Aktiflik_Durumu FROM Urun WHERE Satici_ID = :satici_id ORDER BY Urun_ID DESC";
        $stmt_products = $conn->prepare($query_products);
        $stmt_products->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_products->execute();
        $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("manage_product.php: Ürün listesi çekilirken veritabanı hatası: " . $e->getMessage());
        $message = "Ürünler listelenirken bir sorun oluştu.";
        $message_type = "error";
        $alert_class = 'alert-danger-custom alert-danger';
        $products = [];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background-color: rgb(91, 140, 213); }
        .main-container { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-top: 20px; margin-bottom: 20px; }
        .page-title { font-family: 'Playfair Display', serif; color: #2c3e50; text-align: center; margin-bottom: 30px; font-size: 2.5rem; font-weight: 700; }
        .section-title { font-family: 'Playfair Display', serif; color: #34495e; margin-top: 0; margin-bottom: 25px; font-size: 1.8rem; font-weight: 600; }
        .product-form-section, .product-list-section { background-color: #fdfdff; padding: 30px; border-radius: 10px; box-shadow: 0 6px 20px rgba(0,0,0,0.07); margin-bottom: 40px; }
        .form-label { font-weight: 600; color: #495057; margin-bottom: 0.3rem; }
        .form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.55rem 0.9rem; font-size: 0.95rem; }
        .form-control:focus, .form-select:focus { border-color: rgb(91, 140, 213); box-shadow: 0 0 0 0.2rem rgba(91, 140, 213, 0.25); }
        .form-check-input:checked { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); }
        .form-switch .form-check-input { width: 2.5em; height: 1.25em; margin-top: 0.25em; }
        .form-switch .form-check-label { padding-left: 0.5em; font-size: 1rem; }
        .btn-submit-product { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); color: white; padding: 0.6rem 1.3rem; font-size: 1rem; font-weight: 600; border-radius: 6px; transition: background-color 0.2s ease, border-color 0.2s ease; }
        .btn-submit-product:hover { background-color: rgb(70, 120, 190); border-color: rgb(70, 120, 190); }
        .product-table img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; }
        .table th { font-weight: 600; color: #495057; background-color: #e9ecef; border-bottom-width: 2px; }
        .table td { vertical-align: middle; }
        .btn-action { padding: 0.3rem 0.6rem; font-size: 0.85rem; margin-right: 5px; }
        .btn-edit { background-color: #ffc107; border-color: #ffc107; color: #212529;}
        .btn-edit:hover { background-color: #e0a800; border-color: #d39e00;}
        .btn-delete { background-color: #dc3545; border-color: #dc3545; color:white;}
        .btn-delete:hover { background-color: #c82333; border-color: #bd2130;}
        .alert-custom { border-left-width: 5px; border-radius: 6px; padding: 0.9rem 1.1rem; font-size: 0.95rem; }
        .alert-danger-custom { border-left-color: #dc3545; }
        .alert-success-custom { border-left-color: #198754; }
        .alert-info-custom { border-left-color: #0dcaf0; }
        .status-badge { padding: 0.3em 0.6em; font-size: 0.85em; font-weight: 600; border-radius: 0.25rem; }
        .status-aktif { background-color: #d1e7dd; color: #0f5132; }
        .status-pasif { background-color: #f8d7da; color: #842029; }
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
                <li class="nav-item ps-3"><a class="nav-link active" href="manage_product.php">Ürün Yönetimi</a></li>
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
    <h1 class="page-title">Ürün Yönetim Paneli</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-custom <?php echo $alert_class; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <section class="product-form-section">
        <h2 class="section-title"><i class="bi bi-plus-circle-fill me-2"></i>Yeni Ürün Ekle</h2>
        <form action="product_action.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="add_product" value="1">
            <div class="row g-3">
                <div class="col-md-6 mb-3">
                    <label for="product_name" class="form-label">Ürün Adı:</label>
                    <input type="text" class="form-control" name="product_name" id="product_name" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="product_price" class="form-label">Fiyat (₺):</label>
                    <input type="number" class="form-control" name="product_price" id="product_price" step="0.01" required min="0">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="product_stock" class="form-label">Stok Adedi:</label>
                    <input type="number" class="form-control" name="product_stock" id="product_stock" required min="0">
                </div>
                <div class="col-md-12 mb-3">
                    <label for="product_description" class="form-label">Ürün Açıklaması (En fazla 250 karakter):</label>
                    <textarea class="form-control" name="product_description" id="product_description" rows="3" maxlength="250"></textarea>
                    <div id="charCountDescription" class="form-text text-end">0 / 250</div>
                </div>
                <div class="col-md-12 mb-3">
                    <label for="product_story" class="form-label">Ürün Hikayesi:</label>
                    <textarea class="form-control" name="product_story" id="product_story" rows="4" placeholder="Bu ürünün arkasındaki ilhamı, yapım sürecini veya özel anlamını paylaşın..."></textarea>
                </div>
                <div class="col-md-7 mb-3">
                    <label for="product_image" class="form-label">Ürün Görseli:</label>
                    <input type="file" class="form-control" name="product_image" id="product_image">
                </div>
                 <div class="col-md-5 mb-3 align-self-center">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="product_status" id="product_status" checked>
                        <label class="form-check-label" for="product_status">Ürün Satışta (Aktif)</label>
                    </div>
                </div>
            </div>
            <div class="text-end mt-3">
                <button type="submit" class="btn btn-submit-product"><i class="bi bi-check-lg me-2"></i>Ürünü Kaydet</button>
            </div>
        </form>
    </section>

    <section class="product-list-section mt-5">
        <h2 class="section-title"><i class="bi bi-list-ul me-2"></i>Mevcut Ürünleriniz</h2>
        <div class="table-responsive">
            <table class="table table-hover product-table">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Görsel</th>
                        <th>Ürün Adı</th>
                        <th>Fiyat</th>
                        <th>Stok</th>
                        <th>Satış Durumu</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars($product['Urun_ID']) ?></td>
                                <td><img src="<?= $product['Urun_Gorseli'] ? '../uploads/' . htmlspecialchars($product['Urun_Gorseli']) : 'https://placehold.co/60x60/e0e0e0/757575?text=Görsel+Yok' ?>" alt="<?= htmlspecialchars($product['Urun_Adi']) ?>"></td>
                                <td><?= htmlspecialchars($product['Urun_Adi']) ?></td>
                                <td><?= number_format(htmlspecialchars($product['Urun_Fiyati']), 2, ',', '.') ?> TL</td>
                                <td><?= htmlspecialchars($product['Stok_Adedi']) ?></td>
                                <td><span class="status-badge <?= $product['Aktiflik_Durumu'] ? 'status-aktif' : 'status-pasif' ?>"><?= $product['Aktiflik_Durumu'] ? 'Aktif' : 'Pasif' ?></span></td>
                                <td>
                                    <a href="edit_product.php?id=<?= $product['Urun_ID'] ?>" class="btn btn-sm btn-warning btn-action" title="Düzenle"><i class="bi bi-pencil-fill"></i></a>
                                    <!-- Silme formu, güvenli bir şekilde POST isteği gönderecek şekilde güncellendi -->
                                    <form action="manage_product.php?delete=<?= $product['Urun_ID'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Bu ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')">
                                        <button type="submit" class="btn btn-sm btn-danger btn-action" title="Sil"><i class="bi bi-trash3-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4"><i class="bi bi-info-circle fs-3 d-block mb-2"></i>Henüz mağazanıza ürün eklemediniz.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms).forEach(function (form) {
          form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
              event.preventDefault()
              event.stopPropagation()
            }
            form.classList.add('was-validated')
          }, false)
        })
    })();
    
    document.addEventListener("DOMContentLoaded", function() {
        // ... (Karakter sayacı ve mesaj kapatma scriptleri aynı kalıyor)
    });
</script>
</body>
</html>
