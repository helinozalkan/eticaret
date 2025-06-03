<?php
// Sepetim Sayfası
session_start(); // Oturumu başlat
include_once '../database.php'; // Veritabanı bağlantısını dahil et (PDO bağlantısı kurduğunu varsayıyoruz)

// Özel istisna sınıfı tanımla
class CartDisplayException extends Exception {}

// Kullanıcının oturum açıp açmadığını kontrol et
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?status=not_logged_in");
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
        $musteri_id = $musteri_data['Musteri_ID'];  // Müşteri ID'sini al
    } else {
        // Eğer müşteri ID'si yoksa, sepeti boş göster ve hata logla
        error_log("my_cart.php: Musteri kaydı bulunamadı User_ID: " . $user_id);
        // Kullanıcıya hata mesajı göstermek yerine boş sepet gösterilebilir
        // throw new CartDisplayException("Müşteri kaydı bulunamadı.");
    }

    // Şimdi doğru müşteri ID'sini kullanarak sepet verilerini çekebilirsiniz
    if ($musteri_id !== null) {
        $query_cart = "SELECT Sepet.Sepet_ID, Sepet.Boyut, Sepet.Miktar, Sepet.Eklenme_Tarihi, Urun.Urun_Adi, Urun.Urun_Fiyati, Urun.Urun_Gorseli
                      FROM Sepet
                      JOIN Urun ON Sepet.Urun_ID = Urun.Urun_ID
                      WHERE Sepet.Musteri_ID = :musteri_id";
        $stmt_cart = $conn->prepare($query_cart);
        $stmt_cart->bindParam(':musteri_id', $musteri_id, PDO::PARAM_INT);
        $stmt_cart->execute();
        $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC); // Tüm sepet öğelerini al
    }

    // Sepet ürünü silme işlemi (GET ile tetiklenir, update_cart.php'ye yönlendirilmesi daha iyi)
    // Bu kısım aslında update_cart.php tarafından yönetilmeli, burada sadece yönlendirme yapılmalı.
    // Ancak mevcut yapınızda doğrudan burada silme mantığı olduğu için PDO'ya çeviriyorum.
    // İdealde, bu link update_cart.php'ye POST isteği göndermeliydi.
    if (isset($_GET['delete'])) {
        $sepet_id_to_delete = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);

        if ($sepet_id_to_delete === false || $sepet_id_to_delete <= 0) {
            header("Location: my_cart.php?status=invalid_delete_id");
            exit();
        }

        // SonarQube S1192 hatasını gidermek için parametre adı sabitlerini tanımla
        $param_sepet_id = ':sepet_id';
        $param_musteri_id = ':musteri_id';

        $conn->beginTransaction(); // İşlemi başlat
        $delete_query = "DELETE FROM Sepet WHERE Sepet_ID = " . $param_sepet_id . " AND Musteri_ID = " . $param_musteri_id;
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam($param_sepet_id, $sepet_id_to_delete, PDO::PARAM_INT);
        $delete_stmt->bindParam($param_musteri_id, $musteri_id, PDO::PARAM_INT);
        
        if ($delete_stmt->execute()) {
            $conn->commit(); // İşlemi onayla
            header("Location: my_cart.php?status=item_deleted");
            exit();
        } else {
            $conn->rollBack(); // Hata oluşursa geri al
            error_log("my_cart.php: Sepet öğesi silinirken hata oluştu. Sepet ID: " . $sepet_id_to_delete);
            throw new CartDisplayException("Sepet öğesi silinirken bir sorun oluştu.");
        }
    }

} catch (CartDisplayException $e) {
    error_log("my_cart.php: Kart Görüntüleme Hatası: " . $e->getMessage());
    // Kullanıcıya gösterilecek hata mesajı veya boş sepet
    $cart_items = []; // Hata durumunda sepeti boş göster
    // $error_message = "Sepet bilgileri alınırken bir sorun oluştu."; // Eğer hata mesajı göstermek isterseniz
} catch (PDOException $e) {
    error_log("my_cart.php: Veritabanı Hatası: " . $e->getMessage());
    $cart_items = []; // Hata durumunda sepeti boş göster
    // $error_message = "Veritabanı bağlantısında bir sorun oluştu."; // Eğer hata mesajı göstermek isterseniz
} catch (Exception $e) {
    error_log("my_cart.php: Beklenmedik Hata: " . $e->getMessage());
    $cart_items = []; // Hata durumunda sepeti boş göster
    // $error_message = "Beklenmedik bir hata oluştu."; // Eğer hata mesajı göstermek isterseniz
}

