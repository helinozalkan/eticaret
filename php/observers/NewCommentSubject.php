<?php
// php/observers/NewCommentSubject.php - DÜZELTİLMİŞ HALİ

// Gerekli arayüz dosyasını dahil et
include_once 'ObserverInterfaces.php';

class NewCommentSubject implements SubjectInterface { // Değişiklik burada
    /**
     * Gözlemcilerin listesini tutan dizi.
     * @var ObserverInterface[]
     */
    private $observers = [];

    /**
     * Gözlemcilere iletilecek olan yeni yorum verisi.
     * @var array
     */
    private $newCommentData;

    public function attach(ObserverInterface $observer) { // Değişiklik burada
        $this->observers[] = $observer;
    }

    public function detach(ObserverInterface $observer) { // Değişiklik burada
        $key = array_search($observer, $this->observers, true);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    public function notify() {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    /**
     * Bu metot, asıl olayı tetikler. Yeni yorum verisini alır ve
     * tüm gözlemcilere haber verir (notify).
     * @param array $commentData
     */
    public function addNewComment(array $commentData) {
        $this->newCommentData = $commentData;
        $this->notify();
    }

    /**
     * Gözlemcilerin, güncellenen veriye erişmesini sağlayan metot.
     * @return array
     */
    public function getNewCommentData() {
        return $this->newCommentData;
    }
}