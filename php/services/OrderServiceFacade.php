<?php
// php/services/OrderServiceFacade.php

class OrderServiceFacade {
    private $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Sipariş verme işleminin tüm adımlarını yöneten ana metot.
     * @param int $userId Siparişi veren kullanıcının ID'si
     * @param array $postData Formdan gelen teslimat bilgileri
     * @return int Yeni oluşturulan siparişin ID'si
     * @throws Exception İşlem sırasında bir hata olursa
     */
    public function placeOrder(int $userId, array $postData): int {
        try {
            // 1. Müşteri ID'sini al
            $stmt_musteri = $this->conn->prepare("SELECT Musteri_ID FROM musteri WHERE User_ID = :user_id");
            $stmt_musteri->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt_musteri->execute();
            $musteri_data = $stmt_musteri->fetch(PDO::FETCH_ASSOC);

            if (!$musteri_data) {
                throw new Exception("Sipariş oluşturulurken müşteri profiliniz bulunamadı.");
            }
            $musteriId = $musteri_data['Musteri_ID'];

            // 2. Sepetteki ürünleri, stok ve durum kontrolü için çek
            $stmt_cart_items = $this->conn->prepare("SELECT s.Urun_ID, s.Miktar, u.Urun_Adi, u.Urun_Fiyati, u.Stok_Adedi, u.Aktiflik_Durumu FROM Sepet s JOIN Urun u ON s.Urun_ID = u.Urun_ID WHERE s.Musteri_ID = :musteri_id");
            $stmt_cart_items->bindParam(':musteri_id', $musteriId, PDO::PARAM_INT);
            $stmt_cart_items->execute();
            $cartItems = $stmt_cart_items->fetchAll(PDO::FETCH_ASSOC);

            if (empty($cartItems)) {
                throw new Exception("Sepetiniz boş. Sipariş oluşturmak için lütfen ürün ekleyin.");
            }
            
            // 3. Stok kontrolü yap ve toplam tutarı hesapla
            $siparisTutari = 0;
            foreach ($cartItems as $item) {
                if ($item['Aktiflik_Durumu'] != 1) throw new Exception("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün artık satışta değil.");
                if ($item['Stok_Adedi'] < $item['Miktar']) throw new Exception("Sepetinizdeki '" . htmlspecialchars($item['Urun_Adi']) . "' adlı ürün için yeterli stok bulunmamaktadır.");
                $siparisTutari += (float)$item['Urun_Fiyati'] * $item['Miktar'];
            }

            // 4. Veritabanı Transaction'ını başlat
            $this->conn->beginTransaction();

            // 5. Sipariş ana kaydını oluştur
            $shipping_name = htmlspecialchars($postData['shipping_name'] ?? '');
            $shipping_address = htmlspecialchars($postData['shipping_address'] ?? '');
            $shipping_city = htmlspecialchars($postData['shipping_city'] ?? '');
            $shipping_zip = htmlspecialchars($postData['shipping_zip'] ?? '');
            $teslimat_adresi = "$shipping_name, $shipping_address, $shipping_zip $shipping_city";
            $fatura_adresi = isset($postData['billing_same_as_shipping']) ? $teslimat_adresi : (htmlspecialchars($postData['billing_name'] ?? '') . ", " . htmlspecialchars($postData['billing_address'] ?? ''));

            $stmt_order = $this->conn->prepare("INSERT INTO Siparis (Musteri_ID, Siparis_Tarihi, Siparis_Tutari, Teslimat_Adresi, Fatura_Adresi, Siparis_Durumu) VALUES (:musteri_id, CURDATE(), :siparis_tutari, :teslimat_adresi, :fatura_adresi, 'Beklemede')");
            $stmt_order->execute([
                ':musteri_id' => $musteriId,
                ':siparis_tutari' => $siparisTutari,
                ':teslimat_adresi' => $teslimat_adresi,
                ':fatura_adresi' => $fatura_adresi
            ]);
            $siparisId = $this->conn->lastInsertId();

            // 6. Sipariş ürünlerini ekle ve stokları güncelle
            $stmt_order_product = $this->conn->prepare("INSERT INTO SiparisUrun (Siparis_ID, Urun_ID, Miktar, Fiyat) VALUES (:siparis_id, :urun_id, :miktar, :fiyat)");
            $stmt_update_stock = $this->conn->prepare("UPDATE Urun SET Stok_Adedi = Stok_Adedi - :miktar WHERE Urun_ID = :urun_id");

            foreach ($cartItems as $item) {
                $stmt_order_product->execute([':siparis_id' => $siparisId, ':urun_id' => $item['Urun_ID'], ':miktar' => $item['Miktar'], ':fiyat' => $item['Urun_Fiyati']]);
                $stmt_update_stock->execute([':miktar' => $item['Miktar'], ':urun_id' => $item['Urun_ID']]);
            }

            // 7. Sepeti temizle
            $stmt_clear_cart = $this->conn->prepare("DELETE FROM Sepet WHERE Musteri_ID = :musteri_id");
            $stmt_clear_cart->bindParam(':musteri_id', $musteriId, PDO::PARAM_INT);
            $stmt_clear_cart->execute();
            
            // 8. Her şey yolundaysa, işlemi onayla
            $this->conn->commit();
            
            return (int)$siparisId;

        } catch (Exception $e) {
            // 9. Herhangi bir hata olursa, tüm işlemleri geri al
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            // Hatayı tekrar fırlatarak çağıran kodun haberdar olmasını sağla
            throw $e;
        }
    }
}