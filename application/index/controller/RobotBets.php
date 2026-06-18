<?php
namespace app\index\controller;

use app\index\common;
use app\index\model\CardGame;
use app\index\model\SystemLog;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use think\facade\Request;
use WebSocket\Client;
use think\cache\driver\Redis;
use function GuzzleHttp\json_decode;
use app\index\model\BetsLog;

class RobotBets
{
    
    const WSURL = 'ws://154.23.221.76:9612';
   
    
    /**
     *
     * @function  登录ws
     * @return array
     */
    public static function loginWs($token, $username)
    {
        $wsArr = [
            "cmd" => 11,
            "ip" => "14.250.62.67",
            "ip_info" => null,
            "time" => time(),
            "token" => $token,
            "username" => $username
        ];
        return $wsArr;
    }
    
    /**
     *
     * @function 下注数组
     */
    public static function betsArr()
    {
        $bets = [
            'cmd' => 3006,
            'card_game_id' => 0,
            'color' => 1,
            'createtime' => time(),
            'cur_time' => date('m-d H:i', time()),
            'fromuser' => [
                'agentsSbShareRate' => 0,
                'agents_account' => 'shangshui9608',
                'agents_id' => 525,
                'agents_share_rate' => 6,
                'extraShare' => 0,
                'hasOddsInGroup' => [],
                'head' => '',
                'losewin' => 0,
                'name' => 'xiaoxiao',
                'no_say' => '',
                'score' => 5307,
                'score_old' => 21585,
                'status' => 0,
                'tid' => 525,
                'tourist' => 0,
                'uid' => 16640,
                'username' => 'xx001',
                'xh_config' => 0,
                'xm_rate' => 6,
                'xm_type' => 2
            ],
            'group' => [
                'admin_headimg' => 'http://shangshui-image-168.oss-cn-beijing.aliyuncs.com/images/20240502090244_%E5%A4%B4%E5%83%8F.png',
                'admin_id' => 16187,
                'admin_name' => '领投娱乐客服小助手',
                'counttime' => 30,
                'game_type' => 0,
                'groupid' => 100,
                'groupname' => '领头娱乐V8桌',
                'headimgurl' => 'http://shangshui-image-168.oss-cn-beijing.aliyuncs.com/images/20240502090244_%E5%A4%B4%E5%83%8F.png',
                'mark' => 100,
                'ps_name' => '领投娱乐客服小助手',
                'state' => 0,
                'tid' => 525,
                'video_link' => 'http://143.92.61.151:8976/index.html?t=iframe&roomNumber=116',
                'xstate' => 1
            ],
            'groupid' => 100,
            'head' => '',
            'msg' => '庄1234',
            'msg_id' => 212,
            'msgtype' => 0,
            'name' => 'xiaoxiao',
            'ret' => 1,
            'uid' => 16640,
            'uuid' => '42120'
        ];
        
        return $bets;
    }
    
