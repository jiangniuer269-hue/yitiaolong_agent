<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\16 0016
 * Time: 17:14
 */

namespace app\index\model;

use think\Model;

class User extends Model
{
    /**
     * @function 查询用户
     */
    public static function selectUser($where = [], $field = '*')
    {
        $data = User::where($where)->field($field)->select();
        return $data;
    }

    /**
     * @function  获取粉丝数量
     */
    public static function getFansNum($uid = 0)
    {
        $data = User::where('agent_id', $uid)->count();
        return $data;
    }

    /**
     * @function 获取用户余分
     */
    public static function getUserScore($uid)
    {
        $data = User::where('uid', $uid)->field('score')->find();
        if (!empty($data)) {
            return $data['score'];
        } else {
            return 0;
        }
    }

}