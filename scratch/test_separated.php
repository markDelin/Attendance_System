<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['type'] = 'end_of_year';
$_POST['content'] = 'Test Student';
require 'api/process_announcement.php';
?>
