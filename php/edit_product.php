<?php
// edit_product.php - Ürün düzenleme sayfası
session_start();

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');
$redirect_page = 'manage_product.php';

// Kullanıcı oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header(HTTP_HEADER_LOCATION . "login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
$logged_in = isset($_SESSION['user_id']); 
$username_session = $_SESSION['username'] ?? 'Satıcı';

$satici_id = null;
$product = null;
$error_message = "";
$success_message = "";

// Satıcı ID'sini al
try {
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        $_SESSION['form_error_message'] = "Satıcı kaydı bulunamadı.";
        header(HTTP_HEADER_LOCATION . $redirect_page);
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("edit_product.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı hatası oluştu.";
    header(HTTP_HEADER_LOCATION . $redirect_page);
    exit();
}

// Ürün ID kontrolü
$product_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($product_id_to_edit === false || $product_id_to_edit <= 0) {
    $_SESSION['form_error_message'] = "Geçersiz ürün ID'si.";
    header(HTTP_HEADER_LOCATION . $redirect_page);
    exit();
}

// *** Form POST edildiğinde güncelleme işlemi ***
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product_action'])) {
    
    // Formdan gelen verileri al
    $new_product_name = trim(htmlspecialchars($_POST['product_name'] ?? ''));
    $new_product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    $new_product_stock = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT);
    $new_product_active = isset($_POST['product_status']) ? 1 : 0;
    $new_product_description = trim(htmlspecialchars($_POST['product_description'] ?? ''));
    $new_product_story = trim(htmlspecialchars($_POST['product_story'] ?? ''));

    // Validasyon
    if (empty($new_product_name) || $new_product_price === false || $new_product_price < 0 || $new_product_stock === false || $new_product_stock < 0) {
        $error_message = "Lütfen tüm zorunlu alanları doldurun ve geçerli değerler girin.";
    } elseif (mb_strlen($new_product_description) > 250) {
        $error_message = "Ürün açıklaması en fazla 250 karakter olabilir.";
    } else {
        try {
            // Önce mevcut görseli al, yeni görsel yüklenmezse bu kullanılacak.
            $stmt_current_image = $conn->prepare("SELECT Urun_Gorseli FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id");
            $stmt_current_image->bindParam(':product_id', $product_id_to_edit, PDO::PARAM_INT);
            $stmt_current_image->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
            $stmt_current_image->execute();
            $current_image_filename = $stmt_current_image->fetchColumn();

            // Yeni görsel yüklendiyse, eskisini sil ve yeni adı ata
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                // (Buraya dosya tipi, boyutu vb. için daha detaylı kontroller eklenebilir)
                $file_tmp_path = $_FILES['product_image']['tmp_name'];
                $file_ext = strtolower(pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION));
                $new_image_name = bin2hex(random_bytes(16)) . '.' . $file_ext;

                if (move_uploaded_file($file_tmp_path, $upload_dir . $new_image_name)) {
                    // Yeni dosya başarıyla yüklendiyse, eski dosyayı sil (eğer varsa)
                    if ($current_image_filename && file_exists($upload_dir . $current_image_filename)) {
                        unlink($upload_dir . $current_image_filename);
                    }
                    $current_image_filename = $new_image_name; // Veritabanına kaydedilecek dosya adı güncellendi.
                } else {
                    $error_message = "Yeni görsel yüklenirken bir hata oluştu.";
                }
            }

            // Hata yoksa veritabanını güncelle
            if (empty($error_message)) {
                $conn->beginTransaction();
                $update_query = "UPDATE Urun SET 
                                    Urun_Adi = :product_name, 
                                    Urun_Fiyati = :product_price, 
                                    Stok_Adedi = :product_stock, 
                                    Urun_Gorseli = :product_image, 
                                    Urun_Aciklamasi = :product_description, 
                                    Aktiflik_Durumu = :product_active,
                                    Urun_Hikayesi = :product_story
                                 WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
                $update_stmt = $conn->prepare($update_query);

                $update_stmt->bindParam(':product_name', $new_product_name);
                $update_stmt->bindParam(':product_price', $new_product_price);
                $update_stmt->bindParam(':product_stock', $new_product_stock, PDO::PARAM_INT);
                $update_stmt->bindParam(':product_image', $current_image_filename);
                $update_stmt->bindParam(':product_description', $new_product_description);
                $update_stmt->bindParam(':product_active', $new_product_active, PDO::PARAM_INT);
                $update_stmt->bindParam(':product_story', $new_product_story);
                $update_stmt->bindParam(':product_id', $product_id_to_edit, PDO::PARAM_INT);
                $update_stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    $conn->commit();
                    $_SESSION['form_success_message'] = "Ürün başarıyla güncellendi!";
                    header(HTTP_HEADER_LOCATION . $redirect_page . "?status=product_updated&id=" . $product_id_to_edit);
                    exit();
                } else {
                    $conn->rollBack();
                    $error_message = "Ürün güncellenirken bir veritabanı hatası oluştu.";
                }
            }
        } catch(PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("edit_product.php: Güncelleme hatası: " . $e->getMessage());
            $error_message = "Ürün güncellenirken bir veritabanı hatası oluştu.";
        }
    }
}


