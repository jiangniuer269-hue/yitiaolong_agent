<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
namespace app\index;

use app\index\model\Agents;
use think\Db;
use app\index\model\MenuUrl;
use app\index\model\Domain;
use think\facade\Session;
use OSS\OssClient;
use OSS\Core\OssException;
use Obs\ObsClient;

class common
{
    /**
     * @function 代理验证登录
     *
     * @return bool|mixed
     */
    public static function checkLogin()
    {
        if (Session::has('agents_id') and Session::has('tid')) {
            $agents_id = Session::get('agents_id');
            $tid = Session::get('tid');
            //获取代理信息
            $agent = Agents::where('agents_id', $tid)->field('use_end_time')->find();
            if (time() > strtotime($agent['use_end_time'])) {
                return ['code' => 400, 'msg' => '您的软件已到期，请联系客服'];
            }
            //更新session时间
            Session::set('agents_id', $agents_id);
            Session::set('tid', $tid);
            return ['code' => 200, 'msg' => '登录成功', 'data' => ['agents_id' => $agents_id, 'tid' => $tid]];
        } else {
            //return ['code' => 200, 'msg' => '登录成功', 'data' => ['agents_id' => 555, 'tid' => 525]];
            return ['code' => 400, 'msg' => '登录失效'];
        }
    }


    /**
     * @param $agents_id
     * @return boolean
     */
    public static function checkEmp($agents_id)
    {
        $agent = Agents::where('agents_id', $agents_id)->field('agent_type')->find();
        if (empty($agent)) {
            return 0;
        } else {
            return $agent['agent_type'];
        }
    }

    public static function checkAuthType($agents_id)
    {
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type')->find();
        if (empty($agent)) {
            return 0;
        } else {
            return $agent['auth_type'];
        }
    }

    /**
     * @param $agents_id
     * @return int
     */
    public static function changeAgentId($agents_id)
    {
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,tid')->find();
        if ($agent['auth_type'] == 1) {
            return $agent['tid'];
        } else {
            return $agents_id;
        }
    }

    /**
     * @function 发送短信验证码
     */
    public static function sendSMS($code, $phone)
    {
        $accountSid = '781cbb65340e2ff621d6499fcc9b2402';
        $token = 'd23abca4fc0506c5bdac30febd0b88d7';
        $timestamp = self::msectime();
        $url = 'https://openapi.miaodiyun.com/distributor/sendSMS';
        $postData = [
            'accountSid' => $accountSid,//开发者账号
            // 'smsContent' => '【银钻娱乐】您的验证码为{1}，请于{2}分钟内正确输入，如非本人操作，请忽略此短信。',//短信内容
            'templateid' => '248754',//模板ID
            'to' => (string)$phone,//手机号
            'param' => "$code,2",
            'timestamp' => $timestamp,
            'sig' => md5($accountSid . $token . $timestamp),
        ];
        $res = self::http_request($url, $postData, 5);
        return $res;
    }

