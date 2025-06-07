<?php
/**
 * customer_review.php
 * Kullanıcının bir ürün için yorum ve puan göndermesini sağlar.
 * Yorum başarıyla kaydedildiğinde, Observer Tasarım Deseni'ni kullanarak
 * ilgili bildirimleri (Admin, Satıcı vb.) tetikler.
 */

// Geliştirme aşamasında hataları görmek için (üretimde bu ayarlar farklı olmalı)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Gerekli dosyaları dahil et
include_once '../database.php';
include_once 'observers/ObserverInterfaces.php';
include_once 'observers/NewCommentSubject.php';
include_once 'observers/NotificationObservers.php';

// Veritabanı bağlantısını al
$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');

// Oturum ve ürün bilgilerini hazırla
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $logged_in ? (int)$_SESSION['user_id'] : null;
$current_user_role = $logged_in ? $_SESSION['role'] : null;
$username = $logged_in && isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : null;

$product_to_review_id = null;
$product_name_to_review = "Bilinmeyen Ürün";
$message = "";

// GET parametresinden ürün ID'sini al ve doğrula
if (isset($_GET['product_id']) && filter_var($_GET['product_id'], FILTER_VALIDATE_INT)) {
    $product_to_review_id = (int)$_GET['product_id'];

    try {
        // Yorum yapılacak ürünün adını veritabanından çek
        $stmt_product_name = $conn->prepare("SELECT Urun_Adi FROM Urun WHERE Urun_ID = :urun_id");
        $stmt_product_name->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
        $stmt_product_name->execute();
        $product_data = $stmt_product_name->fetch(PDO::FETCH_ASSOC);

        if ($product_data) {
            $product_name_to_review = htmlspecialchars($product_data['Urun_Adi']);
        } else {
            $message = "Yorum yapılacak ürün bulunamadı.";
        }
    } catch (PDOException $e) {
        error_log("customer_review.php: Ürün adı çekme hatası: " . $e->getMessage());
        $message = "Ürün bilgileri yüklenirken bir sorun oluştu.";
    }
} else {
    $message = "Yorum yapılacak ürün belirtilmemiş.";
}

// Yetki kontrolü: Sadece giriş yapmış müşteriler yorum yapabilir
if (!$logged_in || $current_user_role !== 'customer') {
    $_SESSION['review_error_message'] = "Yorum yapabilmek için müşteri olarak giriş yapmanız gerekmektedir.";
    $redirect_url = "login.php?redirect=product_detail.php?id=" . $product_to_review_id;
    header(HTTP_HEADER_LOCATION . $redirect_url);
    exit;
}

