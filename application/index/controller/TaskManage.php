<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/6/17
 * Time: 5:13 PM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\BetsMerge;
use app\index\model\CardGameReport;
use app\index\model\CardGame;
use app\index\model\DateCardGameReport;
use app\index\model\Domain;
use app\index\model\IntegralLog;
use app\index\model\LosewinDay;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use Obs\ObsClient;
use think\facade\Request;
use app\index\model\IntegralDate;
use WebSocket\Client;
class TaskManage
{
    
    const  JILI_IP = '154.23.221.76';//吉利ip
    const  QILIN_IP = '154.23.221.76';//麒麟ip
    const  JIMCLOUD_IP = '27.124.44.146';
    const  DUI_WAI = '47.101.143.146';
    /**
     * @function 用户输赢任务
     */
    public function losewinTask()
    {
        $sql = BetsMerge::field('id,uid,nickname,agents_id,agents_account,agents_name,user_zx_xm,user_sb_xm,user_lucky_xm,win,mktime,tid');
        $sql->where('tongji_losewin', 0)->limit(500);
        $data = $sql->select();
        foreach ($data as $value) {
            $datetime = date('Ymd', $value['mktime']);;
            $uid = $value['uid'];
            $nickname = $value['nickname'];
            $agents_id = $value['agents_id'];
            $agents_name = $value['agents_name'];
            $agents_account = $value['agents_account'];
            $integral_make = $value['user_zx_xm'] + $value['user_sb_xm'] + $value['user_lucky_xm'];
            $losewin = $value['win'];
            $ukey = $datetime . '_' . $uid . '_' . $agents_id;
            $integral_exchange = 0;
            $integral = 0;
            $tid = $value['tid'];
            //统计已兑换的积分
            $intesql = IntegralLog::field('inte_rate,integral');
            $intesql->where('integral', '<>', 0)->where('uid', $uid)->where('date_time', $datetime);
            $intesql->where('tid', $tid);
            $inte = $intesql->select();
            foreach ($inte as $item) {
                if ($item['integral'] < 0) {
                    $integral_exchange += common::math_mul($item['inte_rate'], $item['integral'], 2);
                } elseif ($item['integral'] > 0) {
                    $integral += $item['integral'];
                }
            }
            $losewinData = LosewinDay::where('ukey', $ukey)->field('integral_make,integral_exchange,losewin,integral')->find();
            if (empty($losewinData)) {
                $losewin_insert = [
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'agents_id' => $agents_id,
                    'agents_name' => $agents_name,
                    'agents_account' => $agents_account,
                    'integral_make' => $integral_make,
                    'integral_exchange' => $integral_exchange,
                    'integral' => $integral,
                    'losewin' => $losewin,
                    'datetime' => $datetime,
                    'mktime' => $value['mktime'],
                    'ukey' => $ukey,
                    'tid' => $tid
                ];
                LosewinDay::create($losewin_insert);
            } else {
                $update_sql = LosewinDay::where('ukey', $ukey)->inc('integral_make', $integral_make)->inc('losewin', $losewin);
                $update_sql->update(['integral_exchange' => $integral_exchange, 'integral' => $integral]);
            }
            BetsMerge::where('id', $value['id'])->update(['tongji_losewin' => 1]);
        }
        echo date('Y-m-d H:i:s') . '-任务执行正常';
    }
    
