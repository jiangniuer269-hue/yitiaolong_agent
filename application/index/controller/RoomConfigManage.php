<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\14 0014
 * Time: 23:01
 */

namespace app\index\controller;

use app\index\model\Domain;
use app\index\model\TeamRoom;
use app\index\model\TeamConfig;
use app\index\model\User;
use app\index\model\Admin;

use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\common;

include_once 'mysql.php';

class RoomConfigManage
{
    const  HTTPURL = 'http://154.23.221.76:9212';

    public function __construct()
    {
        common::checkLogin();
    }


    /**
     * @function
     */
    public function getfasttext()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $fast_text = TeamConfig::where('tid', $tid)->field('fast_msg')->find();
        $fast_text_arr = explode('+',$fast_text['fast_msg']);
        return ['code' => 200, 'msg' => '请求成功', 'data' => ['fast_msg'=>$fast_text_arr]];
    }

    /**
     * @function 修改系统信息
     */
    public function updateRoomConfig()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $requestData = $request->post();
        $teamroom = TeamRoom::where('tid', $tid)->where('id', $requestData['id'])->find();
        if (empty($teamroom)) {
            return ['code' => 500, 'msg' => '参数错误'];
        }
        $updateData = [];
        //限红设置
        if ($requestData['keep'] == 1) {
            $updateData = [
                'odds_zx_min' => $requestData['odds_zx_min'],//庄闲个人当局累计最小限红
                'odds_zx_max' => $requestData['odds_zx_max'],//庄闲个人当局累计最大限红
                'odds_zd_min' => $requestData['odds_zd_min'],//庄闲个人当局累计最小限红
                'odds_zd_max' => $requestData['odds_zd_max'],//庄闲个人当局累计最大限红
                'odds_xd_min' => $requestData['odds_xd_min'],//庄闲个人当局累计最小限红
                'odds_xd_max' => $requestData['odds_xd_max'],//庄闲个人当局累计最大限红
                'odds_superhe_max' => $requestData['odds_superhe_max'],//庄闲个人当局累计最大限红
                'odds_superhe_min' => $requestData['odds_superhe_min'],//庄闲个人当局累计最大限红
                //'capital' => $requestData['capital'],//台面本金
                'mantissa' => $requestData['mantissa'],//尾数吃码
                'single' => $requestData['single'],//单边限红
                'odds_sb_all' => $requestData['odds_sb_all'],//三宝累计总额限红
                'odds_sb_min' => $requestData['odds_sb_min'],//三宝个人单次最小下注
                'odds_sb_max' => $requestData['odds_sb_max'],//三宝个人累计最大限红
                'odds_lucky_min' => $requestData['odds_lucky_min'],//幸运六个人单次最小下注
                'odds_lucky_max' => $requestData['odds_lucky_max'],//幸运六个人累计最大限红
                'odds_fanbei_min' => $requestData['odds_fanbei_min'],//牛牛翻倍
                'odds_fanbei_max' => $requestData['odds_fanbei_max'],//牛牛翻倍
            ];
        }
        //赔率设置
        if ($requestData['keep'] == 5) {
            $updateData = [
                'zhuang' => $requestData['zhuang'],
                'xian' => $requestData['xian'],
                'he' => $requestData['he'],
                'zhuang_dui' => $requestData['zhuang_dui'],
                'xian_dui' => $requestData['xian_dui'],
                'lucky_six_12' => $requestData['lucky_six_12'],
                'lucky_six_20' => $requestData['lucky_six_20'],
            ];
        }
        //机器人设置
        if ($requestData['keep'] == 2) {
            $updateData = [
                'ai_state' => $requestData['ai_state'],
                'ai_time' => $requestData['ai_time'],
                'ai_num' => $requestData['ai_num'],
                'ai_text' => $requestData['ai_text'],
                'ai_upfen' => $requestData['ai_upfen'],
            ];
        }
        //修改游戏规则
        if ($requestData['keep'] == 3) {
            $updateData['game_rule'] = $requestData['game_rule'];
        }
        //修改系统名称
        if ($requestData['keep'] == 4) {            
            $updateData['counttime'] = $requestData['counttime'];
            $updateData['video_link'] = $requestData['video_link'];
            $updateData['video_link_web'] = $requestData['video_link'];
           /* if (strpos($updateData['video_link'], 'blm') !== false) {
                $updateData['video_link'] = '';
            }*/
            $updateData['headimage'] = $requestData['headimage'];
            $updateData['groupname'] = $requestData['groupname'];
            $updateData['mark'] = $requestData['mark'];
            $updateData['has_luckysix'] = $requestData['has_luckysix'];
            $updateData['ps_name'] = $requestData['ps_name'];
            User::where('uid', $teamroom['admin_uid'])->update([
                'head' => $updateData['headimage'],
                'name' => $updateData['ps_name']
            ]);   
        }
        if (TeamRoom::field('id')->getById($requestData['id'])) {
            TeamRoom::where('id', $requestData['id'])->update($updateData);
            return ['code' => 200, 'msg' => '修改成功'];
        } else {
            return ['code' => 500, 'msg' => '修改失败'];
        }
    }


    /**
     * @function 房间配置
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function RoomConfig()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $groupid = $request->post('groupid');
        $teamRoom = TeamRoom::where('groupid', $groupid)->find();
        if (empty($teamRoom)) {
            return ['code' => 500, 'msg' => '房间不存在'];
        }
        return ['code' => 200, 'msg' => '请求成功', 'data' => ['list' => $teamRoom]];
    }
}