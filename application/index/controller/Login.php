<?php
/**
 * Created by PhpStorm.
 * User: Agentsistrator
 * Date: 2018\9\14 0014
 * Time: 9:38
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\TeamConfig;
use think\Controller;
use think\Db;
use think\facade\Config;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Domain;
use think\facade\Cache;
use think\Facade;

class Login extends Controller
{
    
    const  JILI_IP = '8.137.122.246';//吉利ip
    const  QILIN_IP = '8.137.122.246';//麒麟ip
    
    /**
     * @function 登录页
     */
    public function index()
    {
        return view('login');
    }

    function convertUrlQuery($query)
    {
        $queryParts = explode('&', $query);
        $params = array();
        foreach ($queryParts as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = $item[1];
        }
        return $params;
    }
    /**
     * @function 获取群名称
     */
    public function getQunTitle() {
            $port = $_SERVER['SERVER_PORT'];           
            $tidArr = common::GetPortTid();
            $team = ['team_title'=>'代理后台'];
            if (!empty($tidArr[$port])) {
                $team = TeamConfig::where('tid',$tidArr[$port])->field('team_title')->find();
            }  
            return ['code'=>200,'msg'=>'请求成功','data'=>$team];
    }
    
    /**
     * @function 二维码
     */
    public function qrcode()
    {
        $sys_info = Domain::where('type', 29)->find();
        $wxauth = $sys_info['domain'];
        return view('login/qrcode', [
            'url' => 'http://' . $wxauth . '/v1/login/wx_login_quick?clearUser=0&agent_id=1'
        ]);

    }

    function im_login_auth()
    {
        $request = Request::instance();
        $playid = $request->post('playid');

        $im_url = Domain::where('type', 27)->field('domain')->find();
        $sys_info = common::getChatSystemDomain();
        $arr = parse_url($im_url['domain']);
        $arr_query = $this->convertUrlQuery($arr['query']);
        if (empty($playid)) {
            $playid = md5(time());
        }
        try {
            $sys_info['domain'] = 'http://g5vfv.cn';
            $url = $sys_info['domain'] . '/api/auth/domain/' . $playid . '/' . $arr_query['id'] . '?nickname=' . urlencode('wt1001游客') . '&headimgurl=';
            $file_contents = common::http_request($url, null, 3);
            $resultJson = json_decode($file_contents, 1);
            if ($resultJson['code'] == 406) {
                return ['code' => 406, 'playid' => $playid, 'data' => null];
            } else {
                return ['code' => 200, 'toid' => $arr_query['id'], 'imdomain' => $sys_info['domain'], 'playid' => $playid, 'data' => $resultJson['data']];
            }

        } catch (Exception $e) {
            return ['code' => 500, 'msg' => '请求错误', 'data' => null];
        }
    }

    /**
     * @function 判断是否需要登录验证
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function isEmp3()
    {
        $request = Request::instance();
        $account = $request->post('account');
        if (empty($account)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }
        $agents = Agents::getByAccount($account);
        if (empty($agents)) {
            return ['code' => 500, 'msg' => '账号不存在'];
        }
        return ['code' => 200, 'msg' => 'nocode', 'sms' => FALSE, 'agent_type' => $agents->agent_type];
        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        $agents_login_info = Db::name('user_login_info')->where('uid', $agents->agents_id)->where('ip', $real_ip)->where('user_type', 1)->select();
        $isSameIp = FALSE;
        if (!empty($agents_login_info)) {
            $isSameIp = TRUE;
        }
        if ($isSameIp) {
            return ['code' => 200, 'msg' => 'nocode', 'sms' => FALSE, 'agent_type' => $agents->agent_type];
        } else {
            $code = mt_rand(999, 9999);
            Cache::set('agent_code' . $agents->agents_id, $code, 120);
            return ['code' => 200, 'msg' => '', 'sms' => TRUE, 'agent_type' => $agents->agent_type, 'agents_id' => $agents->agents_id, 'qcode' => $code];


        }
    }

    /**
     * @function 登录验证员工
     */
    public function doEmpLogin()
    {
        $request = Request::instance();
        $account = $request->post('account');
        $password = $request->post('password');
        if (empty($account) || empty($password)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }
     
        $agents = Agents::getByAccount($account);
        if (!empty($agents)) {
            $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
            if ($real_ip == '218.253.32.206' || $real_ip == '103.172.81.132') {
                return ['code' => 500, 'msg' => '账号不存在'] ;
            }
            /* $agents_login_info = Db::name('user_login_info')->where('uid', $agents->agents_id)->where('ip', $real_ip)->where('user_type', 1)->select();
            $isSameIp = TRUE;
            if (!empty($agents_login_info)) {
                $isSameIp = TRUE;
            }

            if ($isSameIp) {

            } else {
                //需要验证验证码
                $code = Cache::get('agent_code' . $agents->agents_id);
                $code_exp = Cache::get('agent_code_exp' . $agents->agents_id);
                if (empty($code_exp) || $code != $request->post('code')) {
                    // Session::set('agent_code_exp'.$agents->agents_id, 0);
                    return ['code' => 500, 'msg' => '请先扫码', 'error' => $code_exp];
                }
            }*/
            Cache::rm('agent_code_exp' . $agents->agents_id);//验证通过后删除缓存
            Cache::rm('agent_code' . $agents->agents_id);//验证通过后删除缓存
            if ($agents->status == 0) {
                if ($agents['password'] == md5($password)) {
                    //图形验证码
                  /*  $img = Session::get('checkcode') ;
                    if (Session::has('checkcode')) {
                        if (Session::get('checkcode') != 100) {
                            return ['code' => 500, 'msg' => '图形验证码不正确'];
                        }else {
                            Session::destroy();
                        }
                    }else {
                        // exit();
                        return ['code' => 500, 'msg' => '图形验证码不正确，请刷新页面重新操作'];
                    }*/
                    //获取软件使用时间
                    $team_info = Agents::where('agents_id', $agents->tid)->field('use_end_time')->find();
                    if (time() > strtotime($team_info['use_end_time'])) {
                        return ['code' => 500, 'msg' => '软件已到期，请联系客服'];
                    }
                    $end_time_near = 0;
                    if (strtotime($team_info['use_end_time'])-time()<172800) {
                        $end_time_near = 1 ;
                    }
                    //获取群名称
                    $teamConfig = TeamConfig::alias('t')->where('t.tid', $agents->tid)->field('t.team_title')->find();
                    $team_title = '';
                    if (!empty($teamConfig)){
                        $team_title = $teamConfig['team_title'];
                    }
                    $sys_info = Domain::whereIn('type', [8, 11, 25, 28])->where('status', 0)->field('domain,type')->select();
                    $wsurl = '';
                    $wxauth = '';
                    $head_domain = '';
                    $chat_url = '';
                   // print_r($_SERVER['REMOTE_ADDR']);exit;
                    //$chat_url = common::getChatSystemDomain();
                    foreach ($sys_info as $item) {
                        if ($item['type'] == 11) {
                            $wsurl = $item['domain'];
                           // $wsurl = 'ws://'.$_SERVER['REMOTE_ADDR'].':'.config('database.mark');
                        }
                        if ($item['type'] == 25) {
                            $wxauth = $item['domain'];
                        }
                        if ($item['type'] == 8) {
                            $head_domain = $item['domain'];
                        }
                        if ($item['type'] == 28) {
                            $chat_url = $item['domain'];
                        }
                    }
                    //二维码域名
                    $domain_info = Domain::whereIn('type', 25)->where('tid',$agents->tid)->where('status', 0)->field('domain,type')->find();
                    if (!empty($domain_info)) {
                        $wxauth= $domain_info['domain'];
                    }
                    Agents::where('agents_id', $agents->agents_id)->update(['uptime' => time()]);
                    if (!empty($request->post('code'))) {
                        Agents::where('agents_id', $agents->agents_id)->update(['smstime' => time()]);
                    }
                    Session::set('agents_id', $agents->agents_id);
                    Session::set('agent_type', $agents->agent_type);
                    Session::set('tid', $agents->tid);
                    //业务域名
                    $redirectdomain = Domain::where('type', 24)->where('status', 0)->field('domain')->find();
                    $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
                    if ($agents['is_show'] == 0) {
                        if ($real_ip == '218.253.32.206' || $real_ip == '103.172.81.132') {
                            return ['code' => 500, 'msg' => '账号不存在'] ;
                        }
                        $insert_data = [
                            'uid' => $agents->agents_id,
                            'username' => $agents->account,
                            'name' => $agents->name,
                            'user_type' => 1,
                            'ip_info' => '{"ip":"' . $real_ip . '"}',
                            'ip' => $real_ip,
                            'mktime' => time(),
                            'agents_id' => $agents->boss_id,
                            'agents_account' => $agents->boss_account,
                            'agents_name' => $agents->boss_name,
                            'tid' => $agents->tid
                        ];
                        Db::name('user_login_info')->insert($insert_data);
                    }
                    $return_data = [
                        'agents_id' => $agents->agents_id,
                        'name' => $agents->name,
                        'mktime' => $agents->mktime,
                        'tid' => $agents->tid,
                        'uptime' => time(),
                        'account' => $agents->account,
                        'agent_score' => 0,
                        'wsurl' => $wsurl,
                        'token' => mt_rand(1000, 9999) . $agents['password'],
                        'wxauth' => $wxauth,
                        'auth_type' => $agents->auth_type,
                        'agent_type' => $agents->agent_type,
                        'agent_group_id'=>$agents->group_id,
                        'redirectdomain' => $redirectdomain['domain'],
                        'head_domain' => $head_domain,
                        'ip' => $real_ip,
                        'team_title' => $team_title,
                        'use_end_time' => $team_info['use_end_time'],
                        'end_time_near' =>$end_time_near
                    ];
                    /*if (empty($agents->playid)) {
                        //注册聊天系统
                        $playid = substr(md5($agents->agents_id . $agents->account . $agents->mktime), 0, 10);
                        $url = $chat_url . '/api/auth/agent/' . $playid . '/-1' . '?nickname=' . urlencode($agents->name) . '&headimgurl=&account=' . urlencode($agents->account) . '&agents_id=' . $agents->agents_id;
                        $file_contents = common::http_request($url, null, 3);
                        $resultJson = json_decode($file_contents, 1);
                        if ($resultJson['code'] == 200) {
                            Agents::where('agents_id', $agents->agents_id)->update(['playid' => $playid]);
                        }
                    }*/
                    return ['code' => 200, 'msg' => '登录成功', 'data' => $return_data];
                } else {
                    return ['code' => 500, 'msg' => '密码错误'];
                }
            } else {
                return ['code' => 500, 'msg' => '账号已停用'];
            }
        } else {
            return ['code' => 500, 'msg' => '账号不存在'];
        }
    }

    /**
     * @function 微信授权
     *
     * @return \think\response\View
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function agent_auth()
    {

        $authdomain = Domain::where('type', 21)->field('domain')->find();
        $appid = Domain::where('type', 22)->field('domain')->find();
        $domain = 'http://' . $authdomain['domain'];

        $request = Request::instance();
        $agent_id = $request->get('agent_id');
        $qcode = $request->get('qcode');
        $redirectdomain = $request->host() . '/v1/agent_auth_check';
        return view('login/agent_auth_view', [
            'appid' => $appid['domain'],
            'domain' => $domain,
            'agent_id' => $agent_id,
            'qcode' => $qcode,
            'redirectdomain' => $redirectdomain
        ]);
    }

    /**
     * @function 微信授权验证
     *
     * @return string|void
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function agent_auth_check()
    {
        $request = Request::instance();
        $agent_id = $request->get('agent_id');
        $code = $request->get('code');
        $qcode = $request->get('qcode');
        if ($qcode != Cache::get('agent_code' . $agent_id)) {
            echo "<p style='font-size:40px;'>验证失败!</p>";
            return;
        }

        //获取微信个人信息
        $authdomain = Domain::where('type', 21)->field('domain')->find();
        $appid = Domain::where('type', 22)->field('domain')->find();
        $appsecret = Domain::where('type', 23)->field('domain')->find();
        $domain = 'http://' . $authdomain['domain'];
        $OAUTH2 = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $appid['domain'] . '&secret=' . $appsecret['domain'] . '&code=' . $code . '&grant_type=authorization_code';
        $authResult = $this->curl_get_https($OAUTH2);
        $authResultJson = json_decode($authResult, 1);
        if (!empty($authResultJson['errcode'])) {
            return "errorcode:" . $authResultJson['errcode'];
        }
        $access_token = $authResultJson['access_token'];
        $openid = $authResultJson['openid'];
        //获取用户信息
        $USERINFO = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $access_token . '&openid=' . $openid . '&lang=zh_CN';
        $userResult = $this->curl_get_https($USERINFO);
        $userResultJson = json_decode($userResult, 1);
        $openid = $userResultJson["unionid"];
        //判断代理是否绑定了openid
        $agent = Agents::where('agents_id', $agent_id)->field('agents_id,name,account,openid')->find();
        if (empty($agent->openid)) {
            Agents::where('agents_id', $agent_id)->update(['openid' => $openid]);
            //TODO 通过验证
            Cache::set('agent_code_exp' . $agent_id, time(), 120);
            echo "<p style='font-size:40px;'>验证成功，请点击登录按钮完成登录！</p>";
        } else {
            if ($openid == $agent->openid) {
                //TODO 通过验证
                Cache::set('agent_code_exp' . $agent_id, time(), 120);
                echo "<p style='font-size:40px;'>验证成功，请点击登录按钮完成登录！</p>";
            } else {
                //TODO 未通过验证
                echo "<p style='font-size:40px;'>验证失败，代理账号已被其他微信绑定</p>";
            }
        }

    }

    /**
     * @function 登录验证代理
     */
    public function doLogin()
    {
        //http://ip-api.com/json/112.120.33.247?lang=zh-CN
        $request = Request::instance();
        $account = $request->post('account');
        $password = $request->post('password');
        if (empty($account) || empty($password)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }
        $agents = Agents::getByAccount($account);
        if (!empty($agents)) {
            if ($agents->agent_type != 1) {
                return ['code' => 500, 'msg' => '该账号不是代理账号'];
            }
            if ($agents->status == 0) {
                if ($agents['password'] == md5($password)) {
                    $sys_info = Domain::whereIn('type', [11, 25])->field('domain,type')->select();
                    $wsurl = '';
                    $wxauth = '';
                    foreach ($sys_info as $item) {
                        if ($item['type'] == 11) {
                            $wsurl = $item['domain'];
                        }
                        if ($item['type'] == 25) {
                            $wxauth = $item['domain'];
                        }
                    }
                    Agents::where('agents_id', $agents->agents_id)->update(['uptime' => time()]);
                    Session::set('agents_id', $agents->agents_id);
                    Session::set('agent_type', $agents->agent_type);
                    $sys_info = Domain::where('type', 8)->field('domain,type')->find();
                    $redirectdomain = Domain::where('type', 24)->where('status', 0)->field('domain')->find();
                    // $boss = Agents::where('agents_id', 1)->field('agent_score')->find();
                    $data = [
                        'agents_id' => $agents->agents_id,
                        'name' => $agents->name,
                        'mktime' => $agents->mktime,
                        'uptime' => time(),
                        'account' => $agents->account,
                        'agent_score' => $agents->agent_score,
                        'tid' => $agents->tid,
                        'wsurl' => $wsurl,
                        'token' => mt_rand(1000, 9999) . $agents['password'],
                        'wxauth' => $wxauth,
                        'auth_type' => $agents->auth_type,
                        'agent_type' => $agents->agent_type,
                        'redirectdomain' => $redirectdomain['domain'],
                        'head_domain' => $sys_info['domain'],
                    ];

                    $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
                    $insert_data = [
                        'uid' => $agents->agents_id,
                        'username' => $agents->account,
                        'name' => $agents->name,
                        'user_type' => 1,
                        'ip_info' => '{"ip":"' . $real_ip . '"}',
                        'ip' => $real_ip,
                        'mktime' => time(),
                        'agents_id' => $agents->boss_id,
                        'agents_account' => $agents->boss_account,
                        'agents_name' => $agents->boss_name
                    ];
                    Db::name('user_login_info')->insert($insert_data);
                    $data["ip"] = $real_ip;
                    if (empty($agents->playid)) {
                        //注册聊天系统
                        $playid = substr(md5($agents->agents_id . $agents->account . $agents->mktime), 0, 10);
                        $sys_info = $chat_url = $item['domain'];
                        $url = $sys_info['domain'] . '/api/auth/agent/' . $playid . '/-1' . '?nickname=' . urlencode($agents->name) . '&headimgurl=&account=' . urlencode($agents->account) . '&agents_id=' . $agents->agents_id;
                        $file_contents = common::http_request($url, null, 3);
                        $resultJson = json_decode($file_contents, 1);
                        if ($resultJson['code'] == 200) {
                            Agents::where('agents_id', $agents->agents_id)->update(['playid' => $playid]);//
                        }
                    }
                    return ['code' => 200, 'msg' => '登录成功', 'data' => $data];
                } else {
                    return ['code' => 500, 'msg' => '密码错误'];
                }
            } else {
                return ['code' => 500, 'msg' => '账号已停用'];
            }
        } else {
            return ['code' => 500, 'msg' => '账号不存在'];
        }
    }


    /**
     * @function 获取图像验证码
     */
    
    public function getImgCode() {
        $code = common::GetRandStr(4);
        $sscode = mb_strtolower(str_replace(" ","",$code));
        $salt_config = Config::get('salt');
        Session::set($salt_config['imagecodestr'].$sscode, $sscode);
        
        if (config('database.mark')== 9701) {
            return ['code'=>200,'msg'=>'图形码请求成功','data'=>[
                'imgurl'=>'http://140.210.202.20:9077/imgcode.png',
                'code'=>$sscode,
            ]];
        }
        // 1.创建画布资源
        $img = imagecreatetruecolor(100, 40);
        // 2.准备颜色
        $black = imagecolorallocate($img, 0, 0, 0);
        $white = imagecolorallocate($img, 255, 255, 255);
        $red = imagecolorallocate($img, 255, 0, 0);
        $green = imagecolorallocate($img, 0, 255, 0);
        $blue = imagecolorallocate($img, 0, 0, 255);
        
        // 3.填充画布
        imagefill($img, 0, 0, $white);
        imageLine($img, 0, rand(20,50), 200, rand(20,50), $black);
        // 4.在画布上画图像或文字
        
        // 两个给定点之间绘制一条线段,用来干扰验证码识别
        imageLine($img, 0, rand(20,50), 200, rand(20,50), $black);
        //水平绘制字符串
        imageString($img, 5, 10, 15, $code, $black);
        // 5.输出最终图像或保存最终图像
        header('content-type:image/png');
        //图片名称
        $imgname = mt_rand(1,10000).'_'.time().'.png';
        // 图片从浏览器上输出
        imagepng($img,'/home/www/wwwroot/imgcode/'.$imgname);
        $mark = config('database.mark');
        $imagehost = '';
        if ($mark == 9611) {
            $imagehost = 'http://8.217.240.173:9077/' ;
        }else if($mark == 9605 || $mark== 9613 || $mark == 9609 || $mark == 9608){
            $imagehost = 'http://'.self::QILIN_IP.':9088/' ;
        }else {
            $imagehost = 'http://'.self::JILI_IP.':9077/' ;
        }
        return ['code'=>200,'msg'=>'图形码请求成功','data'=>['imgurl'=>$imagehost.$imgname]];
    }
    
    /**
     * @function 验证图形验证码
     */
    public function checkImgCode() {
        $request = Request::instance();
        $imagecode = $request->post('imagecode');
        if (empty($imagecode)) {
            return ['code'=>500,'msg'=>'图形验证码为空'];
        }
        $imagecode =  mb_strtolower(str_replace(" ","",$imagecode));
        $salt_config = Config::get('salt');
        $ssimagecode = Session::get($salt_config['imagecodestr'].$imagecode);
        if (empty($ssimagecode)) {
            return ['code'=>500,'msg'=>'图形验证码已过期'];
        }
        $imagecode = mb_strtolower($imagecode);
        $ssimagecode = mb_strtolower($ssimagecode);
        if ($imagecode == $ssimagecode) {
            $salt_config = Config::get('salt');
            Session::set($salt_config['imagecodestr'].$imagecode, null);
            Session::set('checkcode', 100);
            return ['code'=>200,'msg'=>'图形验证码正确'];
        }else {
            return ['code'=>500,'msg'=>'图形验不正确'];
        }
    }
    
    /**
     * @function 退出登录
     */
    public function logout()
    {
        Session::set('agents_id', NULL);
        Session::set('agents_uid', NULL);
        $this->redirect('/login/index');
    }

    /**
     * @function 获取短信验证码
     */
    public function sendSmsCode($code, $phone)
    {
        $res = common::sendSMS($code, $phone);
        $result = json_decode($res, true);
        return $result;
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
}