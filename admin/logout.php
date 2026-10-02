<?php
    require_once("../config.php");
    session_start();
    if(!isset($_SESSION['admin'])){
        header("Location: ".ADMIN_URL."login_admin.php");
        exit();
    }
    session_destroy();
    header("Location: ".ADMIN_URL."login_admin.php");
    exit();
?>