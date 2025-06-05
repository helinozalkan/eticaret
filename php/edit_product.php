<?php
// edit_product.php - Ürün düzenleme sayfası
session_start();
include('../database.php');

// Kullanıcı oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id'];
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
        $_SESSION['form_error_message'] = "Satıcı kaydı bulunamadı."; // manage_product'a mesaj gönder
        header("Location: manage_product.php");
        exit();
    }
    $satici_id = $satici_data['Satici_ID'];

} catch (PDOException $e) {
    error_log("edit_product.php: Satıcı ID alınırken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Veritabanı hatası oluştu.";
    header("Location: manage_product.php");
    exit();
}

// Ürün ID kontrolü
$product_id_to_edit = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($product_id_to_edit === false || $product_id_to_edit <= 0) {
    $_SESSION['form_error_message'] = "Geçersiz ürün ID'si.";
    header("Location: manage_product.php");
    exit();
}

// Ürün bilgilerini çekme (Urun_Hikayesi dahil, Onay_Durumu hariç)
try {
    $query_product = "SELECT Urun_ID, Urun_Adi, Urun_Fiyati, Stok_Adedi, Urun_Gorseli, Urun_Aciklamasi, Aktiflik_Durumu, Urun_Hikayesi FROM Urun WHERE Urun_ID = :product_id AND Satici_ID = :satici_id";
    $stmt_product = $conn->prepare($query_product);
    $stmt_product->bindParam(':product_id', $product_id_to_edit, PDO::PARAM_INT);
    $stmt_product->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
    $stmt_product->execute();
    $product = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $_SESSION['form_error_message'] = "Ürün bulunamadı veya bu ürünü düzenleme yetkiniz yok.";
        header("Location: manage_product.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("edit_product.php: Ürün bilgileri çekilirken veritabanı hatası: " . $e->getMessage());
    $_SESSION['form_error_message'] = "Ürün bilgileri yüklenirken bir sorun oluştu.";
    header("Location: manage_product.php");
    exit();
}

// Form POST edildiğinde güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product_action'])) {
    $new_product_name = trim(htmlspecialchars($_POST['product_name'] ?? ''));
    $new_product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    $new_product_stock = filter_input(INPUT_POST, 'product_stock', FILTER_VALIDATE_INT);
    $new_product_active = isset($_POST['product_status']) ? 1 : 0;
    $new_product_description = trim(htmlspecialchars($_POST['product_description'] ?? ''));
    $new_product_story = trim(htmlspecialchars($_POST['product_story'] ?? '')); // Yeni ürün hikayesi

    if (empty($new_product_name) || $new_product_price === false || $new_product_price < 0 || $new_product_stock === false || $new_product_stock < 0) {
        $error_message = "Lütfen tüm zorunlu alanları doldurun ve geçerli değerler girin.";
    } elseif (mb_strlen($new_product_description) > 250) {
        $error_message = "Ürün açıklaması en fazla 250 karakter olabilir.";
    } else {
        $upload_dir = "../uploads/";
        $current_image_filename = $product['Urun_Gorseli']; // Mevcut görsel adı

        // Yeni görsel yüklendiyse
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_size = $_FILES['product_image']['size'];
            $file_type_mime = mime_content_type($file_tmp_path);

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file_ext, $allowed_extensions) || !in_array($file_type_mime, $allowed_mime_types)) {
                $error_message = "Geçersiz dosya tipi. Yalnızca JPG, JPEG, PNG veya GIF yüklenebilir.";
            } elseif ($file_size > $max_file_size) {
                $error_message = "Dosya boyutu çok büyük. Maksimum " . ($max_file_size / (1024 * 1024)) . "MB.";
            } else {
                $new_image_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
                $upload_path = $upload_dir . $new_image_filename;

                if (move_uploaded_file($file_tmp_path, $upload_path)) {
                    // Eski görseli sil (eğer varsa ve yeni görsel yüklendiyse)
                    if ($current_image_filename && file_exists($upload_dir . $current_image_filename)) {
                        unlink($upload_dir . $current_image_filename);
                    }
                    $current_image_filename = $new_image_filename; // Güncellenecek görsel adı
                } else {
                    $error_message = "Yeni dosya yüklenirken bir hata oluştu.";
                }
            }
        }

        if (empty($error_message)) {
            try {
                $conn->beginTransaction();
                // Onay_Durumu artık güncellenmiyor.
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
                $update_stmt->bindParam(':product_story', $new_product_story); // Ürün hikayesi eklendi
                $update_stmt->bindParam(':product_id', $product_id_to_edit, PDO::PARAM_INT);
                $update_stmt->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);

                if ($update_stmt->execute()) {
                    $conn->commit();
                    $_SESSION['form_success_message'] = "Ürün başarıyla güncellendi!";
                    header("Location: manage_product.php?status=product_updated&id=" . $product_id_to_edit);
                    exit();
                } else {
                    $conn->rollBack();
                    $error_message = "Ürün güncellenirken bir veritabanı hatası oluştu.";
                }
            } catch (PDOException $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                error_log("edit_product.php: Ürün güncelleme sırasında PDO hatası: " . $e->getMessage());
                $error_message = "Veritabanı işlemi sırasında bir sorun oluştu.";
            }
        }
    }
    // Hata oluştuysa veya form ilk kez yükleniyorsa, güncel ürün bilgilerini tekrar çek (eğer $product daha önce çekilmediyse)
    // Bu blok aslında yukarıdaki ilk ürün çekme bloğu ile birleştirilebilir veya $product güncellenebilir.
    // Form gönderimi sonrası $product'ı güncelleyelim ki formda yeni (veya eski, hata varsa) değerler görünsün.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)) {
        $product['Urun_Adi'] = $new_product_name;
        $product['Urun_Fiyati'] = $new_product_price !== false ? $new_product_price : $product['Urun_Fiyati'];
        $product['Stok_Adedi'] = $new_product_stock !== false ? $new_product_stock : $product['Stok_Adedi'];
        $product['Aktiflik_Durumu'] = $new_product_active;
        $product['Urun_Aciklamasi'] = $new_product_description;
        $product['Urun_Hikayesi'] = $new_product_story;
        // Görsel, hata durumunda eski görsel kalır, başarılı yüklemede güncellenir.
    }
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f8f9fa;
            padding-top: 20px; /* Navbar için boşluk */
            padding-bottom: 20px;
        }
        .navbar-custom {
             background-color: rgb(91, 140, 213);
        }
        .edit-form-container {
            max-width: 700px; /* Form genişliği */
            margin: 0 auto;
            background-color: #ffffff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        .page-title {
            font-family: 'Playfair Display', serif;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2rem;
            font-weight: 700;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.3rem;
        }
        .form-control, .form-select {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 0.55rem 0.9rem;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: rgb(91, 140, 213);
            box-shadow: 0 0 0 0.2rem rgba(91, 140, 213, 0.25);
        }
        .form-check-input{
            width: 1em;
            height: 1em;
        }
        .form-check-input:checked {
            background-color: rgb(91, 140, 213);
            border-color: rgb(91, 140, 213);
        }
        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.25em;
            margin-top: 0.25em;
        }
        .form-switch .form-check-label {
            padding-left: 0.5em;
            font-size: 1rem;
        }
        .btn-update-product {
            background-color: rgb(91, 140, 213);
            border-color: rgb(91, 140, 213);
            color: white;
        }
        .btn-update-product:hover {
            background-color: rgb(70, 120, 190);
            border-color: rgb(70, 120, 190);
        }
        .current-image-preview {
            max-width: 150px;
            max-height: 150px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            margin-top: 5px;
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
    <?php
        // Navigasyon barını buraya ekleyebilirsiniz, manage_product.php'deki gibi.
        // Basitlik adına şimdilik eklemiyorum ama tutarlılık için eklenmesi iyi olur.
    ?>
    <div class="container">
        <div class="edit-form-container">
            <h1 class="page-title"><i class="bi bi-pencil-square me-2"></i>Ürün Bilgilerini Düzenle</h1>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-custom alert-danger-custom alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): // Bu mesaj genellikle manage_product.php'ye yönlendirme sonrası gösterilir. ?>
                <div class="alert alert-success alert-custom alert-success-custom alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
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
                        <div class="invalid-feedback">Lütfen ürün adını girin.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="product_price" class="form-label">Ürün Fiyatı (₺):</label>
                        <input type="number" step="0.01" class="form-control" id="product_price" name="product_price" value="<?= htmlspecialchars($product['Urun_Fiyati'] ?? '') ?>" required min="0">
                        <div class="invalid-feedback">Lütfen geçerli bir fiyat girin.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="product_stock" class="form-label">Stok Adedi:</label>
                        <input type="number" class="form-control" id="product_stock" name="product_stock" value="<?= htmlspecialchars($product['Stok_Adedi'] ?? '') ?>" required min="0">
                        <div class="invalid-feedback">Lütfen geçerli bir stok adedi girin.</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="product_description" class="form-label">Ürün Açıklaması (En fazla 250 karakter):</label>
                        <textarea class="form-control" id="product_description" name="product_description" rows="3" maxlength="250"><?= htmlspecialchars($product['Urun_Aciklamasi'] ?? '') ?></textarea>
                        <div id="charCountDescription" class="form-text text-end">0 / 250</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="product_story" class="form-label">Ürün Hikayesi:</label>
                        <textarea class="form-control" id="product_story" name="product_story" rows="4" placeholder="Bu ürünün arkasındaki ilhamı, yapım sürecini veya özel anlamını paylaşın..."><?= htmlspecialchars($product['Urun_Hikayesi'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="product_image" class="form-label">Yeni Ürün Görseli (Değiştirmek istemiyorsanız boş bırakın):</label>
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
                    <!-- Onay Durumu ile ilgili bir alan artık burada olmayacak -->
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="manage_product.php" class="btn btn-secondary me-md-2"><i class="bi bi-x-circle me-1"></i>İptal</a>
                    <button type="submit" class="btn btn-primary btn-update-product"><i class="bi bi-save-fill me-1"></i>Değişiklikleri Kaydet</button>
                </div>
            </form>
            <?php else: ?>
                <div class="alert alert-warning">Düzenlenecek ürün bilgisi bulunamadı.</div>
                <a href="manage_product.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle me-1"></i>Ürün Listesine Dön</a>
            <?php endif; ?>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
      'use strict'
      var forms = document.querySelectorAll('.needs-validation')
      Array.prototype.slice.call(forms)
        .forEach(function (form) {
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
        var closeBtns = document.querySelectorAll(".alert .btn-close");
        closeBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                this.closest('.alert').style.display = 'none';
            });
        });

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
