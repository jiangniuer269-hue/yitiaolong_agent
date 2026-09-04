<?php
try {
    $query = $_SERVER['QUERY_STRING'];
    $redirectdomain = 'http://'.$_GET['rd'].'/';
    $url = $redirectdomain.'?'.$query.'#/login';
    echo "<SCRIPT LANGUAGE=\"JavaScript\">location.href='$url'</SCRIPT>";
} catch (Exception $e) {
    echo $e->getMessage();
    // die(); // 终止异常
}