    /**
     * @function http请求
     *
     * @param $url
     * @param null $data
     * @param int $secound
     * @return mixed
     */
    public static function http_request($url, $data = null, $secound = 0, $header = ['X-Domain:2653165'])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if ($secound > 0) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $secound); //设置超时时间
        }

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * @function 获取上月开始、结束日期
     */
    public static function getLastMonth()
    {
        $m_statetime = date('Y-m-01 00:00:00', strtotime('-1 month'));
        $m_endtime = date("Y-m-d 23:59:59", strtotime(-date('d') . 'day'));
        return [$m_statetime, $m_endtime];
    }

    /**
     * @return array 获取当月开始，结束日期
     */
    public static function getCurMonth()
    {
        $m_statetime = date('Y-m-01 00:00:00', strtotime(date("Y-m-d")));
        $m_endtime = date('Y-m-d 23:59:59', strtotime("$m_statetime +1 month -1 day"));
        return [$m_statetime, $m_endtime];
    }

    /**
     * @function 昨日开始，结束日期
     *
     * @return array
     */
    public static function getYesterday()
    {
        $m_statetime = date('Y-m-d 00:00:00', time() - 86400);
        $m_endtime = date("Y-m-d 23:59:59", time() - 86400);
        return [$m_statetime, $m_endtime];
    }

    /**
     * @function 今日开始，结束时间
     *
     * @return array
     */
    public static function getToday()
    {
        $m_statetime = date('Y-m-d 00:00:00', time());
        $m_endtime = date("Y-m-d 23:59:59", time());
        return [$m_statetime, $m_endtime];
    }

    /**
     * @function 获取一周开始结束时间
     *
     * @param string $time
     * @param string $format
     * @return array
     */
    public static function getWeek($time = '', $format = 'Y-m-d')
    {
        $time = $time != '' ? $time : time();
        //获取当前周几
        $week = date('w', $time);
        $weekname = array('周一', '周二', '周三', '周四', '周五', '周六', '周日');
        //星期日排到末位
        if (empty($week)) {
            $week = 7;
        }
        $date = [];
        for ($i = 0; $i < 7; $i++) {
            $date_time = date($format, strtotime('+' . ($i + 1 - $week) . ' days', $time));
            if ($i == 0) {
                $date_time .= ' 00:00:00';
            }
            if ($i == 6) {
                $date_time .= ' 23:59:59';
            }
            $date[$i]['date'] = $date_time;
            $date[$i]['time'] = strtotime($date_time);
            $date[$i]['week'] = $weekname[$i];
        }
        return $date;
    }

    /**
     * @function 获取菜单
     */
    public static function getMenu()
    {
        $menus = MenuUrl::where('online', 1)->order('sort', 'desc')->select();
        $menuArr = self::makeMenuArr($menus);
        return $menuArr;
    }

    /**
     * @function 生成菜单数组
     */
    public static function makeMenuArr($menus = [])
    {
        $menuArr = [];
        //父级菜单
        foreach ($menus as $key => $item) {
            if ($item->father_menu == 0) {
                $menuArr[$item->menu_id] = [
                    'menu_id' => $item->menu_id,
                    'menu_name' => $item->menu_name,
                    'module_id' => $item->module_id,
                    'father_menu' => $item->father_menu,
                    'menu_url' => $item->menu_url,
                    'icon' => $item->icon,
                    'child' => []
                ];
                unset($menus[$key]);
            }
        }
        //子级菜单
        foreach ($menus as $key => $item) {
            if ($item->father_menu > 0) {
                if (isset($menuArr[$item->father_menu])) {
                    $menuArr[$item->father_menu]['child'][] = [
                        'menu_id' => $item->menu_id,
                        'menu_name' => $item->menu_name,
                        'module_id' => $item->module_id,
                        'father_menu' => $item->father_menu,
                        'menu_url' => $item->menu_url
                    ];
                }
            }
        }

        return $menuArr;
    }

    /**
     * @creator ajax
     * @data 2018/1/05
     * @desc 数据导出到excel(csv文件)
     * @param $filename 导出的csv文件名称 如date("Y年m月j日").'-test.csv'
     * @param array $tileArray 所有列名称
     * @param array $dataArray 所有列数据
     */
    public static function exportToExcel($filename, $tileArray = [], $dataArray = [], $pageNum = 0)
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0);
        ob_end_clean();
        ob_start();
        header("Pragma: public");
        header("Expires: 0");
        header("Accept-Ranges:bytes");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl;charset=utf-8");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename=' . $filename . '');
        header("Content-Transfer-Encoding:binary");
        $fp = fopen('php://output', 'w');
        fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));//转码 防止乱码(比如微信昵称(乱七八糟的))
        fputcsv($fp, $tileArray);
        $index = 0;
        foreach ($dataArray as $item) {
            if ($index == $pageNum) {
                $index = 0;
                ob_flush();
                flush();
            }
            $index++;
            fputcsv($fp, $item);
        }

        ob_flush();
        flush();
        ob_end_clean();
    }

    /**
     * @function 写日志
     *
     * @param $data
     */
    public static function write_log($data, $logname = 'log')
    {
        $years = date('Y-m');
        //设置路径目录信息
        $url = './log/' . date('Ymd') . '_' . $logname . '.txt';
        $dir_name = dirname($url);
        //目录不存在就创建
        if (!file_exists($dir_name)) {
            //iconv防止中文名乱码
            $res = mkdir(iconv("UTF-8", "GBK", $dir_name), 0777, true);
        }
        $fp = fopen($url, "a");//打开文件资源通道 不存在则自动创建
        fwrite($fp, var_export($data, true) . "\r\n");//写入文件
        fclose($fp);//关闭资源通道
    }


    /**
     * @function 无限代
     */
    public static function wxagents($level = 1, $agents_id = 0, $boss_id = 0, $agents_account = '', $agents_name = '', $relation = [], $relation_link = '')
    {
       // if ($agents_id > $boss_id) {
            $agents = Db::name('agents')->field('boss_id,account,name,agents_id,tid')->where('agents_id', $boss_id)->find();
            $relation[] = [
                'account' => $agents['account'],
                'name' => $agents['name'],
                'agents_id' => $agents['agents_id']
            ];
            $relation_link .= $agents['name'] . '(' . $agents['account'] . ')';
            $insertData = [
                'agents_id' => $agents_id,
                'boss_id' => $boss_id,
                'level' => $level,
                'ukey' => $agents_id . '_' . $boss_id,
                'relation' => json_encode($relation),
                'relation_link' => $relation_link,
                'tid' => $agents['tid']
            ];

            Db::name('wxagents')->insert($insertData, false, true);
            $boss_id = $agents['boss_id'];
            if ($boss_id == 0) {
                return $boss_id;
            } else {
                $relation_link .= '->';
                $level++;
                return self::wxagents($level, $agents_id, $boss_id, $agents_account, $agents_name, $relation, $relation_link);
            }
        //}
    }


    /**
     * @function 导出execl报表
     *
     * @param $fileName
     * @param $sheetName
     * @param $fileType
     * @param $data
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public static function exportExcel($fileName, $sheetName, $fileType, $data)
    {

        $obj = new \PHPExcel();

        // 以下内容是excel文件的信息描述信息
        $obj->getProperties()->setCreator(''); //设置创建者
        $obj->getProperties()->setLastModifiedBy(''); //设置修改者
        $obj->getProperties()->setTitle(''); //设置标题
        $obj->getProperties()->setSubject(''); //设置主题
        $obj->getProperties()->setDescription(''); //设置描述
        $obj->getProperties()->setKeywords('');//设置关键词
        $obj->getProperties()->setCategory('');//设置类型

        // 设置当前sheet
        $obj->setActiveSheetIndex(0);

        // 设置当前sheet的名称
        $obj->getActiveSheet()->setTitle($sheetName);

        // 列标
        $list = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA'];
      
        // 填充数据
        for ($j = 0; $j < count($data); $j++) {//多少行
            for ($i = 0; $i < count($data[0]); $i++) {//多少列
                $obj->getActiveSheet()->setCellValue($list[$i] . ($j + 1), $data[$j][$i], \PHPExcel_Cell_DataType::TYPE_STRING);
            }
        }

        // 导出
        ob_clean();
        if ($fileType == 'xls') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="' . $fileName . '.xls');
            header('Cache-Control: max-age=1');
            $objWriter = new \PHPExcel_Writer_Excel5($obj);
            $objWriter->save('php://output');
            exit();
        } elseif ($fileType == 'xlsx') {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx');
            header('Cache-Control: max-age=1');
            $objWriter = \PHPExcel_IOFactory::createWriter($obj, 'Excel2007');
            $objWriter->save('php://output');
            exit();
        }
    }


    /**
     * @function 系统日志
     */
    public static function system_log($deal_account, $be_deal_account, $type, $note, $c_ip = NULL, $address = NULL, $tid)
    {
        $agents = Agents::where('account', $deal_account)->field('agents_id,is_show')->find();
        if ($agents['is_show'] == 0){
            $insertID = Db::name('system_log')->insert([
                'deal_agents_id' => $agents['agents_id'],
                'deal_account' => $deal_account,
                'be_deal_account' => $be_deal_account,
                'type' => $type,
                'note' => $note,
                'ip' => $c_ip,
                'address' => $address,
                'mktime' => time(),
                'tid' => $tid
            ], false, true);

         return $insertID;
        }else {
            return 0;
        }
    }

    /**
     * @function 类型转换
     * @type 类型,110取消下注，122领取红包，111牌局结算，122重新结算,11上分，12下分,13下分删除,20手动上分，21手动下分
     */
    public static function exchangeType($type)
    {
        $typeArr = [
            1 => '下注',
            2 => '下注',
            3 => '下注',
            4 => '下注',
            5 => '下注',
            7 => '下注',
            15 => '下注',
            16 => '下注',
            17 => '下注',
            11 => '上分',
            12 => '下分',
            13 => '下分删除',
            20 => '手动上分',
            21 => '手动下分',
            100 => '码粮结算',
            110 => '取消下注',
            111 => '牌局结算',
            112 => '牌局取消',
            121 => '重新结算',
            122 => '领取红包',
            1100 => '代理上分',
            1200 => '代理下分'
        ];
        if (empty($typeArr[$type])) {
            return '下注';
        }
        return $typeArr[$type];
    }

    /**
     * @function  更改桌号
     */
    public static function exchangeRoom($room_id)
    {
        switch ($room_id) {
            case 71:
                return 'V1';
                break;
            case 72:
                return 'V2';
                break;
            case 73:
                return 'V3';
                break;
            case 74:
                return 'V4';
                break;
            case 75:
                return 'V5';
                break;
            case 76:
                return 'V6';
                break;
            case 77:
                return 'V7';
                break;
            case 78:
                return 'P17';
                break;
            case 79:
                return 'V8';
                break;
            case 14:
                return 'P14';
                break;
            case 17:
                return 'P17';
                break;
            case 12:
                return 'P12';
                break;
            case 11:
                return 'P11';
                break;
            case 10:
                return 'P10';
                break;
            case 9:
                return 'P9';
                break;
            case 8:
                return 'P8';
                break;
            case 'v1':
                return 71;
                break;
            case 'V1':
                return 71;
                break;
            case 'v2':
                return 72;
                break;
            case 'V2':
                return 72;
                break;
            case 'v3':
                return 73;
                break;
            case 'V3':
                return 73;
                break;
            case 'v4':
                return 74;
                break;
            case 'V4':
                return 74;
                break;
            case 'v5':
                return 75;
                break;
            case 'V5':
                return 75;
                break;
            case 'v6':
                return 76;
                break;
            case 'V6':
                return 76;
                break;
            case 'v7':
                return 77;
                break;
            case 'V7':
                return 77;
                break;
            case 'P8':
                return 8;
                break;
            case 'p8':
                return 8;
                break;
            case 'P9':
                return 9;
                break;
            case 'p9':
                return 9;
                break;
            case 'P10':
                return 10;
                break;
            case 'p10':
                return 10;
                break;
            case 'P11':
                return 11;
                break;
            case 'p11':
                return 11;
                break;
            case 'P12':
                return 12;
                break;
            case 'p12':
                return 12;
                break;
            case 'P14':
                return 14;
                break;
            case 'p14':
                return 14;
                break;
            case 'P17':
                return 78;
                break;
            case 'p17':
                return 78;
                break;
            case 'V8':
                return 79;
                break;
            case 'v8':
                return 79;
                break;

        }
    }

    /**
     * 精确加法
     * @param [type] $a [description]
     * @param [type] $b [description]
     */
    public static function math_add($a, $b, $scale = '2')
    {
        return bcadd($a, $b, $scale);
    }

    /**
     * 精确减法
     * @param [type] $a [description]
     * @param [type] $b [description]
     */
    public static function math_sub($a, $b, $scale = '2')
    {
        return bcsub($a, $b, $scale);
    }

    /**
     * 精确乘法
     * @param [type] $a [description]
     * @param [type] $b [description]
     */
    public static function math_mul($a, $b, $scale = '2')
    {
        return bcmul($a, $b, $scale);
    }

    /**
     * 精确除法
     * @param [type] $a [description]
     * @param [type] $b [description]
     */
    public static function math_div($a, $b, $scale = '2')
    {
        return bcdiv($a, $b, $scale);
    }

    /**
     * 精确求余/取模
     * @param [type] $a [description]
     * @param [type] $b [description]
     */
    public static function math_mod($a, $b)
    {
        return bcmod($a, $b);
    }

    /**
     * 比较大小
     * @param [type] $a [description]
     * @param [type] $b [description]
     * 大于 返回 1 等于返回 0 小于返回 -1
     */
    public static function math_comp($a, $b, $scale = '5')
    {
        return bccomp($a, $b, $scale); // 比较到小数点位数
    }

    /**
     * @function 加密函数
     *
     * @param $txt
     * @param string $key
     * @return string
     */
    public static function string2secret($txt, $key = 'www.jb51.net')
    {
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
        $nh = rand(0, 64);
        $ch = $chars[$nh];
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = base64_encode($txt);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = ($nh + strpos($chars, $txt[$i]) + ord($mdKey[$k++])) % 64;
            $tmp .= $chars[$j];
        }
        return urlencode($ch . $tmp);
    }

    /**
     * @function 解密函数
     *
     * @param $txt
     * @param string $key
     * @return string
     */
    public static function secret2string($txt, $key = 'www.jb51.net')
    {
        $txt = urldecode($txt);
        $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-=+";
        $ch = $txt[0];
        $nh = strpos($chars, $ch);
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = substr($txt, 1);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
            while ($j < 0) $j += 64;
            $tmp .= $chars[$j];
        }
        return base64_decode($tmp);
    }

    /**
     * @function 层级转化
     * @param $level
     * @return string
     */
    public static function changeLevel($level)
    {
        if ($level == 0) {
            $level = '自己';
        } else {
            $level = $level . '级';
        }
        return $level;
    }

    /**
     * @function 判断文件夹是否存在不存在则创建
     *
     * @param $dir
     * @param int $mode
     * @return bool
     */
    public static function mkdirs($dir, $mode = 0777)
    {
        if (is_dir($dir) || @mkdir($dir, $mode)) return TRUE;
        if (!mkdirs(dirname($dir), $mode)) return FALSE;
        return @mkdir($dir, $mode);
    }

    /**
     * @function 返回当前毫秒时间戳
     */
    public static function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;

    }


    /**
     * @function oss 上传
     *
     * @param $accessKeyId
     * @param $accessKeySecret
     * @param $endpoint
     * @param $bucket
     * @param $object
     * @param $content
     * @return mixed
     */
    public static function moveOss($object, $content)
    {
        $accessKeyId = 'LTAI5tQpyMk78F4MP1uRjyxi';
        $accessKeySecret = 'VNp2ZNO7NB5KVV1nvNFkmtIcSlU0uJ';
        $endpoint = 'oss-cn-beijing.aliyuncs.com';
        $bucket = 'shangshui-image-168';
        $options = array(
            //可以参看https://help.aliyun.com/document_detail/31859.html?spm=a2c4g.11186623.2.10.481e2b72ggLS4F#concept-lkf-swy-5db
            OssClient::OSS_CONTENT_TYPE => 'image/jpg',  // 简单的举例使用 要根据实际的图片类型 可以看下MimeTypes::getMimetype()里的
        );
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
            $res = $ossClient->putObject($bucket, $object, $content, $options);
        } catch (OssException $e) {
            print $e->getMessage();
        }
        return $res['info']['url'];
    }
    /**
     * @function 上传obs图片
     * @return void
     */
    public static function moveOBS($bucket = '', $pathname = '', $filename = '')
    {
        // 创建ObsClient实例
        $obsClient = new ObsClient([
            'key' => '74PBE1PQQNVCZXFQISGN',
            'secret' => 'ctbgM2wWZfmu0PkXxQPvTd1pQPtP1TfmN5KXd7YX',
            'endpoint' => 'obs.cn-southwest-2.myhuaweicloud.com',
        ]);

        //创建桶
//桶是OBS全局命名空间，相当于数据的容器、文件系统的根目录，可以存储若干对象
       $resp = $obsClient->createBucket([
            'Bucket' => 'bucketname'
           
       ]);
        //上传文件
        $resp = $obsClient->putObject([
            'Bucket' => $bucket,
            'Key' => $pathname,
           'SourceFile' => $filename
        ]);

//SourceFile参数和Body参数不能同时使用
        //下载对象
//        $resp = $obsClient->getObject([
//            'Bucket' => 'bucketname',
//            'Key' => 'objectkey'
//        ]);
echo $resp ['Body'];

        //删除对象
//        $resp = $obsClient->deleteObject([
//            'Bucket' => 'bucketname',
//            'Key' => 'objectkey'
//        ]);
        //$resp ['RequestId'];  返回操作ID
        // 关闭obsClient
        $obsClient->close();
    }

    /**
     * @function 获取聊天室域名
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getChatSystemDomain()
    {
        $sys_info = Domain::where('type', 28)->field('domain')->find();
        $url = $sys_info['domain'] . '/api/domain/business';
        $file_contents = self::http_request($url, null, 3);
        $resultJson = json_decode($file_contents, 1);
        return ['domain' => $resultJson['url']];

    }

    public static function fun_each(&$array)
    {
        $res = array();
        $key = key($array);
        if ($key !== null) {
            next($array);
            $res[1] = $res['value'] = $array[$key];
            $res[0] = $res['key'] = $key;
        } else {
            $res = false;
        }
        return $res;
    }

    public static function fun_count($array_or_countable, $mode = COUNT_NORMAL)
    {
        $res = 0;
        if (is_array($array_or_countable) || is_object($array_or_countable)) {
            $res = count($array_or_countable, $mode);
        }
        return $res;
    }
    
    /**
     * @function 生成随机字符
     */
    public static function GetRandStr($length){
        //字符组合
        $str = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $len = strlen($str)-1;
        $randstr = '';
        for($i=0;$i<$length;$i++) {
            $num=mt_rand(0,$len);
            $randstr .=" ".$str[$num];
        }
        
        return $randstr;
    }
    
    /**
     * @function 端口对应的tid
     */
    public static function GetPortTid(){
        return  ['7192'=>191,'7899'=>563];
    }
    



}
