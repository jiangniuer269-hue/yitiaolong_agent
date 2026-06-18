<?php

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
use think\facade\Request;
use app\index\model\AgentsIntegralLog;
use app\index\model\User;
use app\index\model\Gonggao;


class ControlManage
{
    /**
     * @function 增加时间
     */
    public function addTime()
    {
        $request = Request::instance();
        $tid = $request->get('tid');
        $data = Agents::where('agents_id', $tid)->field('use_end_time')->find();
        if (empty($data)) {
            return ['code' => 500, 'msg' => 'tid不存在', 'data' => []];
        }
        $addtime = date('Y-m-d H:i:s', (strtotime($data['use_end_time']) + 86400 * 31));
        Agents::where('agents_id', $tid)->update(['use_end_time' => $addtime]);
        return ['code' => 200, 'msg' => '操作成功', 'data' => []];
    }

    /**
     * @function 更换二维码域名
     */
    public function changeQrDomain()
    {
        $data = Domain::where('type', 24)->where('status', 0)->order('id', 'desc')->field('id,domain')->find();
        if (empty($data)) {
            return ['code' => 500, 'msg' => '没有可使用的域名', 'data' => []];
        }
        Domain::where('type', 25)->update(['domain' => $data['domain']]);
        Domain::where('id', $data['id'])->update(['status' => 2]);
        return ['code' => 200, 'msg' => '操作成功', 'data' => []];
    }

    /**
     * @function 更换对外域名
     */
    public function changeDwDomain()
    {
        Domain::where('type', 24)->where('status', 0)->limit(1)->update(['status' => 1]);
        return ['code' => 200, 'msg' => '操作成功', 'data' => []];
    }

    /**
     * function 获取二维码域名
     */
    public function getQrDomain()
    {
        $qrdomain = Domain::where('type', 25)->field('id,domain,status')->find();
        return ['code' => 200, 'msg' => '请求成功', 'data' => $qrdomain];
    }

    /**
     * @function 获取对外域名
     */
    public function getDwDomain()
    {
        $dwdomain = Domain::where('type', 24)->where('status', 0)->limit(1)->field('id,domain,status')->find();
        return ['code' => 200, 'msg' => '请求成功', 'data' => $dwdomain];
    }

    /**
     * @function 检测二维码域名
     */
    public function checkQrDomain()
    {
        $url01 = 'http://139.9.154.221:';
        $url02 = 'http://47.108.239.230:';
        $qrarray = [9204, 9205, 9206, 9209, 9210, 9211, 9212,9213];
        foreach ($qrarray as $port) {
            if ($port == 9205 || $port == 9213 || $port == 9209) {
                $url = $url02;
            }else {
                $url = $url01;
            }
            $data = file_get_contents($url . $port . '/v1/control/getQrDomain');
            $domain_array = json_decode($data, true);
            $qrdomain = $domain_array['data']['domain'];
            $checkData = file_get_contents("http://wxapi2.jnoo.com/api/wxapijnoo3173/d8ab42e8f3835593967cb2ebfc618cc1?domain=" . $qrdomain);
            $check_data_array = json_decode($checkData, true);
            $status = $check_data_array['status'];
            if ($status == 2 or $status == 3) {
                file_get_contents($url . $port . '/v1/control/changeQrDomain');
                echo $port . ':域名' . $qrdomain . '要更换成功' . PHP_EOL;
            } else {
                echo $port . ':域名' . $qrdomain . '正常' . PHP_EOL;
            }
            sleep(5);
        }
    }

    /**
     * @return void @function 检测对外域名
     */
    public function checkDwDomain()
    {
        $url01 = 'http://139.9.154.221:';
        $url02 = 'http://47.108.239.230:';
        $qrarray = [9204, 9205, 9206, 9209, 9210, 9211, 9212,9213];
        foreach ($qrarray as $port) {
            if ($port == 9205 || $port == 9213 || $port == 9209) {
                $url = $url02;
            }else {
                $url = $url01;
            }
            $data = file_get_contents($url . $port . '/v1/control/getDwDomain');
            $domain_array = json_decode($data, true);
            $dwdomain = $domain_array['data']['domain'];
            $checkData = file_get_contents("http://wxapi2.jnoo.com/api/wxapijnoo3173/d8ab42e8f3835593967cb2ebfc618cc1?domain=" . $dwdomain);
            $check_data_array = json_decode($checkData, true);
            $status = $check_data_array['status'];
            if ($status == 2 or $status == 3) {
                file_get_contents($url . $port . '/v1/control/changeDwDomain');
                echo $port . ':域名' . $dwdomain . '要更换成功' . PHP_EOL;
            } else {
                echo $port . ':域名' . $dwdomain . '正常' . PHP_EOL;
            }
            sleep(5);
        }
    }

