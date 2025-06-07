<?php
// php/models/GenericProduct.php
require_once 'AbstractProduct.php';

class GenericProduct extends AbstractProduct {
    public function getProductType(): string {
        return 'Genel Ürün';
    }
}