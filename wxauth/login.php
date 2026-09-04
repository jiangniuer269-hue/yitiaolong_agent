<?php
    try {
        $type = $_GET['type'];
        $id = $_GET['id'];
        $rd = $_GET['rd'];
        $playid = $_GET['playid'];
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=wx3088ce836b07014c&redirect_uri=http://stxqh.cn/redirect.php?type='.$type.'%26id='.$id.'%26rd='.$rd.'%26playid='.$playid.'&response_type=code&scope=snsapi_userinfo';
        echo "<SCRIPT LANGUAGE=\"JavaScript\">location.href='$url'</SCRIPT>";
    } catch (Exception $e) {
        echo $e->getMessage();
        // die(); // 终止异常
    }
