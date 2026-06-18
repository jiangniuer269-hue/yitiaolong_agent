<?php

/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2020/1/14
 * Time: 12:45 AM
 */

namespace app\index\controller;

use app\index\model\TeamRoom;
use app\index\common;
use think\facade\Request;
use app\index\model\Agents;
class RoomManage
{
    /**
     * @function 房间列表
     */
    public function roomList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agent = Agents::where('agents_id', $agents_id)->field('auth_type,agents_id,agent_type,group_id')->find();
        
        if (!empty($agent)){
          //  if ($agent['agent_type'] == 2 ||$agent['agent_type'] == 3 ){
                $sql = TeamRoom::field('groupname,xstate,game_type,mark,state,groupid,desktop,video_link,counttime')->where('tid',$tid);
                    if (!empty($agent['group_id'])) {
                        $sql->where('id',$agent['group_id']);
                    }
                  
                  $teamRoom =  $sql->order('room_sort', 'asc')->select();
                 /*   foreach ($teamRoom as $key=>$temp){
                        if (strpos($temp['video_link'], 'blm') !== false) {
                            $teamRoom[$key]['video_link'] = '';
                        }
                    }*/
                return ['code' => 200, 'msg' => '请求成功', 'data' => ['rooms' => $teamRoom]];
           /* }else{
                return ['code'=>500,'msg'=>'没有权限操作1'];
            }*/
        }else{
            return ['code'=>500,'msg'=>'代理不存在'];
        }
    }
}