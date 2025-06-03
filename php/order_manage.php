<?php
// order_manage.php - Satıcı Sipariş Yönetimi Sayfası (PDO ile güncellendi)
session_start();
include('../database.php'); // database.php PDO bağlantısını kuruyor

// Satıcı yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$seller_user_id = $_SESSION['user_id']; // users tablosundaki ID

$orders = []; // Sipariş verilerini tutacak dizi
$error_message = ""; // Hata mesajlarını tutacak değişken

try {
    // 1. Kullanıcının Satici_ID'sini al
    // users tablosundaki ID ile Satici tablosundaki Satici_ID'yi eşleştiriyoruz.
    $stmt_satici = $conn->prepare("SELECT Satici_ID FROM Satici WHERE User_ID = :user_id");
    $stmt_satici->bindParam(':user_id', $seller_user_id, PDO::PARAM_INT);
    $stmt_satici->execute();
    $satici_data = $stmt_satici->fetch(PDO::FETCH_ASSOC);

    if (!$satici_data) {
        $error_message = "Satıcı kaydı bulunamadı. Lütfen bir satıcı hesabı oluşturduğunuzdan emin olun.";
        // Satıcı ID'si olmadan devam edemeyeceğimiz için burada işlem durdurulur.
    } else {
        $satici_id = $satici_data['Satici_ID'];

        // 2. Bu satıcıya ait ürünlerin olduğu siparişleri çek
        // Bir satıcı, kendi ürünlerinin yer aldığı tüm siparişleri görmelidir.
        // SiparisUrun tablosunu, Siparis tablosunu ve Urun tablosunu birleştirerek bu veriye ulaşılır.
        $query_orders = "SELECT
                            SU.Siparis_ID,
                            SU.Miktar,
                            SU.Fiyat,
                            S.Siparis_Tarihi,
                            S.Siparis_Durumu,
                            U.Urun_Adi,
                            U.Urun_ID
                         FROM
                            SiparisUrun SU
                         JOIN
                            Siparis S ON SU.Siparis_ID = S.Siparis_ID
                         JOIN
                            Urun U ON SU.Urun_ID = U.Urun_ID
                         WHERE
                            U.Satici_ID = :satici_id
                         ORDER BY
                            S.Siparis_Tarihi DESC";

        $stmt_orders = $conn->prepare($query_orders);
        $stmt_orders->bindParam(':satici_id', $satici_id, PDO::PARAM_INT);
        $stmt_orders->execute();
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("order_manage.php: Veritabanı hatası: " . $e->getMessage());
    $error_message = "Siparişler çekilirken bir veritabanı hatası oluştu. Lütfen daha sonra tekrar deneyin.";
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/css.css"> <style>
        /* Bu dosya için özel stil ekleyebilirsin veya css/css.css içine ekleyebilirsin */
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            display: inline-block; /* Yan yana durmaları için */
        }
        .status-beklemede { background-color: #ffc107; } /* Sarı */
        .status-kargoda { background-color: #17a2b8; }    /* Mavi */
        .status-teslim-edildi { background-color: #28a745; } /* Yeşil */
        .status-iptal-edildi { background-color: #dc3545; } /* Kırmızı */
        .form-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .form-group select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .form-group button {
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-group button:hover {
            background-color: #0056b3;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sipariş Yönetimi</h1>

        <?php if (!empty($error_message)): ?>
            <div class="message error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <?php if (empty($orders) && empty($error_message)): ?>
            <p>Şu anda size ait ürünleri içeren herhangi bir sipariş bulunmamaktadır.</p>
        <?php elseif (!empty($orders)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Sipariş ID</th>
                        <th>Ürün Adı</th>
                        <th>Miktar</th>
                        <th>Fiyat</th>
                        <th>Sipariş Tarihi</th>
                        <th>Durum</th>
                        <th>Güncelle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['Siparis_ID']) ?></td>
                            <td><?= htmlspecialchars($order['Urun_Adi']) ?></td>
                            <td><?= htmlspecialchars($order['Miktar']) ?></td>
                            <td><?= htmlspecialchars($order['Fiyat']) ?> TL</td>
                            <td><?= htmlspecialchars($order['Siparis_Tarihi']) ?></td>
                            <td>
                                <?php
                                $status_class = strtolower(str_replace(' ', '-', $order['Siparis_Durumu'])); // CSS sınıfı için düzenle
                                ?>
                                <span class="status-badge status-<?= $status_class ?>">
                                    <?= htmlspecialchars($order['Siparis_Durumu']) ?>
                                </span>
                            </td>
                            <td>
                                <form action="update_order_status.php" method="post" class="form-group">
                                    <input type="hidden" name="siparis_id" value="<?= htmlspecialchars($order['Siparis_ID']) ?>">
                                    <select name="siparis_durumu">
                                        <option value="Beklemede" <?= ($order['Siparis_Durumu'] === 'Beklemede') ? 'selected' : '' ?>>Beklemede</option>
                                        <option value="Kargoda" <?= ($order['Siparis_Durumu'] === 'Kargoda') ? 'selected' : '' ?>>Kargoda</option>
                                        <option value="Teslim Edildi" <?= ($order['Siparis_Durumu'] === 'Teslim Edildi') ? 'selected' : '' ?>>Teslim Edildi</option>
                                        <option value="İptal Edildi" <?= ($order['Siparis_Durumu'] === 'İptal Edildi') ? 'selected' : '' ?>>İptal Edildi</option>
                                    </select>
                                    <button type="submit">Güncelle</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>