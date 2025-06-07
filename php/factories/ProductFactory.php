<?php
// php/factories/ProductFactory.php

require_once __DIR__ . '/../models/GenericProduct.php';
require_once __DIR__ . '/../models/CeramicProduct.php';
require_once __DIR__ . '/../models/DokumaProduct.php'; 
// Gelecekte eklenecek diğer ürün sınıflarını buraya dahil edebilirsiniz.

class ProductFactory {
    /**
     * Veritabanından gelen ürün verisine ve kategori adına göre
     * uygun ürün nesnesini oluşturur.
     *
     * @param array $productData Ürün bilgilerini içeren dizi
     * @return AbstractProduct
     */
    public static function create(array $productData): AbstractProduct {
        $categoryName = $productData['Kategori_Adi'] ?? 'default';

        switch ($categoryName) {
            case 'Seramik ve Çini':
                return new CeramicProduct($productData);
            
            case 'Dokuma Ürünler': // <-- YENİ EKLENEN CASE
                return new DokumaProduct($productData);

            default:
                // Herhangi bir kategoriye uymuyorsa genel ürün nesnesi oluştur
                return new GenericProduct($productData);
        }
    }
}