    /**
     * @function losewin同步积分
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function integral_task()
    {
        $data = LosewinDay::where('integral_tongji', 0)->field('datetime,uid,agents_id,losewin,integral_make')->limit(1000)->select();
        foreach ($data as $item) {
            $uid = $item['uid'];
            $datetime = $item['datetime'];
            $agents_id = $item['agents_id'];
            $ukey = $datetime . '_' . $uid . '_' . $agents_id;
            $losewin = 0;
            $integral_make = 0;
            $intesql = BetsMerge::field('user_zx_xm,user_sb_xm,user_lucky_xm,win');
            $intesql->where('mktime', '>=', 1593532800)->where('uid', $uid)->where('mktime', '<=', 1593619199)->where('tongji_losewin', 1);
            $inte = $intesql->select();
            if (!empty($inte)) {
                foreach ($inte as $item) {
                    $losewin += $item['win'];
                    $integral_make += ($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm']);
                }
                LosewinDay::where('ukey', $ukey)->update(['losewin' => $losewin, 'integral_make' => $integral_make, 'integral_tongji' => 1]);
            }
            echo $ukey . '-执行完毕';
        }
        
        
    }
    
    
    /**
     * @function 统计用户每日上下分
     */
    public function updowFenTask()
    {
        $userlog = UserScoreLog::field('score_change,uid,type,id,time,agents_id')->whereIn('type', [11, 12])->where('user_ai', 0)->where('tourist', 0)->where('tongji_state', 0)->select();
        foreach ($userlog as $item) {
            $uid = $item['uid'];
            $agents_id = $item['agents_id'];
            $mktime = $item['time'];
            $fen = $item['score_change'];
            //统计用户每日上下分
            $datetime = date('Ymd', $mktime);
            $ukey = $datetime . '_' . $uid . '_' . $agents_id;
            $upfen = 0;
            $dowfen = 0;
            if ($fen > 0) {
                $upfen = $fen;
            } else {
                $dowfen = $fen;
            }
            $losewinData = LosewinDay::where('ukey', $ukey)->field('upfen,dowfen')->find();
            if (empty($losewinData)) {
                //获取用户信息
                $user = User::field('name,agents_account,agents_name,agents_id')->where('uid', $uid)->find();
                $losewin_insert = [
                    'uid' => $uid,
                    'nickname' => $user['name'],
                    'agents_id' => $user['agents_id'],
                    'agents_name' => $user['agents_name'],
                    'agents_account' => $user['agents_account'],
                    'integral_make' => 0,
                    'integral_exchange' => 0,
                    'losewin' => 0,
                    'upfen' => $upfen,
                    'dowfen' => $dowfen,
                    'datetime' => $datetime,
                    'mktime' => $mktime,
                    'ukey' => $ukey
                ];
                LosewinDay::create($losewin_insert);
            } else {
                $sql = LosewinDay::where('ukey', $ukey);
                if ($upfen != 0) {
                    $sql->inc('upfen', $upfen);
                }
                if ($dowfen != 0) {
                    $sql->inc('dowfen', $dowfen);
                }
                $sql->update();
            }
            UserScoreLog::where('id', $item['id'])->update(['tongji_state' => 1]);
        }
        echo date('Y-m-d H:i:s') . '-执行成功';
    }
    
    /**
     * @function 结算报表按天统计
     */
    public function dateGameReportTask_bak()
    {
        $datas = CardGameReport::where('tongji_state', 0)
        ->field('id,tmyk,wsyk,sbyk,dcyk,zxxm,sbxm,khyk,luckysix_yk,groupid,lq_yk,fb_yk,superhe_yk,mktime,tid')->limit(1000)->select();
        foreach ($datas as $item) {
            $tid = $item['tid'];
            $id = $item['id'];
            $date_time = date('Ymd', $item['mktime']);
            $dateData = DateCardGameReport::where('date_time', $date_time)->find();
            if (empty($dateData)) {
                $insertData = [
                    'tmyk' => $item['tmyk'],
                    'dcyk' => $item['dcyk'],
                    'sbyk' => $item['sbyk'],
                    'zxxm' => $item['zxxm'],
                    'sbxm' => $item['sbxm'],
                    'khyk' => $item['khyk'],
                    'luckysix_yk' => $item['luckysix_yk'],
                    'groupid' => $item['groupid'],
                    'lq_yk' => $item['lq_yk'],
                    'fb_yk' => $item['fb_yk'],
                    'superhe_yk' => $item['superhe_yk'],
                    'date_time' => $date_time,
                    'mktime' => date('Y-m-d H:i:s'),
                    'tid' => $item['tid']
                ];
                DateCardGameReport::insert($insertData, false, true);
            } else {
                $updateData = [
                    'tmyk' => $item['tmyk'] + $dateData['tmyk'],
                    'dcyk' => $item['dcyk'] + $dateData['dcyk'],
                    'sbyk' => $item['sbyk'] + $dateData['sbyk'],
                    'zxxm' => $item['zxxm'] + $dateData['zxxm'],
                    'sbxm' => $item['sbxm'] + $dateData['sbxm'],
                    'khyk' => $item['khyk'] + $dateData['khyk'],
                    'luckysix_yk' => $item['luckysix_yk'] + $dateData['luckysix_yk'],
                    'lq_yk' => $item['lq_yk'] + $dateData['lq_yk'],
                    'fb_yk' => $item['fb_yk'] + $dateData['fb_yk'],
                    'superhe_yk' => $item['superhe_yk'] + $dateData['superhe_yk']
                ];
                DateCardGameReport::where('date_time', $date_time)->where('tid', $tid)->update($updateData);
            }
            CardGameReport::where('id', $id)->update(['tongji_state' => 1]);
        }
        
        echo '执行成功_' . date('Y-m-d H:i:s');
    }
    
