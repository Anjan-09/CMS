<?php
$host = 'localhost';
$db   = 'complaint_system';
$user = 'root';
$pass = '';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$full_name = $_POST['full_name'] ?? 'Rasul Ghatane';
$email     = $_POST['email'] ?? 'user@user.com';
$phone     = $_POST['phone'] ?? '9845521545';
$password  = $_POST['password'] ?? '12345678';
$role      = $_POST['role'] ?? 'customer';

$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, email_verified, is_active) VALUES (?, ?, ?, ?, ?, 1, 1)");
$stmt->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $role);

if ($stmt->execute()) {
    echo "User created successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>