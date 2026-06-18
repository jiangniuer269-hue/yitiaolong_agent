<?php
$pnumber = $_GET['kkw'];
$url = "http://zb.kkw888.live:30008/?code=huojian&desk=".$pnumber."&t=iframe#/game";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>直播live</title>
    <style type="text/css">
        html, body {
            background-color: #fff;
            text-align: center;
            font-family: "Microsoft Yahei", Georgia, Serif;
            font-size: 14px;
        }

        .box {
            background-color: #fff;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="box">
   
    <iframe  name="myiframe" id="myrame" src="<?php echo $url;?>" frameborder="0" width="1000"
            height="900" scrolling="no">


    </iframe>
     <img style="position: relative;top: -650px; left: 300px;width:350px;height:350px" src ="./bg_login.png" />
      
  
</div>
</body>
</html>