<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/2/1
 * Time: 10:21 PM
 */

namespace app\index\controller;

use app\index\model\Agents;
use app\index\model\BetsMerge;
use app\index\model\CardGameReport;
use app\index\model\DateCardGameReport;
use app\index\model\ZjhCardGameReport;
use app\index\model\ZyTongji;
use think\facade\Session;
use think\facade\Request;
use app\index\common;
use app\index\model\LhCardGameReport;

class ZyManage
{
    /**
     * @function 结算报表
     */
    public function report()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('agent_type')->find();
        if ($agent['agent_type'] == 0) {
            return ['code' => 500, 'msg' => '没有权限操作'];
        }
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $sql = DateCardGameReport::order('id', 'desc');
        $sql->field('tmyk,dcyk,zxyk,zxxm,sbyk,sbxm,luckysix_yk,luckysix_xm,lq_yk,lq_xm,fb_yk,fb_xm,superhe_yk,superhe_xm,dlt_yk,dlt_xm,khyk,date_time as mktime');
        $sql->where('game_type', $game_type);
        $sql->where('tid', $tid);
        if (!empty($begin_time)) {
            $begin_date_time = date('Ymd', strtotime($begin_time));
            $sql->where('date_time', '>=', $begin_date_time);
        }
        if (!empty($end_time)) {
            $end_date_time = date('Ymd', strtotime($end_time));
            $sql->where('date_time', '<=', $end_date_time);
        }
        $data = $sql->select();
        return ['code' => 200, 'msg' => '请求成功', 'data' => ['list' => $data]];
    }

}