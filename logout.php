<?php
require 'includes/config.php';
log_activity($pdo,'Logout: '.($_SESSION['full_name']??''));
session_unset(); session_destroy();
header('Location:login.php'); exit;
