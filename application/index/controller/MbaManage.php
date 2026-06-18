<?php
/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/6/8
 * Time: 9:39 PM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\BetsLog;
use app\index\model\IntegralDate;
use app\index\model\OddsDataDate;
use app\index\model\User;
use think\Db;
use think\facade\Session;
use think\facade\Request;
use app\index\model\Wxuser;
use app\index\model\OddsDataUser;

class MbaManage
{

    /**
     * @function 数据按天统计
     */
    public function bigdata()
    {
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $uid = $request->get('uid');
        $sql = new OddsDataDate();
        $sql->alias('od')->join('user u', 'u.uid=od.uid')
            ->field('u.name,od.id,od.uid,od.win_lose,od.odds_z,od.odds_x,od.odds_h,od.odds_zd,od.odds_xd,od.odds_xy,od.integral,od.score,od.date_time');
        if (!empty($begin_time)) {
            $begin_time_sql = intval(date('Ymd', strtotime($begin_time)));
            $sql->where('od.date_time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = intval(date('Ymd', strtotime($end_time)));
            $sql->where('od.date_time', '<=', $end_time_sql);
        }
        if (intval($uid) > 0) {
            $sql->where('od.uid', '=', $uid);
        }
        $data = $sql->select();
        return view('bigdata', ['data' => $data, 'parama' => ['begin_time' => $begin_time, 'end_time' => $end_time, 'uid' => $uid]]);
    }

    /**
     * @function 累计数据统计
     */
    public function bigdataUser()
    {
        $request = Request::instance();
        $uid = $request->get('uid');
        $sql = new OddsDataUser();
        $sql->alias('od')->join('user u', 'u.uid=od.uid')
            ->field('u.name,od.id,od.uid,od.win_lose,od.odds_z,od.odds_x,od.odds_h,od.odds_zd,od.odds_xd,od.odds_xy,od.integral');
        if(!empty($uid)){
            $sql->where('od.uid',$uid);
        }
        $data = $sql->select();
        return view('bigdataUser', ['data' => $data, 'parama' => ['uid' => $uid]]);
    }


    /**
     * @function 下载excel表格
     */
    public function doExcel()
    {

        $sql = new OddsDataUser();
        $sql->alias('od')->join('user u', 'u.uid=od.uid')
            ->field('u.name,od.id,od.uid,od.win_lose,od.odds_z,od.odds_x,od.odds_h,od.odds_zd,od.odds_xd,od.odds_xy,od.integral');
        if(!empty($uid)){
            $sql->where('od.uid',$uid);
        }
        $data = $sql->select();
        $count = count($data);// 查询满足要求的总记录数
        $title_str = "用户ID,昵称,庄注,闲注,和注,庄对,闲对,幸运六,输赢";
        if ($count == 0) {
            $exl_title = explode(',', $title_str);
            common::exportToExcel('用户下注输赢报表.xls', $exl_title, [], 100);
            exit();
        }

        $kkk = 1;
        $rs[$kkk] = $data;
        $title_arr[$kkk] = $title_str;
        $exl_title[$kkk] = explode(',', $title_arr[$kkk]);
        foreach ($rs[$kkk] as $k => $v) {
            $exl[$kkk][] = array(
                $v['uid'], $v['name'], $v['odds_z'], $v['odds_x'], $v['odds_h'], $v['odds_zd'],$v['odds_xd'],$v['odds_xy'],$v['win_lose']
            );
        }

        common::exportToExcel('用户下注输赢报表.xls', $exl_title[$kkk], $exl[$kkk], 1000);
        exit();
    }

    /**
     * @function 下载excel表格
     */
    public function doExcelDate()
    {
        $request = Request::instance();
        $begin_time = $request->get('begin_time');
        $end_time = $request->get('end_time');
        $uid = $request->get('uid');
        $sql = new OddsDataDate();
        $sql->alias('od')->join('user u', 'u.uid=od.uid')
            ->field('u.name,od.id,od.uid,od.win_lose,od.odds_z,od.odds_x,od.odds_h,od.odds_zd,od.odds_xd,od.odds_xy,od.integral,od.score,od.date_time');
        if (!empty($begin_time)) {
            $begin_time_sql = intval(date('Ymd', strtotime($begin_time)));
            $sql->where('od.date_time', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = intval(date('Ymd', strtotime($end_time)));
            $sql->where('od.date_time', '<=', $end_time_sql);
        }
        if (intval($uid) > 0) {
            $sql->where('od.uid', '=', $uid);
        }
        $data = $sql->select();
        $count = count($data);// 查询满足要求的总记录数
        $title_str = "用户ID,昵称,庄注,闲注,和注,庄对,闲对,幸运六,输赢";
        if ($count == 0) {
            $exl_title = explode(',', $title_str);
            common::exportToExcel('用户下注输赢报表.xls', $exl_title, [], 100);
            exit();
        }

        $kkk = 1;
        $rs[$kkk] = $data;
        $title_arr[$kkk] = $title_str;
        $exl_title[$kkk] = explode(',', $title_arr[$kkk]);
        foreach ($rs[$kkk] as $k => $v) {
            $exl[$kkk][] = array(
                $v['uid'], $v['name'], $v['odds_z'], $v['odds_x'], $v['odds_h'], $v['odds_zd'],$v['odds_xd'],$v['odds_xy'],$v['win_lose']
            );
        }

        common::exportToExcel('用户下注输赢报表.xls', $exl_title[$kkk], $exl[$kkk], 1000);
        exit();
    }
}