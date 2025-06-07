<?php
// my_cart.php - Sepetim Sayfası (Veritabanı tabanlı)

session_start(); // Oturumu başlat

// Yeni Database sınıfımızı projemize dahil ediyoruz.
include_once '../database.php';

// Veritabanı bağlantısını Singleton deseni üzerinden alıyoruz.
$db = Database::getInstance();
$conn = $db->getConnection();

// *** İYİLEŞTİRME: Tekrar eden metinler için sabit ve değişkenler tanımlıyoruz. ***
define('HTTP_HEADER_LOCATION', 'Location: ');
$login_page = 'login.php';
$cart_page = 'my_cart.php';


// Özel istisna sınıfı tanımla
class CartDisplayException extends Exception {}

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isset($_SESSION['user_id'])) {
    header(HTTP_HEADER_LOCATION . $login_page . "?status=not_logged_in");
    exit();
}

$user_id = $_SESSION['user_id']; // Oturumdan users.id'yi al
$musteri_id = null; // Müşteri ID'sini tutacak değişken
$cart_items = []; // Sepet ürünlerini tutacak dizi

try {
    // Kullanıcının Musteri_ID'sini almak için sorgu
    $stmt_musteri = $conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
    $stmt_musteri->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_musteri->execute();
    $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

    if ($musteri_data) {
        $musteri_id = $musteri_data['Musteri_ID'];
    } else {
        error_log("my_cart.php: Musteri kaydı bulunamadı User_ID: " . $user_id);
    }

    // Doğru müşteri ID'sini kullanarak sepet verilerini çek
    if ($musteri_id !== null) {
        $query_cart = "SELECT Sepet.Sepet_ID, Sepet.Boyut, Sepet.Miktar, Urun.Urun_ID, Urun.Urun_Adi, Urun.Urun_Fiyati, Urun.Urun_Gorseli
                         FROM Sepet
                         JOIN Urun ON Sepet.Urun_ID = Urun.Urun_ID
                         WHERE Sepet.Musteri_ID = :musteri_id";
        $stmt_cart = $conn->prepare($query_cart);
        $stmt_cart->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_cart->execute();
        $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (CartDisplayException | PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log("my_cart.php Hatası: " . $e->getMessage());
    $cart_items = [];
    $_SESSION['cart_error'] = "Sepetiniz yüklenirken bir sorun oluştu.";
}

// Toplam tutarı hesapla
$genel_toplam = 0;
foreach ($cart_items as $item) {
    $genel_toplam += $item['Urun_Fiyati'] * $item['Miktar'];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepetim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .cart-container { max-width: 960px; margin: 40px auto; background-color: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .cart-table th { background-color: #e9ecef; }
        .cart-table td { vertical-align: middle; }
        .product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; }
        .total-row strong { font-size: 1.15rem; }
        .btn-update-quantity { border: none; background: none; padding: 0.3rem 0.6rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(91, 140, 213);">
    <!-- Navbar HTML kısmı aynı kalıyor -->
</nav>

<div class="container cart-container">
    <div class="text-center mb-4">
        <h1 class="display-5" style="font-family: 'Playfair Display', serif;">Sepetim</h1>
    </div>

    <?php if (count($cart_items) > 0): ?>
        <div class="table-responsive">
            <table class="table cart-table table-hover">
                <thead class="table-light">
                    <tr>
                        <th scope="col" colspan="2">Ürün</th>
                        <th scope="col" class="text-center">Fiyat</th>
                        <th scope="col" class="text-center">Miktar</th>
                        <th scope="col" class="text-end">Toplam</th>
                        <th scope="col" class="text-center">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cart_items as $row): ?>
                    <tr>
                        <td style="width: 80px;">
                            <a href="product_detail.php?id=<?= $row['Urun_ID'] ?>">
                                <img src="../uploads/<?= htmlspecialchars($row['Urun_Gorseli']) ?>" alt="<?= htmlspecialchars($row['Urun_Adi']) ?>" class="product-image">
                            </a>
                        </td>
                        <td>
                            <a href="product_detail.php?id=<?= $row['Urun_ID'] ?>" class="text-dark text-decoration-none fw-bold"><?= htmlspecialchars($row['Urun_Adi']) ?></a>
                            <small class="d-block text-muted">Boyut: <?= htmlspecialchars($row['Boyut']) ?></small>
                        </td>
                        <td class="text-center"><?= number_format($row['Urun_Fiyati'], 2, ',', '.') ?> TL</td>
                        <td class="text-center">
                            <div class="input-group justify-content-center" style="width: 120px;">
                                <button class="btn btn-outline-secondary btn-sm btn-update-quantity" type="button" onclick="updateCart('decrement', <?= $row['Sepet_ID'] ?>)">-</button>
                                <input type="text" class="form-control form-control-sm text-center" value="<?= htmlspecialchars($row['Miktar']) ?>" readonly>
                                <button class="btn btn-outline-secondary btn-sm btn-update-quantity" type="button" onclick="updateCart('increment', <?= $row['Sepet_ID'] ?>)">+</button>
                            </div>
                        </td>
                        <td class="text-end fw-bold"><?= number_format($row['Urun_Fiyati'] * $row['Miktar'], 2, ',', '.') ?> TL</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Ürünü Kaldır" onclick="updateCart('remove', <?= $row['Sepet_ID'] ?>)"><i class="bi bi-trash3-fill"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <th colspan="4" class="text-end">Genel Toplam:</th>
                        <th colspan="2" class="text-start fs-5"><?= number_format($genel_toplam, 2, ',', '.') ?> TL</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <!-- *** DÜZELTME: Butonlar eklendi *** -->
        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Alışverişe Devam Et
            </a>
            <form action="checkout.php" method="POST">
                <button type="submit" class="btn btn-primary btn-lg">
                    Sepeti Onayla <i class="bi bi-arrow-right"></i>
                </button>
            </form>
        </div>
    <?php else: ?>
        <div class="text-center p-5 bg-light rounded">
            <i class="bi bi-cart-x fs-1 text-secondary"></i>
            <h4 class="mt-3">Sepetinizde ürün bulunmamaktadır.</h4>
            <p class="text-muted">Hemen alışverişe başlayarak sepetinizi doldurabilirsiniz.</p>
            <a href="../index.php" class="btn btn-primary mt-2">Alışverişe Başla</a>
        </div>
    <?php endif; ?>
</div>

<script>
    function updateCart(action, sepetId) {
        if (action === 'remove' && !confirm('Bu ürünü sepetinizden silmek istediğinizden emin misiniz?')) {
            return;
        }
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'update_cart.php';
        form.style.display = 'none';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);

        const sepetIdInput = document.createElement('input');
        sepetIdInput.type = 'hidden';
        sepetIdInput.name = 'sepet_id';
        sepetIdInput.value = sepetId;
        form.appendChild(sepetIdInput);

        document.body.appendChild(form);
        form.submit();
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
