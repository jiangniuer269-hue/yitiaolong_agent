<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/3/4
 * Time: 11:56 AM
 */

namespace app\index\controller;

require_once APP_PUBLIC . 'aipspeech/AipSpeech.php';
use AipSpeech;
use app\index\common;
use think\facade\Request;
const APP_ID = '14667434';
const API_KEY = 'mY0FXVT6BbVIPTjkWy471S7s';
const SECRET_KEY = '58zPZxVxfN3QOAZTiTG2Vv5eB6imTWO0 ';
class VoiceManage
{
    /**
     * @function 语音生成
     */

    public function makeVoice()
    {
        $request = Request::instance();
        $str = $request->get('voice');
        $client = new AipSpeech(APP_ID, API_KEY, SECRET_KEY);
        $result = $client->synthesis($str, 'zh', 1, array(
            'vol' => 15,
            'per' => 0  //0女生 1男生
        ));
        $dir_name = 'voice';
        $mktime = time();
        $voice_name = $dir_name . '/voice' . $mktime;
        $voice = 'voice' . $mktime . '.mp3';
        // 识别正确返回语音二进制 错误则返回json 参照下面错误码
        if (!is_array($result)) {
            $dir = common::mkdirs($dir_name);
            if ($dir) {
                $res = file_put_contents($dir_name . '/' . $voice, $result);
                if ($res > 0) {
                    return ['error' => 200, 'voice' => $voice_name];
                } else {
                    return ['error' => 500];
                }
            } else {
                return ['error' => 500];
            }
        } else {
            return ['error' => 500];
        }
    }

}