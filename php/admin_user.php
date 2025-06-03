<?php
//admin panel sayfası
session_start();
include('../database.php');

// Giriş yapmış kullanıcı bilgilerini kontrol et
$logged_in = isset($_SESSION['user_id']); // Kullanıcı giriş yapmış mı kontrol et
$username = $logged_in ? $_SESSION['username'] : null; // Kullanıcı adını al



$query = "SELECT * FROM users WHERE role='seller' OR role='customer'";
$result = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Kullanıcı Yönetimi</title>
     <!-- !BOOTSTRAP'S CSS-->
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- !BOOTSTRAP'S CSS-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
     <link href="https://fonts.googleapis.com/css2?family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <link rel="stylesheet" href="css/css.css">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courgette&family=Edu+AU+VIC+WA+NT+Hand:wght@400..700&family=Montserrat:wght@100..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Roboto+Slab:wght@100..900&display=swap" rel="stylesheet">
    <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
/>
 <script src="https://code.jquery.com/jquery-1.8.2.min.js" integrity="sha256-9VTS8JJyxvcUR+v+RTLTsd0ZWbzmafmlzMmeZO9RFyk=" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgb(34, 132, 17);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="#" style="margin-left: 5px;">
            <div class="baslik fs-3"> Admin </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1 bg-custom" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
            <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="admin_dashboard.php">
                        Admin Paneli
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="admin_user.php">
                        Kullanıcı Yönetimi
                    </a>
                </li>

                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="seller_verification.php">
                        Satıcı Doğrulama
                    </a>
                </li>
                <li class="nav-item ps-3">
                    <a id="navbarDropdown" class="nav-link" href="product_verification.php">
                        Ürün Doğrulama
                    </a>
                </li>
            </ul>
            <div class="d-flex me-3" style="margin-left: 145px;">
    <i class="bi bi-person-circle text-white fs-4"></i>
    <?php if (isset($_SESSION['username'])): ?>
        <!-- Kullanıcı giriş yaptıysa -->
        <a href="logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            <?php echo htmlspecialchars($_SESSION['username']); ?> <!-- Kullanıcı adı gösteriliyor -->
        </a>
    <?php else: ?>
        <!-- Kullanıcı giriş yapmamışsa -->
        <a href="login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">
            Giriş Yap
        </a>
    <?php endif; ?>
</div>

        </div>
    </div>
</nav>

<div class="container mt-5">
    <h1>Admin Kullanıcı Yönetimi</h1>
    <h2>Kullanıcılar</h2>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Adı Soyadı</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo $user['username']; ?></td>
                <td><?php echo $user['email']; ?></td>
                <td><?php echo $user['role']; ?></td>
                <td>
        <?php 
        $status = $user['status'] ?? 'Pasif';
        echo $status == 1 ? 'Aktif' : 'Pasif';
        ?>
    </td>
                <td>
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Düzenle</a>
                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm">Sil</a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <!-- !BOOTSTRAP'S jS-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- !BOOTSTRAP'S jS-->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
</body>
</html>
