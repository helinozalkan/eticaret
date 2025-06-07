<?php
// php/models/DokumaProduct.php

// Tüm ürünlerin türediği temel sınıfı dahil et
require_once 'AbstractProduct.php';

class DokumaProduct extends AbstractProduct {
    
    // İsteğe bağlı: Dokuma ürünlerine özel bir özellik ekleyebiliriz.
    protected $weaveType = 'El Dokuması';

    /**
     * Bu sınıfın hangi ürün türünü temsil ettiğini döndürür.
     * Bu isim, veritabanındaki kategori adıyla eşleşmelidir.
     */
    public function getProductType(): string {
        return 'Dokuma Ürünler';
    }

    // İsteğe bağlı: Dokuma türünü getiren özel bir metot.
    public function getWeaveType(): string {
        return $this->weaveType;
    }
}