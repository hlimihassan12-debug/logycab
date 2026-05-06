<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /logycab/login.php');
    exit;
}