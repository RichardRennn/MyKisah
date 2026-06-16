<?php
require_once 'includes/config.php';

// Destroy session
session_destroy();
redirect('index.php');
require_once 'includes/config.php';
session_destroy();
header("Location: " . SITE_URL . "/index.php");
exit();
?>