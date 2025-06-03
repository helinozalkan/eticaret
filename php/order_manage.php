<?php
//sipariş yönetimi sayfası
session_start();
include('database.php');

// Satıcı kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$satici_id = $_SESSION['user_id'];

// Satıcının siparişlerini çek
$query = "SELECT SiparisUrun.*, Siparis.Siparis_ID, Siparis.Siparis_Tarihi, Siparis.Siparis_Durumu, Urun.Urun_Adi 
          FROM SiparisUrun 
          JOIN Siparis ON SiparisUrun.Siparis_ID = Siparis.Siparis_ID 
          JOIN Urun ON SiparisUrun.Urun_ID = Urun.Urun_ID 
          WHERE Urun.Satici_ID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $satici_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Yönetimi</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Sipariş Yönetimi</h1>

        <?php if ($result->num_rows > 0): ?>
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
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['Siparis_ID']; ?></td>
                            <td><?php echo $row['Urun_Adi']; ?></td>
                            <td><?php echo $row['Miktar']; ?></td>
                            <td><?php echo $row['Fiyat']; ?></td>
                            <td><?php echo $row['Siparis_Tarihi']; ?></td>
                            <td><?php echo $row['Siparis_Durumu']; ?></td>
                            <td>
                                <form action="update_order_status.php" method="post">
                                    <input type="hidden" name="siparis_id" value="<?php echo $row['Siparis_ID']; ?>">
                                    <select name="siparis_durumu">
                                        <option value="Beklemede" <?php echo $row['Siparis_Durumu'] === 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                        <option value="Kargoda" <?php echo $row['Siparis_Durumu'] === 'Kargoda' ? 'selected' : ''; ?>>Kargoda</option>
                                        <option value="Teslim Edildi" <?php echo $row['Siparis_Durumu'] === 'Teslim Edildi' ? 'selected' : ''; ?>>Teslim Edildi</option>
                                    </select>
                                    <button type="submit">Güncelle</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Şu anda herhangi bir sipariş bulunmamaktadır.</p>
        <?php endif; ?>
    </div>
</body>
</html>
