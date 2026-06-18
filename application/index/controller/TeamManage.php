<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/10/5
 * Time: 10:12 PM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\AgentScoreLog;
use app\index\model\BetsMerge;
use app\index\model\CardGame;
use app\index\model\Domain;
use app\index\model\Team;
use app\index\model\LosewinDay;
use app\index\model\TeamConfig;
use app\index\model\TeamRoom;
use app\index\model\Wxagents;
use think\Db;
use app\index\model\UserScoreLog;
use think\facade\Cache;
use think\facade\Request;
use app\index\model\AgentsIntegralLog;
use app\index\model\User;

/**
 * @function 群主管理
 *
 * Class AgentsManage
 * @package app\index\controller
 */
class TeamManage
{
    /**
     * @function 添加群主
     */
    public function teamLeaderAdd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $boss_id = common::changeAgentId($boss_id);
        $request = Request::instance();
        $postData = $request->post();
        if (!preg_match('/^[a-zA-Z0-9]+$/u', $postData['account'])) {
            return ['code' => 500, 'msg' => '账号只能包含数字和字母'];
        }
        $agents = Agents::findAgentsOne(['account' => $postData['account']]);
        if (!empty($agents)) {
            return ['code' => 500, 'msg' => '代理账号已存在'];
        }
        if (!empty($postData['password1'])) {
            if ($postData['password1'] != $postData['password2']) {
                $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{8,}$/';
                if (preg_match($pattern, $postData['password1'])) {
                   
                } else {
                    return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于8位'];
                }  
                return ['code' => 500, 'msg' => '两次密码输入不一致'];
            }
        } else {
            return ['code' => 500, 'msg' => '密码不能为空'];
        }