// Form gönderilmişse işlemleri yap
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment_text = isset($_POST['comment_text']) ? trim(htmlspecialchars($_POST['comment_text'])) : '';

    // Gelen verilerin geçerliliğini kontrol et
    if ($product_to_review_id && $current_user_id && $rating >= 1 && $rating <= 5 && !empty($comment_text)) {
        try {
            // 1. Yorumu veritabanına ekle
            $sql_insert_review = "INSERT INTO Yorumlar (Urun_ID, Kullanici_ID, Puan, Yorum_Metni, Yorum_Tarihi, Onay_Durumu)
                                  VALUES (:urun_id, :kullanici_id, :puan, :yorum_metni, NOW(), 0)";

            $stmt_insert = $conn->prepare($sql_insert_review);
            $stmt_insert->bindParam(':urun_id', $product_to_review_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':kullanici_id', $current_user_id, PDO::PARAM_INT);
            $stmt_insert->bindParam(':puan', $rating, PDO::PARAM_INT);
            $stmt_insert->bindParam(':yorum_metni', $comment_text, PDO::PARAM_STR);

            if ($stmt_insert->execute()) {

                // --- OBSERVER PATTERN DEVREYE GİRİYOR ---
                
                // Adım 1: Subject (Konu) nesnesini oluştur.
                $newCommentSubject = new NewCommentSubject();

                // Adım 2: Observer (Gözlemci) nesnelerini oluştur ve Subject'e ekle (abone yap).
                $adminObserver = new AdminNotifierObserver();
                $ownerObserver = new ProductOwnerNotifierObserver($conn); // DB bağlantısı gerektiren Observer

                $newCommentSubject->attach($adminObserver);
                $newCommentSubject->attach($ownerObserver);

                // Adım 3: Subject'in durumunu değiştir ve tüm gözlemcilere haber ver.
                $newCommentData = [
                    'urun_id'      => $product_to_review_id,
                    'kullanici_id' => $current_user_id,
                    'yorum_metni'  => $comment_text
                ];
                $newCommentSubject->addNewComment($newCommentData);
                
                // --- OBSERVER PATTERN SONU ---

                // Başarılı işlem sonrası kullanıcıyı yönlendir
                $_SESSION['success_message'] = "Yorumunuz başarıyla gönderildi ve onay bekliyor.";
                header(HTTP_HEADER_LOCATION . "product_detail.php?id=" . $product_to_review_id);
                exit;

            } else {
                $message = "Yorumunuz kaydedilirken bir hata oluştu.";
            }
        } catch (PDOException $e) {
            error_log("customer_review.php: Yorum kaydetme hatası: " . $e->getMessage());
            $message = "Yorumunuz kaydedilirken teknik bir sorun oluştu.";
        }
    } else {
        $message = "Lütfen puan seçin ve yorumunuzu yazın.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Yorumu Yap - <?php echo htmlspecialchars($product_name_to_review); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/css.css">
    <style>
        body { background-color: #f8f9fa; }
        .form-label { font-weight: 500; }
        
        /* --- YILDIZLI PUANLAMA SİSTEMİ İÇİN CSS --- */
        .rating-wrapper {
            display: flex;
            flex-direction: row-reverse; /* Yıldızları ters sırada dizeriz (CSS hilesi için) */
            justify-content: flex-end; /* Ters çevirince solda hizalanır */
        }

        /* Gerçek radio butonları gizle */
        .rating-wrapper input[type="radio"] {
            display: none;
        }

        /* Yıldız olarak görünecek label'lar */
        .rating-wrapper label {
            font-size: 2rem;
            color: #e0e0e0; /* Boş yıldız rengi */
            cursor: pointer;
            transition: color 0.2s ease;
            margin: 0 2px;
        }

        /* Bir yıldızın üzerine gelindiğinde, o ve ondan "önceki" (aslında HTML'de sonraki) tüm yıldızların rengini değiştir */
        .rating-wrapper label:hover,
        .rating-wrapper label:hover ~ label {
            color: #ffc107;
        }

        /* Bir radio buton seçildiğinde, onun label'ı ve ondan "önceki" tüm labelların rengini kalıcı olarak değiştir */
        .rating-wrapper input[type="radio"]:checked ~ label {
            color: #ffc107;
        }
    </style>
</head>
<body>
    
    <?php // include_once 'includes/navbar.php'; // Navbar'ı buradan dahil edebilirsiniz ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Ürün Değerlendirmesi Yap</h4>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="card-title"><?php echo htmlspecialchars($product_name_to_review); ?></h5>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <form action="customer_review.php?product_id=<?php echo $product_to_review_id; ?>" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Puanınız</label>
                                <div class="rating-wrapper">
                                    <input type="radio" id="rate-5" name="rating" value="5" required>
                                    <label for="rate-5" class="bi bi-star-fill"></label>
                                    <input type="radio" id="rate-4" name="rating" value="4" required>
                                    <label for="rate-4" class="bi bi-star-fill"></label>
                                    <input type="radio" id="rate-3" name="rating" value="3" required>
                                    <label for="rate-3" class="bi bi-star-fill"></label>
                                    <input type="radio" id="rate-2" name="rating" value="2" required>
                                    <label for="rate-2" class="bi bi-star-fill"></label>
                                    <input type="radio" id="rate-1" name="rating" value="1" required>
                                    <label for="rate-1" class="bi bi-star-fill"></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="comment_text" class="form-label">Yorumunuz</label>
                                <textarea class="form-control" id="comment_text" name="comment_text" rows="4" required></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary w-100">Yorumu Gönder</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php // include_once 'includes/footer.php'; // Footer'ı buradan dahil edebilirsiniz ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>