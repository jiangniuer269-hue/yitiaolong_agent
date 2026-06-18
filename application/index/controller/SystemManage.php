<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/4/12
 * Time: 11:12 AM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Domain;
use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Agents;

class SystemManage
{
    /**
     * @function 修改牌局密码
     */
    public function updateGamePwd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $post_data = $request->post();
        $newpassword = $post_data['newpassword'];
        $res = Db::name('domain')->where('type', 20)->find();
        if (!empty($res)) {
            $result = Db::name('domain')->where('type', 20)->update(['domain' => md5($newpassword)]);
            if ($result) {
                return ['code' => 200, 'msg' => '修改成功'];
            } else {
                return ['code' => 500, 'msg' => '新密码与原密码一致，无需修改'];
            }
        } else {
            $result = Db::name('domain')->insert(['domain' => md5($newpassword), 'type' => 20, 'note' => '重新结算密码']);
            if ($result) {
                return ['code' => 200, 'msg' => '修改成功'];
            } else {
                return ['code' => 500, 'msg' => '修改失败'];
            }
        }
    }
    /**
     * @function 清空操作日志
     */
    public function clearSystemLog() {
        return ['code' => 500, 'msg' => '该功能暂时关闭'];
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
        
        Db::name('system_log')->where('id','>',0)->delete();
        return ['code' => 200, 'msg' => '操作成功'];*/
    }

    /**
     * @function 系统操作日志
     */
    public function systemLog()
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
        $pageNumber = intval($request->post('pageNumber'));
        $deal_account = $request->post('deal_account');
        $be_deal_account = $request->post('be_deal_account');
        $type = $request->post('type');
        
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;

        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $agents_account = $request->post('agents_account');
        $data_sql = Db::name('system_log')->alias('s')->join('wxagents wx', 's.deal_agents_id=wx.agents_id');
        if(!empty($type) && $type !=-1){
            $data_sql->where('s.type', $type);
        }
        $data_sql->where('wx.boss_id', $cur_agents_id);
        
        $data_sql->field('s.id,s.deal_account,s.deal_agents_id,s.be_deal_account,s.type,s.note,s.mktime,s.ip,s.address,wx.boss_id,wx.agents_id,wx.level,wx.ukey,wx.relation,wx.relation_link');
        if (!empty($deal_account)) {
            $data_sql->where('s.deal_account', $deal_account);
        }
        if (!empty($be_deal_account)) {
            $data_sql->where('s.be_deal_account', $be_deal_account);
        }
        $data = $data_sql->limit($start, $pageSize)->order('s.id', 'desc')->select();
        $total = $data_sql->count();
        foreach ($data as $key => $item) {
            $data[$key]['type'] = $this->log_type($item['type']);
            $data[$key]['mktime'] = date('Y-m-d H:i:s', $item['mktime']);
        }
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total,
                'data' => $data
            ]
        ];
    }

    /**
     * @function 类型
     * @param $type
     * @return mixed
     */
    function log_type($type)
    {
        $logType = [
            1 => '修改积分比例',
            2 => '修改洗码类型',
            3 => '修改占成比例',
            4 => '修改会员昵称',
            5 => '修改密码',
            6 => '上分',
            7 => '下分',
            8 => '下积分',
            9 => '获取临时登录地址',
            10 => '设为游客',
            11 => '设为会员',
            12 => '结算码粮',
            13 => '操作输赢归零',
            14 => '修改代理名称',
            15 => '修改代理上级',
            16 => '重置会员密码',
            17 => '修改会员直属代理',
            18 => '修改代理身份',
            19 => '修改代理在职状态',
            20 => '财务结算代理收益',
            21 => '牌局修改路单',
            22 => '牌局重新结算',
        ];
        return $logType[$type];
    }

    /**
     * @function 域名列表
     */
    public function domain()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $domain = Domain::whereIn('type', [28])->where('status', 0)->where('tid',$tid)
            ->field('id,domain,note')->order('id', 'asc')->select();
        $data = [];
        foreach ($domain as $item){
            $data[]=[
                'id'=>$item['id'],
                'domain'=>$item['domain'].'('.$item['note'].')',
            ];    
        }
        return ['code' => 200, 'msg' => '操作成功', 'data' => ['list' => $data]];
    }

    /**
     * @function 域名禁止
     * @return array
     */
    public function domainForbid()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $id = $request->post('id');
       $number =  Domain::where('type',28)->where('status',0)->count();
       if ($number <= 1 ) {
           return ['code' => 500, 'msg' => '域名不足，请联系技术补充'];
       }
        Domain::where('id', $id)->update(['status' => 1]);
        return ['code' => 200, 'msg' => '操作成功'];
    }


}