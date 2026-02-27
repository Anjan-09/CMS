<?php
// Run this ONCE at http://localhost/cms/reset_admin.php
// Then DELETE it immediately after

$host = 'localhost';
$dbname = 'complaint_system';
$dbuser = 'root';
$dbpass = '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);

$newPassword = 'OneTwo3!';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@123.com'")
    ->execute([$hash]);

echo '<h2 style="font-family:sans-serif;color:green">✓ Admin password reset successfully!</h2>';
echo '<p style="font-family:sans-serif">Email: <strong>admin@123.com</strong></p>';
echo '<p style="font-family:sans-serif">Password: <strong>OneTwo3!</strong></p>';
echo '<p style="font-family:sans-serif;color:red"><strong>DELETE this file now, then <a href="./login.php">go to login</a>.</strong></p>';