    /**
     * @function 结算报表按天统计
     */
    public function dateGameReportTask()
    {
        $datas = BetsMerge::where('tongji_report_state', 0)
        ->field('id,user_zx_losewin,user_zx_xm,user_sb_losewin,user_sb_xm,user_lucky_losewin,user_lucky_xm,user_lq_losewin,user_lq_xm,user_fb_losewin,user_fb_xm,user_super_he_losewin,user_super_he_xm,mktime,groupid,win,game_type,tid')->limit(1000)->select();
        foreach ($datas as $item) {
            $id = $item['id'];
            $game_type = $item['game_type'];
            $date_time = date('Ymd', $item['mktime']);
            $tid = $item['tid'];
            $ukey = $date_time . '_' . $game_type . '_' . $tid;
            $dateData = DateCardGameReport::where('ukey', $ukey)->find();
            if (empty($dateData)) {
                $insertData = [
                    'zxyk' => $item['user_zx_losewin'],
                    'zxxm' => $item['user_zx_xm'],
                    'sbyk' => $item['user_sb_losewin'],
                    'sbxm' => $item['user_sb_xm'],
                    'luckysix_yk' => $item['user_lucky_losewin'],
                    'luckysix_xm' => $item['user_lucky_xm'],
                    'lq_yk' => $item['user_lq_losewin'],
                    'lq_xm' => $item['user_lq_xm'],
                    'fb_yk' => $item['user_fb_losewin'],
                    'fb_xm' => $item['user_fb_xm'],
                    'superhe_yk' => $item['user_super_he_losewin'],
                    'superhe_xm' => $item['user_super_he_xm'],
                    'dlt_yk' => $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin'],
                    'dlt_xm' => $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'],
                    'khyk' => $item['win'],
                    'date_time' => $date_time,
                    'mktime' => date('Y-m-d H:i:s'),
                    'game_type' => $item['game_type'],
                    'ukey' => $ukey,
                    'tid' => $tid
                ];
                DateCardGameReport::insert($insertData, false, true);
            } else {
                $updateData = [
                    'zxyk' => $item['user_zx_losewin'] + $dateData['zxyk'],
                    'zxxm' => $item['user_zx_xm'] + $dateData['zxxm'],
                    'sbyk' => $item['user_sb_losewin'] + $dateData['sbyk'],
                    'sbxm' => $item['user_sb_xm'] + $dateData['sbxm'],
                    'luckysix_yk' => $item['user_lucky_losewin'] + $dateData['luckysix_yk'],
                    'luckysix_xm' => $item['user_lucky_xm'] + $dateData['luckysix_xm'],
                    'lq_yk' => $item['user_lq_losewin'] + $dateData['lq_yk'],
                    'lq_xm' => $item['user_lq_xm'] + $dateData['lq_xm'],
                    'fb_yk' => $item['user_fb_losewin'] + $dateData['fb_yk'],
                    'fb_xm' => $item['user_fb_xm'] + $dateData['fb_xm'],
                    'superhe_yk' => $item['user_super_he_losewin'] + $dateData['superhe_yk'],
                    'superhe_xm' => $item['user_super_he_xm'] + $dateData['superhe_xm'],
                    'dlt_yk' => $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin'] + $dateData['dlt_yk'],
                    'dlt_xm' => $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $dateData['dlt_xm'],
                    'khyk' => $item['win'] + $dateData['khyk'],
                ];
                DateCardGameReport::where('ukey', $ukey)->update($updateData);
            }
            BetsMerge::where('id', $id)->update(['tongji_report_state' => 1]);
        }
        echo '执行成功_' . date('Y-m-d H:i:s');
    }
    