// Formun gösterilmesi için ürün bilgilerini çekme
try {
    $query_product = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Urun_Hikayesi FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
    $stmt_product = $conn->prepare($query_product);
    $stmt_product->bindParam(':product_id', $product_id_to_edit, PDO::PARAM_INT);
    $stmt_product->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['form_error_message'] = "Ürün bulunamadı veya bu ürünü düzenleme yetkiniz yok.";
        header(HTTP_HEADER_LOCATION . $redirect_page);
        exit();
    }
} catch (PDOException $e) {
    error_log("edit_product.php: Ürün bilgileri çekilirken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Ürün bilgileri yüklenirken bir sorun oluştu.";
    header(HTTP_HEADER_LOCATION . $redirect_page);
    exit();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Düzenle - <?php echo htmlspecialchars($product['Urun_Adi'] ?? 'Ürün'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-custom { background-color: rgb(91, 140, 213); }
        .edit-form-container { max-width: 700px; margin: 2rem auto; background-color: #ffffff; padding: 35px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        .page-title { font-family: 'Playfair Display', serif; color: #2c3e50; text-align: center; margin-bottom: 30px; font-size: 2.2rem; font-weight: 700; }
        .form-label { font-weight: 600; color: #495057; }
        .form-control, .form-select { border-radius: 6px; border: 1px solid #ced4da; padding: 0.55rem 0.9rem; font-size: 0.95rem; }
        .form-control:focus, .form-select:focus { border-color: rgb(91, 140, 213); box-shadow: 0 0 0 0.2rem rgba(91, 140, 213, 0.25); }
        .form-check-input:checked { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); }
        .form-switch .form-check-input { width: 2.5em; height: 1.25em; margin-top: 0.25em; }
        .form-switch .form-check-label { padding-left: 0.5em; font-size: 1rem; }
        .btn-update-product { background-color: rgb(91, 140, 213); border-color: rgb(91, 140, 213); color: white; }
        .btn-update-product:hover { background-color: rgb(70, 120, 190); border-color: rgb(70, 120, 190); }
        .current-image-preview { max-width: 150px; height: auto; border-radius: 6px; border: 1px solid #dee2e6; margin-top: 10px; }
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

<div class="container">
    <div class="edit-form-container">
        <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Ürün Bilgilerini Düzenle</h1>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($product): ?>
        <form action="edit_product.php?id=<?php echo htmlspecialchars($product_id_to_edit); ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="update_product_action" value="1">
            <div class="row g-3">
                <div class="col-md-12 mb-3">
                    <label for="product_name" class="form-label">Ürün Adı:</label>
                    <input type="text" class="form-control" id="product_name" name="product_name" value="<?= htmlspecialchars($product['Urun_Adi'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="product_price" class="form-label">Fiyat (₺):</label>
                    <input type="number" step="0.01" class="form-control" id="product_price" name="product_price" value="<?= htmlspecialchars($product['Urun_Fiyati'] ?? '') ?>" required min="0">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="product_stock" class="form-label">Stok Adedi:</label>
                    <input type="number" class="form-control" id="product_stock" name="product_stock" value="<?= htmlspecialchars($product['Stok_Adedi'] ?? '') ?>" required min="0">
                </div>
                <div class="col-md-12 mb-3">
                    <label for="product_description" class="form-label">Açıklama (En fazla 250 karakter):</label>
                    <textarea class="form-control" id="product_description" name="product_description" rows="3" maxlength="250"><?= htmlspecialchars($product['Urun_Aciklamasi'] ?? '') ?></textarea>
                    <div id="charCountDescription" class="form-text text-end">0 / 250</div>
                </div>
                <div class="col-md-12 mb-3">
                    <label for="product_story" class="form-label">Ürün Hikayesi:</label>
                    <textarea class="form-control" id="product_story" name="product_story" rows="4"><?= htmlspecialchars($product['Urun_Hikayesi'] ?? '') ?></textarea>
                </div>
                <div class="col-md-12 mb-3">
                    <label for="product_image" class="form-label">Yeni Görsel (Değiştirmek istemiyorsanız boş bırakın):</label>
                    <input type="file" class="form-control" id="product_image" name="product_image">
                    <?php if (!empty($product['Urun_Gorseli'])): ?>
                        <div class="mt-2">
                            <p class="mb-1 small text-muted">Mevcut Görsel:</p>
                            <img src="../uploads/<?= htmlspecialchars($product['Urun_Gorseli']) ?>" alt="Mevcut Ürün Görseli" class="current-image-preview">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-12 mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="product_status" name="product_status" <?= (isset($product['Aktiflik_Durumu']) && $product['Aktiflik_Durumu'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="product_status">Ürün Satışta (Aktif)</label>
                    </div>
                </div>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <a href="<?php echo $redirect_page; ?>" class="btn btn-secondary me-md-2"><i class="bi bi-x-circle me-1"></i>İptal</a>
                <button type="submit" class="btn btn-primary btn-update-product"><i class="bi bi-save-fill me-1"></i>Değişiklikleri Kaydet</button>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-warning">Düzenlenecek ürün bilgisi bulunamadı veya bu ürünü düzenleme yetkiniz yok.</div>
            <a href="<?php echo $redirect_page; ?>" class="btn btn-secondary"><i class="bi bi-arrow-left-circle me-1"></i>Ürün Listesine Dön</a>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // ... (Karakter sayacı ve mesaj kapatma scriptleri)
        const descriptionTextarea = document.getElementById('product_description');
        const charCountDescription = document.getElementById('charCountDescription');
        if (descriptionTextarea && charCountDescription) {
            function updateCharCount() {
                const currentLength = descriptionTextarea.value.length;
                const maxLength = descriptionTextarea.maxLength;
                charCountDescription.textContent = currentLength + ' / ' + maxLength;
            }
            descriptionTextarea.addEventListener('input', updateCharCount);
            updateCharCount(); // Sayfa yüklendiğinde de çalıştır
        }
    });
</script>
</body>
</html>
