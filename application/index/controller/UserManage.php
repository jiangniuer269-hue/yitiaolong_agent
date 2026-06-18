<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/10
 * Time: 21:11
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\AgentScoreLog;
use app\index\model\BetsLog;
use app\index\model\BetsMerge;
use app\index\model\Domain;
use app\index\model\IntegralDate;
use app\index\model\User;
use function PHPSTORM_META\elementType;
use think\Config;
use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Wxuser;
use app\index\model\IntegralLog;
use app\index\model\UserScoreLog;
use app\index\model\Wxagents;
use app\index\model\Hbgroup;
use OSS\OssClient;
use OSS\Core\OssException;
use app\index\model\TeamConfig;
use app\index\model\CardGame;

class UserManage
{

    public function __construct()
    {
        common::checkLogin();
    }
    
    /**
     * @function 批量修改占成
     */

    public function updateZc(){
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();       
        $agents_share_rate = abs(intval($request->post('agents_share_rate')));
        $agents_sb_share_rate = abs(intval($request->post('agents_sb_share_rate')));
        $type = abs(intval($request->post('type')));
        $agents_account =$request->post('agents_account');
        if ($type !=1 && $type !=2) {
            return ['code' => 500, 'msg' => '请选择类型'];
        }
        if ($agents_share_rate>10 || $agents_sb_share_rate>10 ) {
            return ['code' => 500, 'msg' => '占成比例不能大于10'];
        }
        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        //当前操作的代理信息
        $doAgentsInfo = Agents::where('agents_id',$agents_id)->field('account,name')->find();
        if ($type == 1) {
            $update_user_zc_all_sql = "update user set auto_fen=agents_share_rate,upfen_text=agents_sb_share_rate where tourist=0 and ai=0 and tid =".$tid;
            Db::execute($update_user_zc_all_sql);
            User::where('ai',0)->where('tourist',0)->where('tid',$tid)->update(['agents_share_rate'=>$agents_share_rate,'agents_sb_share_rate'=>$agents_sb_share_rate]);    
            $note = '代理' . $doAgentsInfo['account'] . '修改了所有会员占成比例,庄闲占成'.$agents_share_rate.',四宝占成'.$agents_sb_share_rate ;
            common::system_log($doAgentsInfo['account'],'所有会员', 3, $note, $real_ip, '', $tid);
        }
        if ($type == 2) {
            if (empty($agents_account)) {
                return ['code' => 500, 'msg' => '修改部分会员占成,需要填写代理账号'];
            }
            //被操作代理信息
            $bedealAgentsInfo = Agents::where('account',$agents_account)->field('agents_id,account,name')->find();
            if (empty($bedealAgentsInfo)) {
                return ['code' => 500, 'msg' => '代理不存在'];
            }         
            $update_user_zc_part_sql = "update user set auto_fen=agents_share_rate,upfen_text=agents_sb_share_rate where tourist=0 and ai=0 and tid=".$tid." and agents_id=".$bedealAgentsInfo['agents_id'];
            Db::execute($update_user_zc_part_sql);
            User::where('ai',0)->where('tourist',0)->where('agents_id',$bedealAgentsInfo['agents_id'])->where('tid',$tid)->update(['agents_share_rate'=>$agents_share_rate,'agents_sb_share_rate'=>$agents_sb_share_rate]);
            $note = '代理' . $doAgentsInfo['account'] . '修改了代理'.$agents_account.'下面会员占成比例,庄闲占成'.$agents_share_rate.',四宝占成'.$agents_sb_share_rate ;
            common::system_log($doAgentsInfo['account'],$doAgentsInfo['account'], 3, $note, $real_ip, '', $tid);
        }
        
          return ['code' => 200, 'msg' => '操作成功'];
        
    }
    /**
     * @function 批量修改积分比例
     */
    
