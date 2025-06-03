<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Geçmiş bir zaman ayarlayarak çerezi geçersiz kıl
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

$redirect_url = "/eticaret/index.php"; // Ana sayfa varsayalım
header("Location: " . $redirect_url);

exit();

