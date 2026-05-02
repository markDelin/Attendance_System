<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['type'] = 'student_count';
require 'api/process_announcement.php';
?>
