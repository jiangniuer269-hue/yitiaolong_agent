<?php
/**
 * Created by PhpStorm.
 * User: Agentsistrator
 * Date: 2018\9\14 0014
 * Time: 10:32
 */
namespace app\index\model;

use think\Db;
use think\Model;

class Agents extends Model
{
    protected $pk = 'agents_id';

    /**
     * @function 获取管理员
     */
    public static function selectAgents($where)
    {
        $data = Agents::where($where)->select();
        return $data;
    }

    /**
     * @function 获取一个管理员
     */
    public static function findAgentsOne($where = [], $field = 'account,agents_id,xm_type,name,agent_score,xm_rate,share_rate,xh_config,tid')
    {
        $data = Agents::field($field)->where($where)->find();
        return $data;
    }

    /**
     * @function 创建代理
     */
    public static function insertAgents($insertData = [])
    {
        $insert_id = Agents::insert($insertData,false,true);
        return $insert_id;
    }

    /**
     * @function 获取代理角色信息
     *
     * @return false|\PDOStatement|string|\think\Collection
     */
    public static function getAgentsGroup($where = [])
    {
        $agents = Agents::alias('a')->join('agents_group g', 'a.group_id=g.group_id')->where($where)->where('a.status', 0)->where('a.deleted', 0)
            ->field('a.agents_id,a.uid,a.account,a.playid,a.name,a.group_id,g.group_name,a.agents_desc,g.group_level,g.menu_id')->select();
        return $agents;
    }

}