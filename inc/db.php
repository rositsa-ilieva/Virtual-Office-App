<?php
$mysqli = new mysqli("localhost", "root", "", "virtual_office");
if ($mysqli->connect_error) {
    die("Грешка при връзка с базата: " . $mysqli->connect_error);
}
?>