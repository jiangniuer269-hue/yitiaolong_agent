<?php

/**
 * Created by PhpStorm.
 * User: tei
 * Date: 2019/10/15
 * Time: 4:57 PM
 */

namespace app\index\controller;

use app\index\model\Agents;
use app\index\model\ZjhBetsLog;
use app\index\model\BetsMerge;
use app\index\model\ZjhCardGame;
use app\index\model\User;
use think\Db;
use app\index\common;
use app\index\model\IntegralDate;
use app\index\model\IntegralLog;
use app\index\model\UserScoreLog;
class ZjhTongjiManage
{
    /**
     * @function 递归算法
     */
    public function tongji()
    {
        $card_game = ZjhCardGame::where('bets_merge_state', 0)->where('state', 2)->field('id,room_id,boots_number,ju,zhuang,zhuang_dui,xian_dui,lucky_six,groupid,mktime,super_he,dui_ba')->select();
        if (!empty($card_game)) {
            foreach ($card_game as $item_card_game) {
                $card_game_id = $item_card_game['id'];
                $zhuang_win = $item_card_game['zhuang'];
                $zhuang_dui = $item_card_game['zhuang_dui'];
                $xian_dui = $item_card_game['xian_dui'];
                $lucky_six = $item_card_game['lucky_six'];
                $super_he = $item_card_game['super_he'];
                $dui_ba = $item_card_game['dui_ba'];
                $room_id = $item_card_game['room_id'];
                $boots_number = $item_card_game['boots_number'];
                $ju = $item_card_game['ju'];
                $groupid = $item_card_game['groupid'];
                $mktime = $item_card_game['mktime'];
                //合并下注记录
                $betsData = [];
                $betsAll = [
                    'type1' => 0, //龙  zhuang 1  0.97
                    'type2' => 0, //凤  zhuang 2  0.97
                    'type3' => 0, //幸运一击 dui_ba  豹子X10同花顺X5同花X3顺子X2对子（9-A）X1
                    'type4' => 0, //顺子 zhuang_dui 7
                    'type5' => 0, //同花 xian_dui   8
                    'type6' => 0, //豹子 super_he   120
                    'type7' => 0, //同花顺 lucky_six 100
                ];
                $ZjhBetsLog = ZjhBetsLog::field('odds,type,uid,xm_type,xm_rate,agents_share_rate,agents_id,score_before,ip')
                    ->where('card_game_id', $card_game_id)->where('user_ai', 0)->where('tourist', 0)->where('state', 1)->select();
                if (!empty($ZjhBetsLog)) {
                    foreach ($ZjhBetsLog as $bets_log_item) {
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
                                'xm_type' => $bets_log_item['xm_type'],
                                'xm_rate' => $bets_log_item['xm_rate'],
                                'agents_share_rate' => $bets_log_item['agents_share_rate'],
                                'agents_id' => $bets_log_item['agents_id'],
                                'score_before' => $bets_log_item['score_before'],
                                'ip' => $bets_log_item['ip']
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
                            ->field('u.tourist,u.ai,u.name,u.extra_share,u.xm_rate,u.agents_share_rate,u.xm_type,u.username,a.name as agents_name,a.agents_id,a.account as agents_account')->find();
                        $user_zx_xm = 0;//庄闲洗码
                        $user_sb_xm = 0;//三宝洗码
                        $user_lucky_xm = 0;//幸运六洗码
                        $user_zx_losewin_yx = 0;//庄闲有效输赢
                        $user_zx_losewin = 0;//庄闲输赢
                        $user_sb_losewin = 0;//三宝输赢
                        $user_lucky_losewin = 0;//幸运六输赢
                        $zhuang_yx = 0;//有效庄注
                        $xian_yx = 0;//有效闲注
                        if ($bets_data_item['type1'] > $bets_data_item['type2']) {
                            $zhuang_yx = $bets_data_item['type1'] - $bets_data_item['type2'];
                        }
                        if ($bets_data_item['type2'] > $bets_data_item['type1']) {
                            $xian_yx = $bets_data_item['type2'] - $bets_data_item['type1'];
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

                        //五宝洗码
                        if ($zhuang_win < 3) {
                            if ($super_he == 0) {
                                $user_sb_xm += $bets_data_item['type6'];
                            }
                            if ($zhuang_dui == 0) {
                                $user_sb_xm += $bets_data_item['type4'];
                            }
                            if ($xian_dui == 0) {
                                $user_sb_xm += $bets_data_item['type5'];
                            }
                            if ($lucky_six == 0) {
                                $user_sb_xm += $bets_data_item['type7'];
                            }
                            if ($dui_ba == 0) {
                                $user_sb_xm += $bets_data_item['type3'];
                            }
                        }

                        if ($zhuang_win != 3) {
                            //庄闲输赢
                            if ($zhuang_win == 1) {
                                $user_zx_losewin_yx += common::math_mul($zhuang_yx, 0.97, 2);
                                $user_zx_losewin += common::math_mul($bets_data_item['type1'], 0.97, 2);
                            } else {
                                $user_zx_losewin_yx += -$zhuang_yx;
                                $user_zx_losewin += -$bets_data_item['type1'];
                            }
                            if ($zhuang_win == 2) {
                                $user_zx_losewin_yx += common::math_mul($xian_yx, 0.97, 2);
                                $user_zx_losewin += common::math_mul($bets_data_item['type2'], 0.97, 2);
                            } else {
                                $user_zx_losewin_yx += -$xian_yx;
                                $user_zx_losewin += -$bets_data_item['type2'];
                            }
                        }
                        //五宝输赢
                        if ($super_he > 0) {
                            $user_sb_losewin += $bets_data_item['type6'] * 120;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type6'];
                        }
                        if ($zhuang_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type4'] * 7;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type4'];
                        }
                        if ($xian_dui > 0) {
                            $user_sb_losewin += $bets_data_item['type5'] * 8;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type5'];
                        }
                        if ($lucky_six > 0) {
                            $user_sb_losewin += $bets_data_item['type7'] * 100;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type7'];
                        }

                        if ($dui_ba > 0) {
                            $user_sb_losewin += $bets_data_item['type3'] * $dui_ba;
                        } else {
                            $user_sb_losewin += -$bets_data_item['type3'];
                        }
                        $win_lose = $user_zx_losewin + $user_sb_losewin + $user_lucky_losewin;
                        //获取结算后余分
                        $user_score_log_ukey = $groupid . '_' . $card_game_id . '_' . $bets_log_uid . '_111';
                        $user_score_after = $bets_data_item['score_before'] + $win_lose;
                        $user_score_log_data = UserScoreLog::where('ukey', $user_score_log_ukey)->field('score_after')->find();
                        if (!empty($user_score_log_data['score_after'])) {
                            $user_score_after = $user_score_log_data['score_after'];
                        }
                        if ($user_info['ai'] == 0 && $user_info['tourist'] == 0) {
                            $bets_ukey = $card_game_id . '_' . $uid . '_' . $groupid . '_zjh';
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
                                    'super_he' => $bets_data_item['type6'],
                                    'score_before' => $bets_data_item['score_before'],
                                    'score_after' => $user_score_after,
                                    'win' => $win_lose,
                                    'game_zhuang' => $zhuang_win,
                                    'game_zhuang_dui' => $zhuang_dui,
                                    'game_xian_dui' => $xian_dui,
                                    'game_lucky_six' => $lucky_six,
                                    'game_dui_ba' => $dui_ba,
                                    'game_super_he' => $super_he,
                                    'state' => 0,
                                    'mktime' => $mktime,
                                    'card_game_id' => $card_game_id,
                                    'room_id' => $room_id,
                                    'boots_number' => $boots_number,
                                    'ju' => $ju,
                                    'groupid' => $groupid,
                                    'agents_id' => $user_info['agents_id'],
                                    'agents_account' => $user_info['agents_account'],
                                    'agents_name' => $user_info['agents_name'],
                                    'username' => $user_info['username'],
                                    'user_zx_xm' => $user_zx_xm,
                                    'user_sb_xm' => $user_sb_xm,
                                    'user_lucky_xm' => $user_lucky_xm,
                                    'user_zx_losewin' => $user_zx_losewin,
                                    'user_sb_losewin' => $user_sb_losewin,
                                    'user_lucky_losewin' => $user_lucky_losewin,
                                    'xm_type' => $user_info['xm_type'],
                                    'share_rate' => $user_info['agents_share_rate'],
                                    'xm_rate' => $user_info['xm_rate'],
                                    'zhuang_yx' => $zhuang_yx,
                                    'xian_yx' => $xian_yx,
                                    'zhuang_xian_dc' => min($bets_data_item['type1'], $bets_data_item['type2']),
                                    'user_zx_losewin_yx' => $user_zx_losewin_yx,
                                    'extra_share' => $user_info['extra_share'],
                                    'extra_share_score' => intval($win_lose * $user_info['extra_share'] / 100),
                                    'ukey' => $bets_ukey,
                                    'game_type' => 2,
                                    'ip' => $bets_data_item['ip']
                                ];
                                BetsMerge::create($bets_merge_data);
                            }
                        }
                        $inte_ukey = $card_game_id . '_2_' . $uid . '_' . $groupid;
                        $find_ukey = IntegralLog::where('ukey', $inte_ukey)->field('id')->find();
                        if (empty($find_ukey)) {
                            $integrate_score = $user_zx_xm + $user_sb_xm + $user_lucky_xm;
                            $date_time = date('Ymd');
                            $integral = common::math_div($integrate_score, 1000, 2);
                            //日积分流水
                            $integrate_log_data = [
                                'uid' => $uid,
                                'score' => $integrate_score,
                                'state' => 0,
                                'mktime' => $mktime,
                                'inte_rate' => $user_info['xm_rate'],
                                'integral' => $integral,
                                'date_time' => $date_time,
                                'card_game_id' => $card_game_id,
                                'ukey' => $inte_ukey,
                                'type' => 2,
                                'tourist' => $user_info['tourist'],
                                'user_ai' => $user_info['ai']
                            ];
                            IntegralLog::create($integrate_log_data);
                            IntegralDate::change($uid, $integral);
                        }
                    }
                }
                ZjhCardGame::where('id', $card_game_id)->update(['bets_merge_state' => 1]);
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
        $super_he = $value['super_he'];
        $room_id = $value['room_id'];
        $boots_number = $value['boots_number'];
        $ju = $value['ju'];
        $game_zhuang = $value['game_zhuang'];
        $game_zhuang_dui = $value['game_zhuang_dui'];
        $game_xian_dui = $value['game_xian_dui'];
        $game_lucky_six = $value['game_lucky_six'];
        $game_dui_ba = $value['game_dui_ba'];
        $game_super_he = $value['game_super_he'];
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
                'super_he' => $super_he,
                'card_game_id' => $card_game_id,
                'room_id' => $room_id,
                'boots_number' => $boots_number,
                'ju' => $ju,
                'game_zhuang' => $game_zhuang,
                'game_zhuang_dui' => $game_zhuang_dui,
                'game_xian_dui' => $game_xian_dui,
                'game_lucky_six' => $game_lucky_six,
                'game_dui_ba' => $game_dui_ba,
                'game_super_he' => $game_super_he
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
            $game_data[$agent_game_key]['super_he'] += $super_he;
        }
        $boss_id = intval($agents['boss_id']);
        $insert_game_data = $game_data[$agent_game_key];
        $insert_game_data['boss_id'] = $boss_id;
        $insert_game_data['uid'] = $value['uid'];
        $insert_game_data['level'] = $level;
        $insert_game_data['mktime'] = $mktime;
        $insert_game_data['ukey'] = $card_game_id . '_' . $value['uid'] . '_' . $agents_id . '_zjh';
        $insert_game_data['game_type'] = 2;
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
            'ukey' => $card_game_id . '_' . $fan_agents_id . '_' . $agents_id . '_zjh',
            'game_type' => 2
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

}