    /**
     * @function 更改视频地址
     */
    public function videoUrlTask()
    {
        $data = TeamRoom::field('video_link,id')->select();
        foreach ($data as $item) {
            $url = $item['video_link'];
            $id = $item['id'];
            if (strpos($url, 'Authblm') !== false) {
                $video_link = substr($url, 0, strlen($url) - 6) . mt_rand(111111, 999999);
                TeamRoom::where('id', $id)->update(['video_link' => $video_link]);
            }
        }
        echo '执行成功' . PHP_EOL;
    }
    
    /**
     * @function 五秒一次更改视频地址
     */
    public function updateVideoTask(){
        $tidarray = [262=>9764,5=>9855,584=>9772,544=>7711,8=>9893,29=>9893,525=>9208];
        foreach ($tidarray as $k=>$v){
            if ($k == 5 || $k == 523 ||$k == 8 || $k == 29 || $k==525){
                file_get_contents('http://'.self::QILIN_IP.':'.$v.'/v1/task/videoUrl?tid='.$k);
            }else{
                file_get_contents('http://'.self::JILI_IP.':'.$v.'/v1/task/videoUrl?tid='.$k);
            }
        }
        echo 'success';exit();
    }
    
    /**
     * @return void 制作图片
     */
    public function makeImage()
    {
        $request = Request::instance();
        $type = $request->get('type');
        // 1.创建画布资源
        $width = mt_rand(500, 1000);
        $height = mt_rand(300, 1000);
        $img = imagecreatetruecolor($width, $height);
        // 2.准备颜色
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        $red = imagecolorallocate($img, 255, 0, 0);
        $green = imagecolorallocate($img, 0, 255, 0);
        $blue = imagecolorallocate($img, 0, 0, 255);
        $colorArray = [$black, $red, $green, $white, $blue];
        $randone = array_rand($colorArray);
        $randtwo = array_rand($colorArray);
        $colorone = $colorArray[$randone];
        $colortwo = $colorArray[$randtwo];;
        // 3.填充画布
        imagefill($img, 0, 0, $colorone);
        // 4.在画布上画图像或文字
        $width01 = mt_rand(200, 500);
        $height01 = mt_rand(500, 1000);
        imagefilledellipse($img, 250, 150, $width01, $height01, $colortwo);
        // 5.输出最终图像或保存最终图像
        header('content-type:image/jpeg');
        // 图片从浏览器上输出
        imagejpeg($img);exit();
        // 把图片保存到本地
        $imagename = array_rand(["da_","jy_","xiao_","dyz_","ab_","dad_","dda_","ca_","ba_"],1);
        $number = $imagename.mt_rand(50, 10000);
        $daimage = "/home/www/wwwroot/huaweiobs/$number.png";
        $zzimage = "/home/www/wwwroot/huaweiobs/$number.png";
        imagejpeg($img, $daimage);
        imagejpeg($img, $zzimage);
        // 6.释放画布资源
        imagedestroy($img);
        $filename = $daimage;
        // 97 98 99 103 109 138 139  144 145  155 156
        $obspathArray = [
            'huaweiniuniu/upload/ludan/97',
            'huaweiniuniu/upload/ludan/98',
            'huaweiniuniu/upload/ludan/99',
            'huaweiniuniu/upload/ludan/103',
            'huaweiniuniu/upload/ludan/109',
            'huaweiniuniu/upload/ludan/138',
            'huaweiniuniu/upload/ludan/139',
            'huaweiniuniu/upload/ludan/143',
            'huaweiniuniu/upload/ludan/144',
            'huaweiniuniu/upload/ludan/145',
            'huaweiniuniu/upload/ludan/155',
            'huaweiniuniu/upload/ludan/156',
        ];
        if ($type == 1) {
            $obspathArray = [
                'huaweiniuniu/upload/ludan/97',
                'huaweiniuniu/upload/ludan/98',
                'huaweiniuniu/upload/ludan/99',
                'huaweiniuniu/upload/ludan/103',
            ];
        } elseif ($type == 2) {
            $obspathArray = [
                'huaweiniuniu/upload/ludan/109',
                'huaweiniuniu/upload/ludan/138',
                'huaweiniuniu/upload/ludan/139',
            ];
        } elseif ($type == 3) {
            $obspathArray = [
                'huaweiniuniu/upload/ludan/143',
                'huaweiniuniu/upload/ludan/144',
                
            ];
        } elseif ($type == 4) {
            $obspathArray = [
                'huaweiniuniu/upload/ludan/145',
                'huaweiniuniu/upload/ludan/155',
                'huaweiniuniu/upload/ludan/156',
                'huaweiniuniu/upload/ludan/160',
            ];
        }
        $obspathnumber = array_rand($obspathArray);
        $obspath = $obspathArray[$obspathnumber];
        $dapathname = $obspath . '/' . $number . '.png';
        $zzpathname = $obspath . '/' . $number . '.png';
        common::moveOBS('huadong9878', $dapathname, $daimage);
        common::moveOBS('huadong9878', $zzpathname, $zzimage);
        echo '图片上传成功' . $zzpathname;
        exit();
    }
    