    /**
     *
     * @function 机器人模拟下注
     */
    public function doRobots()
    {
        $request = Request::instance();
        $tid = intval($request->get('tid'));
        $room_id = intval($request->get('room_id'));
        if ($tid <= 0 || $room_id <= 0) {
            return ['msg'=>'参数错误'];
        }
        $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9612';
        if ($tid == 5) {
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9612';
        }elseif ($tid == 523){
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9609';
        }elseif ($tid == 569){
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9654';
        }elseif ($tid == 563){
            $WSURL = 'ws://47.100.28.255:9605';
        }elseif ($tid == 492){
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9602';
        }elseif ($tid == 5678){
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9678';
        }elseif ($tid == 725){
            $WSURL = 'ws://'.$_SERVER['REMOTE_ADDR'].':9669';
        }
        $redis = new Redis();
        $robotRedisHas = $redis->has('robot' . $tid);
        if ($robotRedisHas) {
            //从reids里面取机器人数据，模拟下注
            $robotRedis = $redis->get('robot' . $tid);
            $robotDoBets = json_decode($robotRedis);
            if (empty($robotDoBets)) {
                return ['msg'=>'redis不存在机器人'];
            }
            $mdkey = array_rand($robotDoBets);
            $mdrobot = $robotDoBets[$mdkey];
           
            //获取房间信息
            $cardgame =  CardGame::where('room_id',$room_id)->where('state',0)->where('tid',$tid)->field('id,groupid')->order('id','desc')->find();
            if (empty($cardgame)) {
                return ['msg'=>'房间'.$room_id.' 已截至或者不存在'.date('Y-m-d H:i:s')];
            }
            //机器人不能重复下注
            $betsLog = BetsLog::where('uid',$mdrobot->uid)->where('card_game_id',$cardgame['id'])->find();
            if (!empty($betsLog)) {
                return ['msg'=>$mdrobot->uid.'机器人已下过注了'.date('Y-m-d H:i:s')];
            }
           //机器人自动上分
            $user = User::where('ai', 1)->where('status', 0)->where('uid',$mdrobot->uid)->where('tid',$tid)
            ->field('uid,username,password,tid,score')
            ->find();
            if (!empty($user)) {
                if ($user['score'] < 5000) {
                    User::where('uid',$mdrobot->uid)->update(['score'=>mt_rand(1,10)*1000]);
                }
            }
            $client = new Client($WSURL);
            $loginArr = self::loginWs($mdrobot->token, $mdrobot->username);
            $client->text(json_encode($loginArr));
            // 获取下注字符
            $ai_text_res = TeamRoom::where('id',$room_id)->where('tid',$tid)->field('ai_text,ai_num,ai_time')->find();
            $ai_time_arr = explode('+', $ai_text_res['ai_time']);
            $ai_time_key = array_rand($ai_time_arr);
            $ai_time = $ai_time_arr[$ai_time_key];
            if ($ai_time > 0 ) {
                sleep($ai_time);
            }
            $ai_num_arr = explode('+', $ai_text_res['ai_num']);
            $ai_num_key = array_rand($ai_num_arr);
            $ai_num = $ai_num_arr[$ai_num_key];
            $ai_bets_count = BetsLog::where('user_ai',1)->where('card_game_id',$cardgame['id'])->where('state',0)->count();
            if ($ai_bets_count < $ai_num) {
                $ai_text_arr = explode('+', $ai_text_res['ai_text']);
                $ai_text_key = array_rand($ai_text_arr);
                $ai_text = $ai_text_arr[$ai_text_key];
                $betsArr = [
                    'card_game_id' => $cardgame['id'],
                    'cmd' => 3005,
                    'counttime' => 0,
                    'error_order' => 1,
                    'groupid' => $cardgame['groupid'],
                    'msg' => $ai_text,
                    'msgtype' => 0,
                    'uuid' => $cardgame['id'].$mdrobot->uid.mt_rand(1,99999),
                ];
                
                $client->text(json_encode($betsArr));
                $client->close();
                return ['msg'=>'房间'.$cardgame['groupid'] .'机器人'.$mdrobot->uid. '|'.$ai_text.'下注成功'.date('Y-m-d H:i:s')];
            }else {
                return ['msg' => '房间'.$cardgame['groupid'] .'机器人下注数量已到最大值：'.$ai_num.',时间:'.date('Y-m-d H:i:s')];
            }
            
    
        } else {
            // 获取机器人
            $user = User::where('ai', 1)->where('status', 0)->where('tid',$tid)
            ->field('uid,username,password,tid')
            ->select();
            $robotArr = [];
            foreach ($user as $u) {
                $tid = $u['tid'];
                $uid = $u['uid'];
                $username = $u['username'];
                $u['token'] = mt_rand(1000, 9999) . $u['password'];
                $robotArr[] = $u;
            }
            if (!empty($robotArr)) {
                $key =  'robot'.$tid ;
                $redis->set($key, json_encode($robotArr), 86400);
                return ['msg'=>$key.'已存入redis'. date('Y-m-d H:i:s')];
            }else {
                return ['msg'=>'未查询到机器人'];
            }
            
        }
        
    }
    
    /**
     * @function huojian执行机器人下注
     */
    public function todorobot() {
            $v = [97,98,99];
            foreach ($v as $vv){     
                $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':9212/v1/robotBets/doRobots?tid=5&room_id='.$vv));
                echo $data->msg;
                echo PHP_EOL;
            }            
        echo 'hguojian-success';exit();
    }
    
    
    /**
     * @function yiyun执行机器人下注
     */
    public function yytodorobot() {
        $v = [144,145];
            foreach ($v as $vv){
                $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':9589/v1/robotBets/doRobots?tid=523&room_id='.$vv));
                echo $data->msg;
                echo PHP_EOL;
            }
        
        echo 'yy-success';exit();
    }
    
    /**
     * @function 9654执行机器人下注
     */
    public function todorobot9654() {
        $v = [51,52];
        foreach ($v as $vv){
            $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':5609/v1/robotBets/doRobots?tid=569&room_id='.$vv));
            echo $data->msg;
            echo PHP_EOL;
        }
        
        echo '9654-success';exit();
    }
    
    /**
     * @function 9605执行机器人下注
     */
    public function todorobot9605() {
        $data = json_decode(file_get_contents('http://47.100.28.255:9205/v1/robotBets/doRobots?tid=563&room_id=25'));
        echo $data->msg;
        echo PHP_EOL;
        echo '9605-success';exit();
    }
    
    /**
     * @function 9602执行机器人下注
     */
    public function todorobot9602() {
        $request = Request::instance();
        $room_id = $request->get('room_id');
       // $v = [110,111];
       // foreach ($v as $vv){
        $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':9202/v1/robotBets/doRobots?tid=492&room_id='.$room_id));
            echo $data->msg;
            echo PHP_EOL;
      //  }
        
        echo '9602-room_id:'.$room_id.'-success';exit();
    }
    
    /**
     * @function 9678执行机器人下注
     */
    public function todorobot9678() {
        $v = [19,20];
        foreach ($v as $vv){
            $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':5678/v1/robotBets/doRobots?tid=5678&room_id='.$vv));
            echo $data->msg;
            echo PHP_EOL;
        }
        
        echo '9678-success';exit();
    }
    /**
     * @function 9669执行机器人下注
     */
    public function todorobot9669() {
        $v = [71,72];
        foreach ($v as $vv){
            $data = json_decode(file_get_contents('http://'.$_SERVER['REMOTE_ADDR'].':6169/v1/robotBets/doRobots?tid=5678&room_id='.$vv));
            echo $data->msg;
            echo PHP_EOL;
        }
        
        echo '9669-success';exit();
    }
}