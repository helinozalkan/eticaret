<?php
// php/models/AbstractProduct.php

abstract class AbstractProduct {
    protected $id;
    protected $name;
    protected $price;
    protected $description;
    protected $imageUrl;
    protected $stock;
    protected $storeName; // <-- YENİ EKLENEN ÖZELLİK

    public function __construct(array $data) {
        $this->id = $data['Urun_ID'];
        $this->name = $data['Urun_Adi'];
        $this->price = $data['Urun_Fiyati'];
        $this->description = $data['Urun_Aciklamasi'];
        $this->imageUrl = $data['Urun_Gorseli'];
        $this->stock = $data['Stok_Adedi'];
        // YENİ: Gelen veri dizisinden mağaza adını da alıp nesneye atıyoruz.
        $this->storeName = $data['Magaza_Adi'] ?? 'Belirtilmemiş'; 
    }

    // Ortak Metotlar
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getPrice() { return $this->price; }
    public function getDescription() { return $this->description; }
    public function getImageUrl() { return $this->imageUrl; }
    public function getStock() { return $this->stock; }
    public function getStoreName() { return $this->storeName; } // <-- YENİ EKLENEN METOT

    // Her ürün türünün farklı bir şekilde uygulayabileceği soyut bir metot
    abstract public function getProductType(): string;
}