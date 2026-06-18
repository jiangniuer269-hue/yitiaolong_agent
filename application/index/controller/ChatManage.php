<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/12/13
 * Time: 7:47 PM
 */

namespace app\index\controller;
use think\facade\Request;
use app\index\model\Domain;
use app\index\model\Agents;
use app\index\model\TeamConfig;
use app\index\common;

class ChatManage
{

    /**
     * @function 客服
     */
    public function getchat()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $touserid = $request->post('touserid');
        
        $agents = Agents::where('agents_id', $agents_id)->find();
        if(empty($agents['playid'])){
            $playid =  substr(md5($agents_id . $agents['account'] . $agents['mktime']), 0, 10);
            Agents::where('agents_id', $agents_id)->update(['playid' => $playid]);//
        }else{
            $playid = $agents['playid'];
        }
        //获取聊天信息
        try {
            $sys_info = common::getChatSystemDomain();
            $url = $sys_info['domain'] . '/api/auth/agent/'.$playid.'/'.$touserid.'?nickname='.urlencode($agents['name']).'&headimgurl=&account='.urlencode($agents['account']).'&agents_id='.$agents['agents_id'];
            $file_contents = common::http_request($url, null, 3);
            $resultJson = json_decode($file_contents, 1);
            if ($resultJson['code'] == 406) {
                return ['code' => 406, 'playid' => $playid, 'data' => null];
            } else {
                return ['code' => 200, 'toid' => $touserid, 'imdomain' => $sys_info['domain'], 'playid' => $playid, 'data' => $resultJson['data']];
            }

        } catch (Exception $e) {
            return ['code' => 500, 'msg' => '请求错误', 'data' => null];
        }



    }


    public function getChatUrl(){
        $sys_info = Domain::where('type', 40)->field('domain')->find();
        return ['code' => 200, 'data' => $sys_info['domain']];
    }

     /**
     * @function 客服
     */
    public function getchatkf()
    {

        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }

        $agents = Agents::where('agents_id', $boss_id)->find();
        
        $im_url = TeamConfig::where('tid',$tid)->find();
        if(empty($im_url['server_url'])){
            return ['code' => 200, 'msg' => '客服地址未配置', 'data' => null];
        }
        $arr = parse_url($im_url['server_url']);
        
        $arr_query = $this->convertUrlQuery($arr['query']);

        
        if(empty($agents['playid'])){
            $playid =  substr(md5($agents_id . $agents['account'] . $agents['mktime']), 0, 10);
            Agents::where('agents_id', $agents_id)->update(['playid' => $playid]);//
        }else{
            $playid = $agents['playid'];
        }
        //获取聊天信息
        try {
            $sys_info = common::getChatSystemDomain();
            $url = $sys_info['domain'] . '/api/auth/agent/'.$playid.'/'.$arr_query['id'].'?nickname='.urlencode($agents['name']).'&headimgurl=&account='.urlencode($agents['account']).'&agents_id='.$agents['agents_id'];
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
     * @function 上线
     */
    public function getchatsx()
    {

        $agents_id = common::checkLogin();
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $boss_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }

        $agents = Agents::where('agents_id', $boss_id)->find();
        
        $im_url = TeamConfig::where('tid',$tid)->find();

        $agents_boss = Agents::where('agents_id', $agents->boss_id)->find();
        
        if(empty($agents_boss->playid)){//上线未注册聊天系统
            return ['code' => 500, 'msg' => '上线未注册聊天系统'];
        }else{
            $touserid = $agents_boss->playid;
        }

        if(empty($agents['playid'])){
            $playid =  substr(md5($agents_id . $agents['account'] . $agents['mktime']), 0, 10);
            Agents::where('agents_id', $agents_id)->update(['playid' => $playid]);//
        }else{
            $playid = $agents['playid'];
        }
        //获取聊天信息
        try {
            $sys_info = common::getChatSystemDomain();
            $url = $sys_info['domain'] . '/api/auth/agent/'.$playid.'/'.$touserid.'?nickname='.urlencode($agents['name']).'&headimgurl=&account='.urlencode($agents['account']).'&agents_id='.$agents['agents_id'];
            $file_contents = common::http_request($url, null, 3);
            $resultJson = json_decode($file_contents, 1);
            if ($resultJson['code'] == 406) {
                return ['code' => 406, 'playid' => $playid, 'data' => null];
            } else {
                return ['code' => 200,'toname' =>$agents_boss->name, 'toid' => $touserid, 'imdomain' => $sys_info['domain'], 'playid' => $playid, 'data' => $resultJson['data']];
            }

        } catch (Exception $e) {
            return ['code' => 500, 'msg' => '请求错误', 'data' => null];
        }



    }

    function getunreadmessage(){
        $agents_check = common::checkLogin();
        if ($agents_check['code'] == 400 ) {
            return ['code' => 400, 'msg' => '登录失效'];
        }
        $agents = Agents::where('agents_id', $agents_check['data']['agents_id'])->find();

        $playid = $agents->playid;
       
        $sys_info = common::getChatSystemDomain();
        try {
            $url = $sys_info['domain'] . '/api/weitou/' . $playid;
            $file_contents = common::http_request($url, null, 3);
            $resultJson = json_decode($file_contents, 1);
            return ['code' => 200, 'data' => $resultJson['data']];
        } catch (Exception $e) {
            return ['code' => 500, 'msg' => '请求错误', 'data' => null];
        }
    }

    /**
     * @function 初始化整个代理用户，运行一次
     */
    function initchat(){
        $agents = Agents::where('playid', null)->select();
        $sys_info = common::getChatSystemDomain();

        foreach ($agents as $agent) {
            $playid =  substr(md5($agent->agents_id . $agent->account . $agent->mktime), 0, 10);
            $url = $sys_info['domain'] . '/api/auth/agent/'.$playid.'/-1'.'?nickname='.urlencode($agent->name).'&headimgurl=&account='.urlencode($agent->account).'&agents_id='.$agent->agents_id;
            $file_contents = common::http_request($url, null, 3);
            $resultJson = json_decode($file_contents, 1);
            if ($resultJson['code'] == 200) {
                Agents::where('agents_id', $agent->agents_id)->update(['playid' => $playid]);//
            }
        }

        return ['code' => 200, 'data' => "批量操作成功"];

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

}