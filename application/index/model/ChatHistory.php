<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\14 0014
 * Time: 10:32
 */
namespace app\index\model;

use think\Model;

class ChatHistory extends Model
{

    /**
     * @function 获取聊天记录
     */
    public static function selectChatHistory($where=[])
    {
        $data = ChatHistory::where($where)->select();
        return $data;
    }
}