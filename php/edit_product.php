<?php
//düzenleme yapılacak şu an çalışmıyor
session_start();
include('../database.php');

// Kullanıcı oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['user_id'];

// Ürün ID kontrolü
if (!isset($_GET['product_id'])) {
    header("Location: manage_product.php");
    exit();
}

$product_id = $conn->real_escape_string($_GET['product_id']);

// Ürün bilgilerini çek
$query = "SELECT * FROM Urun WHERE Urun_ID = ? AND Satici_ID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Ürün bulunamadı veya düzenleme yetkiniz yok.");
}

$product = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $product_price = $conn->real_escape_string($_POST['product_price']);
    $product_stock = $conn->real_escape_string($_POST['product_stock']);
    $product_active = isset($_POST['product_active']) ? 1 : 0;

    // Görsel güncelleme işlemi
    $product_image = $product['Urun_Gorseli'];
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $image_tmp = $_FILES['product_image']['tmp_name'];
        $image_name = uniqid() . "_" . basename($_FILES['product_image']['name']);
        $image_path = "../uploads/" . $image_name;

        if (move_uploaded_file($image_tmp, $image_path)) {
            // Eski görseli sil
            if ($product_image && file_exists("../uploads/" . $product_image)) {
                unlink("../uploads/" . $product_image);
            }
            $product_image = $image_name;
        } else {
            die("Dosya yüklenirken bir hata oluştu.");
        }
    }

    // Ürün güncelleme sorgusu
    $update_query = "UPDATE Urun SET Urun_Adi = ?, Urun_Fiyati = ?, Stok_Adedi = ?, Urun_Gorseli = ?, Aktiflik_Durumu = ? WHERE Urun_ID = ? AND Satici_ID = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sdisiii", $product_name, $product_price, $product_stock, $product_image, $product_active, $product_id, $seller_id);

    if ($update_stmt->execute()) {
        header("Location: product_management.php");
        exit();
    } else {
        die("Ürün güncelleme sırasında bir hata oluştu: " . $conn->error);
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Düzenle</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <h1>Ürün Düzenle</h1>
    <form action="" method="POST" enctype="multipart/form-data">
        <div>
            <label for="product_name">Ürün Adı:</label>
            <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($product['Urun_Adi']) ?>" required>
        </div>
        <div>
            <label for="product_price">Ürün Fiyatı (₺):</label>
            <input type="number" step="0.01" id="product_price" name="product_price" value="<?= htmlspecialchars($product['Urun_Fiyati']) ?>" required>
        </div>
        <div>
            <label for="product_stock">Stok Adedi:</label>
            <input type="number" id="product_stock" name="product_stock" value="<?= htmlspecialchars($product['Stok_Adedi']) ?>" required>
        </div>
        <div>
            <label for="product_image">Ürün Görseli:</label>
            <input type="file" id="product_image" name="product_image">
            <?php if ($product['Urun_Gorseli']): ?>
                <p>Mevcut Görsel: <img src="../uploads/<?= htmlspecialchars($product['Urun_Gorseli']) ?>" alt="Ürün Görseli" width="100"></p>
            <?php endif; ?>
        </div>
        <div>
            <label for="product_active">Aktiflik Durumu:</label>
            <input type="checkbox" id="product_active" name="product_active" <?= $product['Aktiflik_Durumu'] ? 'checked' : '' ?>>
        </div>
        <button type="submit">Güncelle</button>
        <a href="manage_product.php">İptal</a>
    </form>
</body>
</html>
