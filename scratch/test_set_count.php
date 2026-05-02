<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['type'] = 'student_count';
$_POST['content'] = 'A';
require 'api/process_announcement.php';
?>
