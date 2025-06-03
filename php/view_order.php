<?php
//sipariş görüntüleme sayfası
session_start();
include('../database.php');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $order_id = $_GET['id'];
    $query = "SELECT * FROM Siparis WHERE Siparis_ID='$order_id'";
    $result = mysqli_query($conn, $query);
    $order = mysqli_fetch_assoc($result);
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı</title>
</head>
<body>
    <h1>Sipariş Detayı</h1>
    <p>Sipariş ID: <?php echo $order['Siparis_ID']; ?></p>
    <p>Sipariş Tarihi: <?php echo $order['Siparis_Tarihi']; ?></p>
    <p>Durum: <?php echo $order['Siparis_Durumu']; ?></p>
    <p>Toplam Tutar: <?php echo $order['Siparis_Tutari']; ?> TL</p>
    <p>Adres: <?php echo $order['Teslimat_Adresi']; ?></p>
    <p>Fatura Adresi: <?php echo $order['Fatura_Adresi']; ?></p>
</body>
</html>
