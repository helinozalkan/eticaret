<?php
// Logout page
session_start(); // Start the session

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Ensure session data is written and closed before redirection
session_write_close();

// Redirect to the homepage
header("Location: /eticaret/index.php");
exit(); // Stop script execution after redirection

