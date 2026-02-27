<?php
// Entry point — redirect to login or dashboard
require 'includes/config.php';
header('Location:'.(logged_in()?'dashboard.php':'login.php'));
exit;
