<?php
// order_success.php - Geliştirilmiş Sipariş Başarılı Sayfası
session_start();

// order.php tarafından session'a atanan son sipariş ID'sini alalım
$order_id = $_SESSION['last_order_id'] ?? null;
// Sipariş numarasını gösterdikten sonra session'dan temizleyelim ki sayfayı yenileyince görünmesin
unset($_SESSION['last_order_id']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Başarılı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/eticaret/css/css.css"> <style>
        body {
            background-color: #f8f9fa;
        }
        .success-container {
            max-width: 600px;
        }
        .success-icon {
            font-size: 5rem; /* 80px */
            color: #198754; /* Bootstrap success green */
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(155, 10, 109) ;">
        <div class="container-fluid">
            <a class="navbar-brand d-flex ms-4" href="../index.php">
                <div class="baslik fs-3" style="color:white; text-decoration:none;">ETİCARET</div>
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    </ul>
                <div class="d-flex me-3 align-items-center">
                    <i class="bi bi-person-circle text-white fs-4"></i>
                    <?php if (isset($_SESSION['username'])): ?>
                        <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
                            <?php echo htmlspecialchars($_SESSION['username']); ?> (Çıkış Yap)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <div class="container success-container text-center my-5">
        <div class="bg-white p-5 rounded-3 shadow-sm">
            
            <div class="success-icon mb-3">
                <i class="bi bi-check-circle-fill"></i>
            </div>

            <h1 class="display-5 fw-bold">Siparişiniz Başarıyla Alındı!</h1>
            
            <p class="lead text-muted mt-3">
                Teşekkür ederiz. Siparişinizi en kısa sürede hazırlayıp kargoya vereceğiz.
            </p>

            <?php if ($order_id): ?>
                <p class="mt-4">
                    <strong>Sipariş Numaranız:</strong>
                    <span class="badge bg-secondary fs-6">#<?php echo htmlspecialchars($order_id); ?></span>
                </p>
            <?php endif; ?>
            
            <hr class="my-4">

            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                <a href="customer_orders.php" class="btn btn-primary btn-lg px-4">Siparişlerimi Görüntüle</a>
                <a href="../index.php" class="btn btn-outline-secondary btn-lg px-4">Alışverişe Devam Et</a>
            </div>
        </div>
    </div>

</body>
</html>