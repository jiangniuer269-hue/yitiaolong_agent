<?php
$lantian_ip = 'http://154.23.221.76:9212';
$duifang_ip = 'http://154.23.221.76:9205';
//154-160-97 155-98 156-99
$roomTemp = file_get_contents($lantian_ip.'/v1/game156/getCmdRoom');
$room = json_decode($roomTemp, true);
foreach ($room['data'] as &$v) {
    $room_id = 0;
    $groupid = $v['id'];
    if ($groupid == 155) {
        $room_id = 98;
    } elseif ($groupid == 156) {
        $room_id = 99;
    }elseif ($groupid == 160) {
        $room_id = 97;
    }
    if ($groupid == 155 || $groupid == 156) {
        $bootsNumberTemp = file_get_contents($duifang_ip.'/v1/game156/getBootsNumber?groupid=' . $room_id);
        $bootsNumberArr = json_decode($bootsNumberTemp, true);
        $v['boots_number'] = $bootsNumberArr['boots_number'];
    }
    if ($groupid == 160) {
        $bootsNumberTemp = file_get_contents($duifang_ip.'/v1/game156/getBootsNumber?groupid=' . $room_id);
        $bootsNumberArr = json_decode($bootsNumberTemp, true);
        $v['boots_number'] = $bootsNumberArr['boots_number'];
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<script type="text/javascript" src="./static/js/jquery.min.js"></script>
<head>
    <meta charset="UTF-8">
    <title>自动开奖按钮</title>

</head>
<body>
       <div style="margin-top: 10px;margin-bottom: 10px">
       <font style="font-size: larger;color:red"> 当前时间： <?php echo date('Y-m-d H:i:s'); ?></font>
         
        </div>
<?php

foreach ($room['data'] as $item) {
    if ($item['id'] == 156) {
        ?>
        <div style="margin-top: 10px;margin-bottom: 10px">
            <font style="font-size: larger;"><?php echo '(任务：156)  '.$item['groupname'] . '  ' . '当前靴号：' . $item['boots_number']; ?></font>
        </div>
        <div style="margin-top: 10px;margin-bottom: 10px">
            <button style="color: red;font-size: larger" onclick="begin_cmd('确定要开启自动开奖?',1)">
                开启自动开奖
            </button>
            <button style="color: blue;font-size: larger" onclick="begin_cmd('确定要停止自动开奖?',2)">
                停止自动开奖
            </button>
        </div>
        <?php
    }

    if ($item['id'] == 160) {
        ?>
        <div style="margin-top: 10px;margin-bottom: 10px">
            <font style="font-size: larger;"><?php echo '(任务：154)  '.$item['groupname']. '  ' . '当前靴号：' . $item['boots_number']; ?></font>
        </div>
        <div>
            <button style="color: red;font-size: larger" onclick="begin_cmd('确定要开启自动开奖?',3)">
                开启自动开奖
            </button>
            <button style="color: blue;font-size: larger" onclick="begin_cmd('确定要停止自动开奖?',4)">
                停止自动开奖
            </button>
        </div>
        <?php
    }

    if ($item['id'] == 155) {
        ?>
        <div style="margin-top: 10px;margin-bottom: 10px">
            <font style="font-size: larger;"><?php echo '(任务：155)  '.$item['groupname']. '  ' . '当前靴号：' . $item['boots_number']; ?></font>
        </div>
        <div>
            <button style="color: red;font-size: larger" onclick="begin_cmd('确定要开启自动开奖?',5)">
                开启自动开奖
            </button>
            <button style="color: blue;font-size: larger" onclick="begin_cmd('确定要停止自动开奖?',6)">
                停止自动开奖
            </button>
        </div>
        <?php
    }
}
?>

</body>
<script>
    // $(function () {
    function begin_cmd(notice, cmdtype) {
        var con = confirm(notice);
        if (con == true) {
            //请求参数
            var list = {"cmdtype": cmdtype};
            $.ajax({
                //请求方式
                type: "POST",
                //请求的媒体类型
                contentType: "application/json;charset=UTF-8",
                //请求地址
                url: "http://154.23.221.76:9527/v1/game156/docmd",
                //数据，json字符串
                data: JSON.stringify(list),
                //请求成功
                success: function (result) {
                    console.log(result);
                    alert(result.msg);
                    return false;
                },
                //请求失败，包含具体的错误信息
                error: function (e) {
                    console.log(e.status);
                    console.log(e.responseText);
                }
            });
        }
    }

    // })
</script>

</html>