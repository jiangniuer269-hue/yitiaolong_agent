<?php
//header('Access-Control-Allow-Origin: http://www.baidu.com'); //设置http://www.baidu.com允许跨域访问
//header('Access-Control-Allow-Headers: X-Requested-With,X_Requested_With'); //设置允许的跨域header
date_default_timezone_set("Asia/chongqing");
error_reporting(E_ERROR);
header("Content-Type: text/html; charset=utf-8");

$CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", file_get_contents("config.json")), true);
$action = $_GET['action'];
$port = $_SERVER['SERVER_PORT'];
/*$domainArr = [
    '9208' =>'http://27.124.46.84:9208',
    '9212' =>'http://27.124.46.84:9532',
    '5168' =>'http://27.124.46.84:9784',
    '5154' =>'http://27.124.46.84:5154',
    '6169' =>'http://27.124.46.84:6169',
    '9202' =>'http://27.124.46.84:9202',
    '9205' =>'http://27.124.46.84:2025',
    '9206' =>'http://27.124.46.84:9206',
    '9209' =>'http://27.124.46.84:9589',
    '9210' =>'http://27.124.46.84:9210',
    '9211' =>'http://27.124.46.84:9211',
    '9213' =>'http://27.124.46.84:9823',
];*/
$domainArr = [
    '9208' =>'http://154.23.221.76:9208',
    '9212' =>'http://154.23.221.76:9532',
    '5168' =>'http://154.23.221.76:5168',
    '5154' =>'http://154.23.221.76:5154',
    '6169' =>'http://154.23.221.76:6169',
    '9202' =>'http://154.23.221.76:9202',
    '9205' =>'http://154.23.221.76:2025',
    '9206' =>'http://154.23.221.76:9206',
    '9209' =>'http://154.23.221.76:9589',
    '9210' =>'http://154.23.221.76:9210',
    '9211' =>'http://154.23.221.76:9211',
    '9213' =>'http://154.23.221.76:9823',
];
switch ($action) {
    case 'config':
        $result =  json_encode($CONFIG);
        break;

    /* 上传图片 */
    case 'uploadimage':
    /* 上传涂鸦 */
    case 'uploadscrawl':
    /* 上传视频 */
    case 'uploadvideo':
    /* 上传文件 */
    case 'uploadfile':
        $result = include("action_upload.php");
        break;

    /* 列出图片 */
    case 'listimage':
        $result = include("action_list.php");
        break;
    /* 列出文件 */
    case 'listfile':
        $result = include("action_list.php");
        break;

    /* 抓取远程文件 */
    case 'catchimage':
        $result = include("action_crawler.php");
        break;

    default:
        $result = json_encode(array(
            'state'=> '请求地址出错'
        ));
        break;
}
$BASE_PATH = str_replace('\\','/',realpath(dirname(__FILE__).'/../../../'));
/* 输出结果 */
if (isset($_GET["callback"])) {
    if (preg_match("/^[\w_]+$/", $_GET["callback"])) {
        echo htmlspecialchars($_GET["callback"]) . '(' . $result . ')';
    } else {
        echo json_encode(array(
            'state'=> 'callback参数不合法'
        ));
    }
} else {
    $url=$domainArr[$port].'/v1/userManage/uploadoss?path='.str_replace('+','|',$BASE_PATH.json_decode($result)->url).'&name='.json_decode($result)->title;
    $res = curl_get_https($url);
    $res  = rtrim($res, "\"");
    $res  = ltrim($res, "\"");
    $newres = json_decode($result);
    $newres->url = stripslashes($res);
    echo json_encode($newres);
}
function curl_get_https($url)
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 跳过证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // 从证书中检查SSL加密算法是否存在
        $tmpInfo = curl_exec($curl);     //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $tmpInfo;    //返回json对象
    }