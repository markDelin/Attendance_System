<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['type'] = 'new_student';
$_POST['content'] = 'John Doe';
require 'api/process_announcement.php';
?>
