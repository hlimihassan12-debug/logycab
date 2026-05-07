<?php
session_start();
session_destroy();
header('Location: /logycab/login.php');
exit;