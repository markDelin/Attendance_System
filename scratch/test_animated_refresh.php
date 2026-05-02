<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['type'] = 'system_refresh';
require 'api/process_announcement.php';
?>
