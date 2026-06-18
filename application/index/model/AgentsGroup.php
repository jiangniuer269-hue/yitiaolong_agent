<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/2
 * Time: 11:42
 */
namespace app\index\model;

class AgentsGroup extends \think\Model
{
    protected $pk = 'group_id';

    /**
     * @function 查询分组
     */
    public static function selectGroup($where=[]){
        $data = AgentsGroup::where($where)->where('deleted',0)->select();
        return $data;
    }

    /**
     * @fucntion 创建分组
     * @param $insertData
     * @return int|string
     */
    public static function insertGroup($insertData){
        $data = AgentsGroup::insert($insertData);
        return $data;
    }
}