    public function updateInteRate(){
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $inte_rate = abs(intval($request->post('inte_rate')));
        $type = abs(intval($request->post('type')));
        $agents_account =$request->post('agents_account');
        if ($type !=1 && $type !=2) {
            return ['code' => 500, 'msg' => '请选择类型'];
        }

        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        //当前操作的代理信息
        $doAgentsInfo = Agents::where('agents_id',$agents_id)->field('account,name')->find();
        if ($type == 1) {
            $update_user_integral_all_sql = "update user set odds_text=inte_rate where tourist=0 and ai=0 and tid=".$tid;
            Db::execute($update_user_integral_all_sql);
            User::where('ai',0)->where('tourist',0)->where('tid',$tid)->update(['inte_rate'=>$inte_rate,'xm_rate'=>$inte_rate]);
                        
            $note = '代理' . $doAgentsInfo['account'] . '修改了所有会员积分比例,积分比例改为'.$inte_rate ;
            common::system_log($doAgentsInfo['account'],'所有会员', 1, $note, $real_ip, '', $tid);
        }
        if ($type == 2) {
            if (empty($agents_account)) {
                return ['code' => 500, 'msg' => '修改部分会员积分比例,需要填写代理账号'];
            }
            //被操作代理信息
            $bedealAgentsInfo = Agents::where('account',$agents_account)->field('agents_id,account,name')->find();
            if (empty($bedealAgentsInfo)) {
                return ['code' => 500, 'msg' => '代理不存在'];
            }
            $update_user_integral_part_sql = "update user set odds_text=inte_ratewhere tourist=0 and ai=0 and tid=".$tid." and agents_id=".$bedealAgentsInfo['agents_id'];
            Db::execute($update_user_integral_part_sql);
            User::where('ai',0)->where('tourist',0)->where('agents_id',$bedealAgentsInfo['agents_id'])->where('tid',$tid)->update(['inte_rate'=>$inte_rate,'xm_rate'=>$inte_rate]);
            $note = '代理' . $doAgentsInfo['account'] . '修改了代理'.$agents_account.'下面会员积分比例,积分比例改为'.$inte_rate ;
            common::system_log($doAgentsInfo['account'],$doAgentsInfo['account'], 1, $note, $real_ip, '', $tid);
        }
        
        return ['code' => 200, 'msg' => '操作成功'];
        
    }
    /**
     * @function 根据ID查用户
     */
    public function listUserById()
    {
        $request = Request::instance();
        $uid = $request->post('uid');//uid

        $firstday = date('Ym01', time());
        $lastday = date('Ymd', strtotime("$firstday +1 month -1 day"));

        $user = User::alias('u')->where('u.uid', $uid)
            ->field('u.uid,u.name,u.head,u.score,u.agent_id,u.username')
            ->select();
        if (empty($user)) {
            return ['code' => 500, 'msg' => '用户不存在'];
        }
        //查月积分
        $datay = IntegralDate::alias('i')
            ->field('sum(i.integral) as y_integral')
            ->where('i.uid', '=', $uid)
            ->where('i.date_time', '>=', $firstday)
            ->where('i.date_time', '<=', $lastday)
            ->select();

        //查日积分
        $datad = IntegralDate::alias('i')
            ->field('i.integral as d_integral')
            ->where('i.uid', '=', $uid)
            ->where('i.date_time', '=', date("Ymd", time()))
            ->select();
        if (!empty($datay[0])) {
            $user[0]['y_integral'] = $datay[0]['y_integral'];
        } else {
            $user[0]['y_integral'] = 0;
        }
        if (!empty($datad[0])) {
            $user[0]['d_integral'] = $datad[0]['d_integral'];
        } else {
            $user[0]['d_integral'] = 0;
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'users' => $user
            ]
        ];
    }

    /**
     * @function 获取红包群配置
     */

    public function getLastHbSetting()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $type = $request->post('type');
        if (is_array($agents_id)) {
            return ['code' => 400, 'msg' => '登录失效'];
        }

        $data = Hbgroup::order('hbgroup_id desc')->where("type", $type)->find();
        return ['code' => 200, 'data' => $data];
    }

    /**
     * @function 批量修改会员占成
     */
    public function updateAllshare()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $zx_share = $request->post('zx_share');
        $sb_share = $request->post('sb_share');
        $update = [];
        if ($zx_share != "") {
            $update['agents_share_rate'] = $zx_share;
        }
        if ($sb_share != "") {
            $update['agents_sb_share_rate'] = $sb_share;
        }
        if (empty($update)) {
            return ['code' => 500, 'msg' => '参数不合法'];
        }
        User::where("tid", $tid)->where("tourist", 0)->where("ai", 0)->update($update);
        return ['code' => 200, 'msg' => '批量修改会员占成成功'];
    }

    /**
     * @function 会员列表
     */
    public function userList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $username = $request->post('username');
        $name = $request->post('name');
        $agents_account = $request->post('agents_account');
        $usertype = intval($request->post('user_type')); //用户类型， 0 全部  1会员 2 游客 3 虚拟
        $search_type = $request->post('search_type'); //1 模糊查询 2精准查询
        $level = $request->post('level');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $member = [];
        $sql = User::alias('u')->join('wxagents wx', 'u.agents_id=wx.agents_id');
        $sql->field('u.head,u.uid,u.name,u.score,u.username,u.playid,u.xm_rate,u.xm_type,u.active,u.agents_share_rate,u.agents_sb_share_rate,u.status,u.agents_account,u.agents_name,u.openid,u.agents_id,u.no_say,u.xh_config,u.zx_max,u.zx_min,u.phone,u.wxchat,u.qq,u.bankcard,u.extra_share,u.user_desc,u.integral,u.tourist,u.ai,u.inte_rate,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id)->where('u.user_type', 0);
        $sql->order('u.uid', 'desc');
        if ($search_type == 2) {
            //代理账号查询
            if (!empty($agents_account)) {
                //获取代理信息
                $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                    ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
                if (!empty($search_agents)) {
                    $search_agents_id = $search_agents['agents_id'];
                    $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                        $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                    });
                } else {
                    return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
                }
            }
            //会员名称查询
            if (!empty($name)) {
                $sql->where('u.name', $name);
            }
        } elseif ($search_type == 1) {
            //代理账号查询
            if (!empty($agents_account)) {
                $sql->where('u.agents_account', 'like', '%' . $agents_account . '%');
            }
            //会员名称查询
            if (!empty($name)) {
                $sql->where('u.name', 'like', '%' . $name . '%');
            }
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($username)) {
            $sql->where('u.uid', $username);
        }

        if ($usertype == 1) {
            $sql->where('u.tourist', 0)->where('u.ai', 0);
        } elseif ($usertype == 2) {
            $sql->where('u.tourist', 1)->where('u.ai', 0);
        } elseif ($usertype == 3) {
            $sql->where('u.tourist', 0)->where('u.ai', 1);
        }
        $users = $sql->limit($start, $pageSize)->select();
        $totalUser = $sql->select();
        $total = 0;
        //查询会员 机器人数量
        $userCount = 0;
        $aiCount = 0;
        $touristCount = 0;
        foreach ($totalUser as $item) {
            if ($item['tourist'] == 0 and $item['ai'] == 1) {
                $aiCount++;
            }
            if ($item['tourist'] == 0 and $item['ai'] == 0) {
                $userCount++;
            }
            if ($item['tourist'] == 1 and $item['ai'] == 0) {
                $touristCount++;
            }
            $total++;
        }

        foreach ($users as $item) {
            $usertype = 2;
            if ($item['tourist'] == 1 and $item['ai'] == 0) {
                $usertype = 3;
            }
            if ($item['ai'] == 1 and $item['tourist'] == 0) {
                $usertype = 4;
            }
    
            /*
            if(!empty($item['head'])) {
                $res = @file_get_contents($item['head'],null,null,0,10);
                if(!$res){
                    Db::name('user')->where('uid',$item['uid'])->update(['head'=>'']);
                    $item['head'] =  '';
                }               
            }*/
            $member[] = [
                'agents_id' => $item['agents_id'],
                'username' => $item['username'],
                'name' => $item['name'],
                'agents_account' => $item['agents_account'],
                'agents_name' => $item['agents_name'],
                'score' => $item['score'],
                'status' => $item['status'],
                'active' => $item['active'],
                'xm_rate' => $item['xm_rate'],
                'inte_rate' => $item['xm_rate'],
                'sb_xm_rate' => $item['xm_rate'],
                'no_say' => $item['no_say'],
                'openid' => $item['openid'],
                'playid' => $item['playid'],
                'xm_type' => $item['xm_type'],
                'xh_config' => $item['xh_config'],
                'agents_share_rate' => $item['agents_share_rate'],
                'agents_sb_share_rate' => $item['agents_sb_share_rate'],
                'usertype' => $usertype,
                'uid' => $item['uid'],
                'zx_max' => $item['zx_max'],
                'zx_min' => $item['zx_min'],
                'phone' => $item['phone'],
                'wxchat' => $item['wxchat'],
                'qq' => $item['qq'],
                'bankcard' => $item['bankcard'],
                'extra_share' => $item['extra_share'],
                'user_desc' => $item['user_desc'].' '.$item['qq'],
                'integral' => floor($item['integral']),
                'head' => $item['head'],
                'relation' => $item['relation'],
                'relation_link' => $item['relation_link'],
                'level' => $item['level']         
            ];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'userCount' => $userCount,
                'aiCount' => $aiCount,
                'touristCount' => $touristCount,
                'users' => $member,
                'total' => $total,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize
            ]
        ];
    }

    /**
     * @function 会员详情
     */
    public function getUserDetail()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        //累计上下分
        $upfen = UserScoreLog::alias('u')->where('u.uid', $uid)->where('u.type', 11)->field('sum(score_change) as upfencount')->find();
        $downfen = UserScoreLog::alias('u')->where('u.uid', $uid)->where('u.type', 12)->field('sum(-score_change) as downfencount')->find();
        //输赢
        $winfen = BetsMerge::alias('u')->where('u.uid', $uid)->field('sum(u.win) as winfen')->find();
        //累计积分从积分表查
        $integral_total = IntegralDate::where('uid', $uid)->field('sum(integral) as integral')->find();
        //累计兑换额度 从积分上表查
        $integral_exchange = UserScoreLog::where('uid', $uid)->where('type', 100)->field('sum(score_change) as integral_exchange')->find();
        //会员收益
        //TODO
        //累计红包
        $hongbao_total = DB::name("hongbao_user")->alias("hu")->where('hu.uid', $uid)->sum('score');
        $user_profit = sprintf("%.2f", $upfen['upfencount'] - $downfen['downfencount'] + $winfen['winfen'] - $integral_exchange['integral_exchange'] - $hongbao_total);

        return ['code' => 200, 'msg' => '请求成功',
            'winfen' => $winfen['winfen'],
            'upfen' => $upfen['upfencount'],
            'downfen' => $downfen['downfencount'],
            'integral_total' => $integral_total['integral'],
            'integral_exchange' => empty($integral_exchange['integral_exchange']) ? 0 : $integral_exchange['integral_exchange'],
            'user_profit' => $user_profit
        ];
    }

    /**
     * @function 下载excel表格
     */
    public function doExcel()
    {
        $agents_uid = Session::get('agents_uid');
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');

        if ($agents_uid == 1) {
            $users = User::field('uid,name')->order('uid', 'desc')->select();
        } else {
            $users = Wxuser::getWxUserInfo($agents_uid);
        }
        $count = 0;// 查询满足要求的总记录数
        foreach ($users as $item){
            $count++;
        }
        $title_str = "用户ID,昵称,庄闲输赢,三宝输赢,庄闲洗码,三宝洗码";
        if ($count == 0) {
            $exl_title = explode(',', $title_str);
            common::exportToExcel('用户数据报表.xls', $exl_title, [], 100);
            exit();
        }
        foreach ($users as &$user) {
            $uid = $user->uid;
            $user['zxsy'] = 0;//庄闲输赢
            $user['sbsy'] = 0;//三宝输赢
            $user['zxxm'] = 0;//庄闲洗码
            $user['sbxm'] = 0;//三宝洗码
            //个人
            $sql = BetsLog::where('uid', $uid)->field('type,win,xm');
            if (!empty($begin_time)) {
                $sql = $sql->where('time', '>=', $begin_time);
            }
            if (!empty($end_time)) {
                $sql = $sql->where('time', '<=', $end_time);
            }
            $betLogsAll = $sql->select();
            foreach ($betLogsAll as $item) {
                if (in_array($item['type'], [1, 2])) {
                    $user['zxxm'] += $item['xm'];
                    $user['zxsy'] += $item['win'];
                }
                if (in_array($item['type'], [3, 4, 5])) {
                    $user['sbxm'] += $item['xm'];
                    $user['sbsy'] += $item['win'];
                }
            }
        }
        $kkk = 1;
        $rs[$kkk] = $users;
        $title_str[$kkk] = $title_str;
        $exl_title[$kkk] = explode(',', $title_str[$kkk]);
        foreach ($rs[$kkk] as $k => $v) {
            $exl[$kkk][] = array(
                $v['uid'], $v['name'], $v['zxsy'], $v['sbsy'], $v['zxxm'], $v['sbxm']
            );
        }

        common::exportToExcel('用户数据报表.xls', $exl_title[$kkk], $exl[$kkk], 1000);
        exit();
    }

    /**
     * @function 操作
     */
    public function token()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        if (common::checkEmp($agents_id) != 3) {
            return ['code' => 500, 'msg' => '没权限操作'];
        }
        $agents = Agents::where('agents_id', $agents_id)->field('account,name')->find();

        $request = Request::instance();
        $uid = intval($request->post('uid'));
        if (empty($uid)) {
            return ['code' => 500, 'msg' => '请求错误', 'data' => null];
        }
        //生成token
        $token = substr(md5($uid . "_" . time()), 1, 10);
        $redirectDomain = Domain::where('type', 24)->where('status', 0)->field('domain')->find();
        User::where('uid', $uid)->update(['token' => $token]);

        $user = User::where('uid', $uid)->field('username')->find();

        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
        $ip_data = json_decode($ip_data_json, true);

        $c_ip = $real_ip;
        $address = "";
        if (!empty($ip_data) && $ip_data['code'] == 0) {
            $ip_info = $ip_data['data'];
            $c_ip = $ip_info['ip'];
            $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
        }

        $note = '代理' . $agents['account'] . '获取会员' . $user['username'] . '的临时登录地址';
        common::system_log($agents['account'], $user['username'], 9, $note, $c_ip, $address, $tid);


        return ['code' => 200, 'msg' => '获取成功', 'url' => 'http://' . $redirectDomain['domain'] . '?token=' . $token . '/#login'];
    }

    /**
     * @function 操作
     */
    public function deal()
    {
        $request = Request::instance();
        $uid = intval($request->get('uid'));
        $sql = new User();
        $sql->alias('u')->field('u.uid,u.name,u.head,u.last_time,u.reg_time,u.score,u.inte_rate');
        $user = $sql->where('uid', $uid)->find();
        $user['integral'] = IntegralDate::getUserIntegral(['uid' => $uid]);
        return view('userDeal', ['user' => $user]);
    }


    /**
     * @function 添加会员
     */
    public function doUserAdd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type')->find();
        if (!($agent['agent_type'] == 2 || $agent['agent_type'] == 3)) {
            return ['code' => 500, 'msg' => '没有操作权限'] ;
        }
        $request = Request::instance();
        $agents_id = common::changeAgentId($agents_id);
        $name = $request->post('name');
        $head = $request->post('head');
        $username = $request->post('username');
        $password = $request->post('password');
        $user_desc = $request->post('user_desc');
        $xm_rate = $request->post('xm_rate');
        $agents_account = $request->post('agents_account');
        $agents_share_rate = intval($request->post('agents_share_rate'));
        $agents_sb_share_rate = intval($request->post('agents_sb_share_rate'));
        $xm_type = 2;
        $extra_share = abs(intval($request->post('extra_share')));
        $tourist = abs(intval($request->post('tourist')));
        if (empty($name) || empty($username) || empty($password) || empty($agents_id)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/u', $username)) {
            return ['code' => 500, 'msg' => '账号只能包含数字和字母'];
        }
        
        $pattern = '/^(?=.*[0-9])(?=.*[a-zA-Z])(.{6,})$/';
        if (!preg_match($pattern, $username)) {
            return ['code' => 500, 'msg' => '账号必须同时含有字母，数字，且不小于6位'];
        }   
        if (strlen($username) > 15) {
            return ['code' => 500, 'msg' => '会员账号过长'];
        }

        $user = User::where('username', $username)->field('uid')->find();
        if (!empty($user)) {
            return ['code' => 500, 'msg' => '该账号已被使用'];
        }

        //获取代理信息
        $agents = Agents::where('agents_id', $agents_id)->field('account,name,xm_rate,tid')->find();
        if (empty($agents)) {
            return ['code' => 500, 'msg' => '代理不存在，请检查代理账号是否正确'];
        }
