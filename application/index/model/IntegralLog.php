<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/6/8
 * Time: 11:36 AM
 */

namespace app\index\model;

use think\Model;


class IntegralLog extends Model
{
    /**
     * @function 查询日积分
     *
     * @param array $where
     * @param string $field
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function selectIntegralLog($where = [], $field = '*')
    {
        $data = IntegralLog::where($where)->field($field)->select();
        return $data;
    }
}