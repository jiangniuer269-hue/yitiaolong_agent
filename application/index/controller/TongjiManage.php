<?php

/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/10/15
 * Time: 4:57 PM
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\Agents;
use app\index\model\BetsLog;
use app\index\model\BetsMerge;
use app\index\model\CardGame;
use app\index\model\IntegralDate;
use app\index\model\IntegralLog;
use app\index\model\TeamRoom;
use app\index\model\User;
use app\index\model\UserScoreLog;
use think\Db;
use app\index\model\Domain;
use app\index\model\DanXmDate;

class TongjiManage
{
    /**
     * @function 递归算法
     */
    public function tongji()
    {
        $card_game = CardGame::where('bets_merge_state', 0)->where('state', 2)->field('id,room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,groupid,mktime,super_he,fb,lq,tid')->select();
        if (!empty($card_game)) {
            foreach ($card_game as $item_card_game) {              
                $card_game_id = $item_card_game['id'];
                $zhuang_win = $item_card_game['zhuang'];
                $zhuang_dui = $item_card_game['zhuang_dui'];
                $xian_dui = $item_card_game['xian_dui'];
                $lucky_six = $item_card_game['lucky_six'];
                $super_he = $item_card_game['super_he'];
                $fb = $item_card_game['fb'];
                $lq = $item_card_game['lq'];
                $room_id = $item_card_game['room_id'];
                $boots_number = $item_card_game['boots_number'];
                $ju = $item_card_game['ju'];
                $groupid = $item_card_game['groupid'];
                $mktime = $item_card_game['mktime'];
                $tid = $item_card_game['tid'];
                //获取赔率
                $query_sql = TeamRoom::alias('t')->join('team_config c', 't.tid=c.tid');
                $query_sql->where('t.groupid', $groupid);
                $query_sql->field('t.zhuang,t.xian,t.zhuang_dui,t.xian_dui,t.he,t.lq,t.fb,t.super_he,t.lucky_six_12,t.lucky_six_20,c.score_rate,c.integral_tongji_way');
                $system_info = $query_sql->find();
                $zhuang_rate = $system_info['zhuang'];
                $xian_rate = $system_info['xian'];
                $lucky_six_12 = $system_info['lucky_six_12'];
                $lucky_six_20 = $system_info['lucky_six_20'];
                $he_rate = $system_info['he'];
                $zhuang_dui_rate = $system_info['zhuang_dui'];
                $xian_dui_rate = $system_info['xian_dui'];
                $lq_rate = $system_info['lq'];
                $fb_rate = $system_info['fb'];
                $super_he_rate = $system_info['super_he'];
                $score_rate = $system_info['score_rate'];//流水比积分
                $integral_tongji_way = intval($system_info['integral_tongji_way']);//积分统计方式：0按自然日1按开工时间
                $start_work_time = '';
                if ($integral_tongji_way >0) {
                    //获取开工时间
                    $start_work_time_data = Domain::where('type',16)->field('domain')->find();
                    if ($start_work_time_data['domain']<=0) {
                        echo '没有设置开工时间_' . date('Y-m-d H:i:s');exit();
                    }
                    $start_work_time = intval(date('Ymd',strtotime($start_work_time_data['domain'])));
                }
                //合并下注记录
                $betsData = [];
                $betsAll = [
                    'type1' => 0,
                    'type2' => 0,
                    'type3' => 0,
                    'type4' => 0,
                    'type5' => 0,
                    'type6' => 0,
                    'type7' => 0,
                    'type8' => 0,//龙七
                    'type9' => 0,//凤八
                    'type10' => 0,//超和
                ];
                $betsLog = BetsLog::field('odds,type,uid,xm_type,xm_rate,agents_share_rate,agents_id,score_before,ip,tid,mark,groupname,time')
                    ->where('card_game_id', $card_game_id)->where('user_ai', 0)->where('tourist', 0)->where('state', 1)->select();
                if (!empty($betsLog)) {
             
                    foreach ($betsLog as $bets_log_item) {
                        $bets_log_uid = $bets_log_item['uid'];
                        $bets_log_type = 'type' . $bets_log_item['type'];
                        $bets_log_odds = $bets_log_item['odds'];
                        $betsAll[$bets_log_type] += $bets_log_odds;  //下注总额
                        if (empty($betsData[$bets_log_uid])) {
                            $betsData[$bets_log_uid] = [
                                'uid' => $bets_log_uid,
                                'type1' => 0,
                                'type2' => 0,
                                'type3' => 0,
                                'type4' => 0,
                                'type5' => 0,
                                'type6' => 0,
                                'type7' => 0,
                                'type8' => 0,//龙七
                                'type9' => 0,//凤八
                                'type10' => 0,//超和
                                'xm_type' => $bets_log_item['xm_type'],
                                'xm_rate' => $bets_log_item['xm_rate'],
                                'agents_share_rate' => $bets_log_item['agents_share_rate'],
                                'agents_id' => $bets_log_item['agents_id'],
                                'score_before' => $bets_log_item['score_before'],
                                'ip' => $bets_log_item['ip'],
                                'tid' => $bets_log_item['tid'],
                                'mark' => $bets_log_item['mark'],
                                'groupname' => $bets_log_item['groupname'],
                                'oddstime'=>$bets_log_item['time']
                            ];
                            $betsData[$bets_log_uid][$bets_log_type] = $bets_log_odds;
                        } else {
                            $betsData[$bets_log_uid][$bets_log_type] += $bets_log_odds;
                        }
                    }

                    //写入合并后的记录
                    foreach ($betsData as $bets_data_item) {
                        $uid = $bets_data_item['uid'];
                        $user_info = User::alias('u')->join('agents a', 'u.agents_id=a.agents_id')->where('u.uid', $uid)
                            ->field('u.tourist,u.ai,u.name,u.extra_share,u.xm_rate,u.agents_share_rate,u.agents_sb_share_rate,u.xm_type,u.username,a.name as agents_name,a.agents_id,a.account as agents_account')->find();
                        $user_zx_xm_dan = 0;//单边庄闲洗码
                        $user_zx_xm = 0;//庄闲洗码
                        $user_sb_xm = 0;//三宝洗码
                        $user_lucky_xm = 0;//幸运六洗码
                        $user_super_he_xm = 0;//超和洗码
                        $user_lq_xm = 0;//龙七洗码
                        $user_fb_xm = 0;//凤八洗码
                        $user_zx_losewin_yx = 0;//庄闲有效输赢
                        $user_zx_losewin = 0;//庄闲输赢
                        $user_sb_losewin = 0;//三宝输赢
                        $user_lucky_losewin = 0;//幸运六输赢
                        $user_super_he_losewin = 0;//超和洗码
                        $user_lq_losewin = 0;//龙七洗码
                        $user_fb_losewin = 0;//凤八洗码
                        $zhuang_yx = 0;//有效庄注
                        $xian_yx = 0;//有效闲注
                        if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                            $zhuang_yx = $bets_data_item['type1'] - $bets_data_item['type2'];
                        }
                        if ($bets_data_item['type2'] > $bets_data_item['type1']) {
                            $xian_yx = $bets_data_item['type2'] - $bets_data_item['type1'];
                        }
                        //庄闲单边洗码
                        if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                            if ($zhuang_win == 2) {
                                $user_zx_xm_dan += ($bets_data_item['type1'] - $bets_data_item['type2']);
                            }
                        } elseif ($bets_data_item['type2'] > $bets_data_item['type1']) {
                            if ($zhuang_win == 1) {
                                $user_zx_xm_dan += ($bets_data_item['type2'] - $bets_data_item['type1']);
                            }
                        }
                        if ($user_info['xm_type'] == 1) {
                            if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                                if ($zhuang_win == 2) {
                                    $user_zx_xm += ($bets_data_item['type1'] - $bets_data_item['type2']);
                                }
                            }
                            if ($bets_data_item['type2'] > $bets_data_item['type1']) {
                                if ($zhuang_win == 1) {
                                    $user_zx_xm += ($bets_data_item['type2'] - $bets_data_item['type1']);
                                }
                            }
                        } elseif ($user_info['xm_type'] == 2) {
                            if ($zhuang_win != 3) {
                                $user_zx_xm = abs($bets_data_item['type1'] - $bets_data_item['type2']);
                            }
                        }
                        if ($zhuang_win < 3) {
                            $user_sb_xm += $bets_data_item['type3'];
                        }
                        if ($zhuang_dui == 0) {
                            $user_sb_xm += $bets_data_item['type4'];
                        }
                        if ($xian_dui == 0) {
                            $user_sb_xm += $bets_data_item['type5'];
                        }
                        if ($lucky_six == 0) {
                            $user_lucky_xm += $bets_data_item['type7'];
                        }
                        if ($lq == 0) {
                            $user_lq_xm += $bets_data_item['type8'];
                        }
                        if ($fb == 0) {
                            $user_fb_xm += $bets_data_item['type9'];
                        }
                        if ($super_he == 0) {
                            $user_super_he_xm += $bets_data_item['type10'];
                        }

                        if ($zhuang_win != 3) {
                            if ($zhuang_win == 1) {
                                $user_zx_losewin_yx += common::math_mul($zhuang_yx, $zhuang_rate, 2);
                                $user_zx_losewin += common::math_mul($bets_data_item['type1'], $zhuang_rate, 2);
                            } else {
                                $user_zx_losewin_yx += -$zhuang_yx;
                                $user_zx_losewin += -$bets_data_item['type1'];
                            }
                            if ($zhuang_win == 2) {
                                $user_zx_losewin_yx += $xian_yx;
                                $user_zx_losewin += $bets_data_item['type2'];
                            } else {
                                $user_zx_losewin_yx += -$xian_yx;
                                $user_zx_losewin += -$bets_data_item['type2'];
                            }
                        }
                        if ($zhuang_win == 3) {
                            $user_sb_losewin += $bets_data_item['type3'] * $he_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type3'];
                        }
                        if ($zhuang_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type4'] * $zhuang_dui_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type4'];
                        }
                        if ($xian_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type5'] * $xian_dui_rate;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type5'];
                        }
                        if ($lucky_six == 0) {
                            $user_lucky_losewin += -$bets_data_item['type7'];
                        } elseif ($lucky_six == 6) {
                            $user_lucky_losewin += $bets_data_item['type7'] * $lucky_six_12;
                        } elseif ($lucky_six == 7) {
                            $user_lucky_losewin += $bets_data_item['type7'] * $lucky_six_20;
                        }
                        if ($lq > 0) {
                            $user_lq_losewin += $bets_data_item['type8'] * $lq_rate;
                        } else {
                            $user_lq_losewin += -$bets_data_item['type8'];
                        }
                        if ($fb > 0) {
                            $user_fb_losewin += $bets_data_item['type9'] * $fb_rate;
                        } else {
                            $user_fb_losewin += -$bets_data_item['type9'];
                        }
                        if ($super_he > 0) {
                            $user_super_he_losewin += $bets_data_item['type10'] * $super_he_rate;
                        } else {
                            $user_super_he_losewin += -$bets_data_item['type10'];
                        }

