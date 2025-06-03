<?php
//customer panel sayfası
session_start();
include('../database.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$query = "SELECT * FROM Siparis WHERE Musteri_ID = '$customer_id'";
$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Müşteri Paneli</title>
</head>
<body>
    <h1>Müşteri Paneli</h1>
    <h2>Siparişler</h2>
    <table border="1">
        <tr>
            <th>Sipariş Tarihi</th>
            <th>Sipariş Durumu</th>
            <th>Toplam Tutar</th>
            <th>Aksiyon</th>
        </tr>
        <?php while ($order = mysqli_fetch_assoc($result)) { ?>
        <tr>
            <td><?php echo $order['Siparis_Tarihi']; ?></td>
            <td><?php echo $order['Siparis_Durumu']; ?></td>
            <td><?php echo $order['Siparis_Tutari']; ?> TL</td>
            <td>
                <a href="view_order.php?id=<?php echo $order['Siparis_ID']; ?>">Detaylar</a>
            </td>
        </tr>
        <?php } ?>
    </table>
</body>
</html>
