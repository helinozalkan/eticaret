<nav class="navbar navbar-expand-lg navbar-dark" style="background-color:rgb(91, 140, 213);">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="/eticaret/index.php">
            <img src="/eticaret/images/ana_logo.png" alt="Logo" width="40" height="40" class="align-text-top">
            <div class="baslik fs-3" style="color: white; text-decoration: none;">ETİCARET</div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse mt-1" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <?php
                // Kullanıcının rolüne göre menü linklerini belirle
                $role = $_SESSION['role'] ?? 'guest'; // Eğer rol yoksa misafir olarak kabul et

                if ($role === 'seller') {
                    // Satıcı Menüsü
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/seller_dashboard.php">Satıcı Paneli</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/seller_manage.php">Mağaza Yönetimi</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/manage_product.php">Ürün Yönetimi</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/order_manage.php">Sipariş Yönetimi</a></li>';
                } elseif ($role === 'admin') {
                    // Admin Menüsü
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/admin_dashboard.php">Kontrol Paneli</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/admin_user.php">Kullanıcı Yönetimi</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/seller_verification.php">Satıcı Doğrulama</a></li>';
                } else {
                    // Müşteri veya Misafir Menüsü
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/girisimciler.php">Girişimcilerimiz</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/customer_orders.php">Sipariş İşlemleri</a></li>';
                    echo '<li class="nav-item ps-3"><a class="nav-link" href="/eticaret/php/seller_register.php">Satıcı Ol</a></li>';
                }
                ?>
            </ul>
            <div class="d-flex align-items-center">
                <a href="/eticaret/php/favourite.php" class="text-white fs-5 me-3"><i class="bi bi-heart"></i></a>
                <a href="/eticaret/php/my_cart.php" class="text-white fs-5 me-4"><i class="bi bi-cart3"></i></a>
            </div>
            <div class="d-flex me-3 align-items-center">
                <i class="bi bi-person-circle text-white fs-4"></i>
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="/eticaret/php/logout.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;"><?= htmlspecialchars($_SESSION['username']); ?> (Çıkış Yap)</a>
                <?php else: ?>
                    <a href="/eticaret/php/login.php" class="text-white mt-2 ms-2" style="font-size: 15px; text-decoration: none;">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>