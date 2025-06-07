<?php
// php/observers/NotificationObservers.php - DÜZELTİLMİŞ HALİ

// Gerekli dosyaları dahil et
include_once 'ObserverInterfaces.php';
// NewCommentSubject sınıfı bu dosyada doğrudan kullanılmadığı için include etmeye gerek yok,
// ancak tip kontrolü (instanceof) için kalabilir.
include_once 'NewCommentSubject.php';

/**
 * Admin'e bildirim gönderen Observer.
 */
class AdminNotifierObserver implements ObserverInterface { // Değişiklik burada
    public function update(SubjectInterface $subject) { // Değişiklik burada
        if ($subject instanceof NewCommentSubject) {
            $commentData = $subject->getNewCommentData();
            $urunId = $commentData['urun_id'];
            $yorumMetni = substr($commentData['yorum_metni'], 0, 50) . '...';
            
            $notificationMessage = "Onay Bekleyen Yorum: ID'si {$urunId} olan ürüne yeni bir yorum yapıldı. Yorum: '{$yorumMetni}'";
            error_log("ADMIN NOTIFICATION: " . $notificationMessage);
        }
    }
}

/**
 * Ürün sahibine (satıcıya) bildirim gönderen Observer.
 */
class ProductOwnerNotifierObserver implements ObserverInterface { // Değişiklik burada
    private $dbConnection;

    public function __construct(PDO $conn) {
        $this->dbConnection = $conn;
    }

    public function update(SubjectInterface $subject) { // Değişiklik burada
        if ($subject instanceof NewCommentSubject) {
            $commentData = $subject->getNewCommentData();
            $urunId = $commentData['urun_id'];

            try {
                $sql = "SELECT s.User_ID, u.email 
                        FROM Urun ur 
                        JOIN Satici s ON ur.Satici_ID = s.Satici_ID 
                        JOIN users u ON s.User_ID = u.id 
                        WHERE ur.Urun_ID = :urun_id";

                $stmt = $this->dbConnection->prepare($sql);
                $stmt->bindParam(':urun_id', $urunId, PDO::PARAM_INT);
                $stmt->execute();
                $seller = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($seller) {
                    $notificationMessage = "Mağazanızdaki ID'si {$urunId} olan ürününüze yeni bir yorum yapıldı.";
                    error_log("SELLER NOTIFICATION (User ID: {$seller['User_ID']}, Email: {$seller['email']}): " . $notificationMessage);
                }
            } catch (PDOException $e) {
                error_log("ProductOwnerNotifierObserver PDOException: " . $e->getMessage());
            }
        }
    }
}