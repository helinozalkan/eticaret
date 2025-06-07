<?php
session_start();

// Veritabanı bağlantısını ve temel ayarları dahil et
include_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Admin yetki kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?status=unauthorized");
    exit();
}

$message = '';
$message_type = 'success';

// Yorum Onaylama veya Reddetme İşlemi
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $yorum_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if ($yorum_id) {
        try {
            if ($action === 'approve') {
                // Yorumu onayla (Onay_Durumu = 1 yap)
                $stmt = $conn->prepare("UPDATE Yorumlar SET Onay_Durumu = 1 WHERE Yorum_ID = :yorum_id");
                $stmt->bindParam(':yorum_id', $yorum_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $message = "Yorum #" . $yorum_id . " başarıyla onaylandı.";
                }
            } elseif ($action === 'reject') {
                // Yorumu reddet (veritabanından sil)
                $stmt = $conn->prepare("DELETE FROM Yorumlar WHERE Yorum_ID = :yorum_id");
                $stmt->bindParam(':yorum_id', $yorum_id, PDO::PARAM_INT);
                if ($stmt->execute()) {
                    $message = "Yorum #" . $yorum_id . " başarıyla reddedildi ve silindi.";
                }
            }
        } catch (PDOException $e) {
            $message = "İşlem sırasında bir veritabanı hatası oluştu.";
            $message_type = 'danger';
            error_log("Admin Review Management Error: " . $e->getMessage());
        }
    }
}

// Onay bekleyen yorumları çek (Onay_Durumu = 0 olanlar)
try {
    $query_pending_reviews = "SELECT 
                                y.Yorum_ID,
                                y.Yorum_Metni,
                                y.Yorum_Tarihi,
                                u.Urun_Adi,
                                usr.username AS Kullanici_Adi
                              FROM Yorumlar y
                              JOIN Urun u ON y.Urun_ID = u.Urun_ID
                              JOIN users usr ON y.Kullanici_ID = usr.id
                              WHERE y.Onay_Durumu = 0
                              ORDER BY y.Yorum_Tarihi ASC";
    $stmt_reviews = $conn->prepare($query_pending_reviews);
    $stmt_reviews->execute();
    $pending_reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_reviews = [];
    $message = "Yorumlar çekilirken bir hata oluştu.";
    $message_type = 'danger';
    error_log("Admin Review Fetch Error: " . $e->getMessage());
}

$logged_in = isset($_SESSION['user_id']);
$username_session = $logged_in ? htmlspecialchars($_SESSION['username']) : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yorum Yönetimi - Admin Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f8f9fa; }
        .navbar-admin { background-color: rgb(34, 132, 17); }
        .main-container { background-color: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); margin-top: 20px; }
        .page-title { font-family: 'Playfair Display', serif; text-align: center; margin-bottom: 35px; }
        .review-card { border: 1px solid #e0e0e0; border-left-width: 4px; border-left-color: #ffc107; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
    <div class="container-fluid">
        <a class="navbar-brand d-flex ms-4" href="admin_dashboard.php"><div class="baslik fs-3">Admin Paneli</div></a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0" style="margin-left: 110px;">
                <li class="nav-item ps-3"><a class="nav-link" href="admin_dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Kontrol Paneli</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="admin_user.php"><i class="bi bi-people-fill me-1"></i>Kullanıcı Yönetimi</a></li>
                <li class="nav-item ps-3"><a class="nav-link" href="seller_verification.php"><i class="bi bi-patch-check-fill me-1"></i>Satıcı Doğrulama</a></li>
                <li class="nav-item ps-3"><a class="nav-link active" href="admin_review_management.php"><i class="bi bi-chat-square-text-fill me-1"></i>Yorum Yönetimi</a></li>
            </ul>
            <div class="d-flex me-3 align-items-center">
                <?php if ($logged_in): ?>
                    <i class="bi bi-person-circle text-white fs-4 me-2"></i>
                    <a href="logout.php" class="text-white" style="text-decoration: none;"><?php echo $username_session; ?> (Çıkış Yap)</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container main-container">
    <h1 class="page-title"><i class="bi bi-chat-square-text-fill me-2"></i>Onay Bekleyen Yorumlar</h1>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($pending_reviews)): ?>
        <div class="alert alert-success text-center">
            <i class="bi bi-check-circle-fill fs-3 d-block mb-2"></i>
            Onay bekleyen yeni bir yorum bulunmamaktadır.
        </div>
    <?php else: ?>
        <?php foreach ($pending_reviews as $review): ?>
            <div class="card review-card mb-3">
                <div class="card-body">
                    <blockquote class="blockquote mb-0">
                        <p>"<?php echo nl2br(htmlspecialchars($review['Yorum_Metni'])); ?>"</p>
                        <footer class="blockquote-footer">
                            <strong><?php echo htmlspecialchars($review['Kullanici_Adi']); ?></strong> tarafından 
                            <cite title="Source Title">"<?php echo htmlspecialchars($review['Urun_Adi']); ?>"</cite> ürününe yazıldı.
                            <small class="text-muted d-block"><?php echo date("d.m.Y H:i", strtotime($review['Yorum_Tarihi'])); ?></small>
                        </footer>
                    </blockquote>
                </div>
                <div class="card-footer bg-transparent border-top-0 text-end">
                    <a href="admin_review_management.php?action=approve&id=<?php echo $review['Yorum_ID']; ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg"></i> Onayla
                    </a>
                    <a href="admin_review_management.php?action=reject&id=<?php echo $review['Yorum_ID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu yorumu silmek istediğinizden emin misiniz?');">
                        <i class="bi bi-trash3-fill"></i> Reddet
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>