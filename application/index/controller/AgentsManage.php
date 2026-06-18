<?php
/**
 * Created by PhpStorm.
 * User: Agentsistrator
 * Date: 2018/12/2
 * Time: 15:12
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\AgentScoreLog;
use app\index\model\BetsMerge;
use app\index\model\Domain;
use app\index\model\LosewinDay;
use app\index\model\TeamConfig;
use app\index\model\Wxagents;
use think\Db;
use app\index\model\UserScoreLog;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Request;
use app\index\model\AgentsIntegralLog;
use app\index\model\User;
use app\index\model\Gonggao;
use Monolog\Handler\NullHandler;
use app\index\model\TeamRoom;


class AgentsManage
{
    public function __construct()
    {
        common::checkLogin();
    }

    /**
     * @function 添加代理
     */
    public function doAgentsAdd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $postData = $request->post();
        if (!preg_match('/^[a-zA-Z0-9]+$/u', $postData['account'])) {
            return ['code' => 500, 'msg' => '账号只能包含数字和字母'];
        }
        
      /*  $pattern = '/^(?=.*[0-9])(?=.*[a-zA-Z])(.{6,})$/';
        if (!preg_match($pattern, $postData['account'])) {
            return ['code' => 500, 'msg' => '账号必须同时含有字母，数字，且不小于6位'];
        }   */
        
        $agents = Agents::findAgentsOne(['account' => $postData['account']]);
        if (!empty($agents)) {
            return ['code' => 500, 'msg' => '代理账号已存在'];
        }
        
        if (!empty($postData['password1'])) {
            
            $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{8,}$/';
            if (!preg_match($pattern, $postData['password1'])) {
                return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于8位'];
            } 
            if ($postData['password1'] != $postData['password2']) {
                return ['code' => 500, 'msg' => '两次密码输入不一致'];
            }
        } else {
            return ['code' => 500, 'msg' => '密码不能为空'];
        }
        //获取上级信息
        $boss_info_old = Agents::where('account', $postData['boss_account'])->field('agents_id,xm_rate,name,account')->find();
        if (empty($boss_info_old)) {
            return ['code' => 500, 'msg' => '上级代理不存在'];;
        }
        $postData['boss_id'] = common::changeAgentId($boss_info_old['agents_id']);
        $agent_inte_rate = config('database.inte_rate');
        //获取上级信息
        $boss_info = Agents::where('agents_id', $postData['boss_id'])->field('agents_id,xm_rate,name,account')->find();
        if (intval($postData['xm_rate']) > intval($boss_info['xm_rate']) || intval($postData['xm_rate']) > $agent_inte_rate) {
            return ['code' => 500, 'msg' => '积分比例不能大于上级的积分比例'];
        }

        $phone = !empty($postData['phone']) ? $postData['phone'] : '';
        $xh_config = [];
        $time = time();
        $insertData = [
            'account' => $postData['account'],
            'name' => $postData['name'],
            'xm_type' => $postData['xm_type'],
            'password' => md5($postData['password1']),
            'status' => 0,
            'boss_id' => $boss_info['agents_id'],
            'boss_name' => $boss_info['name'],
            'boss_account' => $boss_info['account'],
            'phone' => $phone,
            'agent_type' => 0,
            'mktime' => $time,
            'uptime' => $time,
            'xm_rate' => $postData['xm_rate'],
            'share_rate' => $postData['share_rate'],
            'xh_config' => json_encode($xh_config),
            'sb_xm_rate' => $postData['xm_rate'],
            'sb_share_rate' => $postData['share_rate'],
            'wxchat' => $postData['wxchat'],
            'qq' => $postData['qq'],
            'bankcard' => $postData['bankcard'],
            'service_code' => $postData['service_code'],
            'tid' => $tid,
            'agents_desc' => !empty($postData['agents_desc']) ? $postData['agents_desc'] : '',
        ];
        $agents_id = Agents::insertAgents($insertData);

        //注册聊天系统
       /* $playid = substr(md5($agents_id . $postData['account'] . $time), 0, 10);
        $sys_info = common::getChatSystemDomain();
        $url = $sys_info['domain'] . '/api/auth/agent/' . $playid . '/-1' . '?nickname=' . urlencode($postData['name']) . '&headimgurl=&account=' . urlencode($postData['account']) . '&agents_id=' . $agents_id;
        $file_contents = common::http_request($url, null, 3);
        $resultJson = json_decode($file_contents, 1);
        if ($resultJson['code'] == 200) {
            Agents::where('agents_id', $agents_id)->update(['playid' => $playid]);//
        }*/
        
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
            'tid' => $tid
        ];
        $relation_link = $postData['name'] . '(' . $postData['account'] . ')->';
        Db::name('wxagents')->insert($insertData, false, true);
        common::wxagents(1, $agents_id, $postData['boss_id'], $postData['account'], $postData['name'], $relation, $relation_link);
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['agents_id' => $agents_id]];
    }

    /**
     * @function 添加员工
     */
    public function doAgentsAddEmp()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $boss_info = Agents::where('agents_id', $cur_agents_id)->field('agent_type')->find();
        if ($boss_info['agent_type'] !=2) {
            return ['code' => 500, 'msg' => '没有权限'];;
        }
         
       // agent_type=2 主管账号
        $request = Request::instance();
        $postData = $request->post();
        $postData['agent_group_id'] = !empty($postData['agent_group_id'])?$postData['agent_group_id']:null;
        $agent_type = $postData['agent_type'];
        if ($agent_type == 3 && empty($postData['agent_group_id'])) {
            return ['code' => 500, 'msg' => '主持账号需要绑定桌子'];
        }
        if (!preg_match('/^[a-zA-Z0-9]+$/u', $postData['account'])) {
            return ['code' => 500, 'msg' => '账号只能包含数字和字母'];
        }
        $agents = Agents::findAgentsOne(['account' => $postData['account']]);
        if (!empty($agents)) {
            return ['code' => 500, 'msg' => '员工账号已存在'];
        }
        if (!empty($postData['password1'])) {
            if ($postData['password1'] != $postData['password2']) {
                $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{6,}$/';
                if (preg_match($pattern, $postData['password1'])) {
                   
                } else {
                    return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于6位'];
                }  
                return ['code' => 500, 'msg' => '两次密码输入不一致'];
            }
        } else {
            return ['code' => 500, 'msg' => '密码不能为空'];
        }
        
        $postData['boss_id'] = common::changeAgentId($postData['boss_id']);
        //获取上级信息
       /* $boss_info = Agents::where('agents_id', $postData['boss_id'])->field('xm_rate')->find();
        if (intval($postData['xm_rate']) > intval($boss_info['xm_rate'])) {
            return ['code' => 500, 'msg' => '积分比例不能大于上级的积分比例'];
        }*/
//        if ($postData['share_rate'] >= 90 && $postData['boss_id'] > 2) {
//            return ['code' => 500, 'msg' => '占成率不能大于等于90%'];
//        }
        //$phone = !empty($postData['phone']) ? $postData['phone'] : '';
        $agents_desc = !empty($postData['agents_desc']) ? $postData['agents_desc'] : '';
       // $xh_config = [];
