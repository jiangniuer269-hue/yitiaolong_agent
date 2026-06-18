<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\19 0019
 * Time: 10:00
 */

namespace app\index\model;

use think\Model;

class Domain extends Model
{
    /**
     * @function 系统信息 
     */
    public static function selectDomainInfo($where = [], $field = '*')
    {
        $data = Domain::where($where)->field($field)->order('id', 'desc')->select();
        return $data;
    }

}