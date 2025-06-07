<?php
// php/models/CeramicProduct.php
require_once 'AbstractProduct.php';

class CeramicProduct extends AbstractProduct {
    // Seramiğe özel bir özellik (örneğin)
    protected $material = 'Porselen';

    public function getProductType(): string {
        return 'Seramik & Çini';
    }

    // Seramiğe özel bir metot
    public function getMaterial(): string {
        return $this->material;
    }
}