    /**
     * @fucntion 域名检测
     */
    public function checkDomain()
    {
        $domain = Domain::where('type', 25)->find();
        $qrdomain = $domain['domain'];
        $result_json = file_get_contents('http://wxapi2.jnoo.com/api/wxapijnoo3173/d8ab42e8f3835593967cb2ebfc618cc1?domain=' . $qrdomain);
        $result = json_decode($result_json, TRUE);
        if ($result['status'] >= 1) {
            if ($result['status'] > 1) {
                $domainused = Domain::where('type', 24)->where('tid', 0)->where('status', 0)->field('id,domain,status,type')->find();
                if (!empty($domainused)) {
                    Domain::where('type', 25)->update(['domain' => $domainused['domain']]);
                    Domain::where('id', $domainused['id'])->update(['status' => 3]);
                    echo '域名：' . $qrdomain . '已被风控,更换域名：' . $domainused['domain'];
                } else {
                    echo '没有可使用域名';
                }
            } else {
                echo '域名正常';
            }
        } else {
            echo '接口参数错误';
        }
    }
    
    /**
     * @function 更新路单图片
     */
    public function ludanImage(){
        $request = Request::instance();
        $tid = intval($request->get('tid'));
        if($tid == 0){
            echo 'tid is null';exit();
        }
        $group = [];
        $key = '3YYQKWBNE6NOCPFYQICY';
        $secret = 'eb8g7rL8ZdzwoWR2wbIVksaEAjMNoWbyTqjcpIt6';
        $Bucket = 'ludan-image';
        if($tid == 3){
            $group = [241,248];
        }else if($tid == 5){
            $group = [97,98,99];
        }else if($tid == 584){
            $group = [155,156,160];
        }else if($tid == 262){
            $group = [103,109];
        }else if($tid == 544){
            $group = [149,152];
        }else if($tid == 8){
            $group = [244,247];            
        }else if($tid == 5678){
            $group = [19,20];          
        }else if($tid == 525){
            $group = [100,101,102];            
        }else if($tid == 549){
            $group = [244,245];
        }else if($tid == 569){
            $group = [51,52,53];
        }else if($tid == 492){
            $group = [110,111];
        }else if($tid == 725){
            $group = [71,72];
        }else if($tid == 560){
            $group = [123,124];
        }
        // 创建ObsClient实例
        $obsClient = new ObsClient([
            'key' => $key,
            'secret' => $secret,
            'endpoint' => 'obs.cn-southwest-2.myhuaweicloud.com',
        ]);
        foreach($group as $groupid){
            $cardGame = CardGame::where('tid', $tid)->where('groupid', $groupid)->where('state',2)->where('ludan',0)->field('id')->order('id', 'desc')->limit(1)->find();
            $cardGame2 = CardGame::where('tid', $tid)->where('groupid', $groupid)->where('state',2)->where('start',1)->where('startludan',0)->field('id')->order('id', 'desc')->limit(1)->find();
            if(!empty($cardGame) || !empty($cardGame2)){
                $pngarray = ['zz_12.png','da_26.png','dyz_26.png','xiao_13.png','jy_13.png','da_20.png'];
                foreach($pngarray as $pngimg){
                    //下载对象
                    $resp = $obsClient->getObject([
                        'Bucket' => $Bucket,
                        'Key' => 'zuihou/upload/ludan/'.$groupid.'/'.$pngimg,
                        'SaveAsFile'=>'/home/www/wwwroot/shangshui_image/upload/ludan/'.$groupid.'/'.$pngimg,
                    ]);
                }
                if(!empty($cardGame)){
                    CardGame::where('id', $cardGame['id'])->update(['ludan'=>1]);
                }
                if(!empty($cardGame2)){
                    CardGame::where('id', $cardGame2['id'])->update(['startludan'=>1]);
                }
                echo   '房间'.$groupid. ' 执行成功'.PHP_EOL;
            }else{
                echo '房间'.$groupid. ' 暂无处理记录'.PHP_EOL;
            }
        }
    }
    