                        $win_lose = $user_zx_losewin + $user_sb_losewin + $user_lucky_losewin + $user_lq_losewin + $user_fb_losewin + $user_super_he_losewin;
                        //获取结算后余分
                        $user_score_log_ukey = $groupid . '_' . $card_game_id . '_' . $bets_log_uid . '_111';
                        $user_score_after = $bets_data_item['score_before'] + $win_lose;
                        $user_score_log_data = UserScoreLog::where('ukey', $user_score_log_ukey)->field('score_after')->find();
                        if (!empty($user_score_log_data['score_after'])) {
                            $user_score_after = $user_score_log_data['score_after'];
                        }
                        if ($user_info['ai'] == 0 && $user_info['tourist'] == 0) {
                            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid;
                            $find_ukey = BetsMerge::where('ukey', $bets_ukey)->field('id')->find();
                            if (empty($find_ukey)) {
                                $bets_merge_data = [
                                    'uid' => $uid,
                                    'nickname' => $user_info['name'],
                                    'zhuang' => $bets_data_item['type1'],
                                    'xian' => $bets_data_item['type2'],
                                    'he' => $bets_data_item['type3'],
                                    'zhuang_dui' => $bets_data_item['type4'],
                                    'xian_dui' => $bets_data_item['type5'],
                                    'lucky_six' => $bets_data_item['type7'],
                                    'lq' => $bets_data_item['type8'],
                                    'fb' => $bets_data_item['type9'],
                                    'super_he' => $bets_data_item['type10'],
                                    'score_before' => $bets_data_item['score_before'],
                                    'score_after' => $user_score_after,
                                    'win' => $win_lose,
                                    'game_zhuang' => $zhuang_win,
                                    'game_zhuang_dui' => $zhuang_dui,
                                    'game_xian_dui' => $xian_dui,
                                    'game_lucky_six' => $lucky_six,
                                    'game_lq' => $lq,
                                    'game_fb' => $fb,
                                    'game_super_he' => $super_he,
                                    'state' => 0,
                                    'mktime' => $bets_data_item['oddstime'],
                                    'card_game_id' => $card_game_id,
                                    'room_id' => $room_id,
                                    'boots_number' => $boots_number,
                                    'ju' => $ju,
                                    'groupid' => $groupid,
                                    'agents_id' => $user_info['agents_id'],
                                    'agents_account' => $user_info['agents_account'],
                                    'agents_name' => $user_info['agents_name'],
                                    'username' => $user_info['username'],
                                    'user_zx_xm_dan' => $user_zx_xm_dan,
                                    'user_zx_xm' => $user_zx_xm,
                                    'user_sb_xm' => $user_sb_xm,
                                    'user_lucky_xm' => $user_lucky_xm,
                                    'user_lq_xm' => $user_lq_xm,
                                    'user_fb_xm' => $user_fb_xm,
                                    'user_super_he_xm' => $user_super_he_xm,
                                    'user_zx_losewin' => $user_zx_losewin,
                                    'user_sb_losewin' => $user_sb_losewin,
                                    'user_lucky_losewin' => $user_lucky_losewin,
                                    'user_lq_losewin' => $user_lq_losewin,
                                    'user_fb_losewin' => $user_fb_losewin,
                                    'user_super_he_losewin' => $user_super_he_losewin,
                                    'xm_type' => $user_info['xm_type'],
                                    'agents_share_rate' => $user_info['agents_share_rate'],
                                    'agents_sb_share_rate' => $user_info['agents_sb_share_rate'],
                                    'xm_rate' => $user_info['xm_rate'],
                                    'zhuang_yx' => $zhuang_yx,
                                    'xian_yx' => $xian_yx,
                                    'zhuang_xian_dc' => min($bets_data_item['type1'], $bets_data_item['type2']),
                                    'user_zx_losewin_yx' => $user_zx_losewin_yx,
                                    'extra_share' => $user_info['extra_share'],
                                    'extra_share_score' => intval($win_lose * $user_info['extra_share'] / 100),
                                    'ukey' => $bets_ukey,
                                    'game_type' => 0,
                                    'ip' => $bets_data_item['ip'],
                                    'tid' => $bets_data_item['tid'],
                                    'mark' => $bets_data_item['mark'],
                                    'groupname' => $bets_log_item['groupname']
                                ];
                                BetsMerge::create($bets_merge_data);
                                //单边洗码按天统计
                                $dan_date_time = date('Ymd');
                                if ($integral_tongji_way > 0) {
                                    $dan_date_time = $start_work_time ;
                                }
                                $dan_uky = $uid.'_'.$dan_date_time;
                                $find_dan_data = DanXmDate::where('ukey', $dan_uky)->field('id,user_zx_xm_dan,user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,xm_total')->find();                                
                                $dan_xm_data = [
                                    'user_zx_xm_dan'=>$user_zx_xm_dan,
                                    'user_sb_xm'=>$user_sb_xm,
                                    'user_lucky_xm'=>$user_lucky_xm,
                                    'user_lq_xm'=>$user_lq_xm,
                                    'user_fb_xm'=>$user_fb_xm,
                                    'user_super_he_xm'=>$user_super_he_xm,      
                                    'xm_total'=>$user_zx_xm_dan+$user_sb_xm+$user_lucky_xm+$user_lq_xm+$user_fb_xm+$user_super_he_xm, 
                                ];
                                if (empty($find_dan_data)) {
                                    //日单边洗码
                                    $dan_xm_date_ins = $dan_xm_data;
                                    $dan_xm_date_ins['uid'] = $uid;
                                    $dan_xm_date_ins['nickname'] = $user_info['name'];
                                    $dan_xm_date_ins['username'] = $user_info['username'];
                                    $dan_xm_date_ins['agents_id'] = $user_info['agents_id'];
                                    $dan_xm_date_ins['agents_account'] = $user_info['agents_account'];
                                    $dan_xm_date_ins['agents_name'] = $user_info['agents_name'];      
                                    $dan_xm_date_ins['ukey'] = $dan_uky;
                                    $dan_xm_date_ins['date_time'] = $dan_date_time;
                                    $dan_xm_date_ins['mktime'] = time();
                                    DanXmDate::create($dan_xm_date_ins);                                  
                                }else {
                                    $dan_xm_data_update = [
                                        'user_zx_xm_dan' => common::math_add($find_dan_data['user_zx_xm_dan'], $user_zx_xm_dan,2),
                                        'user_sb_xm' => common::math_add($find_dan_data['user_sb_xm'], $user_sb_xm,2),
                                        'user_lucky_xm' => common::math_add($find_dan_data['user_lucky_xm'], $user_lucky_xm,2),
                                        'user_lq_xm' => common::math_add($find_dan_data['user_lq_xm'], $user_lq_xm,2),
                                        'user_fb_xm' => common::math_add($find_dan_data['user_fb_xm'], $user_fb_xm,2),
                                        'user_super_he_xm' => common::math_add($find_dan_data['user_super_he_xm'], $user_super_he_xm,2),            
                                    ];
                                    $dan_xm_data_update['xm_total'] = $dan_xm_data_update['user_zx_xm_dan']+$dan_xm_data_update['user_sb_xm']+$dan_xm_data_update['user_lucky_xm']+$dan_xm_data_update['user_lq_xm']+$dan_xm_data_update['user_fb_xm']+$dan_xm_data_update['user_super_he_xm'];
                                    DanXmDate::where('ukey',$dan_uky)->update($dan_xm_data_update);
                                }
                                
                               
                            }
                        }
                        $inte_ukey = $card_game_id . '_0_' . $uid . '_' . $groupid;
                        $find_ukey = IntegralLog::where('ukey', $inte_ukey)->field('id')->find();       
                        if (empty($find_ukey)) {
                            $integrate_score = $user_zx_xm + $user_sb_xm + $user_lucky_xm + $user_lq_xm + $user_fb_xm + $user_super_he_xm;
                            $date_time = date('Ymd');
                            if ($integral_tongji_way > 0) {
                                $date_time = $start_work_time ;
                            }
                            $integral  = 0;
                            if ($score_rate >0){
                                $integral = common::math_div($integrate_score, $score_rate, 2);
                            }
                            //日积分流水
                            $integrate_log_data = [
                                'uid' => $uid,
                                'score' => $integrate_score,
                                'state' => 1,
                                'mktime' => $mktime,
                                'inte_rate' => $user_info['xm_rate'],
                                'integral' => $integral,
                                'date_time' => $date_time,
                                'card_game_id' => $card_game_id,
                                'ukey' => $inte_ukey,
                                'type' => 1,
                                'tourist' => $user_info['tourist'],
                                'user_ai' => $user_info['ai'],
                                'tid' => $tid
                            ];
                            IntegralLog::create($integrate_log_data);
                            IntegralDate::change($uid, $integral, $tid);
                        }
                    }
                }
                CardGame::where('id', $card_game_id)->update(['bets_merge_state' => 1]);
                echo '牌局ID' . $card_game_id . '执行完毕_' . date('Y-m-d H:i:s');
            }
        } else {
            echo '暂无牌局记录_' . date('Y-m-d H:i:s');
        }
    }


    /**
     * @function   代理下注
     */
    public
    function agents_odds($agents_id, $value, $game_data, $level, $mktime)
    {
        $card_game_id = $value['card_game_id'];
        $zhuang_yx = $value['zhuang'];
        $xian_yx = $value['xian'];
        $zhuang = $value['zhuang'];
        $xian = $value['xian'];
        $he = $value['he'];
        $zhuang_dui = $value['zhuang_dui'];
        $xian_dui = $value['xian_dui'];
        $lucky_six = $value['lucky_six'];
        $room_id = $value['room_id'];
        $boots_number = $value['boots_number'];
        $ju = $value['ju'];
        $game_zhuang = $value['game_zhuang'];
        $game_zhuang_dui = $value['game_zhuang_dui'];
        $game_xian_dui = $value['game_xian_dui'];
        $game_lucky_six = $value['game_lucky_six'];
        //获取代理信息
        $agents = Agents::where('agents_id', $agents_id)->find();
        $agent_game_key = $card_game_id . '_' . $agents_id;
        if (empty($game_data[$agent_game_key])) {
            $game_data[$agent_game_key] = [
                'agents_id' => $agents_id,
                'agents_account' => $agents['account'],
                'agents_name' => $agents['name'],
                'xm_type' => $agents['xm_type'],
                'xm_rate' => $agents['xm_rate'],
                'share_rate' => $agents['share_rate'],
                'sb_share_rate' => $agents['sb_share_rate'],
                'sb_xm_rate' => $agents['sb_xm_rate'],
                'boss_id' => $agents['boss_id'],
                'zhuang_yx' => $zhuang_yx,
                'xian_yx' => $xian_yx,
                'zhuang' => $zhuang,
                'xian' => $xian,
                'he' => $he,
                'zhuang_dui' => $zhuang_dui,
                'xian_dui' => $xian_dui,
                'lucky_six' => $lucky_six,
                'card_game_id' => $card_game_id,
                'room_id' => $room_id,
                'boots_number' => $boots_number,
                'ju' => $ju,
                'game_zhuang' => $game_zhuang,
                'game_zhuang_dui' => $game_zhuang_dui,
                'game_xian_dui' => $game_xian_dui,
                'game_lucky_six' => $game_lucky_six
            ];
        } else {
            $game_data[$agent_game_key]['zhuang'] += $zhuang;
            $game_data[$agent_game_key]['xian'] += $xian;
            $game_data[$agent_game_key]['zhuang_yx'] += $zhuang_yx;
            $game_data[$agent_game_key]['xian_yx'] += $xian_yx;
            $game_data[$agent_game_key]['he'] += $he;
            $game_data[$agent_game_key]['zhuang_dui'] += $zhuang_dui;
            $game_data[$agent_game_key]['xian_dui'] += $xian_dui;
            $game_data[$agent_game_key]['lucky_six'] += $lucky_six;
        }
        $boss_id = intval($agents['boss_id']);
        $insert_game_data = $game_data[$agent_game_key];
        $insert_game_data['boss_id'] = $boss_id;
        $insert_game_data['uid'] = $value['uid'];
        $insert_game_data['level'] = $level;
        $insert_game_data['mktime'] = $mktime;
        $insert_game_data['ukey'] = $card_game_id . '_' . $value['uid'] . '_' . $agents_id;
        $insert_game_data['game_type'] = 0;
        Db::name('agents_bets_merge_log')->insert($insert_game_data, false, true);
        if ($boss_id == 0) {
            return $game_data;
        } else {
            $level++;
            return $this->agents_odds($boss_id, $value, $game_data, $level, $mktime);
        }
    }


    /**
     * @function 代理占成
     * @parama 大于等于90占干成
     */
    public
    function agent_share($agents_zx_losewin, $agents_sb_losewin, $agents_lucky_losewin, $agents_id, $level, $card_game_id, $fan_agents_id, $mktime)
    {
        //代理信息
        $agents = Agents::where('agents_id', $agents_id)->find();
        $share_rate = $agents['share_rate'];
        if ($share_rate >= 90) {
            $share_rate = 100;
        }
        $sb_share_rate = $agents['sb_share_rate'];
        if ($sb_share_rate >= 90) {
            $sb_share_rate = 100;
        }
        $zx_share_losewin = sprintf("%.2f", $agents_zx_losewin * $share_rate / 100);
        $sb_share_losewin = sprintf("%.2f", $agents_sb_losewin * $sb_share_rate / 100);
        $lucky_share_losewin = sprintf("%.2f", $agents_lucky_losewin * $sb_share_rate / 100);
        $agents_share_data = [
            'agents_id' => $agents['agents_id'],
            'agents_account' => $agents['account'],
            'agents_name' => $agents['name'],
            'zx_losewin' => $agents_zx_losewin,
            'sb_losewin' => $agents_sb_losewin,
            'lucky_losewin' => $agents_lucky_losewin,
            'share_rate' => $agents['share_rate'],
            'sb_share_rate' => $agents['sb_share_rate'],
            'zx_share_losewin' => $zx_share_losewin,
            'sb_share_losewin' => $sb_share_losewin,
            'lucky_share_losewin' => $lucky_share_losewin,
            'card_game_id' => $card_game_id,
            'level' => $level,
            'fan_agents_id' => $fan_agents_id,
            'mktime' => $mktime,
            'ukey' => $card_game_id . '_' . $fan_agents_id . '_' . $agents_id
        ];
        Db::name('agents_share_log')->insert($agents_share_data, false, true);
        $agents_zx_losewin = $agents_zx_losewin - $zx_share_losewin;
        $agents_sb_losewin = $agents_sb_losewin - $sb_share_losewin;
        $agents_lucky_losewin = $agents_lucky_losewin - $lucky_share_losewin;
        //获取上级信息
        $boss_id = $agents['boss_id'];
        if ($boss_id == 0) {
            return $boss_id;
        } else {
            $level++;
            return $this->agent_share($agents_zx_losewin, $agents_sb_losewin, $agents_lucky_losewin, $boss_id, $level, $card_game_id, $fan_agents_id, $mktime);
        }
    }


    /**
     * @function 代理额外抽水
     */
    public function extra_share_score()
    {
        $bets = BetsMerge::field('id,uid,nickname,username,agents_id,agents_name,agents_account,extra_share_score,mktime')->where('extra_share_score', '>', 0)->where('extra_state', 0)->select();
        foreach ($bets as $value) {
            $agents_id = $value['agents_id'];
            $agents = Agents::where('agents_id', $agents_id)->field('name,account,agent_score')->find(); //获取代理余额
            $fen = $value['extra_share_score'];
//            $data_text = [
//                'account' => $value['username'],
//                'name' => $value['nickname'],
//                'score_change' => -$fen,
//                'score' => 0,
//                'score_after' => 0,
//                'note' => '抽水额度',
//                'do_agents_account' => $agents['account'],
//                'mktime' => date('Y-m-d H:i:s'),
//                'user_type' => 2
//            ];
            //代理余分
            $agent_score_after = $agents['agent_score'] + $fen;

//            $insert_agent_data = [
//                'agents_id' => $agents_id,
//                'name' => $agents['name'],
//                'account' => $agents['account'],
//                'score' => $agents['agent_score'],
//                'score_change' => $fen,
//                'score_after' => $agent_score_after,
//                'type' => 12,
//                'time' => $time,
//                'orderid' => $value['id'],
//                'ukey' => $value['id'] . '-extra',
//                'note' => '额度从' . $agents['agent_score'] . '改为' . $agent_score_after,
//                'do_agents_account' => $agents['account'],
//                'data_text' => json_encode($data_text)
//            ];
//            $insert_id = AgentScoreLog::insert($insert_agent_data, false, true);
            Agents::where('agents_id', $agents_id)->update(['agent_score' => $agent_score_after]);
            BetsMerge::where('id', $value['id'])->update(['extra_state' => 1]);
        }

        return date('Y-m-d H:i:s') . '_代理额外抽水值统计完毕' . PHP_EOL;

    }

}