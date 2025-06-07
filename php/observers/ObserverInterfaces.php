<?php
// php/observers/ObserverInterfaces.php - DÜZELTİLMİŞ VE DOĞRU HALİ

/**
 * Projemize özel Subject arayüzü. Gözlemlenecek olan nesnenin
 * uyması gereken kontratı tanımlar. PHP'nin dahili SplSubject arayüzü ile
 * isim çakışması yaşanmaması için kendi ismimizi (SubjectInterface) kullanıyoruz.
 */
interface SubjectInterface {
    /**
     * Bir gözlemci (observer) ekler.
     * @param ObserverInterface $observer
     */
    public function attach(ObserverInterface $observer);

    /**
     * Bir gözlemciyi (observer) çıkarır.
     * @param ObserverInterface $observer
     */
    public function detach(ObserverInterface $observer);

    /**
     * Tüm gözlemcilere durum değişikliğini bildirir.
     */
    public function notify();
}

/**
 * Projemize özel Observer arayüzü. Gözlemci nesnelerin
 * uyması gereken kontratı tanımlar.
 */
interface ObserverInterface {
    /**
     * Subject'ten bir güncelleme alır.
     * @param SubjectInterface $subject
     */
    public function update(SubjectInterface $subject);
}