    /**
     * @return void function 获取公众号配置
     */
    public function getWxConfig()
    {
        $data = Domain::whereIn('type', [21, 22, 23])->where('status', 0)->select();
        $wxconfig = [
            'authdomain' => '',
            'appid' => '',
            'appsecret' => ''
        ];
        foreach ($data as $item) {
            if ($item['type'] == 21) {
                $wxconfig['authdomain'] = $item['domain'];
            }
            if ($item['type'] == 22) {
                $wxconfig['appid'] = $item['domain'];
            }
            if ($item['type'] == 23) {
                $wxconfig['appsecret'] = $item['domain'];
            }
        }
        return ['code' => 200, 'msg' => '获取公众号配置', 'data' => $wxconfig];
    }

    /**
     * function 切换公众号配置
     */
    public function changeWxconfig()
    {
        Domain::whereIn('type', [21, 22, 23])->where('status', 0)->update(['status' => 1]);
        Domain::whereIn('type', [21, 22, 23])->where('status', 2)->update(['status' => 0]);
        Domain::whereIn('type', [21, 22, 23])->where('status', 1)->update(['status' => 2]);
        $data = Domain::whereIn('type', [21, 22, 23])->where('status', 0)->select();
        $wxconfig = [
            'authdomain' => '',
            'appid' => '',
            'appsecret' => ''
        ];
        foreach ($data as $item) {
            if ($item['type'] == 21) {
                $wxconfig['authdomain'] = $item['domain'];
            }
            if ($item['type'] == 22) {
                $wxconfig['appid'] = $item['domain'];
            }
            if ($item['type'] == 23) {
                $wxconfig['appsecret'] = $item['domain'];
            }
        }
        $url = 'http://124.71.214.64:';
        $qrarray = [9204, 9205, 9206, 9209, 9210, 9211];
        foreach ($qrarray as $port) {
            file_get_contents($url . $port . '/v1/control/updateWxconfig?authdomain=' . $wxconfig['authdomain'] . '&appid=' . $wxconfig['appid'] . '&appsecret=' . $wxconfig['appsecret']);
        }
        return ['code' => 200, 'msg' => '切换公众号配置成功'];
    }

    /**
     * @function 获取聊天室域名
     */
    public function getImDomain()
    {
        $data = Domain::whereIn('type', 28)->where('status', 0)->limit(1)->field('id,domain')->find();
        return ['code' => 200, 'msg' => '获取聊天室域名', 'data' => ['imdomain' => $data['domain']]];
    }

    /**
     * @function 更换聊天室域名
     */
    public function changeImdomain()
    {
        Domain::where('type', 28)->where('status', 0)->update(['status' => 1]);
        Domain::where('type', 28)->where('status', 2)->update(['status' => 0]);
        Domain::where('type', 28)->where('status', 1)->update(['status' => 2]);
        $data = Domain::where('type', 28)->where('status', 0)->find();
        return ['code' => 200, 'msg' => '更换聊天室域名成功,新聊天室地址：' . $data['domain']];
    }

    /**
     * @return void 更新公众号配置
     */
    public function updateWxconfig()
    {
        $request = Request::instance();
        $authdomain = $request->get('authdomain');
        $appid = $request->get('appid');
        $appsecret = $request->get('appsecret');
        Domain::where('type', 21)->where('status', 0)->update(['domain' => $authdomain]);
        Domain::where('type', 22)->where('status', 0)->update(['domain' => $appid]);
        Domain::where('type', 23)->where('status', 0)->update(['domain' => $appsecret]);
        return ['code' => 200, 'msg' => '公众号配置更新成功'];
    }

    /**
     * @function 更换节点1
     */
    public function changeWs()
    {
        $request = Request::instance();
        $type = $request->get('type');
        $wsport = $request->get('wsport');
        $wsArray = [
            'ws://8.210.114.209:',
            'ws://8.218.28.194:',
            'ws://116.63.133.208:'
        ];
        $wsurl = $wsArray[$type] . $wsport;
        Domain::where('type', 12)->update(['domain' => $wsurl]);
        return ['code' => 200, 'msg' => '节点更新成功'];
    }

}