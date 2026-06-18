<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\19 0019
 * Time: 10:00
 */

namespace app\index\model;

use think\Model;

class TeamRoom extends Model
{
    /**
     * @function 系统信息
     */
    public static function selectSystemInfo($where = [], $field = '*')
    {
        $data = TeamRoom::where($where)->field($field)->order('id', 'desc')->find();
        return $data;
    }

}