        //获取上级信息
        $boss_info = Agents::where('agents_id', $boss_id)->field('agents_id,name,account,agent_type')->find();
        if ($boss_info['agent_type'] != 1) {
            return ['code' => 500, 'msg' => '没有操作权限'];
        }
        $time = time();
        if (empty($postData['use_end_time'])) {
            return ['code' => 500, 'msg' => '请设置可使用时间'];
        }
        $postData['use_end_time'] = date('Y-m-d H:i:s', strtotime($postData['use_end_time']));
        $insertData = [
            'account' => $postData['account'],
            'name' => $postData['name'],
            'password' => md5($postData['password1']),
            'status' => 0,
            'boss_id' => $boss_info['agents_id'],
            'boss_name' => $boss_info['name'],
            'boss_account' => $boss_info['account'],
            'agent_type' => 2,
            'mktime' => $time,
            'uptime' => $time,
            'use_end_time' => $postData['use_end_time'],
            'fee' => $postData['fee']
        ];
        $agents_id = Agents::insertAgents($insertData);
        Agents::where('agents_id', $agents_id)->update(['tid' => $agents_id]);
        if ($agents_id > 0) {
            $team_title = $agents_id . '号群';
            $insertConfig = [
                'tid' => $agents_id,
                'team_title' => $team_title,
                'notice' => '感谢使用尚水微投软件',
                'bets_way' => 2,
                'nosay' => 1,
                'has_server' => 2,
                'integral_rate' => 6,
                'score_rate' => 1000
            ];
            TeamConfig::insert($insertConfig, false, true);
        } else {
            return ['code' => 500, 'msg' => '插入数据错误'];
        }
        //注册聊天系统
        $playid = substr(md5($agents_id . $postData['account'] . $time), 0, 10);
        $sys_info = common::getChatSystemDomain();
        $url = $sys_info['domain'] . '/api/auth/agent/' . $playid . '/-1' . '?nickname=' . urlencode($postData['name']) . '&headimgurl=&account=' . urlencode($postData['account']) . '&agents_id=' . $agents_id;
        $file_contents = common::http_request($url, null, 3);
        $resultJson = json_decode($file_contents, 1);
        if ($resultJson['code'] == 200) {
            Agents::where('agents_id', $agents_id)->update(['playid' => $playid, 'tid' => $agents_id]);//
        }
//        } else {
//            Agents::where('agents_id', $agents_id)->delete();
//            return ['code' => 500, 'msg' => '注册失败，请重试'];
//        }
        $relation[] = [
            'account' => $postData['account'],
            'name' => $postData['name'],
        ];
        $relation_link = $postData['name'] . '(' . $postData['account'] . ')';
        $insertData = [
            'agents_id' => $agents_id,
            'boss_id' => $agents_id,
            'level' => 0,
            'ukey' => $agents_id . '_' . $agents_id,
            'relation' => json_encode($relation),
            'relation_link' => $relation_link,
            'tid' => $agents_id
        ];
        $relation_link = $postData['name'] . '(' . $postData['account'] . ')->';
        Db::name('wxagents')->insert($insertData, false, true);
        common::wxagents(1, $agents_id, $postData['boss_id'], $postData['account'], $postData['name'], $relation, $relation_link);
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['agents_id' => $agents_id]];
    }

    /**
     * @function 群主列表
     */
    public function teamLeaderList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $boss_id = common::changeAgentId($boss_id);
        //获取上级信息
        $boss_info = Agents::where('agents_id', $boss_id)->field('agents_id,name,account,agent_type')->find();
        if ($boss_info['agent_type'] != 1) {
            return ['code' => 500, 'msg' => '没有操作权限'];
        }
        $request = Request::instance();
        $postData = $request->post();
        $sql = Agents::field('agents_id,tid,account,name,use_end_time,fee,status,agent_type,mktime');
        $sql->where('agent_type', 2);
        if (!empty($postData['agents_id'])) {
            $sql->where('agents_id', $postData['agents_id']);
        }
        if (!empty($postData['account'])) {
            $sql->where('account', $postData['account']);
        }
        $data = $sql->select();
        foreach ($data as &$item) {
            $bjl_room_number = 0;
            $lh_room_number = 0;
            $tid = $item['tid'];
            //剩余域名
            $domain = Domain::where('tid', $tid)->where('status', 0)->count();
            $item['domain_number'] = $domain;
            //房间数
            $teamroom = TeamRoom::where('tid', $tid)->field('game_type')->select();
            foreach ($teamroom as $value) {
                if ($value['game_type'] == 0) {
                    $bjl_room_number++;
                }
                if ($value['game_type'] == 1) {
                    $lh_room_number++;
                }
            }
            $item['room_number'] = '百家乐 ' . $bjl_room_number . ' | 龙虎 ' . $lh_room_number;
            $item['mktime'] = date('Y-m-d H:i:s');
        }
        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => $data
            ]
        ];
    }

    /**
     * @function 修改群主信息
     */
    public function updateTeamLeader()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $boss_id = common::changeAgentId($boss_id);
        //获取上级信息
        $boss_info = Agents::where('agents_id', $boss_id)->field('agents_id,name,account,agent_type')->find();
        if ($boss_info['agent_type'] != 1) {
            return ['code' => 500, 'msg' => '没有操作权限'];
        }
        $request = Request::instance();
        $postData = $request->post();
        $agents_id = intval($postData['agents_id']);
        if (empty($postData['use_end_time'])) {
            return ['code' => 500, 'msg' => '请设置可使用时间'];
        }
        $postData['use_end_time'] = date('Y-m-d H:i:s', strtotime($postData['use_end_time']));
        $teamer = Agents::field('name,use_end_time,fee,status')->where('agents_id', $agents_id)->find();
        if (count($teamer) == 0) {
            return ['code' => 500, 'msg' => '用户不存在'];
        }
        $updateData = [
            'name' => $postData['name'],
            'use_end_time' => $postData['use_end_time'],
            'fee' => $postData['fee'],
            'status' => $postData['status']
        ];
        if (!empty($postData['password'])) {
            $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{8,}$/';
            if (preg_match($pattern, $postData['password'])) {
                $updateData['password'] = md5($postData['password']);
            } else {
                return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于8位'];
            }  
          
        }
        Agents::where('agents_id', $agents_id)->update($updateData);
        return ['code' => 200, 'msg' => '修改成功'];
    }

    /**
     * @function 获取群配置
     */
    public function getTeamConfig()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
       // $request = Request::instance();
      //  $postData = $request->post();
      //  $tid = intval($postData['tid']);
        $return_data = [
            'tid' => $tid,
            'team_title' => '',
            'notice' => '',
            'fast_msg' => '',
           // 'has_server' => '',
           // 'server_url' => '',
           // 'bets_way' => 2,
            'nosay' => 1,
           // 'is_limit_num' => 1,
            'integral_rate' => 0,
            'score_rate' => 1000,
            'integral_tongji_way'=>0,
            'wsData' => [
                'currentWsId'=>0,
                'wsDataArr'=>[],
            ],
            'touristfunc' => 0,
            'remote_ip' => '',
            'start_work_status'=>0,
            'start_work_time'=>''
        ];
        $data = TeamConfig::where('tid', $tid)->find();
        if (!empty($data)) {
            $domainData =  Domain::whereIn('type',[10,16])->field('id,domain,type,status,note')->select();
            if (!empty($domainData)) {
                $return_data['wsData']['wsDataArr']=$domainData;
                foreach ($domainData as $domainDataItem) {  
                    if ($domainDataItem['type'] == 10) {                
                        if ($domainDataItem['status'] == 1) {
                            $return_data['wsData']['currentWsId'] = $domainDataItem['id'];
                        }                            
                    }
                    if ($domainDataItem['type'] == 16) {
                        $return_data['start_work_status'] = $domainDataItem['status'];
                        $return_data['start_work_time'] = $domainDataItem['domain'];
                    }
                };
            }     
            //游客功能
            $agents = Agents::where('agents_id',$tid)->field('fee')->find();
            $return_data['touristfunc'] = $agents['fee'];
            $return_data['team_title'] = $data['team_title'];
            $return_data['notice'] = $data['notice'];
            $return_data['fast_msg'] = $data['fast_msg'];
            $return_data['has_server'] = $data['has_server'];
            $return_data['server_url'] = $data['server_url'];
            $return_data['bets_way'] = $data['bets_way'];
            $return_data['nosay'] = $data['nosay'];
           // $return_data['is_limit_num'] = $data['is_limit_num'];
            $return_data['integral_rate'] = $data['integral_rate'];
            $return_data['score_rate'] = $data['score_rate'];
            $return_data['integral_tongji_way'] = $data['integral_tongji_way'];
            $return_data['remote_ip'] = '27.124.44.146';
            return ['code' => 200, 'msg' => '请求成功', 'data' => $return_data];
        }else {
            return ['code' => 500, 'msg' => '未配置系统设置'];          
        }

    }

    /**
     * @function 修改群配置
     */
    public function updateTeamConfig()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $tid = $loginData['data']['tid'];
            $boss_id = $loginData['data']['agents_id'];
        }
        $request = Request::instance();
        $postData = $request->post();
        //$tid = intval($postData['tid']);
        $data = TeamConfig::where('tid', $tid)->find();
        $update_data['team_title'] = $postData['team_title'];
        $update_data['notice'] = $postData['notice'];
        $update_data['fast_msg'] = $postData['fast_msg'];
      //  $update_data['server_url'] = $postData['server_url'];
       // $update_data['has_server'] = $postData['has_server'];
      //  $update_data['bets_way'] = $postData['bets_way'];
        $update_data['nosay'] = $postData['nosay'];
       // $update_data['is_limit_num'] = $postData['is_limit_num'];
        $update_data['integral_rate'] = $postData['integral_rate'];
        $update_data['score_rate'] = $postData['score_rate'];
        $update_data['integral_tongji_way'] = $postData['integral_tongji_way'];
        if (!empty(intval($postData['wsDataId']))) {
           $domainData = Domain::where('id',$postData['wsDataId'])->where('status',0)->field('domain,status,type')->find();
           if (!empty($domainData)) {
               Domain::where('type',11)->update(['domain'=>trim($domainData['domain'])]);
              // Domain::where('type',12)->update(['domain'=>trim($domainData['domain'])]);
               $domain_update_sql = "UPDATE domain
                                    SET status = CASE
                                                  WHEN id = ".$postData['wsDataId']." THEN STATUS +1
                                                  WHEN STATUS = 1 THEN STATUS -1
                                                  ELSE STATUS
                                                END
                                    WHERE TYPE = 10";
               
               Db::execute($domain_update_sql);
           }
        }
        //游客功能
        $postData['touristfunc']  = intval($postData['touristfunc']);
        Agents::where('tid',$tid)->where('agents_id',$tid)->update(['fee'=>$postData['touristfunc']]);       
        if ($postData['touristfunc'] == 1) { //游客功能关闭
            User::where('tourist',1)->where('user_type',1)->delete();          
        }
        if (!empty($data)) {
            TeamConfig::where('tid', $tid)->update($update_data);
            return ['code' => 200, 'msg' => '修改成功', 'data' => $update_data];
        } else {
            $update_data['tid'] = $tid;
            TeamConfig::insert($update_data, false, true);
            return ['code' => 200, 'msg' => '请求成功'];
        }
    }

    /**
     * @function 添加域名
     */

    public function addTeamDomain()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $postData = $request->post();
        $tid = intval($postData['tid']);
        $domain_id = $postData['domain_id'];
        Domain::whereIn('id', $domain_id)->update(['tid' => $tid]);
        return ['code' => 200, 'msg' => '添加成功'];

    }

    /**
     * @function 域名列表
     */
    public function domainList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $data = Domain::where('tid', 0)->where('type', 24)->field('id,domain')->limit(20)->select();
        return ['code' => 200, 'msg' => '请求成功', 'data' => $data];
    }

    /**
     * @function 添加代理房间
     */
    public function addTeamRoom()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
        }
        $request = Request::instance();
        $postData = $request->post();
        $tid = intval($postData['tid']);
        $game_type = intval($postData['game_type']);
        $room_number = intval($postData['room_number']);
        $cur_date = date('Y-m-d H:i:s');
        $cur_time = time();
        for ($i = $room_number; $i > 0; $i--) {
            $roundstr = md5(time());
            $insertData = [
                'name' => '房间管理员',
                'username' => '房间管理员',
                'password' => $roundstr,
                'reg_time' => $cur_date,
                'agents_id' => 1,
                'agents_name' => 1,
                'agents_account' => 1,
                'last_time' => $cur_date,
                'openid' => $roundstr,
                'unionid' => $roundstr,
                'head' => '',
                'agents_share_rate' => 0,
                'user_desc' => '',
                'xh_config' => 1,
                'zx_max' => 10000,
                'zx_min' => 10,
                'phone' => '',
                'wxchat' => '',
                'qq' => '',
                'bankcard' => '',
                'extra_share' => 0,
                'tourist' => 0,
                'score' => 0,
                'active' => 1,
                'tid' => $tid,
                'user_type' => 1
            ];
            $userModel = new User();
            $insert_id = $userModel->insert($insertData, false, true);
            $room_data = [
                'tid' => $tid,
                'game_type' => $game_type,
                'do_agents_id' => $agents_id,
                'admin_uid' => $insert_id,
                'video_link' => '请输入视频地址'
            ];
            $groupid = TeamRoom::insert($room_data, false, true);
            if ($groupid > 0) {
                //初始化房间牌局数据
                CardGame::create(
                    [
                        'room_id' => $groupid,
                        'boots_number' => 1,
                        'ju' => 1,
                        'uptime' => $cur_time,
                        'mktime' => $cur_time,
                        'zhuang' => 1,
                        'start' => 1,
                        'state' => 2,
                        'ukey' => md5($groupid . '_' . $cur_time),
                        'groupid' => $groupid,
                        'tid' => $tid,
                        'tongji_state' => 1,
                        'bets_merge_state' => 1
                    ]
                );
                TeamRoom::where('id', $groupid)->update(
                    [
                        'groupid' => $groupid,
                        'groupname' => $groupid . '号桌',
                        'mark' => $groupid,
                        'room_sort' => $groupid
                    ]);
            }
        }
        return ['code' => 200, 'msg' => '操作成功'];
    }
    
    /**
     * @function 设置开工时间
     */
    
    public function setStartWorkTime() {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
        }
    
        Domain::where('type',16)->update(['domain'=>date('Y-m-d H:i:s',time()),'status'=>0]); 
        return ['code' => 200, 'msg' => '操作成功','start_work_time'=>date('Y-m-d H:i:s',time()),'start_work_status'=>0];
   }


}