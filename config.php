<?php
// Hostinger usually uses 'localhost' unless your DB is on a remote server.
$host = 'localhost'; 
$username = 'u924420507_root';
$password = '/APp1HNvB7';
$database = 'u924420507_senior_system';

// Use a try-catch block or a more refined error reporting for production
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4"); // Essential for supporting special characters/emojis
} catch (Exception $e) {
    // On Hostinger, don't reveal full credentials in error messages for security
    error_log($e->getMessage());
    die("Connection failed. Please check your configuration.");
}

// Session start should ideally be at the very top before any logic
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Functions
 */
function generateQRCode($data) {
    // API is fine, but ensure the data is properly escaped
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($data);
}

function calculateAge($birthdate) {
    if (empty($birthdate)) return 0;
    $birthDate = new DateTime($birthdate);
    $currentDate = new DateTime();
    return $currentDate->diff($birthDate)->y;
}
?>