// Toplam tutarı hesapla
$genel_toplam = 0;
foreach ($cart_items as $item) {
    $genel_toplam += $item['Urun_Fiyati'] * $item['Miktar'];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sepetim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap"
          rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="css/css.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap"
          rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <script src="https://code.jquery.com/jquery-1.8.2.min.js"
            integrity="sha256-9VTS8JJyxvcUR+v+RTLTsd0ZWbzmafmlzMmeZO9RFyk=" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
</head>

<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 0;
    }

    .container {
        width: 80%;
        margin: 50px auto;
        background-color: #fff;
        padding: 20px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        border-radius: 10px;
    }

    h1 {
        text-align: center;
        color: #333;
        margin-bottom: 20px;
        font-size: 28px;
    }

    .cart-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .cart-table th, .cart-table td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .cart-table th {
        background-color: #f8f8f8;
        font-size: 16px;
    }

    .cart-table td {
        font-size: 14px;
    }

    .cart-table tr:hover {
        background-color: #f1f1f1;
    }

    .total-row {
        background-color: #f8f8f8;
        font-weight: bold;
    }

    .total-row td {
        border-top: 2px solid #ddd;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    /* .action-buttons a {  -- Bu kısım artık kullanılmayacak, button'a dönüştürüldü */
    /* background-color: #007bff; */
    /* color: white; */
    /* border: none; */
    /* padding: 5px 10px; */
    /* cursor: pointer; */
    /* border-radius: 5px; */
    /* text-decoration: none; */
    /* display: inline-block; */
    /* text-align: center; */
    /* } */

    /* .action-buttons a:hover { */
    /* background-color: #0056b3; */
    /* } */

    .action-buttons .delete-button {
        background-color: rgb(155, 10, 109);
        color: white; /* Button için renk ekledim */
        border: none; /* Button için border kaldırdım */
        padding: 5px 10px; /* Button için padding ekledim */
        cursor: pointer; /* Button için cursor ekledim */
        border-radius: 5px; /* Button için border-radius ekledim */
    }

    .action-buttons .delete-button:hover {
        background-color: rgb(155, 10, 109);
    }

    .btn-success {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        cursor: pointer;
        border-radius: 5px;
        font-size: 16px;
    }

    .btn-success:hover {
        background-color: #218838;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
    }

    .quantity-controls button {
        background-color: rgb(0, 0, 0);
        color: white;
        border: none;
        padding: 5px 10px;
        cursor: pointer;
        border-radius: 5px;
        margin: 0 5px;
    }

    .quantity-controls button:hover {
        background-color: rgb(0, 0, 0);
    }

    .quantity-controls input {
        width: 50px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 5px;
    }
</style>

<body>

<nav class="navbar  navbar-expand-lg navbar-dark" style="background-color:rgb(155, 10, 109) ;">
    <div class="container-fluid">

        <a class="navbar-brand d-flex ms-4" href="#" style="margin-left: 5px;">
            <img src="../images/logo.png" alt="Logo" width="35" height="35" class="align-text-top">

            <div class="baslik fs-3">
                <a class="dropdown-item" href="../index.php">
                    ETİCARET
                </a>
            </div>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
            <ul class="navbar-nav  me-auto mb-2 mb-lg-0 " style="margin-left: 110px;">
                <li class="nav-item dropdown ps-3">
                    <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
                       aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
                        Ana sayfa
                    </button>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="#">Pizza Palace</a>
                        <a class="dropdown-item" href="#">Coffee House</a>
                        <a class="dropdown-item" href="#">Gourmet Gateway</a>
                        <a class="dropdown-item" href="#">Flavor Fiesta</a>
                        <a class="dropdown-item" href="#">Epicurean Explorer</a>
                        <a class="dropdown-item" href="#">Epicurean Explorer Dark</a>
                    </div>
                </li>
                <li class="nav-item dropdown ps-3">
                    <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
                       aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
                        Satıcı
                    </button>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="php/seller_register.php">Satıcı oluştur</a>
                        <a class="dropdown-item" href="php/motivation.php">Girişimci Kadınlarımız</a>
                        <a class="dropdown-item" href="#">Shop Details Coffee</a>
                        <a class="dropdown-item" href="#">Cart</a>
                        <a class="dropdown-item" href="#">Checkout</a>
                    </div>
                </li>
                <li class="nav-item dropdown ps-3">
                    <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
                       aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
                        Blog
                    </button>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="#">Blog Grid One</a>
                        <a class="dropdown-item" href="#">Blog Grid Two</a>
                        <a class="dropdown-item" href="#">Blog Standard</a>
                        <a class="dropdown-item" href="#">Blog Deails</a>

                    </div>
                </li>
                <li class="nav-item dropdown ps-3">
                    <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
                       aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
                        Siparişlerim
                    </button>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="php/customer_orders.php">Sipariş Detay</a>
                        <a class="dropdown-item" href="#">Sipariş Listem</a>
                        <a class="dropdown-item" href="#">Geçmiş Siparişlerim</a>
                    </div>
                </li>
                <li class="nav-item dropdown ps-3">
                    <button id="navbarDropdown" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown"
                       aria-expanded="false" style="background: none; border: none; padding: 0; color: inherit; font: inherit; cursor: pointer;">
                        Hakkımızda
                    </button>
                    <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <a class="dropdown-item" href="#">Contact</a>
                        <a class="dropdown-item" href="#">Contact With Map</a>
                    </div>
                </li>
            </ul>
            <div style="margin-left: 0px;">
                <i class="bi bi-search text-white fs-5"></i>

                <a href="favourite.php">
                    <i class="bi bi-heart text-white fs-5" style="margin-left: 20px;"></i>
                </a>

                <a href="my_cart.php">
                    <i class="bi bi-cart3 text-white fs-5" style="margin-left: 20px;"></i>
                </a>
            </div>

            <div class="d-flex me-3" style="margin-left: 145px;">
                <i class="bi bi-person-circle text-white fs-4"></i>
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="php/logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                        <?php echo htmlspecialchars($_SESSION['username']); ?> </a>
                <?php else: ?>
                    <a href="php/login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                        Giriş Yap
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>


<div class="container">
    <h1>Sepetim</h1>
    <?php if (count($cart_items) > 0): ?>
        <form action="order_success.php" method="POST">
            <table class="cart-table">
                <thead>
                <tr>
                    <th>Ürün Adı</th>
                    <th>Fiyat</th>
                    <th>Boyut</th>
                    <th>Miktar</th>
                    <th>Toplam</th>
                    <th>Eklenme Tarihi</th>
                    <th>İşlemler</th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($cart_items as $row):
                    $urun_toplam = $row['Urun_Fiyati'] * $row['Miktar'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Urun_Adi']) ?></td>
                        <td><?= htmlspecialchars($row['Urun_Fiyati']) ?> TL</td>
                        <td><?= htmlspecialchars($row['Boyut']) ?></td>
                        <td>
                            <div class="quantity-controls">
                                <button type="button" onclick="updateCartQuantity(<?= $row['Sepet_ID'] ?>, 'decrement')">-</button>
                                <input type="number" name="quantity[<?= $row['Sepet_ID'] ?>]" id="quantity-<?= $row['Sepet_ID'] ?>"
                                       value="<?= htmlspecialchars($row['Miktar']) ?>" min="1"
                                       onchange="updateCartQuantity(<?= $row['Sepet_ID'] ?>, 'set_quantity', this.value)">
                                <button type="button" onclick="updateCartQuantity(<?= $row['Sepet_ID'] ?>, 'increment')">+</button>
                            </div>
                        </td>
                        <td><?= $urun_toplam ?> TL</td>
                        <td><?= htmlspecialchars($row['Eklenme_Tarihi']) ?></td>
                        <td class="action-buttons">
                            <button type="button" class="delete-button"
                                    onclick="if(confirm('Bu ürünü sepetinizden silmek istediğinizden emin misiniz?')) { updateCartQuantity(<?= $row['Sepet_ID'] ?>, 'remove'); }">Sil</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>Genel Toplam:</strong></td>
                    <td><strong><?= $genel_toplam ?> TL</strong></td>
                    <td></td>
                </tr>
                </tbody>
            </table>
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" class="btn btn-success">Sepeti Onayla</button>
            </div>
        </form>
    <?php else: ?>
        <p>Sepetinizde ürün bulunmamaktadır.</p>
    <?php endif; ?>
</div>
<script>
    // updateCartQuantity fonksiyonu, update_cart.php'ye POST isteği gönderir
    function updateCartQuantity(sepetId, action, newQuantity = null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'update_cart.php'; // update_cart.php'ye yönlendir

        const sepetIdInput = document.createElement('input');
        sepetIdInput.type = 'hidden';
        sepetIdInput.name = 'sepet_id'; // update_cart.php'de sepet_id bekliyorsa
        sepetIdInput.value = sepetId;
        form.appendChild(sepetIdInput);

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);

        if (action === 'set_quantity' && newQuantity !== null) {
            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = 'quantity';
            quantityInput.value = newQuantity;
            form.appendChild(quantityInput);
        }

        document.body.appendChild(form);
        form.submit();
    }
</script>
</body>
</html>