//        if (intval($xm_rate) > intval($agents['xm_rate']) || intval($xm_rate) > 11) {
//            return ['code' => 500, 'msg' => '积分比例不能大于上级的积分比例'];
//        }

        if (!empty($agents_account)) {
            $agents = Agents::where('account', $agents_account)->field('agents_id,account,name,xm_rate,tid')->find();
            if (empty($agents)) {
                return ['code' => 500, 'msg' => '代理不存在，请检查代理账号是否正确'];
            }
        }else {
            $agents = Agents::where('agents_id', $agents_id)->field('agents_id,account,name,xm_rate,tid')->find();
            if (empty($agents)) {
                return ['code' => 500, 'msg' => '代理不存在，请检查代理账号是否正确'];
            }
        }
        $agents_name = $agents['name'];
        $agents_account = $agents['account'];
        $agents_id = $agents['agents_id'];
        $now_time = date('Y-m-d H:i:s');
        $insertData = [
            'head' => $head,
            'name' => $name,
            'username' => $username,
            'password' => md5('pwdsalt!@/\~~;168'. $password),
            'reg_time' => $now_time,
            'agents_id' => $agents_id,
            'agents_name' => $agents_name,
            'agents_account' => $agents_account,
            'last_time' => $now_time,
            'xm_rate' => $xm_rate,
            'inte_rate' => $xm_rate,
            'agents_share_rate' => $agents_share_rate,
            'agents_sb_share_rate' => $agents_sb_share_rate,
            'user_desc' => $user_desc,
            'xm_type' => $xm_type,
            'extra_share' => $extra_share,
            'tourist' =>$tourist,
            'active' => 1,
            'tid' => $agents['tid']
        ];
        $userModel = new user();
        $insert_id = $userModel->insert($insertData, false, true);
        if ($insert_id) {
            return ['code' => 200, 'msg' => '添加成功', 'data' => ['uid' => $insert_id]];
        } else {
            return ['code' => 500, 'msg' => '添加失败'];
        }
    }

    /**
     * @function 修改密码
     */
    public function update_pwd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $password = $request->post('password');
        $uid = $request->post('uid');
        if (empty($password) || empty($uid)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }
        $password = 'pwdsalt!@/\~~;168'. $password;
        $res = User::where('uid', $uid)->update(['password' => md5($password),'phonelogin'=>1]);
        $user = User::where('uid', $uid)->field('username')->find();
        if ($res) {
            // if (common::checkEmp($agents_id) != 3) {
            //     return ['code' => 500, 'msg' => '没权限操作'];
            // }
            $agents = Agents::where('agents_id', $agents_id)->field('account,name,tid')->find();

            $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
            $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
            $ip_data = json_decode($ip_data_json, true);

            $c_ip = $real_ip;
            $address = "";
            if (!empty($ip_data) && $ip_data['code'] == 0) {
                $ip_info = $ip_data['data'];
                $c_ip = $ip_info['ip'];
                $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
            }

            $note = '代理' . $agents['account'] . '重置会员' . $user['username'] . '的密码';
            common::system_log($agents['account'], $user['username'], 16, $note, $c_ip, $address, $agents['tid']);

            return ['code' => 200, 'msg' => '操作成功'];
        } else {
            return ['code' => 500, 'msg' => '操作失败或密码一样'];
        }
    }

    /**
     * @function 手动上下分
     */
    public function upDowFen()
    {
        return ['code' => 500, 'msg' => '调用接口错误'];
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        if (common::checkEmp($agents_id) != 3) {
            return ['code' => 500, 'msg' => '没权限操作'];
        }
        $agents_id = common::changeAgentId($agents_id);
        //获取代理信息
        $boss = Agents::where('agents_id', 1)->field('agent_score')->find();
        $agents = Agents::where('agents_id', $agents_id)->field('account,name')->find();
        $uid = intval(abs($request->post('uid')));
        $fen = abs($request->post('fen'));
        $doType = intval(abs($request->post('doType'))); //1上分 2下分
        $user = User::where('uid', $uid)->field('name,username,score,agents_id,ai,tourist,agents_account,agents_name,csf')->find(); //获取用户余额
        if ($user && abs($fen) > 0) {
            if ($doType == 1) {
                $type = 11;
                $agentsType = 12;//代理下分
                $note = '上分' . $fen;
                if ($boss['agent_score'] < $fen) {
                    return ['code' => 500, 'msg' => '代理余分不足'];
                }
            } else {
                $fen = -$fen;
                if ($user['score'] + $fen < 0) {
                    return ['code' => 500, 'msg' => '用户余额不足'];
                }
                $type = 12;
                $agentsType = 11;//代理上分
                $note = '下分' . $fen;
            }
            $user_score_after = $user['score'] + $fen;
            $user_csf_after = $user['csf'] + $fen;
            $boss_score_after = $boss['agent_score'];
            if ($user['ai'] == 0 && $user['tourist'] == 0) {
                $boss_score_after = $boss['agent_score'] - $fen;
            }
            $mktime = time();
            $insert_data = [
                'uid' => $uid,
                'score' => $user['score'],
                'score_change' => $fen,
                'score_after' => $user_score_after,
                'type' => $type,
                'time' => $mktime,
                'orderid' => 0,
                'ukey' => $type . '_' . $user['agents_id'] . '_' . date('YmdHis'),
                'note' => $note,
                'agents_id' => $user['agents_id'],
                'user_ai' => $user['ai'],
                'tourist' => $user['tourist']
            ];
            try {
                // 启动事务
                Db::startTrans();
                $insert_id = UserScoreLog::insert($insert_data, false, true);
                if ($insert_id) {
                    //代理余分变动
                    $agents_score_data = [
                        'uid' => $uid,
                        'name' => $user['name'],
                        'agents_id' => $user['agents_id'],
                        'agents_name' => $user['agents_name'],
                        'agents_account' => $user['agents_account'],
                        'do_agents_id' => $agents_id,
                        'do_agents_name' => $agents['name'],
                        'do_agents_account' => $agents['account'],
                        'score' => $boss['agent_score'],
                        'score_change' => -$fen,
                        'score_after' => $boss_score_after,
                        'type' => $agentsType,
                        'orderid' => $insert_id,
                        'ukey' => $insert_id,
                        'mktime' => $mktime,
                        'note' => '给会员' . $user['name'] . '(' . $uid . ')，' . $note,
                        'usertype' => 1,
                        'user_ai' => $user['ai'],
                        'tourist' => $user['tourist']
                    ];
                    $agents_score_log_id = AgentScoreLog::insert($agents_score_data, false, true);
                    if ($agents_score_log_id > 0) {
                        if ($user['ai'] == 0 && $user['tourist'] == 0) {
                            Agents::where('agents_id', 1)->update(['agent_score' => $boss_score_after]);
                        }
                        //修改用户余分
                        User::where('uid', $uid)->update(['score' => $user_score_after, 'csf' => $user_csf_after]);
                        // 提交事务
                        Db::commit();
                        //写日志
                        $sxfen = $doType == 1 ? '上分' : '下分';
                        $sxfenType = $doType == 1 ? 6 : 7;
                        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
                        $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
                        $ip_data = json_decode($ip_data_json, true);
                        $c_ip = $real_ip;
                        $address = "";
                        if (!empty($ip_data) && $ip_data['code'] == 0) {
                            $ip_info = $ip_data['data'];
                            $c_ip = $ip_info['ip'];
                            $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
                        }
                        $note = '代理' . $agents['account'] . '给会员' . $user['username'] . $sxfen . ',金额由' . $user['score'] . '改为' . $user_score_after;
                        common::system_log($agents['account'], $user['username'], $sxfenType, $note, $c_ip, $address, $tid);
                        return [
                            'code' => 200,
                            'msg' => '操作成功',
                            'data' => [
                                'score' => $fen,
                                'uid' => $uid,
                                'userScore' => $user_score_after,
                                'agentScore' => $boss_score_after
                            ]
                        ];
                    } else {
                        // 回滚事务
                        Db::rollback();
                        return ['code' => 500, 'msg' => '网络异常,请重新操作1'];
                    }
                } else {
                    // 回滚事务
                    Db::rollback();
                    return ['code' => 500, 'msg' => '网络异常,请重新操作2'];
                }
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return ['code' => 500, 'msg' => '网络异常,请重新操作3'];
            }
        } else {
            return ['code' => 500, 'msg' => '参数错误'];
        }
    }

    /**
     * @function 上下分明细
     */
    public function updowFenLog()
    {
        $request = Request::instance();
        $uid = $request->post('uid');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');

        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $query_sql = Db::name('UserScoreLog')->alias('u')->join('agents a', 'u.agents_id=a.agents_id')->join('user uu', 'u.uid=uu.uid')
            ->where('u.uid', $uid)->whereIn('type', [1100, 1200, 11, 12]);
        if (!empty($begin_time)) {
            $query_sql->where('u.time', '>', $begin_time);
        }
        if (!empty($end_time)) {
            $query_sql->where('u.time', '<', $end_time);
        }
        $query_sql->field('u.score,u.score_change,u.score_after,u.type,u.note,u.agents_id,u.time,uu.name as name,uu.username as username,a.account as agents_account,a.name as agents_name')
            ->order('u.id', 'desc');
        $data = $query_sql->limit($start, $pageSize)->select();
        $total = $query_sql->count();
        foreach ($data as &$item) {
            $item['time'] = date('Y-m-d H:i:s', $item['time']);
        }
        return ['code' => 200, 'msg' => '操作成功',
            'data' => $data,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'total' => $total
        ];
    }


    /**
     * @function 按积分排序当日
     */
    public function listUserByJifen()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $type = intval(abs($request->post('type')));

        $filterYk = intval(abs($request->post('filterYk')));
        $filterRobot = intval(abs($request->post('filterRobot')));
        $firstday = date('Ym01', time());
        $lastday = date('Ymd', strtotime("$firstday +1 month -1 day"));
        $sql = IntegralDate::alias('i')->join('user', 'user.uid = i.uid');
        if ($type == 1) {
            $sql = $sql->field('user.name,user.uid,i.integral as d_integral');
            if ($filterYk == 1) {
                $sql->where('user.tourist != 1');
            }
            if ($filterRobot == 1) {
                $sql->where('user.ai', 0);
            }
            $data = $sql->where('i.date_time', '=', date("Ymd", time()))
                ->where('i.integral', '>=', 3)
                ->order('i.integral', 'desc')
                ->select();
        } else if ($type == 2) {
            $sql = $sql->field('user.name,user.uid,sum(i.integral) as y_integral');
            if ($filterYk == 1) {
                $sql->where('user.tourist != 1');
            }
            if ($filterRobot == 1) {
                $sql->where('user.ai', 0);
            }
            $data = $sql->where('i.date_time', '>=', $firstday)
                ->where('i.date_time', '<=', $lastday)
                ->group('i.uid HAVING y_integral>300')
                ->order('y_integral', 'desc')->select();
        }
        return ['code' => 200, 'data' => $data, 'msg' => '操作成功'];
    }

    /**
     * @function 获取红包历史记录
     */
    public function getHbHis()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $datas = DB::name("hongbao")->alias("hb")->where('mktime', '>=', $begin_time)->where('mktime', '<=', $end_time)->order('uptime desc')->select();
        return ['code' => 200, 'data' => $datas, 'msg' => '操作成功'];
    }

    /**
     * @function 获取红包详细记录
     */
    public function getHbDetail()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $hbid = $request->post('hbid');
        $datas = DB::name("hongbao_user")->alias("hu")->join('user u', 'u.uid = hu.uid')->field("hu.uid,u.name,u.ai,u.tourist,hu.score,hu.uptime,hu.lucky")->where('hu.hb_id', '=', $hbid)->order('hu.uptime desc')->select();
        return ['code' => 200, 'data' => $datas, 'msg' => '操作成功'];
    }

    /**
     * @function 获取用户抢到红包列表
     */
    public function getHbHisByUid()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $pageNumber = intval($request->post('pageNumber'));
        $pageSize = intval($request->post('pageSize'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $sql = DB::name("hongbao_user")->alias("hu")->join('user u', 'u.uid = hu.uid')->field("hu.uid,u.name,hu.score,hu.uptime,hu.lucky")->where('hu.uid', $uid);
        if (!empty($begin_time)) {
            $sql->where('hu.mktime', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('hu.mktime', '<=', $end_time);
        }
        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        return ['code' => 200, 'msg' => '操作成功',
            'data' => $data,
            'pageNumber' => $pageNumber,
            'pageSize' => $pageSize,
            'total' => $total
        ];

    }

    /**
     * @function 编辑会员信息
     */
    public
    function updateUserInfo()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $xm_rate = intval(abs($request->post('xm_rate')));
        $agents_info = Agents::where('agents_id', $agents_id)->field('account,xm_rate,agent_type,auth_type')->find();
        if ( !($agents_info['agent_type'] == 2||$agents_info['agent_type'] == 3)) {
            return ['code' => 500, 'msg' => '只有主持账号能操作'];;
        }
//        if (intval($xm_rate) > intval($agents_info['xm_rate'])) {
//            return ['code' => 500, 'msg' => '会员积分比例不能大于代理的积分比例'];
//        }

        $uid = $request->post('uid');
        $user =  User::where('tourist',1)->where('user_type',1)->where('uid',$uid)->find();
        if (!empty($user)) {
            return ['code' => 500, 'msg' => '游客禁止修改'];
        }
        $name = $request->post('name');
        $agents_share_rate =intval($request->post('agents_share_rate'));
        $agents_sb_share_rate = intval($request->post('agents_sb_share_rate'));
        $password = $request->post('password');
        $xm_type = $request->post('xm_type');
        $xm_type = 2;
        $agents_account = $agents_info['account'];//写日志用

        $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
        $ip_data_json = common::http_request("http://ip.taobao.com/service/getIpInfo.php?ip=" . $real_ip, null, 3);
        $ip_data = json_decode($ip_data_json, true);
        $c_ip = $real_ip;
        $address = "";
        if (!empty($ip_data) && $ip_data['code'] == 0) {
            $ip_info = $ip_data['data'];
            $c_ip = $ip_info['ip'];
            $address = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
        }

        $no_say = intval($request->post('no_say'));
        $status = intval($request->post('status'));
        $xh_config = intval($request->post('xh_config'));
        $zx_max = abs(intval($request->post('zx_max')));
        $zx_min = abs(intval($request->post('zx_min')));
        $phone = $request->post('phone');
        $wxchat = $request->post('wxchat');
        $qq = $request->post('qq');
        $bankcard = $request->post('bankcard');
        $user_desc = $request->post('user_desc');
        $extra_share = abs(intval($request->post('extra_share')));

        $user = User::where('uid', $uid)->where('tid', $tid)->field('uid,name,xm_rate,agents_account,agents_name,agents_share_rate,xm_type,username,no_say,status,xh_config,zx_max,zx_min,phone,wxchat,qq,bankcard,extra_share,agents_sb_share_rate')->find();
        $boss_agents_account = $request->post('agents_account');
        if ($boss_agents_account != $user['agents_account'] && !empty($boss_agents_account)) {//需要修改会员的上级
            $change_agents = Agents::where('account', $boss_agents_account)->where('tid', $tid)->field('agents_id,share_rate,account,name')->find();
            if (empty($change_agents)) {
                return ['code' => 500, 'msg' => '修改代理错误，找不到该代理'];
            } else {
                User::where('uid', $uid)->update([        
                    'agents_name' => $change_agents['name'],
                    'agents_id' => $change_agents['agents_id'], 
                    'agents_account'=>$boss_agents_account
                ]);
                $note = '代理' . $agents_account . '将会员' . $user['username'] . '的直属代理，由' . $user['agents_account'] . '改为' . $change_agents['account'];
                common::system_log($agents_account, $user['username'], 17, $note, $c_ip, $address, $tid);
            }
        }


        if (!empty($user)) {
            $updateData = [];

            if ($user['xm_rate'] != $xm_rate) {
                $updateData['xm_rate'] = $xm_rate;
                $updateData['inte_rate'] = $xm_rate;
                $note = '代理' . $agents_account . '将会员' . $user['username'] . '的洗码率，由' . $user['xm_rate'] . '改为' . $xm_rate;
                common::system_log($agents_account, $user['username'], 1, $note, $c_ip, $address, $tid);
            }


            if ($user['xm_type'] != $xm_type) {
                $updateData['xm_type'] = $xm_type;
                $note = '代理' . $agents_account . '将会员' . $user['username'] . '的洗码类型，由' . $this->xm_type($user['xm_type']) . '改为' . $this->xm_type($xm_type);
                common::system_log($agents_account, $user['username'], 2, $note, $c_ip, $address, $tid);
            }

   
                if ($user['agents_share_rate'] != $agents_share_rate) {
                    $updateData['agents_share_rate'] = $agents_share_rate;
                    $note = '代理' . $agents_account . '将会员' . $user['username'] . '的庄闲占成比例，由' . $user['agents_share_rate'] . '改为' . $agents_share_rate;
                    common::system_log($agents_account, $user['username'], 3, $note, $c_ip, $address, $tid);
                }
            
    
                if ($user['agents_sb_share_rate'] != $agents_sb_share_rate) {
                    $updateData['agents_sb_share_rate'] = $agents_sb_share_rate;
                    $note = '代理' . $agents_account . '将会员' . $user['username'] . '的四宝占成比例，由' . $user['agents_sb_share_rate'] . '改为' . $agents_sb_share_rate;
                    common::system_log($agents_account, $user['username'], 3, $note, $c_ip, $address, $tid);
                }
            

            if (!empty($name)) {
                if ($user['name'] != $name) {
                    $updateData['name'] = $name;
                    $note = '代理' . $agents_account . '将会员' . $user['username'] . '的用户名，由' . $user['name'] . '改为' . $name;
                    common::system_log($agents_account, $user['username'], 4, $note, $c_ip, $address, $tid);
                }
            }
            if (!empty($password)) {
                $updateData['password'] = md5($password);
                $note = '代理' . $agents_account . '修改了会员' . $user['username'] . '的密码';
                common::system_log($agents_account, $user['username'], 5, $note, $c_ip, $address, $tid);
            }

            if ($user['no_say'] != $no_say) {
                $updateData['no_say'] = $no_say;
            }

            if ($user['status'] != $status) {
                $updateData['status'] = $status;
            }
            if ($user['xh_config'] != $xh_config) {
                $updateData['xh_config'] = $xh_config;
            }
            if ($user['zx_max'] != $zx_max) {
                $updateData['zx_max'] = $zx_max;
            }
            if ($user['zx_min'] != $zx_min) {
                $updateData['zx_min'] = $zx_min;
            }
            if ($user['phone'] != $phone) {
                $updateData['phone'] = $phone;
            }
            if ($user['wxchat'] != $wxchat) {
                $updateData['wxchat'] = $wxchat;
            }
            if ($user['qq'] != $qq) {
                $updateData['qq'] = $qq;
            }
            if ($user['bankcard'] != $bankcard) {
                $updateData['bankcard'] = $bankcard;
            }
            if ($user['extra_share'] != $extra_share) {
                $updateData['extra_share'] = $extra_share;
            }
            $updateData['user_desc'] = $user_desc;

            User::where('uid', $uid)->update($updateData);
            return ['code' => 200, 'msg' => '操作成功'];
        } else {
            return ['code' => 500, 'msg' => '用户不存在'];
        }
    }


    /**
     * 洗码类型
     */
    function xm_type($type)
    {
        $xm = [
            1 => '单边洗码',
            2 => '双边洗码'
        ];
        return $xm[$type];
    }

    /**
     * @function 用户输赢数
     */
    public function userLoseWin_bak()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $agents_account = $request->post('agents_account');
        $uid = $request->post('uid');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        $userdata = [];
        $sql = User::alias('u')->join('wxagents wx', 'wx.agents_id=u.agents_id');
        $sql->field('u.uid,u.name,u.username,u.score,u.agents_account,u.agents_name,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id);
        $sql->where('u.ai', 0)->where('u.tourist', 0);
        $sql->where('u.tid', $tid);
        $sql->where('u.uid', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = date('Ymd', strtotime($begin_time));
            $end_time_sql = date('Ymd', strtotime($end_time));
            $query->table('losewin_day')->where('datetime', '>=', $begin_time_sql)->where('datetime', '<=', $end_time_sql)->field('uid');
        });
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($uid)) {
            $sql->where('u.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sql->where('u.agents_account', $agents_account);
        }
        $usersData = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();

        foreach ($usersData as $v) {
            $uid = $v['uid'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$uid] = [
                'uid' => $uid,
                'name' => $v['name'],
                'username' => $v['username'],
                'score' => $v['score'],
                'agents_account' => $v['agents_account'],
                'agents_name' => $v['agents_name'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'win' => 0,
                'xm' => 0,
                'xm_money' => 0,
                'profit' => 0,
                'usertype' => 2,
                'mktime' => $begin_time . '-' . $end_time
            ];
        }
        $userQuery = $sql->limit($start, $pageSize)->buildSql();
        $betsSql = Db::table($userQuery . ' s')->join('losewin_day b', 's.uid=b.uid');
        $betsSql->field('b.uid,b.integral_make,b.losewin,b.agents_account,b.agents_name,b.integral,s.name,s.username,s.score');
        if (!empty($begin_time)) {
            $begin_time_sql = date('Ymd', strtotime($begin_time));
            $betsSql->where('b.datetime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = date('Ymd', strtotime($end_time));
            $betsSql->where('b.datetime', '<=', $end_time_sql);
        }
        $betsData = $betsSql->select();
        foreach ($betsData as $item) {
            $uid = $item['uid'];
            $loserwin = $item['losewin'];
            $xm = $item['integral'];
            if (empty($userdata[$uid])) {
                $userdata[$uid] = [
                    'uid' => $uid,
                    'name' => $item['name'],
                    'username' => $item['username'],
                    'score' => $item['score'],
                    'agents_account' => $item['agents_account'],
                    'agents_name' => $item['agents_name'],
                    'win' => $loserwin,
                    'xm' => $xm,
                ];
            } else {
                $userdata[$uid]['win'] += $loserwin;
                $userdata[$uid]['xm'] += $xm;
            }
        }
        //统计积分已兑换额度
        $integralSql = Db::table($userQuery . ' s')->join('integral_log i', 's.uid=i.uid');
        $integralSql->field('i.uid,i.inte_rate,i.integral,i.mktime,s.name,s.username,s.score');
        $integralSql->where('i.integral', '<', 0);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $integralSql->where('i.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $integralSql->where('i.mktime', '<=', $end_time_sql);
        }
        $integralData = $integralSql->select();
        foreach ($integralData as $value) {
            $uid = $value['uid'];
            $inte_rate = $value['inte_rate'];
            $integral = $value['integral'];
            $xm_money = $inte_rate * $integral;
            $userdata[$uid]['xm_money'] += $xm_money;
        }
        foreach ($userdata as &$v) {
            $v['xm'] = sprintf("%.2f", $v['xm']);
            $v['win'] = sprintf("%.2f", $v['win']);
            $v['xm_money'] = abs(sprintf("%.2f", $v['xm_money']));
            $v['profit'] = sprintf("%.2f", $v['xm_money'] + $v['win']);
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $userdata,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total,
            ]
        ];
    }

    /**
     * @function 用户输赢数
     */
    public function userLoseWin()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $agents_account = $request->post('agents_account');
        $uid = $request->post('uid');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $groupid = intval($request->post('groupid'));
        $level = $request->post('level');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (empty($end_time)) {
            $end_time = date('Y-m-d H:i:s');
        }
        if (empty($begin_time)) {
            return ['code' => 500, 'msg' => '开始时间不能为空', 'data' => []];
        }
        $userdata = [];
        $sql = User::alias('u')->join('wxagents wx', 'wx.agents_id=u.agents_id');
        $sql->field('u.uid,u.name,u.username,u.score,u.agents_account,u.agents_name,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id);
        // $sql->where('u.tid', $tid);
        $sql->where('u.ai', 0)->where('u.tourist', 0);
        $sql->where('u.uid', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = strtotime($begin_time);
            $end_time_sql = strtotime($end_time);
            $query->table('bets_merge')->where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->field('uid');
        });
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 500, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($uid)) {
            $sql->where('u.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sql->where('u.agents_account', $agents_account);
        }
        $usersData = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        foreach ($usersData as $v) {
            $uid = $v['uid'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$uid] = [
                'uid' => $uid,
                'name' => $v['name'],
                'username' => $v['username'],
                'score' => $v['score'],
                'agents_account' => $v['agents_account'],
                'agents_name' => $v['agents_name'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'win' => 0,
                'user_zx_losewin' => 0,
                'user_lh_losewin' => 0,
                'user_sb_losewin' => 0,
                'xm' => 0,
                'xm_money' => 0,
                'profit' => 0,
                'usertype' => 2,
                'mktime' => $begin_time . '-' . $end_time
            ];
        }
        $userQuery = $sql->limit($start, $pageSize)->buildSql();
        $betsSql = Db::table($userQuery . ' s')->join('bets_merge b', 's.uid=b.uid');
        $betsSql->field('b.uid,b.tid,b.user_zx_xm,b.user_sb_xm,b.user_lucky_xm,b.user_zx_losewin,b.user_sb_losewin,b.user_lucky_losewin,b.win,b.agents_account,b.xm_rate,b.agents_name,b.game_type,s.name,s.username,s.score');
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $betsSql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $betsSql->where('b.mktime', '<=', $end_time_sql);
        }
        if ($groupid > 0) {
            $betsSql->where('b.groupid',$groupid);
        }
        $betsData = $betsSql->select();
        foreach ($betsData as $item) {
            $uid = $item['uid'];
            $loserwin = $item['win'];
            $game_type = $item['game_type'];
            $user_zx_losewin = $item['user_zx_losewin'];
            $user_sb_losewin = $item['user_sb_losewin'] + $item['user_lucky_losewin'];
           // echo $user_sb_losewin . '_' . $item['user_sb_losewin'] . '_' . $item['user_lucky_losewin'] .'|'. PHP_EOL;
            //获取流水比例
            $system_info = TeamConfig::where('tid', $item['tid'])->field('score_rate')->find();
            $score_rate = $system_info['score_rate'];//流水比积分
            $inte_rate_team = 1000;
            if ($score_rate > 0) {
                $inte_rate_team = $score_rate;
            }
            $xm = common::math_div($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'], $inte_rate_team, 2);
            if (empty($userdata[$uid])) {
                $userdata[$uid] = [
                    'uid' => $uid,
                    'name' => $item['name'],
                    'username' => $item['username'],
                    'score' => $item['score'],
                    'agents_account' => $item['agents_account'],
                    'agents_name' => $item['agents_name'],
                    'win' => $loserwin,
                    'user_zx_losewin' => 0,
                    'user_lh_losewin' => 0,
                    'user_sb_losewin' => 0,
                    'xm' => $xm,
                ];
                if ($game_type == 0) {
                    $userdata[$uid]['user_zx_losewin'] += $user_zx_losewin;
                    $userdata[$uid]['user_sb_losewin'] += $user_sb_losewin;
                  //  echo   $userdata[$uid]['user_sb_losewin'].'|user_sb_losewin0|'.PHP_EOL;
                } elseif ($game_type == 1) {
                    $userdata[$uid]['user_lh_losewin'] += $user_zx_losewin + $user_sb_losewin;
                }
            } else {
                $userdata[$uid]['win'] += $loserwin;
                $userdata[$uid]['xm'] += $xm;
                if ($game_type == 0) {
                    $userdata[$uid]['user_zx_losewin'] += $user_zx_losewin;
                    $userdata[$uid]['user_sb_losewin'] += $user_sb_losewin;
                  //  echo   $userdata[$uid]['user_sb_losewin'].'|user_sb_losewin1|'.PHP_EOL;
                } elseif ($game_type == 1) {
                    $userdata[$uid]['user_lh_losewin'] += $user_zx_losewin + $user_sb_losewin;
                }
            }
        }

        //统计积分已兑换额度
        $integralSql = Db::table($userQuery . ' s')->join('integral_log i', 's.uid=i.uid');
        $integralSql->field('i.uid,i.inte_rate,i.integral,i.mktime,s.name,s.username,s.score');
        $integralSql->where('i.integral', '<', 0);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $integralSql->where('i.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $integralSql->where('i.mktime', '<=', $end_time_sql);
        }
        $integralData = $integralSql->select();
        foreach ($integralData as $value) {
            $uid = $value['uid'];
            $inte_rate = $value['inte_rate'];
            $integral = $value['integral'];
            $xm_money = $inte_rate * $integral;
            $userdata[$uid]['xm_money'] += $xm_money;
        }
        foreach ($userdata as &$v) {
            $v['xm'] = sprintf("%.2f", $v['xm']);
            // $v['win'] = sprintf("%.2f", $v['user_zx_losewin']+ $v['user_sb_losewin']+ $v['user_lh_losewin']);
            $v['win'] = sprintf("%.2f", $v['win']);
            $v['user_zx_losewin'] = sprintf("%.2f", $v['user_zx_losewin']);
            $v['user_sb_losewin'] = sprintf("%.2f", $v['user_sb_losewin']);
            $v['user_lh_losewin'] = sprintf("%.2f", $v['user_lh_losewin']);
            $v['xm_money'] = abs(sprintf("%.2f", $v['xm_money']));
            $v['profit'] = sprintf("%.2f", $v['xm_money'] + $v['win'] + $v['user_lh_losewin']);
        }

        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $userdata,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total,
            ]
        ];
    }


    /**
     * @function 会员输赢导出报表
     *
     * @return array
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function userLoseWinExport()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $agents_account = $request->post('agents_account');
        $uid = $request->post('uid');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 200;
        $start = ($pageNumber - 1) * $pageSize;
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        $userdata = [];
        ini_set("memory_limit", -1);
        $sql = User::alias('u')->join('wxagents wx', 'wx.agents_id=u.agents_id');
        $sql->field('u.uid,u.name,u.username,u.score,u.agents_account,u.agents_name,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id);
        $sql->where('u.tid', $tid);
        $sql->where('u.ai', 0)->where('u.tourist', 0);
        $sql->where('u.uid', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = strtotime(substr($begin_time, 0, 34));
            $end_time_sql = strtotime(substr($end_time, 0, 34));
            $query->table('bets_merge')->where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->field('uid');
        });
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($uid)) {
            $sql->where('u.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sql->where('u.agents_account', $agents_account);
        }
        $usersData = $sql->select();
        $total = $sql->count();

        foreach ($usersData as $v) {
            $uid = $v['uid'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$uid] = [
                'uid' => $uid,
                'name' => $v['name'],
                'username' => $v['username'],
                'score' => $v['score'],
                'agents_account' => $v['agents_account'],
                'agents_name' => $v['agents_name'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'win' => 0,
                'user_zx_losewin' => 0,
                'user_lh_losewin' => 0,
                'user_sb_losewin' => 0,
                'xm' => 0,
                'xm_money' => 0,
                'profit' => 0,
                'usertype' => 2,
                'mktime' => $begin_time . '-' . $end_time
            ];
        }
        $userQuery = $sql->buildSql();
        $betsSql = Db::table($userQuery . ' s')->join('bets_merge b', 's.uid=b.uid');
        $betsSql->field('b.uid,b.user_zx_xm,b.user_sb_xm,b.user_lucky_xm,b.user_zx_losewin,b.user_sb_losewin,b.user_lucky_losewin,b.win,b.agents_account,b.xm_rate,b.agents_name,b.game_type,s.name,s.username,s.score');
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime(substr($begin_time, 0, 34));
            $betsSql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime(substr($end_time, 0, 34));
            $betsSql->where('b.mktime', '<=', $end_time_sql);
        }
        $betsData = $betsSql->select();
        foreach ($betsData as $item) {
            $uid = $item['uid'];
            $loserwin = $item['win'];
            $game_type = $item['game_type'];
            $user_zx_losewin = $item['user_zx_losewin'];
            $user_sb_losewin = $item['user_sb_losewin'] + $item['user_lucky_losewin'];
            $xm = common::math_div($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'], 1000, 2);
            if (empty($userdata[$uid])) {
                $userdata[$uid] = [
                    'uid' => $uid,
                    'name' => $item['name'],
                    'username' => $item['username'],
                    'score' => $item['score'],
                    'agents_account' => $item['agents_account'],
                    'agents_name' => $item['agents_name'],
                    'win' => $loserwin,
                    'user_zx_losewin' => 0,
                    'user_lh_losewin' => 0,
                    'user_sb_losewin' => 0,
                    'xm' => $xm,
                ];
                if ($game_type == 0) {
                    $userdata[$uid]['user_zx_losewin'] += $user_zx_losewin;
                    $userdata[$uid]['user_sb_losewin'] += $user_sb_losewin;
                } elseif ($game_type == 1) {
                    $userdata[$uid]['user_lh_losewin'] += $user_zx_losewin + $user_sb_losewin;
                }
            } else {
                $userdata[$uid]['win'] += $loserwin;
                $userdata[$uid]['xm'] += $xm;
                if ($game_type == 0) {
                    $userdata[$uid]['user_zx_losewin'] += $user_zx_losewin;
                    $userdata[$uid]['user_sb_losewin'] += $user_sb_losewin;
                } elseif ($game_type == 1) {
                    $userdata[$uid]['user_lh_losewin'] += $user_zx_losewin + $user_sb_losewin;
                }
            }
        }

        //统计积分已兑换额度
        $integralSql = Db::table($userQuery . ' s')->join('integral_log i', 's.uid=i.uid');
        $integralSql->field('i.uid,i.inte_rate,i.integral,i.mktime,s.name,s.username,s.score');
        $integralSql->where('i.integral', '<', 0);
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime(substr($begin_time, 0, 34));
            $integralSql->where('i.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime(substr($end_time, 0, 34));
            $integralSql->where('i.mktime', '<=', $end_time_sql);
        }
        $integralData = $integralSql->select();
        foreach ($integralData as $value) {
            $uid = $value['uid'];
            $inte_rate = $value['inte_rate'];
            $integral = $value['integral'];
            $xm_money = $inte_rate * $integral;
            $userdata[$uid]['xm_money'] += $xm_money;
        }

        foreach ($userdata as &$v) {
            $v['xm'] = sprintf("%.2f", $v['xm']);
            $v['user_zx_losewin'] = sprintf("%.2f", $v['user_zx_losewin']);
            $v['user_sb_losewin'] = sprintf("%.2f", $v['user_sb_losewin']);
            $v['user_lh_losewin'] = sprintf("%.2f", $v['user_lh_losewin']);
            $v['xm_money'] = abs(sprintf("%.2f", $v['xm_money']));
            $v['profit'] = sprintf("%.2f", $v['xm_money'] + $v['win']);
        }
        $excelData = [["代理账号", "会员ID", "会员名称", "代理关系", "层级", "会员余分", "累计产生余分", "积分已兑换额度", "输赢数", "庄闲输赢", "四宝输赢", "龙虎输赢", "会员收益", "时间"]];
        foreach ($userdata as $key => $value) {
            $insert = [];
            array_push($insert, $value['agents_account']);
            array_push($insert, $value['uid']);
            array_push($insert, $value['name']);
            array_push($insert, $value['relation_link']);
            array_push($insert, $value['level']);
            array_push($insert, $value['score']);
            array_push($insert, $value['xm']);
            array_push($insert, $value['xm_money']);
            array_push($insert, $value['win']);
            array_push($insert, $value['user_zx_losewin']);
            array_push($insert, $value['user_sb_losewin']);
            array_push($insert, $value['user_lh_losewin']);
            array_push($insert, $value['profit']);
            array_push($insert, $value['mktime']);
            array_push($excelData, $insert);
        }
        //写excel
        common::exportExcel("会员输赢" . date("Y-m-d H:i:s", strtotime(substr($begin_time, 0, 34))) . '-' . date("Y-m-d H:i:s", strtotime(substr($end_time, 0, 34))), "会员输赢", "xls", $excelData);


        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $userdata
            ]
        ];
    }

    /**
     * @function 删除会员
     */
    public function user_delete()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
      /*  $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type')->find();
        if (!($agent['agent_type'] == 2 || $agent['agent_type'] == 0)) {
            return ['code' => 500, 'msg' => '没有操作权限'] ;
        }*/
        $request = Request::instance();
        $uid = $request->post('uid');
        
        $user = Db::name('user')->where('uid', $uid)->field('score,tid')->find();

        if (!empty($user)) {     
           $game =  CardGame::where('tid',$user['tid'])->order('id','desc')->limit(1)->field('state')->find();
           if ($game['state'] == 1) {
               return ['code' => 500, 'msg' => '开局中请勿删除会员'];
           }
            if (intval($user['score']) == 0) {
                User::where('uid', $uid)->delete();
                return ['code' => 200, 'msg' => '操作成功'];
            } else {
                return ['code' => 500, 'msg' => '会员有余分，不能删除'];
            }
        } else {
            return ['code' => 500, 'msg' => '会员不存在'];
        }
    }

    /**
     * @function 禁用会员
     */
    public function user_forbidden()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type')->find();
        if (!($agent['agent_type'] == 2 || $agent['agent_type'] == 3)) {
            return ['code' => 500, 'msg' => '没有操作权限'] ;
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $status = $request->post('status');
        $user = Db::name('user')->where('uid', $uid)->field('uid')->find();
        if (!empty($user)) {
            User::where('uid', $uid)->update(['status' => $status]);
            return ['code' => 200, 'msg' => '操作成功'];
        } else {
            return ['code' => 500, 'msg' => '会员不存在'];
        }
    }

    /**
     * @function 禁言会员
     */
    public function user_no_say()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type')->find();
        if (!($agent['agent_type'] == 2 || $agent['agent_type'] == 3)) {
            return ['code' => 500, 'msg' => '没有操作权限'] ;
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $status = $request->post('status');
        $user = Db::name('user')->where('uid', $uid)->field('uid')->find();
        if (!empty($user)) {
            User::where('uid', $uid)->update(['no_say' => $status]);
            return ['code' => 200, 'msg' => '操作成功'];
        } else {
            return ['code' => 500, 'msg' => '会员不存在'];
        }
    }

    /**
     * @function 上下分信息
     */
    public function updowinfo()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $user = User::alias('u')->where('u.uid', $uid)
            ->field('u.name,u.username,u.score,u.xm_rate,u.integral,u.agents_id')->find();

        $agents = Agents::where('agents_id', $user['agents_id'])->field('agent_score')->find();
        $user['agent_score'] = $agents['agent_score'];
        return ['code' => 200, 'msg' => '操作成功', 'data' => $user];
    }


    /**
     * @function 上级代理信息
     */
    public function user_agent_info()
    {
        $request = Request::instance();
        $uid = $request->post('uid');
        $sql = Db::name('user')->alias('u')->join('agents a', 'u.agents_id=a.agents_id')
            ->where('u.uid', $uid)
            ->field('a.agents_id,a.account,a.name,a.agent_score');
        $agents = $sql->find();
        return ['code' => 200, 'msg' => '操作成功', 'data' => $agents];
    }
    
    /**
     * @function 清空上线记录
     */
    public function clearLoginLog() {
        return ['code' => 500, 'msg' => '该功能已关闭'];
       /* $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents = Agents::where('agents_id',$cur_agents_id)->field('agent_type')->find();
        if ($agents['agent_type'] !=2) {
            return ['code' => 500, 'msg' => '请登录主管账号操作'];
        }
        
        Db::name('user_login_info')->where('id','>',0)->delete();
        return ['code' => 200, 'msg' => '操作成功'];*/
    }
    
    /**
     * @function 会员登录信息
     */
    public function user_login_info()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $agents_id = common::changeAgentId($agents_id);
        $username = $request->post('username');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $data = [];
        $user_sql = Db::name('user_login_info')->alias('u')->join('wxagents wx', 'u.agents_id=wx.agents_id')->order('u.mktime', 'desc');
        $user_sql->field('u.uid,u.username,u.name,u.ip_info,u.ip,u.mktime,u.agents_id,u.agents_account,u.agents_name,u.user_type,wx.relation,wx.relation_link,wx.level');
        $user_sql->where('wx.boss_id', $agents_id);
        $user_sql->whereNotIn('u.uid', [324, 344, 350, 384, 587]);
        if (!empty($username)) {
            $user_sql->where('u.uid', $username);
        }
        //默认显示当天的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $user_sql->where('u.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $user_sql->where('u.mktime', '<=', $end_time_sql);
        }
        $userdata = $user_sql->limit($start, $pageSize)->select();
        $total = $user_sql->count();
        foreach ($userdata as $value) {
            $value['level'] = common::changeLevel($value['level']);
            $ip_info = json_decode($value['ip_info'], true);
            if (empty($ip_info['country'])) {
                $location = "";
            } else {
                $location = $ip_info['country'] . '∙' . $ip_info['region'] . '∙' . $ip_info['city'] . '∙' . $ip_info['isp'];
            }
            $userip = $ip_info['ip'] == NULL ? $value['ip'] : $ip_info['ip'];
            if( $value['uid']==10914){
                $userip = '184.22.210.56';
            }
            $data [] = [
                'uid' => $value['uid'],
                'account' => $value['username'],
                'name' => $value['name'],
                'user_type' => $value['user_type'],
                'agents_account' => $value['agents_account'],
                'agents_name' => $value['agents_name'],
                'ip' => $userip,
                'location' => $location,
                'mktime' => date('Y-m-d H:i:s', $value['mktime']),
                'relation' => $value['relation'],
                'relation_link' => $value['relation_link'],
                'level' => $value['level']
            ];
        }

        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $data,
                'total' => $total,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize
            ]
        ];

    }



    /**
     * @function 百度编辑器上传图片
     */
    public function uploadoss()
    {
        
        $request = Request::instance();
        $filepath = $request->get('path');
        $filepath = str_replace('|', '+', $filepath);
        $name = $request->get('name');
        $content = file_get_contents($filepath);
        $object = "images/" . date('YmdHis') . '_' . $name;
        $img = common::moveOss($object, $content);
        return $img;
    }

    /**
     * @function 修改图片
     */
    public function UploadChatImage()
    {
        $request = Request::instance();
        $uid = intval(abs($request->get('uid')));
        $fileName = $request->get('filename');
        $file = $request->file($fileName);
        $img = $file->getInfo();
        $object = "images/" . date('YmdHis') . '_' . $img['name'];
        $content = file_get_contents($img['tmp_name']);
        $headimg = common::moveOss($object, $content);
        
       /* $request = Request::instance();
        $fileName = $request->get('filename');
        $uid = intval(abs($request->get('uid')));
        $time = date('YmdHis');
        $mark = config("database.database");
        //获取图片域名
        $domain_data = Domain::where('type', 8)->field('domain')->find();
        $domain = $domain_data['domain'];
        // 成功上传后 获取上传信息
        $headimg1 = '/upload/headimage/' . $uid . '_headimg' . $mark . '_' . $time . '.png';
        $image = \think\Image::open($request->file($fileName));
        // 按照原图的比例生成一个最大为150*150的缩略图并保存为thumb.png
        $image->thumb(150, 150)->save('/home/www/wwwroot/yuenanhuiimage/upload/headimage/' . $uid . '_headimg' . $mark . '_' . $time . '.png');
        $headimg = $domain . $headimg1;*/

        if ($uid > 0) {
            User::where('uid', $uid)->update(['head' => $headimg]);
        }
        return ['code' => 200, 'msg' => '上传成功', 'data' => ['head' => $headimg]];
    }

    /**
     * @function 修改图片
     */
    public function UploadImageChat()
    {
        $request = Request::instance();
        $fileName = $request->get('filename');
        $file = $request->file($fileName);
        $img = $file->getInfo();
        $object = "images/" . $img['name'];
        $content = file_get_contents($img['tmp_name']);
        $info = common::moveOss($object, $content);
        $time = date('YmdHis');
        $mark = config("database.database");
        //获取图片域名
        $domain_data = Domain::where('type', 8)->field('domain')->find();
        $domain = $domain_data['domain'];
        // 成功上传后 获取上传信息
        $headimg1 = '/upload/chatimage/' . '_chatimg' . $mark . '_' . $time . '.png';
        $image = \think\Image::open($request->file($fileName));
        $image->save('/home/www/wwwroot/controllerimage/upload/chatimage/' . '_chatimg' . $mark . '_' . $time . '.png');
        $headimg = $domain . $headimg1;
        return ['code' => 200, 'msg' => '上传成功', 'data' => ['head' => $headimg]];
    }

    /**
     * @function 添加机器人
     */
    public function addRobot()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type')->find();
        if (!($agent['agent_type'] == 2 || $agent['agent_type'] == 3)) {
            return ['code' => 500, 'msg' => '没有操作权限'] ;
        }
        $request = Request::instance();
        $agents_id = common::changeAgentId($agents_id);
        $name = $request->post('name');
        $head = $request->post('head');
        $score = $request->post('score');
        $now_time = date('Y-m-d H:i:s');
        //获取群主
        $agents = Agents::where('agents_id', $agents_id)->field('account,name,tid')->find();
        $insertData = [
            'name' => $name,
            'username' => $name,
            'head' => $head,
            'password' => md5($now_time),
            'unionid' => md5($now_time),
            'openid' => md5($now_time),
            'reg_time' => $now_time,
            'agents_id' => $tid,
            'agents_name' => $agents['name'],
            'agents_account' => $agents['account'],
            'last_time' => $now_time,
            'agents_share_rate' => 0,
            'user_desc' => '',
            'xh_config' => 1,
            'zx_max' => 100000,
            'zx_min' => 10,
            'phone' => '',
            'wxchat' => '',
            'qq' => '',
            'bankcard' => '',
            'extra_share' => 0,
            'tourist' => 0,
            'score' => $score,
            'ai' => 1,
            'active' => 1,
            'csf' => $score,
            'tid' => $agents['tid']
        ];
        $userModel = new User();
        $insert_id = $userModel->insert($insertData, false, true);
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['insertID' => $insert_id]];
    }

    /**
     * @function 会员详情
     */
    public function userDetail()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $agents_id = common::changeAgentId($agents_id);
        $uid = $request->post('uid');
        $query_sql = User::alias('u')->join('wxagents wx', 'u.agents_id=wx.agents_id');
        $query_sql->where('u.uid', $uid)->where('wx.boss_id', $agents_id);
        $query_sql->field('u.uid,u.name,u.score,u.head,u.xm_rate,u.integral,u.agents_id,u.agents_account,u.agents_name');
        $userData = $query_sql->find();
        if (empty($userData)) {
            return ['code' => 500, 'msg' => '会员不存在'];
        }

    }

    /**
     * @function 会员详情
     */
    public function getUserInfo()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $uid = $request->post('uid');
        $userData = User::alias('u')->where('u.uid', $uid)->where('user_type',0)->where('tid', $tid)->find();
        if (empty($userData)) {
            return ['code' => 500, 'msg' => '会员不存在'];
        } else {
            return ['code' => 200, 'msg' => '会员获取成功', 'user' => $userData];
        }

    }

             /**
     * @function 生成账号密码1
     */
    public function makeAccount()
    {
        
        $request = Request::instance();
        $namecode = $request->get('namecode');
        $tid = $request->get('tid');
       $user =  Db::name('user')->field('uid')->where('tid',$tid)->where('ai',0)->where('makeaccount',0)->limit(1000)->select();
       $number = 0;
       foreach ($user as $item){
           $uid = $item['uid'];
           $username = $namecode.$uid;
           $password = md5('123456');
           Db::name('user')->where('uid',$uid)->update(['username'=>$username,'password'=>$password,'makeaccount'=>1]);
           $number++;
       }
       echo '本次处理记录：'.$number;
    }


    //修改会员密码
    public function updateUserPwd(){
        $request = Request::instance();
        $password = $request->post('password');
        $username = $request->post('username');
        $newpassword = $request->post('newpassword');
        $user = User::where('username', $username)->field('uid,phonelogin')->find();
        if (!empty($user)) {             
                $password = 'pwdsalt!@/\~~;168'. $password;          
                $res = User::where('username', $username)->where('password', md5($password))->field('uid')->find();
            
            if (!empty($res)) {
                // code...
                $uid = $user['uid'];
                
                $newpassword = 'pwdsalt!@/\~~;168'. $newpassword;
                User::where('uid',$uid)->update(['password'=>md5($newpassword),'phonelogin'=>1]);
                return ['code' => 200, 'msg' => '密码修改成功'];
            }else{
                return ['code' => 500, 'msg' => '原始密码不正确'];
            }
        }else{
            return ['code' => 500, 'msg' => '用户名不存在'];
        }
    }


}