    /**
     * @function 五秒一次
     */
    public function ludanTask(){
        $tidarray = [];
        //$tidarray = [569=>5609,262=>5168,5=>9212,544=>9211,492=>9202,5678=>5678,725=>6169,3=>9213];
        foreach ($tidarray as $k=>$v){      
            file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':'.$v.'/v1/task/ludanImage?tid='.$k);
            }
        
        echo 'success';exit();
    }
    
    /**
     * @function 删除火箭聊天室图片
     *       $accessKeyId = 'HPUAEGBPX949TBKDCSS5';
        $accessKeySecret = 'swOSCwj7bg3tnywIAMoTM079XzFg6CEATvyqc6J4';
        $endpoint = 'obs.cn-east-4.myhuaweicloud.com';
        $bucket = 'liaotianshi1202';
     */
    public function delchatimghuojian(){
        /*$accessKeyId = 'HPUADKD860ULTAAE8TDB';
         $accessKeySecret = '165di598gNnONKYGVStNRyZFx6ElS13oVJrmKiZC';
         $endpoint = 'obs.cn-north-12.myhuaweicloud.com';
         $bucket = 'kefuimage1202';*/
        $request = Request::instance();
        $name = $request->get('name');
        $key = 'HPUADKD860ULTAAE8TDB';
        $secret = '165di598gNnONKYGVStNRyZFx6ElS13oVJrmKiZC';
        $Bucket = 'kefuimage1202';
        // 创建ObsClient实例
        $obsClient = new ObsClient([
            'key' => $key,
            'secret' => $secret,
            'endpoint' => 'obs.cn-north-12.myhuaweicloud.com',
            'socket_timeout' => 30,
            'connect_timeout' => 10
        ]);
        $date = date('Y-m-d');
        $resp = $obsClient->getObject([
            'Bucket' => $Bucket,
            'Key' => $name,
            'SaveAsFile'=>'/home/www/wwwroot/changlong_image/upload/kefu/'.$date.'/'.$name,
        ]);
        //20260601182922_1780309762855392.png  20260601183541_1780310141414036.png
        //https://kefuimage1202.obs.cn-north-12.myhuaweicloud.com/images/20260601182922_1780309762855392.png
        $resp = $obsClient->listObjects ( [
            'Bucket' => $Bucket
        ] );
        /*   foreach ($resp['Contents'] as $item ){
         echo $item['Key'].PHP_EOL;
         $resp =  $obsClient->deleteObject ( [
         'Bucket' => $Bucket,
         'Key' => $item['Key']
         ] );
         }*/
        echo '聊天室obs执行完成';exit();
    }
    
    /**
     * @function 删除聊天室图片
       $accessKeyId = 'HPUADKD860ULTAAE8TDB';
        $accessKeySecret = '165di598gNnONKYGVStNRyZFx6ElS13oVJrmKiZC';
        $endpoint = 'obs.cn-north-12.myhuaweicloud.com';
        $bucket = 'kefuimage1202';
     */
    public function delchatimg(){
        $key = 'HPUADKD860ULTAAE8TDB';
        $secret = '165di598gNnONKYGVStNRyZFx6ElS13oVJrmKiZC';
        $Bucket = 'kefuimage1202';
        // 创建ObsClient实例
        $obsClient = new ObsClient([
            'key' => $key,
            'secret' => $secret,
            'endpoint' => 'obs.cn-north-12.myhuaweicloud.com',
            'socket_timeout' => 30,
            'connect_timeout' => 10
        ]);
        //https://shanghai9527.obs.cn-east-3.myhuaweicloud.com/images/20230304025805_1677869885565400.png
        /*  $resp = $obsClient -> getObjectMetadata([
         'Bucket' => $Bucket,
         'Key' => 'images/20230304025805_1677869885565400.png'
         ]);
         print_r($resp);
         exit();*/
        /* $resp =  $obsClient->deleteObject ( [
         'Bucket' => $Bucket,
         'Key' => 'images/20230304025805_1677869885565400.png'
         ] );
         print_r($resp);
         exit();*/
        $resp = $obsClient->listObjects ( [
            'Bucket' => $Bucket
        ] );
        //  print_r($resp['Contents']);exit;
        foreach ($resp['Contents'] as $item ){
            echo $item['Key'].PHP_EOL;
            $resp =  $obsClient->deleteObject ( [
                'Bucket' => $Bucket,
                'Key' => $item['Key']
            ] );
            // print_r($resp);exit();
        }
        // print_r($resp);
        // 删除桶
        /*  $resp = $obsClient->deleteBucket([
         'Bucket' => $Bucket
         ]);
         echo '删除桶'.PHP_EOL;
         print_r($resp);
         echo PHP_EOL;*/
        //obs.cn-east-3.myhuaweicloud.com
        //cn-east-3
        
        // 创建桶
        /*  $resp = $obsClient->createBucket([
         'Bucket' => $Bucket,
         // 设置桶访问权限为公共读，默认是私有读写
         'ACL' => ObsClient::AclPublicReadWrite,
         // 设置桶的存储类型为标准存储类型
         'StorageClass' => ObsClient::StorageClassStandard,
         // 设置桶区域位置
         'LocationConstraint' => 'cn-east-3'
         ]);
         */
        // 直接设置桶访问权限
        /*  $resp = $obsClient->setBucketAcl([
         'Bucket' => '$Bucket',
         
         'Grants' => [
         
         // 为所有用户设置完全控制权限
         ['Grantee' => ['Type' => 'Group', 'URI' => ObsClient::GroupAllUsers], 'Permission' => ObsClient::PermissionFullControl],
         ]
         ]);
         */
        echo '聊天室obs删除对象执行完成';exit();
    }
    
    
    //清除游客
    public function delTouristTask() {
        $tidarray = [262=>9204,5=>9205,584=>9212,544=>9211,523=>9209,522=>9206,29=>9213,525=>9208];
        foreach ($tidarray as $k=>$v){
            if ($k == 5 || $k==523 ||$k == 8 || $k == 29 || $k == 9208){
                file_get_contents('http://'.self::QILIN_IP.':'.$v.'/v1/task/delTourist?tid='.$k);
            }else{
                file_get_contents('http://'.self::JILI_IP.':'.$v.'/v1/task/delTourist?tid='.$k);
            }
        }
        echo 'success';exit();
    }
    
    //每天清除游客
    public function delTourist() {
        $request = Request::instance();
        $tid = intval($request->get('tid'));
        if($tid == 0){
            echo 'tid is null';exit();
        }
        User::where('tourist',1)->where('user_type',1)->where('tid',$tid)->delete();
        echo '游客清除成功';
    }
    
    public static   function doUploadSync(&$keys, $keyPrefix, $content)
    {
        global $obsClient;
        global $bucketName;
        for($i = 0;$i < 100;$i++){
            $key = $keyPrefix . strval($i);
            $obsClient -> putObject(['Bucket' => $bucketName, 'Key' => $key, 'Body' => $content]);
            printf("Succeed to put object %s\n\n", $key);
            $keys[] = ['Key' => $key];
        }
    }
    
    public static function doUploadAsync(&$keys, $keyPrefix, $content)
    {
        global $obsClient;
        global $bucketName;
        $promise = null;
        for($i = 0;$i < 100;$i++){
            $key = $keyPrefix . strval($i);
            $p = $obsClient -> putObjectAsync(['Bucket' => $bucketName, 'Key' => $key, 'Body' => $content],
                function($exception, $resp) use ($key){
                    printf("Succeed to put object %s\n\n", $key);
                });
            if($promise === null){
                $promise = $p;
            }
            $keys[] = ['Key' => $key];
        }
        $promise -> wait();
    }
    
    /**
     * @function 积分问题
     */
    public function trouble0302(){
        $logdata = IntegralLog::field('id,uid,integral,date_time,tid')->where('tid',262)->where('date_time','>=',20230201)->where('tongji_state',1)->limit(5000)->select();
        $count = 0;
        foreach ($logdata as $item){
            $id = $item['id'];
            $uid = $item['uid'];
            $num = $item['integral'];
            $date_time = intval($item['date_time']);
            IntegralDate::change0302($uid, $num, 262, $date_time);
            IntegralLog::where('id',$id)->update(['tongji_state'=>2]);
            $count++;
        }

       
        echo $count.'条数据恢复完毕';
    }
    
    /**
     * @function 改变推码颜色
     */
    
    public function tmColor() {
        $request = Request::instance();
        $tid = intval($request->get('tid'));
        $wsurlArr = [5=>'ws://8.217.240.173:9502',549=>'ws://8.217.240.173:9502'];
        $client = new Client($wsurlArr[$tid]);
        $client->text(json_encode(['data'=>1]));
        echo $client->receive();
        $client->close();
    }
    
    /**
     * @function 吉利游客功能
     */
    
    public function  closeyk(){
        $request = Request::instance();
        $fee = intval($request->get('fee'));
        $tid = intval($request->get('tid'));
        Agents::where('tid',$tid)->where('agents_id',$tid)->update(['fee'=>$fee]);
        if ($fee > 0 ) {//关闭游客功能
            User::where('tourist',1)->where('user_type',1)->delete() ;
            echo '游客功能已关闭';
        }else {
            echo '游客功能已开启';
        }
    }
    
    /**
     * @function 激活功能
     */
    
    public function  closejh(){
        $request = Request::instance();
        $smstime = intval($request->get('smstime'));
        $tid = intval($request->get('tid'));
        Agents::where('tid',$tid)->where('agents_id',$tid)->update(['smstime'=>$smstime]);
        if ($smstime> 0 ) {//开启激活功能
            User::where('tourist',1)->where('user_type',1)->delete() ;
            echo '激活功能已开启';
        }else {
            echo '激活功能已关闭';
        }
    }
    
    //后台数据统计
    public function dataCount() {
       $remote_ip =  $_SERVER['REMOTE_ADDR'];
        $tidarray = [
            'shangshui9602'=>9202,
            'shangshui9604'=>9204,
            'shangshui9605'=>2025,
            //'shangshui9206'=>9206,
           // 'shangshui9209'=>9209,
            //'shangshui9210'=>9210,
           // 'shangshui9211'=>9211,
           // 'shangshui9669'=>6169,
            'shangshui9612'=>9212,     
            'shangshui9613'=>9213,
            'shangshui9654'=>5609,
            'shangshui9678'=>5678,
        ];
        foreach ($tidarray as $v){
            file_get_contents('http://'. $remote_ip.':'.$v.'/v1/tongji/agent');//积分统计
            file_get_contents('http://'. $remote_ip.':'.$v.'/v1/data/updateReport');//修正结算报表
            file_get_contents('http://'. $remote_ip.':'.$v.'/v1/task/dateGameReport');//结算报表按天统计  
                echo $v.'执行成功';
        }
        echo 'success';exit();
    }
}