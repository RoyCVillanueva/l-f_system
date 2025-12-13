<?php
    //resume session here to fetch session values
    session_start();

    // if user already logged in
    if (isset($_SESSION['user']) && ($_SESSION['user'] == 'user' || $_SESSION['user'] == 'admin')){
        // for now will send user to view product
        header('location: object/dashboard.php');
    }else{
        // if user is not log in, send them to login
        header('location: object/login.php');
    }