<?php
// order.php - Sipariş oluşturma ve işleme mantığı (Facade ile basitleştirildi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once '../database.php';
// Yeni Facade sınıfımızı dahil ediyoruz
include_once __DIR__ . '/services/OrderServiceFacade.php';

$db = Database::getInstance();
$conn = $db->getConnection();

define('HTTP_HEADER_LOCATION', 'Location: ');

// 1. Kullanıcı Giriş ve Rol Kontrolü
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header(HTTP_HEADER_LOCATION . "login.php?status=unauthorized");
    exit();
}

// Facade nesnesini oluştur
$orderFacade = new OrderServiceFacade($conn);

try {
    // 2. Facade üzerinden sipariş işlemini başlat
    $newOrderId = $orderFacade->placeOrder($_SESSION['user_id'], $_POST);
    
    // 3. Başarılı olursa, sipariş ID'sini session'a kaydet ve başarı sayfasına yönlendir
    $_SESSION['last_order_id'] = $newOrderId;
    header(HTTP_HEADER_LOCATION . "order_success.php");
    exit();

} catch (Exception $e) {
    // 4. Hata olursa, hatayı session'a kaydet ve checkout sayfasına geri yönlendir
    error_log("order.php Exception: " . $e->getMessage());
    $_SESSION['order_error_message'] = $e->getMessage();
    header(HTTP_HEADER_LOCATION . "checkout.php?status=order_failed");
    exit();
}