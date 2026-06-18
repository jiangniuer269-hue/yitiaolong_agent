<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\17 0017
 * Time: 20:26
 */

namespace app\index\model;

use think\Model;

class LhCardGame extends Model
{
    /**
     * @function 游戏记录
     */
    public static function selectCardGame($where)
    {
        $data = LhCardGame::where($where)->select();
        return $data;
    }

    /**
     * @function 归零
     */
    public static function guiling()
    {
        $data = LhCardGame::where('guiling', 0)->where('state',1)->update(['guiling' => 1]);
        return $data;
    }
}