//        if (!empty($postData['xh_config']) && is_array($postData['xh_config'])) {
//            foreach ($postData['xh_config'] as $v) {
//                $xh_config[] = intval($v);
//            }
//        }
        $insertData = [
            'account' => $postData['account'],
            'name' => $postData['name'],
           // 'idcard' => $postData['idcard'],
            'auth_type' => 1,
            'group_id' => $postData['agent_group_id'],
            'agent_type' => 3,
            'agents_desc' => $agents_desc,
           // 'xm_type' => $postData['xm_type'],
            'password' => md5($postData['password1']),
            'status' => 0,
            'boss_id' => $postData['boss_id'],
            'boss_name' => $postData['boss_name'],
            'boss_account' => $postData['boss_account'],
           // 'phone' => $phone,
            'mktime' => time(),
            'uptime' => time(),
           // 'xm_rate' => $postData['xm_rate'],
            //'share_rate' => $postData['share_rate'],
           // 'xh_config' => json_encode($xh_config),
            //'sb_xm_rate' => $postData['xm_rate'],
            //'sb_share_rate' => $postData['share_rate'],
            //'wxchat' => $postData['wxchat'],
            //'qq' => $postData['qq'],
           // 'bankcard' => $postData['bankcard'],
            //'service_code' => $postData['service_code'],
            'tid' => $tid
        ];
        $agents_id = Agents::insertAgents($insertData);
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
            'relation_link' => $relation_link
        ];
        $relation_link = $postData['name'] . '(' . $postData['account'] . ')->';
        Db::name('wxagents')->insert($insertData, false, true);
        common::wxagents(1, $agents_id, $postData['boss_id'], $postData['account'], $postData['name'], $relation, $relation_link);
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['agents_id' => $agents_id]];
    }

    /**
     * @function 停用
     */
    public function doAgentsStop()
    {
        $request = Request::instance();
        $status = intval($request->post('status'));
        $agents_id = $request->post('agents_id');
        Agents::where('agents_id', $agents_id)->update(['status' => $status]);
        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function  删除
     */
    public function doAgentsDelete()
    {
        return ['code' => 500, 'msg' => '代理账号禁止删除'];
        $request = Request::instance();
        $agents_id = $request->post('agents_id');
        Agents::where('agents_id', $agents_id)->delete();
        return ['code' => 200, 'msg' => '操作成功'];
    }


    /**
     * @function 修改代理密码
     */
    public function doAgentsPasswordUpdate()
    {
        $request = Request::instance();
        $agents_id = intval(abs($request->post('agents_id')));
        $oldpassword = $request->post('oldpassword');
        $newpassword1 = $request->post('newpassword1');
        $newpassword2 = $request->post('newpassword1');
        
        $agents = Agents::selectAgents(['agents_id' => $agents_id, 'password' => md5($oldpassword)]);
        if (!empty($agents) && !empty($agents[0])) {
            if (!empty($newpassword2) && !empty($newpassword1) && $newpassword1 == $newpassword2) {
             
                $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{8,}$/';
                if (preg_match($pattern, $newpassword1)) {
                    Agents::where('agents_id', $agents_id)->update(['password' => md5($newpassword1)]);
                    return ['code' => 200, 'msg' => '操作成功'];
                } else {
                    return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于8位'];
                }      
           
            } else {
                return ['code' => 500, 'msg' => '两次密码输入不一致'];
            }
        } else {
            return ['code' => 500, 'msg' => '代理不存在或原始密码输入有误'];
        }
    }

    /**
     * @function 代理列表
     */
    public function AgentsList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $cur_boss_id = common::changeAgentId($cur_boss_id);
        $boss_id = intval(trim($request->post('boss_id')));
        if (!empty($boss_id)) {
            $boss_id = common::changeAgentId($boss_id);
            //获取代理信息
            $search_agents = Wxagents::where('agents_id', $boss_id)->where('boss_id', $cur_boss_id)->field('boss_id')->find();
            if (!empty($search_agents)) {
                $cur_boss_id = $boss_id;
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $account = trim($request->post('account'));
        $name = trim($request->post('name'));
        $level = trim($request->post('level'));
        $dep = trim($request->post('dep'));
        $active = trim($request->post('active'));
        $search_type = trim($request->post('search_type')); //1 模糊查询 2精准查询
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $sql = Db::name('agents')->alias('a')->join('wxagents wx', 'a.agents_id=wx.agents_id')
            ->field('a.agents_desc,a.agents_id,a.account,a.name,a.agent_score,a.playid,a.agent_profit,a.status,a.xm_rate,a.share_rate,a.xh_config,a.xm_type,a.sb_xm_rate,a.sb_share_rate,a.mktime,a.phone,a.wxchat,a.qq,a.bankcard,a.service_code,a.boss_name,a.boss_account,a.dep,a.active,wx.relation,wx.relation_link,wx.level');
            $sql->where('wx.boss_id', $cur_boss_id)->where('wx.level','>',0)->where('a.tid', $tid)->where('a.agents_id','<>',$tid)->where('a.agent_type','<>',3);
        $sql->where('a.is_show', 0);
        $sql->order('a.agents_id', 'desc');
        if ($search_type == 2) { //精准查询
            //代理账号查询
            if (!empty($account)) {
                //获取代理信息
                $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                    ->where('a.account', $account)->where('wx.boss_id', $cur_boss_id)->field('a.agents_id')->find();
                if (!empty($search_agents)) {
                    $search_agents_id = $search_agents['agents_id'];
                    $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                        $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                    });
                } else {
                    return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
                }
            }
            //代理名称查询
            if (!empty($name)) {
                //获取代理信息
                $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                    ->where('a.name', $name)->where('wx.boss_id', $cur_boss_id)->field('a.agents_id')->find();
                if (!empty($search_agents)) {
                    $search_agents_id = $search_agents['agents_id'];
                    $sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                        $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                    });
                } else {
                    return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
                }
            }
        } elseif ($search_type == 1) {//模糊查询
            if (!empty($account)) {
                $sql->where('a.account', 'like', '%' . $account . '%');
            }
            if (!empty($name)) {
                $sql->where('a.name', 'like', '%' . $name . '%');
            }
        }
        if ($dep != 0) {
            $sql->where('a.dep', $dep);
        }
        if ($active != 0) {
            $sql->where('a.active', $active);
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        foreach ($data as &$value) {
            if ($value['level'] == 0) {
                $value['boss_name'] = '';
                $value['boss_account'] = '';
                $value['relation_link'] = '';
                $value['relation'] = '';
            }
            $value['level'] = common::changeLevel($value['level']);
            $value['mktime'] = date('Y-m-d H:i:s', $value['mktime']);
            $value['xh_config'] = json_decode($value['xh_config'], true);
        }
        // $data['pageSize'] = $pageSize;
        // $data['pageNumber'] = $pageNumber;
        // $data['total'] = $total;
        return ['code' => 200, 'msg' => '操作成功', 'data' => $data, 'pageSize' => $pageSize, 'pageNumber' => $pageNumber, 'total' => $total];
    }

    /**
     * @function 员工列表
     */
    public function AgentsEmpList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        
        $cur_agents_info = Agents::where('agents_id',$cur_boss_id)->field('auth_type,agent_type,tid')->find();
        if ($cur_agents_info['agent_type'] == 2) {
            $request = Request::instance();
            $account = $request->post('account');
            $roomsArr = [];
            $roomkey = 'room';
            $rooms =  TeamRoom::where('tid',$cur_agents_info['tid'])->field('id,mark,groupname')->select();
            foreach ($rooms as $roomitem){
                $roomsArr[$roomkey.$roomitem['id']] = ['groupname'=>$roomitem['groupname']];
            }
            $sql = Db::name('agents')->alias('a')
            ->field('a.agents_id,a.account,a.name,a.agent_score,a.agent_type,a.group_id,a.agents_desc,a.status,a.boss_name,a.boss_account,a.mktime');
            $sql->where('a.agent_type', 3)->where('a.tid', $tid)->where('a.is_show', 0)->order('a.agents_id','desc');
            if (!empty($account)) {
                $sql->where('a.account', '=', $account);
            }
            $data = $sql->select();
            foreach ($data as $key=>$item){
                $data[$key]['mktime'] = date('Y-m-d H:i:s',$item['mktime']);
                if (!empty($roomsArr[$roomkey.$item['group_id']])) {
                    $data[$key]['groupname'] = $roomsArr[$roomkey.$item['group_id']]['groupname'];
                }
            }
            return ['code' => 200, 'msg' => '操作成功', 'data' => $data];
        }else {
            return ['code' => 500, 'msg' => '没有操作权限'];
        }

    }

    /**
     * @function 代理搜索
     */
    public function AgentsSearch()
    {
        $request = Request::instance();
        $account = $request->post('account');
        $data = Agents::findAgentsOne(['account' => $account]);

        return ['code' => 200, 'msg' => '操作成功', 'data' => $data];
    }


    /**
     * @function 代理上下分
     */
    public function upDowFen()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id_self = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $doType = intval(abs($request->post('doType'))); //1上分 2下分
        $agents_self = Agents::where('agents_id', $agents_id_self)->field('name,account,agent_score,share_rate,agent_type')->find(); //获取代理余额
        $do_agents_account = $agents_self['account'];
        $agents_id = $request->post('agents_id');//需要上下分的代理id
        //获取需要上分代理
        $agents = Agents::where('agents_id', $agents_id)->field('name,account,agent_score,boss_id,share_rate')->find(); //获取代理余额
        $boss_id = $agents['boss_id'];

        if ($agents_id == $agents_id_self && $doType == 1) {//自己
            return ['code' => 500, 'msg' => '请找上级上分'];
        }
        if ($boss_id != $agents_id_self && $agents_self['agent_type'] != 3 && $doType == 1) {// 直属上级和财务账号
            return ['code' => 500, 'msg' => '请找直属上级上分'];
        }
        if ($doType == 2 && $agents_self['agent_type'] != 3) {
            return ['code' => 500, 'msg' => '无操作权限'];
        }
        if ($agents_self['agent_type'] == 3) {//财务账号用总代理分
            $agents_id_self = 1;
            $boss_id = 1;
            $agents_self = Agents::where('agents_id', $agents_id_self)->field('name,account,agent_score,share_rate,agent_type')->find(); //获取代理余额
            $do_agents_account = $agents_self['account'];
        }
        $fen = abs($request->post('fen'));
        $boss = Agents::where('agents_id', $boss_id)->field('name,account,agent_score,boss_id')->find();//直属上级
        $boss_score_after = $boss['agent_score'];
        if (!empty($boss) && abs($fen) > 0) {
            $agents['agent_score'] = intval($agents['agent_score']);
            if ($doType == 1) {
                $type = 11;
                if ($boss['agent_score'] < $fen) {
                    return ['code' => 500, 'msg' => '上级余分不足'];
                }
                $boss_score_after = $boss['agent_score'] - $fen;
            } else {
                $fen = -$fen;
                if ($agents['agent_score'] + $fen < 0) {
                    return ['code' => 500, 'msg' => '该代理余额不足'];
                }
                $type = 12;
                $boss_score_after = $boss_score_after - $fen;
            }

            $agent_score_after = $agents['agent_score'] + $fen;
            $fentype_ = $doType == 1 ? '上分' : '下分';
            $note = '代理' . $do_agents_account . '给代理' . $agents['account'] . '' . $fentype_ . '，额度从' . $agents['agent_score'] . '改为' . $agent_score_after;
            $insert_data = [
                'agents_id' => $agents_id,
                'agents_account' => $agents['account'],
                'agents_name' => $agents['name'],
                'score' => $agents['agent_score'],
                'score_change' => $fen,
                'score_after' => $agent_score_after,
                'type' => $type,
                'mktime' => time(),
                'orderid' => 0,
                'ukey' => $type . '_' . $boss_id . '_' . date('YmdHis'),
                'note' => $note,
                'do_agents_account' => $do_agents_account,
                'do_agents_name' => $agents_self['name'],
                'do_agents_id' => $agents_id_self
            ];
            $insert_id = AgentScoreLog::insert($insert_data, false, true);
            if ($insert_id) {
                Agents::where('agents_id', $agents_id)->update(['agent_score' => $agent_score_after]);
                if ($doType == 1 or $agents_id_self == 1) {
                    $data_text = [
                        'account' => $agents['account'],
                        'name' => $agents['account'],
                        'score_change' => $fen,
                        'score' => $agents['agent_score'],
                        'score_after' => $agent_score_after,
                        'note' => '额度从' . $agents['agent_score'] . '改为' . $agent_score_after,
                        'do_agents_account' => $do_agents_account,
                        'mktime' => date('Y-m-d H:i:s'),
                        'user_type' => 1
                    ];
                    //上级代理余分
                    $agent_type = $type == 11 ? 12 : 11;
                    $insert_agent_data = [
                        'agents_id' => $boss_id,
                        'agents_account' => $boss['account'],
                        'agents_name' => $boss['name'],
                        'score' => $boss['agent_score'],
                        'score_change' => -$fen,
                        'score_after' => $boss_score_after,
                        'type' => $agent_type,
                        'mktime' => time(),
                        'orderid' => $insert_id,
                        'ukey' => $insert_id . '_' . $type,
                        'note' => '代理' . $do_agents_account . '给代理' . $agents['account'] . '' . $fentype_ . '，额度从' . $boss['agent_score'] . '改为' . $boss_score_after,
                        'do_agents_account' => $do_agents_account,
                        'do_agents_name' => $agents_self['name'],
                        'do_agents_id' => $agents_id_self,
                        'data_text' => json_encode($data_text),
                        'uid' => $agents_id,
                        'name' => $agents['name'],
                        'usertype' => 2
                    ];

                    AgentScoreLog::insert($insert_agent_data, false, true);
                    Agents::where('agents_id', $boss_id)->update(['agent_score' => $boss_score_after]);
                }
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
                $note = '代理' . $agents_self['account'] . '给代理' . $agents['account'] . $sxfen . ',金额由' . $agents['agent_score'] . '改为' . $agent_score_after;
                common::system_log($agents_self['account'], $agents['account'], $sxfenType, $note, $c_ip, $address);
                return ['code' => 200, 'msg' => '操作成功', 'data' => ['agent_score' => $boss_score_after]];
            } else {
                return ['code' => 500, 'msg' => '操作失败'];
            }
        } else {
            return ['code' => 500, 'msg' => '代理不存在'];
        }
    }

    /**
     * @function 给直属会员上分
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function upDowFenUser()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        //判断直属关系
        $uid = intval(abs($request->post('uid')));
        $user = User::where('uid', $uid)->field('name,username,score,agents_id,ai,tourist,agents_account,agents_name')->find(); //获取用户余额
        if ($agents_id != $user['agents_id'] && common::checkEmp($agents_id) != 3) {
            return ['code' => 500, 'msg' => '没权限操作'];
        }
        //获取代理信息
        $agents = Agents::where('agents_id', $agents_id)->field('agents_id,account,name,agent_score')->find();
        if (common::checkEmp($agents_id) == 3) {//财务使用总代理
            $boss = Agents::where('agents_id', 1)->field('agents_id,account,name,agent_score')->find();
        } else {
            $boss = $agents;
        }

        $fen = abs($request->post('fen'));
        $doType = intval(abs($request->post('doType'))); //1上分 2下分
        if ($user && abs($fen) > 0) {

            if ($doType == 1) {
                $type = 1100;
                $agentsType = 12;//代理下分
                $note = '代理' . $boss['account'] . '给会员' . $user['name'] . '上分' . $fen . ',额度由' . $user['score'] . '改为' . ($user['score'] + $fen);
                if ($boss['agent_score'] < $fen) {
                    return ['code' => 500, 'msg' => '代理余分不足'];
                }
            } else {
                $fen = -$fen;
                if ($user['score'] + $fen < 0) {
                    return ['code' => 500, 'msg' => '用户余额不足'];
                }
                $type = 1200;
                $agentsType = 11;//代理上分
                $note = '代理' . $boss['account'] . '给会员' . $user['name'] . '下分' . $fen . ',额度由' . $user['score'] . '改为' . ($user['score'] + $fen);
            }
            $user_score_after = $user['score'] + $fen;
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
            // 启动事务
            Db::startTrans();
            try {
                $insert_id = UserScoreLog::insert($insert_data, false, true);
                $sxfen = $doType == 1 ? '上分' : '下分';
                if ($insert_id) {
                    //代理余分变动
                    $agent_note = '代理' . $boss['account'] . '给会员' . $user['name'] . '' . $sxfen . '' . $fen . ',自己额度由' . $boss['agent_score'] . '改为' . ($boss['agent_score'] - abs($fen));
                    $agents_score_data = [
                        'uid' => $uid,
                        'name' => $user['name'],
                        'agents_id' => $agents_id,
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
                        'note' => $agent_note
                    ];
                    $agents_score_log_id = AgentScoreLog::insert($agents_score_data, false, true);
                    if ($agents_score_log_id > 0) {
                        if ($user['ai'] == 0 && $user['tourist'] == 0) {
                            Agents::where('agents_id', $boss['agents_id'])->update(['agent_score' => $boss_score_after]);
                        }
                        User::where('uid', $uid)->update(['score' => $user_score_after]);
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
                        common::system_log($agents['account'], $user['username'], $sxfenType, $note, $c_ip, $address);

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
     * @TODO 给各个总代理上分功能
     * @function 总代理列表
     * @return array
     * @throws \think\Exception
     */
    public function bossList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        if (common::checkAuthType($agents_id) != -1) {
            return ['code' => 500, 'msg' => '没有操作权限'];
        }

        $conf = \think\facade\Config::get('app.dbconf');
        $java9527wx = Db::connect($conf['java9527wx'])->query('select * from agents a INNER join domain d where a.agents_id=1 and d.type =11');
        $java9529wx = Db::connect($conf['java9529wx'])->query('select * from agents a INNER join domain d where a.agents_id=1 and d.type =11');
        $java9600 = Db::connect($conf['java9600'])->query('select * from agents a INNER join domain d where a.agents_id=1 and d.type =11');
        $java9601 = Db::connect($conf['java9601'])->query('select * from agents a INNER join domain d where a.agents_id=1 and d.type =11');
        $res = [];
        if (!empty($java9527wx)) {
            $java9527wx[0]['database'] = "java9527wx";
            array_push($res, $java9527wx[0]);
        }
        if (!empty($java9529wx)) {
            $java9529wx[0]['database'] = "java9529wx";
            array_push($res, $java9529wx[0]);
        }
        if (!empty($java9600)) {
            $java9600[0]['database'] = "java9600";
            array_push($res, $java9600[0]);
        }
        if (!empty($java9601)) {
            $java9601[0]['database'] = "java9601";
            array_push($res, $java9601[0]);
        }
        return ['code' => 200, 'data' => $res, 'msg' => '请求成功'];
    }

    /**
     * @function 各个总代理信息
     *
     * @return array
     * @throws \think\Exception
     */
    public function groupupdowinfo()
    {

        $request = Request::instance();
        $agents_id = $request->post('agents_id');

        $db = $request->post('db');
        $conf = \think\facade\Config::get('app.dbconf');
        $db = Db::connect($conf[$db]);
        $agents = $db->name('agents')->alias('a')->where('a.agents_id', $agents_id)
            ->field('a.share_rate,a.agent_score,a.name,a.account,a.boss_id,a.boss_name,a.boss_account')->find();
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['agents_score' => $agents]];
    }

    /**
     * @function 各个总代理上下分详情
     *
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function groupupDowFen()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id_self = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }

        if (common::checkAuthType($agents_id_self) != -1) {//必须agent_type = 5才能查看
            return ['code' => 500, 'msg' => '没有操作权限'];
        }
        $doType = intval(abs($request->post('doType'))); //1上分 2下分
        $db = $request->post('db');
        $conf = \think\facade\Config::get('app.dbconf');

        $agents_self = Agents::where('agents_id', $agents_id_self)->field('name,account,agent_score,share_rate,agent_type')->find(); //获取代理余额
        $do_agents_account = $agents_self['account'];

        $agents_id = $request->post('agents_id');//需要上分的代理id
        //获取需要上分代理
        $agents = Db::connect($conf[$db])->name('agents')->where('agents_id', $agents_id)->field('name,account,agent_score,boss_id,share_rate')->find(); //获取代理余额
        $fen = abs($request->post('fen'));

        $agents['agent_score'] = intval($agents['agent_score']);
        if ($doType == 1) {
            $money = $fen;

            if ($agents['share_rate'] < 100) {
                $fen = common::math_div($fen, common::math_sub(1, $agents['share_rate'] / 100));
            }
            $type = 11;

        }

        $agent_score_after = $agents['agent_score'] + $fen;
        $fentype_ = $doType == 1 ? '上分' : '下分';

        $note = '代理' . $do_agents_account . '给代理' . $db . $agents['account'] . '' . $fentype_ . '，额度从' . $agents['agent_score'] . '改为' . $agent_score_after;
        $insert_data = [
            'agents_id' => $agents_id,
            'agents_account' => $agents['account'],
            'agents_name' => $agents['name'],
            'score' => $agents['agent_score'],
            'score_change' => $fen,
            'score_after' => $agent_score_after,
            'type' => $type,
            'mktime' => time(),
            'orderid' => 0,
            'ukey' => $type . '_' . $db . '_' . date('YmdHis'),
            'note' => $note,
            'do_agents_account' => $do_agents_account,
            'do_agents_name' => $agents_self['name'],
            'do_agents_id' => $agents_id_self,
        ];
        $insert_id = AgentScoreLog::insert($insert_data, false, true);
        if ($insert_id) {
            $data_text = [
                'account' => $agents['account'],
                'name' => $agents['account'],
                'score_change' => $fen,
                'score' => $agents['agent_score'],
                'score_after' => $agent_score_after,
                'note' => '额度从' . $agents['agent_score'] . '改为' . $agent_score_after,
                'do_agents_account' => $do_agents_account,
                'mktime' => date('Y-m-d H:i:s'),
                'user_type' => 1
            ];
            Db::connect($conf[$db])->name('agents')->where('agents_id', $agents_id)->update(['agent_score' => $agent_score_after]);

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
            $note = '代理' . $agents_self['account'] . '给代理' . $db . '->' . $agents['account'] . $sxfen . ',金额由' . $agents['agent_score'] . '改为' . $agent_score_after;
            common::system_log($agents_self['account'], $agents['account'], $sxfenType, $note, $c_ip, $address);

            return ['code' => 200, 'msg' => '操作成功', 'data' => ['agent_score' => NULL]];
        } else {
            return ['code' => 500, 'msg' => '操作失败'];
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
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $query_sql = Db::name('UserScoreLog')->alias('u')->join('agents a', 'u.agents_id=a.agents_id')->join('user uu', 'u.uid=uu.uid')
            ->where('u.uid', $uid)->whereIn('type', [1100, 1200, 11, 12])
            ->field('u.score,u.score_change,u.score_after,u.type,u.note,u.agents_id,u.time,uu.name as name,uu.username as username,a.account as agents_account,a.name as agents_name')
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
     * @function 修改代理信息
     */
    public
    function updateAgentInfo()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $do_agents__id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $emp_type = common::checkAuthType($do_agents__id);//账号类型

        if (is_array($do_agents__id)) {
            return ['code' => 400, 'msg' => '登录失效'];
        }
        $real_agents__id = $do_agents__id;
        $real_agents_info = Agents::where('agents_id', $real_agents__id)->field('name,account,agent_score,boss_id,share_rate')->find(); //获取操作代理信息

        $do_agents__id = common::changeAgentId($do_agents__id);
        $do_agents_info = Agents::where('agents_id', $do_agents__id)->field('name,account,agent_score,boss_id,share_rate')->find(); //获取操作代理信息
        if (empty($do_agents_info)) {
            return ['code' => 500, 'msg' => '代理不存在'];
        }
        $boss_account = $request->post('boss_account');
        $agents_id = $request->post('agents_id');
        $password = $request->post('password');
        $xm_rate = intval(abs($request->post('xm_rate')));
        $phone = $request->post('phone');
        $wxchat = $request->post('wxchat');
        $qq = $request->post('qq');
        $bankcard = $request->post('bankcard');
        $agent_group_id = $request->post('agent_group_id');
        $service_code = $request->post('service_code');
        $dep = $request->post('dep');//是否属于业务部门
        $active = $request->post('active');//是否在职
        $name = $request->post('name');
        $idcard = !empty($request->post('idcard')) ? $request->post('idcard') : '';
        $agents_desc = !empty($request->post('agents_desc')) ? $request->post('agents_desc') : '';
        $agent_type = !empty($request->post('agent_type')) ? $request->post('agent_type') : 0;

        //获取上级信息
        $boss_info = $agents = Agents::where('account', $boss_account)->field('agents_id,name,account')->find();
        if (empty($boss_info)) {
            return ['code' => 500, 'msg' => '上级代理不存在'];;
        }    
      /*  if ($do_agents__id > 1) {
            if ($do_agents__id != $boss_info['agents_id']) {
                return ['code' => 500, 'msg' => '没有权限操作'];
            }
        }*/
        $agent_inte_rate = config('database.inte_rate');
        
        /*if (intval($xm_rate) > intval($boss_info['xm_rate']) || intval($xm_rate) > $agent_inte_rate) {
            return ['code' => 500, 'msg' => '积分比例不能大于上级的积分比例'];
        }*/
        if (empty($name)) {
            return ['code' => 500, 'msg' => '代理名称不能为空'];
        }
        //当前代理信息
        $agents = Agents::where('agents_id', $agents_id)->field('name,boss_id,boss_account,xm_rate,account,phone,wxchat,qq,bankcard,idcard,agents_desc,agent_type,dep,active')->find();
        if (!empty($agents)) {
            $updateData = [];
            $real_ip = empty($_SERVER['HTTP_X_REAL_IP']) ? $request->ip() : $_SERVER['HTTP_X_REAL_IP'];
            $c_ip = $real_ip;
            $address = "";
            if (!empty($password)) {
                $pattern = '/^(?![^a-zA-Z]+$)(?!\D+$)(?![a-zA-Z0-9]+$).{8,}$/';
                if (preg_match($pattern, $password)) {
                    $updateData['password'] = md5($password);
                } else {
                    return ['code' => 500, 'msg' => '密码必须同时含有字母，数字，特殊字符，且不小于8位'];
                }   
               
            }
            //修改代理上级
            if ($boss_account != $agents['boss_account']) {
                if ($boss_account == $agents['account']) {
                    return ['code' => 500, 'msg' => '代理上级不能是自己'];;
                }
                $boss_id = $boss_info['agents_id'];
                //修改代理上级信息
                Agents::where('agents_id', $agents_id)->update(
                    ['boss_id' => $boss_id, 'boss_name' => $boss_info['name'], 'boss_account' => $boss_info['account']]
                    );
                //删除代理以前的关系
                Wxagents::where('agents_id', $agents_id)->where('boss_id', '<>', $agents_id)->delete();
                $relation[] = [
                    'account' => $agents['account'],
                    'name' => $agents['name'],
                ];
                $relation_link = $agents['name'] . '(' . $agents['account'] . ')->';
                common::wxagents(1, $agents_id, $boss_id, $agents['account'], $agents['name'], $relation, $relation_link);
                $note = '代理' . $real_agents_info['account'] . '修改代理' . $agents['account'] . '的上级账号,由' .$agents['boss_account']. '改为' . $boss_account;
                common::system_log($real_agents_info['account'], $agents['account'], 15, $note, $c_ip, $address, $tid);
            }
            if (!empty($agent_group_id)) {
                $updateData['group_id'] = $agent_group_id;
            }
            if ($agents['idcard'] != $idcard) {
                $updateData['idcard'] = $idcard;
            }
            if ($agents['agents_desc'] != $agents_desc) {
                $updateData['agents_desc'] = $agents_desc;
            }
            if ($agents['phone'] != $phone) {
                $updateData['phone'] = $phone;
            }
            if ($agents['wxchat'] != $wxchat) {
                $updateData['wxchat'] = $wxchat;
            }
            if ($agents['qq'] != $qq) {
                $updateData['qq'] = $qq;
            }
            if ($agents['bankcard'] != $bankcard) {
                $updateData['bankcard'] = $bankcard;
            }

            if ($agents['dep'] != $dep && $emp_type == 1 && !empty($dep)) {
                $updateData['dep'] = $dep;
                if ($dep == 1) {
                    $dep_note = "业务部门改为普通代理";
                } else if ($dep == 2) {
                    $dep_note = "普通代理改为业务部门";
                }
                $note = '代理' . $real_agents_info['account'] . '修改代理' . $agents['account'] . '的代理身份,由' . $dep_note;
                common::system_log($real_agents_info['account'], $agents['account'], 18, $note, $c_ip, $address);
            }

            if ($agents['xm_rate'] != $xm_rate) {
                $updateData['xm_rate'] = $xm_rate;
                $note = '代理' . $real_agents_info['account'] . '修改代理' . $agents['account'] . '的积分比例,由' . $agents['xm_rate'] . '改为' . $xm_rate;
                common::system_log($real_agents_info['account'], $agents['account'], 15, $note, $c_ip, $address, $tid);
            }
            if ($agents['name'] != $name) {
                $updateData['name'] = $name;
                $note = '代理' . $real_agents_info['account'] . '修改代理' . $agents['account'] . '的名称,由' . $agents['name'] . '改为:' . $name;
                common::system_log($real_agents_info['account'], $agents['account'], 14, $note, $c_ip, $address, $tid);
            }
            Agents::where('agents_id', $agents_id)->update($updateData);
            return ['code' => 200, 'msg' => '操作成功'];
        } else {
            return ['code' => 500, 'msg' => '代理不存在'];
        }
    }

    /**
     * @function 会员额度调整记录
     */
    public function user_score_log()
    {
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $userId = $request->post('uid');
        $agents_account = $request->post('agents_account');
        $card_game_id = $request->post('card_game_id');
        $dataType = intval(abs($request->post('dataType')));
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $sql = UserScoreLog::alias('m')->join('user u', 'u.uid=m.uid')->join('wxagents wx', 'u.agents_id=wx.agents_id')
            ->where('wx.boss_id', $agents_id)->where('m.user_ai', 0)->where('m.tourist', 0)->whereIn('m.type', [11, 12, 100, 122])->where('m.card_game_id', 0)
            ->field('u.uid,u.name,u.agents_account,u.agents_name,m.id,m.score,m.score_change,m.type,m.score_after,m.card_game_id,m.time,m.note,wx.relation,wx.relation_link,wx.level')
            ->order('m.id', 'desc');
        //默认显示一个星期的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('m.time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('m.time', '<=', $end_time_sql);
        }
        if (!empty($userId)) {
            $sql->where('m.uid', $userId);
        }
        if (intval($card_game_id) > 0) {
            $sql->where('m.card_game_id', $card_game_id);
        }
        if ($dataType > 0) {
            $sql->where('m.type', $dataType);
        }
        if (!empty($agents_account)) {
            $sql->where('u.agents_account', $agents_account);
        }

        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        $listData = [];
        foreach ($data as $item) {
            $item['level'] = common::changeLevel($item['level']);
            $listData[] = [
                'account' => $item['uid'],
                'name' => $item['name'],
                'agents_account' => $item['agents_account'],
                'agents_name' => $item['agents_name'],
                'score_change' => $item['score_change'],
                'score' => $item['score'],
                'score_after' => $item['score_after'],
                'note' => $item['note'],
                'do_agents_account' => '',
                'relation' => $item['relation'],
                'relation_link' => $item['relation_link'],
                'mktime' => date('Y-m-d H:i:s', $item['time']),
                'level' => $item['level']
            ];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $listData,
                'total' => $total,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize
            ]
        ];
    }


    /**
     * @function 代理额度调整列表
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function agents_score_log()
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
        $agents_account = trim($request->post('agents_account'));//搜索
        $dataType = intval(abs($request->post('dataType')));
        $userType = intval(abs($request->post('userType'))); //用户类型 1会员 2代理
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;

        $agents_id = common::changeAgentId($agents_id);
        $sql = AgentScoreLog::alias('m')->join('wxagents wx', 'm.agents_id=wx.agents_id');
        $sql->where('wx.boss_id', $agents_id);
        $sql->where('user_ai', 0)->where('tourist', 0);
        $sql->order('m.mktime', 'desc');
        $sql->field('m.agents_id,m.agents_account,m.agents_name,m.score,m.score_change,m.type,m.score_after,m.mktime,m.note,m.do_agents_account,m.do_agents_name,m.do_agents_id,wx.relation,wx.relation_link,wx.level');
        //默认显示一个星期的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('m.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('m.mktime', '<=', $end_time_sql);
        }
        if ($dataType > 0) {
            $sql->where('m.type', $dataType);
        }
        if (!empty($agents_account)) {
            $sql->where('m.agents_account', $agents_account);
        }
        if ($userType > 0) {
            $sql->where('m.usertype', $userType);
        }
        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        $listData = [];

        foreach ($data as $item) {
            $item['level'] = common::changeLevel($item['level']);
            $listData[] = [
                'agents_id' => $item['agents_id'],
                'agents_account' => $item['agents_account'],
                'agents_name' => $item['agents_name'],
                'do_agents_id' => $item['do_agents_id'],
                'do_agents_account' => $item['do_agents_account'],
                'do_agents_name' => $item['do_agents_name'],
                'score_change' => $item['score_change'],
                'score' => $item['score'],
                'score_after' => $item['score_after'],
                'note' => $item['note'],
                'relation' => $item['relation'],
                'relation_link' => $item['relation_link'],
                'mktime' => date('Y-m-d H:i:s', $item['mktime']),
                'level' => $item['level']
            ];
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $listData,
                'total' => $total,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize
            ]
        ];
    }


    /**
     * @function 上下分信息
     */
    public function updowinfo()
    {
        $request = Request::instance();
        $agents_id = $request->post('agents_id');
        $agents = Agents::alias('a')->join('agents b', 'a.boss_id=b.agents_id')->where('a.agents_id', $agents_id)
            ->field('a.share_rate,a.agent_score,a.name,a.account,a.boss_id,a.boss_name,a.boss_account,b.agent_score as boss_score')->find();
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['agents_score' => $agents]];
    }

    /**
     * @function 代理信息
     */
    public function agent_info()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => $loginData['msg']];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
      /*  $teamConfig = TeamConfig::where('tid', $tid)->find();
        if (empty($teamConfig)){
            return ['code' => 500, 'msg' => '请先进入控制台,完成系统设置'];
        }*/
        $request = Request::instance();
        $agents_id = $request->post('agents_id');
        $sql = Db::name('agents')->alias('a')->where('agents_id', $agents_id)
            ->field('a.agents_id,a.account,a.name,a.agent_score,a.group_id,a.status,a.boss_name,a.boss_account,a.xm_rate,a.mktime');
        $agents = $sql->find();
        $domain = Domain::where('type',25)->where('status',0)->field('domain')->find();
        $agents['qrdomain'] = $domain['domain'];
        return ['code' => 200, 'msg' => '操作成功', 'data' => $agents];
    }

    public function deleteGonggao()
    {
        //公告
        $request = Request::instance();
        $id = $request->post('id');
        $type = $request->post('type');

        $gonggao = Gonggao::where('id', $id)->update(['status' => $type]);

        return ['code' => 200, 'msg' => '操作成功'];
    }

    public function getgonggaoall()
    {
        //公告
        $gonggao = Gonggao::where('status != 2')->order('id', 'desc')->select();
        if (empty($gonggao)) {
            return ['code' => 200, 'msg' => '操作成功', 'data' => NULL];
        }
        return ['code' => 200, 'msg' => '操作成功', 'data' => $gonggao];
    }

    public function getgonggao()
    {
        //公告
        $gonggao = Gonggao::where('status = 0')->order('id', 'desc')->find();
        if (empty($gonggao)) {
            return ['code' => 200, 'msg' => '操作成功', 'data' => NULL];
        }
        return ['code' => 200, 'msg' => '操作成功', 'data' => $gonggao];
    }

    /**
     * @function 添加公告
     * @return array
     */
    public function setgonggao()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        }

        $request = Request::instance();
        $gonggaotime = $request->post('gonggaotime');
        if (empty($gonggaotime)) {
            $gonggaotime = NULL;
        } else {
            $gonggaotime = date('Y-m-d H:i:s', strtotime($gonggaotime));
        }
        $gonggaocon = $request->post('gonggaocon');

        //公告
        $insert_data = [
            'content' => $gonggaocon,
            'status' => 0,
            'note' => '代理id' . $loginData['data']['agents_id'] . '修改公告，时间：' . time(),
            'endtime' => $gonggaotime,
            'time' => date("Y-m-d H:i:s")
        ];
        Gonggao::insert($insert_data, false, true);

        return ['code' => 200, 'msg' => '操作成功'];
    }

    public function setgonggaoedit()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        }

        $request = Request::instance();
        $gonggaotime = $request->post('gonggaotime');
        $id = $request->post('id');

        if (empty($gonggaotime)) {
            $gonggaotime = NULL;
        } else {
            $gonggaotime = date('Y-m-d H:i:s', strtotime($gonggaotime));
        }
        $gonggaocon = $request->post('gonggaocon');

        //公告
        $update_data = [
            'content' => $gonggaocon,
            'note' => '代理id' . $loginData['data']['agents_id'] . '修改公告，时间：' . time(),
            'endtime' => $gonggaotime,
            'time' => date("Y-m-d H:i:s")
        ];
        Gonggao::where('id', $id)->update($update_data, false, true);

        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function 代理输赢数
     */
    public function agentsLoseWin()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $cur_agents_id = common::changeAgentId($cur_agents_id);
        $request = Request::instance();
        $boss_id = $request->post('boss_id');
        if (!empty($boss_id)) {
            $boss_id = common::changeAgentId($boss_id);
            //获取代理信息
            $search_agents = Wxagents::where('agents_id', $boss_id)->where('boss_id', $cur_agents_id)->field('boss_id')->find();
            if (!empty($search_agents)) {
                $cur_agents_id = $boss_id;
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $agents_account = $request->post('agents_account');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = intval($request->post('level'));
        $dep = intval($request->post('dep'));
        $active = intval($request->post('active'));
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        $userdata = [];
        //默认显示一个星期的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $cur_agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $cur_agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sql = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id');
        $sql->field('a.agents_id,a.name,a.account,a.boss_name,a.boss_account,a.boss_id,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $cur_agents_id);
        $sql->order('wx.level', 'asc');
        if ($boss_id > 0 && $level == 0 && $dep == 0 && $active == 0) {
            $sql->where('wx.level', '=', 1);
        }
        if ($boss_id == 0 && $dep == 0 && $active == 0 && $level == 0) {
            $sql->where('wx.level', 0);
        }
        if ($level > 0) {
            $sql->where('wx.level', $level);
        }
        if ($dep > 0) {
            $sql->where('a.dep', $dep)->where('wx.level', '>=', 1);
        }
        if ($active > 0) {
            $sql->where('a.active', $active)->where('wx.level', '>=', 1);;
        }
//        $sql->where('wx.agents_id', 'IN', function ($query) use ($begin_time, $end_time) {
//            $begin_time_sql = date('Ymd', strtotime($begin_time));
//            $end_time_sql = date('Ymd', strtotime($end_time));
//            $query->table('losewin_day')->where('datetime', '>=', $begin_time_sql)->where('datetime', '<=', $end_time_sql)->field('agents_id');
//        });
        // $usersData = $sql->limit($start, $pageSize)->select();
        $usersData = $sql->select();
        $total = 0;
        //  $total = $sql->count();
        foreach ($usersData as &$v) {
            $data_key = $v['agents_id'] . '_' . $v['level'];
            $agents_id = $v['agents_id'];
            if ($v['level'] == 0) {
                $v['boss_name'] = '';
                $v['boss_account'] = '';
                $v['relation_link'] = '';
                $v['relation'] = '';
            }
            $v['level'] = common::changeLevel($v['level']);
            $betsSql = LosewinDay::alias('b')->join('wxagents wx', 'b.agents_id=wx.agents_id');
            $betsSql->field('b.uid,b.agents_id,b.integral_make,b.integral_exchange,b.losewin,b.agents_account,b.agents_name,b.integral');
            $betsSql->where('wx.boss_id', $agents_id);
            if (!empty($begin_time)) {
                $begin_time_sql = date('Ymd', strtotime($begin_time));
                $betsSql->where('b.datetime', '>=', $begin_time_sql);
            }
            if (!empty($end_time)) {
                $end_time_sql = date('Ymd', strtotime($end_time));
                $betsSql->where('b.datetime', '<=', $end_time_sql);
            }
            $betsData = $betsSql->select();
            if (!empty($betsData)) {
                $userdata[$data_key] = [
                    'uid' => $agents_id,
                    'agents_id' => $agents_id,
                    'agents_name' => $v['name'],
                    'agents_account' => $v['account'],
                    'boss_account' => $v['boss_account'],
                    'boss_name' => $v['boss_name'],
                    'boss_id' => $v['boss_id'],
                    'relation' => $v['relation'],
                    'relation_link' => $v['relation_link'],
                    'level' => $v['level'],
                    'win' => 0,
                    'xm' => 0,
                    'xm_money' => 0,
                    'profit' => 0,
                    'usertype' => 1,
                    'mktime' => $begin_time . '-' . $end_time,
                    'lower_total' => 0
                ];
                $lower_total = $betsSql->where('wx.level', '>', 0)->group('b.agents_id')->count();
                foreach ($betsData as $item) {
                    $loserwin = $item['losewin'];
                    $xm = $item['integral'];
                    $xm_money = $item['integral_exchange'];
                    $userdata[$data_key]['win'] += $loserwin;
                    $userdata[$data_key]['xm'] += $xm;
                    $userdata[$data_key]['xm_money'] += $xm_money;
                }
                $userdata[$data_key]['xm_money'] = sprintf("%.2f", abs($userdata[$data_key]['xm_money']));
                $userdata[$data_key]['xm'] = sprintf("%.2f", $userdata[$data_key]['xm']);
                $userdata[$data_key]['win'] = sprintf("%.2f", $userdata[$data_key]['win']);
                $userdata[$data_key]['profit'] = sprintf("%.2f", abs($userdata[$data_key]['xm_money']) + $userdata[$data_key]['win']);
                $userdata[$data_key]['lower_total'] = $lower_total;
            }
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
     * @导出execl报表
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function agentsLoseWinExport()
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
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');

        $userdata = [];
        //默认显示一个星���的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sql = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id');
        $sql->field('a.agents_id,a.name,a.account,a.boss_name,a.boss_account,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id);
        $sql->order('wx.level', 'asc');
        $sql->where('a.agents_id', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = strtotime($begin_time);
            $end_time_sql = strtotime($end_time);
            $query->table('bets_merge')->where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->field('agents_id');
        });
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($agents_account)) {
            $sql->where('a.account', $agents_account);
        }
        $usersData = $sql->select();
        $total = $sql->count();
        foreach ($usersData as $v) {
            $data_key = $v['agents_id'] . '_' . $v['level'];
            $agents_id = $v['agents_id'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$data_key] = [
                'uid' => $agents_id,
                'agents_name' => $v['name'],
                'agents_account' => $v['account'],
                'boss_account' => $v['boss_account'],
                'boss_name' => $v['boss_name'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'win' => 0,
                'xm' => 0,
                'xm_money' => 0,
                'profit' => 0,
                'usertype' => 1,
                'mktime' => date("Y-m-d H:i:s", strtotime($begin_time)) . '-' . date("Y-m-d H:i:s", strtotime($end_time))
            ];

            $betsSql = BetsMerge::alias('b')->join('wxagents wx', 'b.agents_id=wx.agents_id');
            $betsSql->field('b.uid,b.agents_id,b.user_zx_xm,b.user_sb_xm,b.user_lucky_xm,b.win,b.agents_account,b.xm_rate,b.agents_name');
            $betsSql->where('wx.boss_id', $agents_id);
            if (!empty($begin_time)) {
                $begin_time_sql = strtotime($begin_time);
                $betsSql->where('b.mktime', '>=', $begin_time_sql);
            }
            if (!empty($end_time)) {
                $end_time_sql = strtotime($end_time);
                $betsSql->where('b.mktime', '<=', $end_time_sql);
            }
            $betsData = $betsSql->select();
            foreach ($betsData as $item) {
                $loserwin = $item['win'];
                $xm = common::math_div($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'], 1000, 2);
                $xm_money = sprintf("%.2f", $xm * $item['xm_rate']);
                $profit = $xm_money + $loserwin;
                $userdata[$data_key]['win'] += $loserwin;
                $userdata[$data_key]['xm'] += $xm;
                $userdata[$data_key]['xm_money'] += $xm_money;
                $userdata[$data_key]['profit'] += $profit;
            }
            $userdata[$data_key]['profit'] = sprintf("%.2f", $userdata[$data_key]['profit']);
            $userdata[$data_key]['xm_money'] = sprintf("%.2f", $userdata[$data_key]['xm_money']);
            $userdata[$data_key]['xm'] = sprintf("%.2f", $userdata[$data_key]['xm']);
            $userdata[$data_key]['win'] = sprintf("%.2f", $userdata[$data_key]['win']);

        }
        $excelData = [["代理名称", "代理账号", "代理关系", "层级", "会员累计产生积分", "会员积分可兑换额度", "会员输赢数", "会员收益", "时间"]];

        foreach ($userdata as $key => $value) {
            $insert = [];
            array_push($insert, $value['agents_name']);
            array_push($insert, $value['agents_account']);
            array_push($insert, $value['relation_link']);
            array_push($insert, $value['level']);
            array_push($insert, $value['xm']);
            array_push($insert, $value['xm_money']);
            array_push($insert, $value['win']);
            array_push($insert, $value['profit']);
            array_push($insert, $value['mktime']);

            array_push($excelData, $insert);
        }
        //写excel
        common::exportExcel("代理输赢" . date("Y-m-d H:i:s", strtotime($begin_time)) . '-' . date("Y-m-d H:i:s", strtotime($end_time)), "代理输赢", "xls", $excelData);
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $excelData
            ]
        ];
    }


    /**
     * @function wxagents
     */
    public function wxagents()
    {
        $agents = Agents::select();
        foreach ($agents as $value) {
            $relation = [];
            $agents_id = $value['agents_id'];
            $agents_account = $value['account'];
            $agents_name = $value['name'];
            $boss_id = $value['boss_id'];
            $boss_name = $value['boss_name'];
            $boss_account = $value['boss_account'];
            $relation[] = [
                'account' => $agents_account,
                'name' => $agents_name,
            ];
            $relation_link = $agents_name . '(' . $agents_account . ')';
            $insertData = [
                'agents_id' => $agents_id,
                'boss_id' => $agents_id,
                'level' => 0,
                'ukey' => $agents_id . '_' . $agents_id,
                'relation' => json_encode($relation),
                'relation_link' => $relation_link
            ];
            Db::name('wxagents')->insert($insertData, false, true);
            $relation_link = $agents_name . '(' . $agents_account . ')->';
            $this->wxagents1(1, $agents_id, $boss_id, $agents_account, $agents_name, $relation, $relation_link);
        }
        echo '执行完毕';
    }

    /**
     * @function 无限代
     */
    public function wxagents1($level = 1, $agents_id = 0, $boss_id = 0, $agents_account = '', $agents_name = '', $relation = [], $relation_link = '')
    {
        if ($agents_id > $boss_id) {
            $agents = Db::name('agents')->field('boss_id,account,name,agents_id')->where('agents_id', $boss_id)->find();
            $relation[] = [
                'account' => $agents['account'],
                'name' => $agents['name'],
                'agents_id' => $agents['agents_id']
            ];
            $relation_link .= $agents['name'] . '(' . $agents['account'] . ')';
            $ukey = $agents_id . '_' . $boss_id;

            Db::name('wxagents')->where('ukey', $ukey)->update([
                'relation' => json_encode($relation),
                'relation_link' => $relation_link
            ]);

            $boss_id = $agents['boss_id'];
            if ($boss_id == 0) {
                return $boss_id;
            } else {
                $relation_link .= '->';
                $level++;
                return self::wxagents1($level, $agents_id, $boss_id, $agents_account, $agents_name, $relation, $relation_link);
            }
        }
    }

    /**
     * @function 代理收益
     */
    public function AgentsProfit()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $cur_agents_id = common::changeAgentId($cur_agents_id);
        $request = Request::instance();
        $boss_id = $request->post('boss_id');
        if (!empty($boss_id)) {
            $boss_id = common::changeAgentId($boss_id);
            $cur_agents_id = $boss_id;
        }
        $agents_account = $request->post('agents_account');
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        $userdata = [];
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $cur_agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $cur_agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sql = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id');
        $sql->field('a.agents_id,a.name,a.account,a.boss_name,a.boss_account,a.boss_id,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $cur_agents_id);
        $sql->order('wx.level', 'asc');
        $sql->where('a.agents_id', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = strtotime($begin_time);
            $end_time_sql = strtotime($end_time);
            $query->table('agents_integral_log')->where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->field('agents_id');
        });
        if (!empty($agents_account)) {
            $sql->where('a.account', $agents_account);
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        $usersData = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        foreach ($usersData as $v) {
            $agents_id = $v['agents_id'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$agents_id] = [
                'agents_id' => $agents_id,
                'agents_name' => $v['name'],
                'agents_account' => $v['account'],
                'boss_account' => $v['boss_account'],
                'boss_name' => $v['boss_name'],
                'boss_id' => $v['boss_id'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'profit' => 0,
                'mktime' => $begin_time . '-' . $end_time
            ];
        }
        $userQuery = $sql->limit($start, $pageSize)->buildSql();
        $betsSql = Db::table($userQuery . 'a')->join('agents_integral_log b', 'a.agents_id=b.agents_id');
        $betsSql->field('b.*');
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $betsSql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $betsSql->where('b.mktime', '<=', $end_time_sql);
        }
        $betsData = $betsSql->select();
        foreach ($betsData as $item) {
            $agents_id = $item['agents_id'];
            $profit = $item['agents_integral'];
            if (empty($userdata[$agents_id])) {
                $userdata[$agents_id] = [
                    'profit' => $profit,
                ];
            } else {
                $userdata[$agents_id]['profit'] += $profit;
            }
            $userdata[$agents_id]['profit'] = sprintf("%.2f", $userdata[$agents_id]['profit']);
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
     * @function 代理收益报表
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function AgentsProfitExport()
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
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');

        $userdata = [];
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $agents_id = $search_agents['agents_id'];
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        $sql = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id');
        $sql->field('a.agents_id,a.name,a.account,a.boss_name,a.boss_account,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $agents_id);
        $sql->order('wx.level', 'asc');
        $sql->where('a.agents_id', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = strtotime($begin_time);
            $end_time_sql = strtotime($end_time);
            $query->table('agents_integral_log')->where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->field('agents_id');
        });
        if (!empty($agents_account)) {
            $sql->where('a.account', $agents_account);
        }
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        $usersData = $sql->select();
        $total = $sql->count();
        foreach ($usersData as $v) {
            $agents_id = $v['agents_id'];
            $v['level'] = common::changeLevel($v['level']);
            $userdata[$agents_id] = [
                'agents_id' => $agents_id,
                'agents_name' => $v['name'],
                'agents_account' => $v['account'],
                'boss_account' => $v['boss_account'],
                'boss_name' => $v['boss_name'],
                'relation' => $v['relation'],
                'relation_link' => $v['relation_link'],
                'level' => $v['level'],
                'profit' => 0,
                'mktime' => date("Y-m-d H:i:s", strtotime($begin_time)) . '-' . date("Y-m-d H:i:s", strtotime($end_time))
            ];
        }
        $userQuery = $sql->buildSql();
        $betsSql = Db::table($userQuery . 'a')->join('agents_integral_log b', 'a.agents_id=b.agents_id');
        $betsSql->field('b.*');
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $betsSql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $betsSql->where('b.mktime', '<=', $end_time_sql);
        }
        $betsData = $betsSql->select();
        foreach ($betsData as $item) {
            $agents_id = $item['agents_id'];
            $profit = $item['agents_integral'];
            if (empty($userdata[$agents_id])) {
                $userdata[$agents_id] = [
                    'profit' => $profit,
                ];
            } else {
                $userdata[$agents_id]['profit'] += $profit;
            }
            $userdata[$agents_id]['profit'] = sprintf("%.2f", $userdata[$agents_id]['profit']);
        }

        $excelData = [["代理名称", "代理账号", "代理关系", "层级", "代理收益", "时间"]];

        foreach ($userdata as $key => $value) {
            $insert = [];
            array_push($insert, $value['agents_name']);
            array_push($insert, $value['agents_account']);
            array_push($insert, $value['relation_link']);
            array_push($insert, $value['level']);
            array_push($insert, $value['profit']);
            array_push($insert, $value['mktime']);

            array_push($excelData, $insert);
        }
        //写excel
        common::exportExcel("代理收益" . date("Y-m-d H:i:s", strtotime($begin_time)) . '-' . date("Y-m-d H:i:s", strtotime($end_time)), "代理收益", "xls", $excelData);


        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'list' => $userdata
            ]
        ];
    }


    /**
     * @function 代理收益明细
     */

    public function AgentsProfitDetail()
    {
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_account = $request->post('agents_account');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $agents_id = $request->post('agents_id');
        if (empty($agents_id)) {
            return ['code' => 500, 'msg' => '代理ID不能为空'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $sql = AgentsIntegralLog::alias('a');
        $sql->where('a.agents_id', $agents_id);
        $sql->order('a.id', 'desc');
        $sql->field('a.agents_id,a.agents_account,a.agents_name,a.agents_xm_rate,a.agents_integral,a.uid,a.user_xm_rate,a.user_name,a.integral,a.agents_relation,a.relation_link,a.level,a.mktime');
        if (!empty($agents_account)) {
            $sql->where('a.agents_account', $agents_account);
        }
        if (!empty($begin_time)) {
            $sql->where('a.mktime', '>=', $begin_time);
        }
        if (!empty($end_time)) {
            $sql->where('a.mktime', '<=', $end_time);
        }
        if (!empty($uid)) {
            $sql->where('a.uid', $uid);
        }
        $data = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        foreach ($data as &$item) {
            $item['user_profit'] = $item['user_xm_rate'] * $item['integral'];
            $item['level'] = common::changeLevel($item['level']);
            $item['mktime'] = date('Y-m-d H:i:s', $item['mktime']);
        }
        return [
            'code' => 200,
            'msg' => '请求成功',
            'data' => [
                'list' => $data,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total
            ]
        ];
    }

//
//    /**
//     * @function 代理余分记录
//     */
//    public function boss_score_log()
//    {
//        $agents_id = common::checkLogin();
//        if (is_array($agents_id)) {
//            return ['code' => 400, 'msg' => '登录失效'];
//        }
//        $agents_id = common::changeAgentId($agents_id);
//        if ($agents_id != 1) {
//            return ['code' => 500, 'msg' => '没有权限查看'];
//        }
//        $request = Request::instance();
//        $begin_time = $request->post('begin_time');
//        $end_time = $request->post('end_time');
//        $dataType = intval(abs($request->post('dataType')));
//        $pageNumber = intval($request->post('pageNumber'));
//        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
//        $pageSize = intval($request->post('pageSize'));
//        $pageSize = $pageSize > 0 ? $pageSize : 30;
//        $start = ($pageNumber - 1) * $pageSize;
//
//        $sql = AgentScoreLog::order('id', 'desc');
//        $sql->field('uid,name,type,mktime,agents_id,agents_account,agents_name,do_agents_name,do_agents_id,do_agents_account,note,score,score_change,score_after');
//        if (!empty($begin_time)) {
//            $begin_time_sql = strtotime($begin_time);
//            $sql->where('mktime', '>=', $begin_time_sql);
//        }
//        if (!empty($end_time)) {
//            $end_time_sql = strtotime($end_time);
//            $sql->where('mktime', '<=', $end_time_sql);
//        }
//        if (!empty($dataType)) {
//            $sql->where('type', $dataType);
//        }
//        $data = $sql->limit($start, $pageSize)->select();
//        $total = $sql->count();
//        foreach ($data as $item) {
//
//        }
//
//
//    }


    /**
     * @function 代理直属会员列表
     */
    public function agentsUser()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $cur_agents_id = common::changeAgentId($cur_agents_id);
        $boss_id = $request->post('boss_id');
        $boss_id = common::changeAgentId($boss_id);
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $sql = User::alias('u')->join('wxagents wx', 'u.agents_id=wx.agents_id');
        $sql->field('u.head,u.uid,u.name,u.score,u.playid,u.username,u.xm_rate,u.xm_type,u.agents_share_rate,u.agents_sb_share_rate,u.status,u.agents_account,u.agents_name,u.agents_id,u.no_say,u.xh_config,u.zx_max,u.zx_min,u.phone,u.wxchat,u.qq,u.bankcard,u.extra_share,u.user_desc,u.integral,u.tourist,u.ai,wx.relation,wx.relation_link,wx.level');
        $sql->where('wx.boss_id', $cur_agents_id);
        $sql->where('u.agents_id', $boss_id);
        $sql->where('u.uid', '>=', 100)->where('u.ai', 0)->where('u.tourist', 0);
        $sql->order('u.uid', 'desc');
        $user = $sql->limit($start, $pageSize)->select();
        $total = $sql->count();
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'users' => $user,
                'total' => $total,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize
            ]
        ];
    }

    /**
     * @function 代理直属会员输赢数
     */
    public function agentUserLoseWin()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $cur_agents_id = common::changeAgentId($cur_agents_id);
        $request = Request::instance();
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $level = $request->post('level');
        $boss_id = $request->post('boss_id');
        if (!empty($boss_id)) {
            $boss_id = common::changeAgentId($boss_id);
            //获取代理信息
            $search_agents = Wxagents::where('agents_id', $boss_id)->where('boss_id', $cur_agents_id)->field('boss_id')->find();
            if (!empty($search_agents)) {
                $cur_agents_id = $boss_id;
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
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
        $sql->where('u.agents_id', $cur_agents_id);
        $sql->where('wx.boss_id', $cur_agents_id);
        $sql->where('u.ai', 0)->where('u.tourist', 0);
        $sql->where('u.uid', 'IN', function ($query) use ($begin_time, $end_time) {
            $begin_time_sql = date('Ymd', strtotime($begin_time));
            $end_time_sql = date('Ymd', strtotime($end_time));
            $query->table('losewin_day')->where('datetime', '>=', $begin_time_sql)->where('datetime', '<=', $end_time_sql)->field('uid');
        });
        if (is_numeric($level) && $level >= 0) {
            $sql->where('wx.level', $level);
        }
        if (!empty($uid)) {
            $sql->where('u.uid', $uid);
        }
        if (!empty($agents_account)) {
            $sql->where('u.agents_account', $agents_account);
        }
        $total = $sql->count();
        $userQuery = $sql->limit($start, $pageSize)->group('uid')->buildSql();
        $betsSql = Db::table($userQuery . ' s')->join('losewin_day b', 's.uid=b.uid');
        $betsSql->field('b.uid,b.integral_make,b.losewin,b.agents_account,b.agents_name,b.integral,s.name,s.username,s.score,s.relation,s.relation_link,s.level');
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
                    'relation' => $item['relation'],
                    'relation_link' => $item['relation_link'],
                    'level' => $item['level'],
                    'win' => $loserwin,
                    'xm' => $xm,
                    'xm_money' => 0,
                    'profit' => 0,
                    'usertype' => 2,
                    'mktime' => $begin_time . '-' . $end_time
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
            $userdata[$uid]['profit'] = abs($userdata[$uid]['xm_money']) + $userdata[$uid]['win'];
        }

        foreach ($userdata as &$v) {
            $v['xm'] = sprintf("%.2f", $v['xm']);
            $v['win'] = sprintf("%.2f", $v['win']);
            $v['xm_money'] = abs(sprintf("%.2f", $v['xm_money']));
            $v['profit'] = sprintf("%.2f", $v['profit']);
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
     * @function 更改代理从属关系
     */
    public function changeAgentsRelation()
    {
        $request = Request::instance();
        $boss_account = $request->post('boss_account');
        $agents_account = $request->post('agents_account');
        $agents_data = Agents::whereIn('account', [$boss_account, $agents_account])->field('account,name,xm_rate,agents_id')->select();
        if (empty($agents_data) ) {
            return ['code' => 500, 'msg' => '代理不存在'];
        }
        $boss_info = [];
        $agents_info = [];
        $agents_id = 0;
        $boss_id = 0;
        foreach ($agents_data as $item) {
            if ($item['account'] == $boss_account) {
                $boss_info = [
                    'account' => $item['account'],
                    'name' => $item['name'],
                    'xm_rate' => $item['xm_rate'],
                    'agents_id' => $item['agents_id']
                ];
                $boss_id = $item['agents_id'];
            } elseif ($item['account'] == $agents_account) {
                $agents_info = [
                    'account' => $item['account'],
                    'name' => $item['name'],
                    'xm_rate' => $item['xm_rate'],
                    'agents_id' => $item['agents_id']
                ];
                $agents_id = $item['agents_id'];
            }
        }

        //修改代理上级信息
        Agents::where('agents_id', $agents_id)->update(
            ['boss_id' => $boss_id, 'boss_name' => $boss_info['name'], 'boss_account' => $boss_info['account']]
        );
        //删除代理以前的关系
        Wxagents::where('agents_id', $agents_id)->where('boss_id', '<>', $agents_id)->delete();
        $relation[] = [
            'account' => $agents_info['account'],
            'name' => $agents_info['name'],
        ];
        $relation_link = $agents_info['name'] . '(' . $agents_info['account'] . ')->';
        common::wxagents(1, $agents_id, $boss_id, $agents_info['account'], $agents_info['name'], $relation, $relation_link);
        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function 兑换收益
     *
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function countProfitHand()
    {
        $request = Request::instance();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id_self = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = $request->post('agents_id');//需要结算收益的代理id
        $fen = abs($request->post('fen'));
        $agents_self = Agents::where('agents_id', $agents_id_self)->field('name,account,agent_profit,agent_score,xm_rate,agent_type')->find(); //获取代理余额
        if ($agents_self['agent_type'] != 3) {
            return ['code' => 500, 'msg' => '无操作权限'];
        }
        //获取需要结算收益代理
        $agents = Agents::where('agents_id', $agents_id)->field('name,account,agent_score,agent_profit,xm_rate')->find();
        $agent_profit_before = $agents['agent_profit'];
        if ($agent_profit_before < $fen) {
            return ['code' => 500, 'msg' => '代理收益不足'];
        }
        //获取总代理信息
        $boss_info = Agents::where('agents_id', 1)->field('account,name,agent_score')->find();
        $boss_score_after = $boss_info['agent_score'] - $fen;
        if ($boss_score_after < 0) {
            return ['code' => 500, 'msg' => '总代理余分不足'];
        }
        $agent_profit_after = $agents['agent_profit'] - $fen;
        $agent_score_before = $agents['agent_score'];
        $agent_score_after = $agent_score_before + $fen;
        $agents_account = $agents['account'];
        $agents_name = $agents['name'];
        $mktime = time();
        $date_time = date('YmdHi', $mktime);
        $ukey = '14_' . $agents_id . '_' . $date_time . '_cwjs';

        $exitsData = AgentScoreLog::where('ukey', $ukey)->field('id')->find();
        if (!empty($exitsData)) {
            return ['code' => 500, 'msg' => '操作频繁，请过一分钟后再试'];
        } else {
            $insert_data = [
                'agents_id' => $agents_id,
                'agents_account' => $agents_account,
                'agents_name' => $agents_name,
                'score' => $agent_score_before,
                'score_change' => $fen,
                'score_after' => $agent_score_after,
                'type' => 14,
                'mktime' => $mktime,
                'orderid' => 0,
                'ukey' => $ukey,
                'note' => '财务给代理结算收益，额度从' . $agent_score_before . '改为' . $agent_score_after,
                'do_agents_account' => $agents_self['account'],
                'do_agents_name' => $agents_self['name'],
                'do_agents_id' => $agents_id_self,
            ];
            $insert_id = AgentScoreLog::insert($insert_data, false, true);
            if ($insert_id) {
                Agents::where('agents_id', $agents_id)->update(['agent_score' => $agent_score_after, 'agent_profit' => $agent_profit_after]);
                //总代理余分变动
                $agents_score_data = [
                    'uid' => $agents_id,
                    'name' => $agents_name,
                    'agents_id' => 1,
                    'agents_name' => $boss_info['name'],
                    'agents_account' => $boss_info['account'],
                    'do_agents_account' => $agents_self['account'],
                    'do_agents_name' => $agents_self['name'],
                    'do_agents_id' => $agents_id_self,
                    'score' => $boss_info['agent_score'],
                    'score_change' => -$fen,
                    'score_after' => $boss_score_after,
                    'type' => 12,
                    'orderid' => $date_time,
                    'ukey' => $date_time . '_profit_count_cwjs',
                    'mktime' => $mktime,
                    'note' => $date_time . '-财务给代理兑换收益',
                ];
                $agents_score_log_id = AgentScoreLog::insert($agents_score_data, false, true);
                if ($agents_score_log_id > 0) {
                    Agents::where('agents_id', 1)->update(['agent_score' => $boss_score_after]);
                }
            }
            //写日志
            $sxfen = '结算收益';
            $sxfenType = 20;
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
            $note = '代理' . $agents_self['account'] . '给代理' . $agents['account'] . $sxfen . ',金额由' . $agent_profit_before . '改为' . $agent_profit_after;
            common::system_log($agents_self['account'], $agents['account'], $sxfenType, $note, $c_ip, $address);
            return [
                'code' => 200,
                'msg' => '操作成功',
                'data' => [
                    'agent_profit_after' => $agent_profit_after,
                    'agent_score_after' => $agent_score_after,
                    'boss_score_after' => $boss_score_after
                ]
            ];
        }

    }
}