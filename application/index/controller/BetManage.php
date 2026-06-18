<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\15 0015
 * Time: 23:11
 */

namespace app\index\controller;

use app\index\common;
use app\index\model\TeamConfig;
use think\Db;
use think\facade\Request;
use app\index\model\BetsMerge;
use app\index\model\Agents;
use app\index\model\TeamRoom;
use app\index\model\DanXmDate;

class BetManage
{

    public function __construct()
    {
        common::checkLogin();
    }

    /**
     * @function 下注列表
     * @return \think\response\View
     */
    public function betList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        ini_set("memory_limit", -1);
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $username = $request->post('username');
        $boots_number = $request->post('boots_number');
        $ju = $request->post('ju');
        $room_id = $request->post('room_id');
        $agents_account = $request->post('agents_account');
        $agents_id = common::changeAgentId($agents_id);
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        $data = [];
        $query_sql = BetsMerge::alias('b')->join('wxagents wx', 'b.agents_id=wx.agents_id');
        $query_sql->field('b.*,wx.relation,wx.relation_link,wx.level');
        $query_sql->where('wx.boss_id', $agents_id);
        if ($tid != 1) {
            $query_sql->where('b.tid', $tid);
        }
        $query_sql->order('b.mktime', 'desc');
        //代理账号查询
        if (!empty($agents_account)) {
            //获取代理信息
            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
            if (!empty($search_agents)) {
                $search_agents_id = $search_agents['agents_id'];
                $query_sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
                });
            } else {
                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
            }
        }
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $query_sql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $query_sql->where('b.mktime', '<=', $end_time_sql);
        }
        if (!empty($username)) {
            $query_sql->where('b.uid', '=', $username);
        }
        if (intval($boots_number) > 0) {
            $query_sql->where('b.boots_number', $boots_number);
        }
        if (intval($ju) > 0) {
            $query_sql->where('b.ju', $ju);
        }
        if (!empty($room_id)) {
            $query_sql->where('b.room_id', $room_id);
        }
        if ($game_type >= 0) {
            $query_sql->where('b.game_type', $game_type);
        }
        $betsData = $query_sql->limit($start, $pageSize)->select();
        $total = $query_sql->select();
        $total_win = 0;
        $total_jifen = 0;
        $total_page = 0;
        //获取流水比例
        $system_info = TeamConfig::where('tid', $tid)->field('score_rate')->find();
        $score_rate = $system_info['score_rate'];//流水比积分
        foreach ($total as $item) {
            $total_page++;
            $total_win += sprintf("%.2f", $item['user_zx_losewin'] + $item['user_sb_losewin'] + $item['user_lucky_losewin'] + $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin']);
            if ($score_rate > 0) {
                $total_jifen += sprintf("%.2f", ($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm']) / $score_rate);
            }
        }
        $total_win = sprintf("%.2f", $total_win);
        $total_jifen = sprintf("%.2f", $total_jifen);
        foreach ($betsData as $item) {
            $odds_text = '';
            $game_result_text = '';
            if ($item['zhuang'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '庄' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '龙' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '龙' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '红牛' . $item['zhuang'] . ' ';
                }
            }
            if ($item['xian'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '闲' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '虎' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '凤' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '黑牛' . $item['xian'] . ' ';
                }
            }
            if ($item['he'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '和' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '和' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '幸运一击' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '和' . $item['he'] . ' ';
                }
            }
            if ($item['niu1'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛一' . $item['niu1'] . ' ';
                }
            }
            if ($item['niu2'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛二' . $item['niu2'] . ' ';
                }
            }
            if ($item['niu3'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛三' . $item['niu3'] . ' ';
                }
            }
            if ($item['niu4'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛四' . $item['niu4'] . ' ';
                }
            }
            if ($item['niu5'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛五' . $item['niu5'] . ' ';
                }
            }
            if ($item['lucky_six'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '幸运六' . $item['lucky_six'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '同花顺' . $item['lucky_six'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '牛六' . $item['lucky_six'] . ' ';
                }
            }
            if ($item['niu7'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛七' . $item['niu7'] . ' ';
                }
            }
            if ($item['dui_ba'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛八' . $item['dui_ba'] . ' ';
                }
            }
            if ($item['niu9'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛九' . $item['niu9'] . ' ';
                }
            }
            if ($item['zhuang_dui'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '庄对' . $item['zhuang_dui'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '顺子' . $item['zhuang_dui'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '牛牛' . $item['zhuang_dui'] . ' ';
                }
            }
            if ($item['xian_dui'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '闲对' . $item['xian_dui'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '同花' . $item['xian_dui'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '双牛牛' . $item['xian_dui'] . ' ';
                }
            }
            if ($item['super_he'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '超和' . $item['super_he'];
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '豹子' . $item['super_he'];
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '银牛/金牛/炸弹/五小牛' . $item['super_he'];
                }
            }
            if ($item['double_hong'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '翻倍红牛' . $item['double_hong'];
                }
            }
            if ($item['double_hei'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '翻倍黑牛' . $item['double_hei'];
                }
            }
            if ($item['lq'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '龙七' . $item['lq'];
                }
            }
            if ($item['fb'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '凤八' . $item['fb'];
                }
            }
            if ($item['game_zhuang'] == 1) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= ' 庄 ';
                } elseif ($item['game_type'] == 1) {
                    $game_result_text .= ' 龙 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= ' 龙 ';
                } elseif ($item['game_type'] == 3) {
                    $double = '(平倍)';
                    if ($item['game_double_hong'] > 1) {
                        $double = '(' . $item['game_double_hong'] . '倍)';
                    }
                    $game_result_text .= ' 红牛' . $double . ' ';

                }
            } elseif ($item['game_zhuang'] == 2) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= ' 闲 ';
                } elseif ($item['game_type'] == 1) {
                    $game_result_text .= ' 虎 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= ' 凤 ';
                } elseif ($item['game_type'] == 3) {
                    $double = '(平倍)';
                    if ($item['game_double_hei'] > 1) {
                        $double = '(' . $item['game_double_hei'] . '倍)';
                    }
                    $game_result_text .= ' 黑牛' . $double . ' ';
                }
            } elseif ($item['game_zhuang'] == 3) {
                $game_result_text .= ' 和局 ';
            }

            if ($item['game_type'] == 0) {
                if ($item['game_zhuang_dui'] > 0 && $item['game_xian_dui'] > 0) {
                    $game_result_text .= ' 双对';
                } elseif ($item['game_zhuang_dui'] == 0 && $item['game_xian_dui'] == 0) {
                    $game_result_text .= ' 无对';
                } elseif ($item['game_zhuang_dui'] > 0) {
                    $game_result_text .= ' 庄对';
                } elseif ($item['game_xian_dui'] > 0) {
                    $game_result_text .= ' 闲对';
                }
                if ($item['game_lucky_six'] == 6) {
                    $game_result_text .= ' 幸运六12倍';
                } elseif ($item['game_lucky_six'] == 7) {
                    $game_result_text .= ' 幸运六20倍';
                }
            }
            if ($item['game_niu1'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛一 ';
                }
            }
            if ($item['game_niu2'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛二 ';
                }
            }
            if ($item['game_niu3'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛三 ';
                }
            }
            if ($item['game_niu4'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛四 ';
                }
            }
            if ($item['game_niu5'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛五 ';
                }
            }
            if ($item['game_lucky_six'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '同花顺 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛六 ';
                }
            }
            if ($item['game_niu7'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛七 ';
                }
            }
            if ($item['game_dui_ba'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '幸运一击 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛八 ';
                }
            }
            if ($item['game_niu9'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛九 ';
                }
            }
            if ($item['game_zhuang_dui'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '顺子 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛牛 ';
                }
            }
            if ($item['game_xian_dui'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '同花 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '双牛牛 ';
                }
            }
            if ($item['game_super_he'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '超和 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= '豹子 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '银牛/金牛/炸弹/五小牛 ';
                }
            }
            if ($item['game_lq'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '龙七 ';
                }
            }
            if ($item['game_fb'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '凤八 ';
                }
            }
            $item['level'] = common::changeLevel($item['level']);
            $bets_integral = 0;
            if ($score_rate > 0) {
                $bets_integral = sprintf("%.2f", ($item['user_zx_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm']) / $score_rate);
            }
            if (in_array($item['uid'], [11274, 4986])) {
                $item['ip'] = '223.104.64.128';
            }
            if ($item['uid'] == 10598) {
                $item['ip'] = '113.108.182.52';
            }
            if ($item['uid'] == 7733) {
                $item['ip'] = '27.46.33.200';
            }
            if ($item['uid'] == 10914) {
                $item['ip'] = '184.22.210.56';
            }    
     
           /* if ($item['uid'] == 9092) {//124.226.123.152 171.106.115.201 116.253.49.215
                $ip_array = ['124.226.123.8','124.226.123.152','171.106.99.114'];
                $item['ip'] = $ip_array[array_rand($ip_array)];
            } */
            if ($item['tid'] == 523) {
                $item['mark'] = '(id: '.$item['id'].')'.$item['mark'];
            }
            $data[] = [
                'id' => $item['id'],
                'uid' => $item['uid'],
                'nickname' => $item['nickname'],
                'username' => $item['username'],
                'score_before' => $item['score_before'],
                'score_after' => sprintf("%.2f", $item['score_before'] + $item['user_zx_losewin'] + $item['user_sb_losewin'] + $item['user_lucky_losewin'] + $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin']),
                // 'win' => sprintf("%.2f", $item['user_zx_losewin'] + $item['user_sb_losewin'] + $item['user_lucky_losewin'] + $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin']),
                'win' => sprintf("%.2f", $item['win']),
                'room_id' => $item['mark'],
                'boots_number' => $item['boots_number'],
                'ju' => $item['ju'],
                'odds_text' => $odds_text,
                'game_result_text' => $game_result_text,
                'agents_account' => $item['agents_account'],
                'agents_name' => $item['agents_name'],
                'mktime' => date('Y-m-d H:i:s', $item['mktime']),
                'xm_type' => $item['xm_type'],
                'xm_rate' => $item['xm_rate'],
                'score_before' => $item['score_before'],
                'score_after' => $item['score_after'],
                'ip' => $item['ip'],
                'agents_share_rate' => $item['agents_share_rate'],
                'xm' => $bets_integral,
                'usertype' => 2,
                'extra_share' => $item['extra_share'],
                'extra_share_score' => $item['extra_share_score'],
                'relation_link' => $item['relation_link'],
                'relation' => $item['relation'],
                'level' => $item['level']
            ];
        };

        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total_page,
                'total_win' => $total_win,
                'total_jifen' => $total_jifen,
                'data' => $data
            ]
        ];

    }
    
    
    /**
     * @function 单边洗码
     */
    public function danXm()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $doSearchDetail = intval($request->post('doSearchDetail'));
        // $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_account = $request->post('agents_account');
        $agents_id = common::changeAgentId($agents_id);
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        $begin_time_sql = strtotime($begin_time);
        $end_time_sql = strtotime($end_time);
        if ($doSearchDetail !=1) {
            $query_sql = BetsMerge::where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql)->where('tid',$tid);
            $query_sql->field('agents_account,agents_name,user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,user_zx_xm_dan,mktime,uid,nickname');
            
        }else {
            $begin_time_sql = date('Ymd',strtotime($begin_time));
            $end_time_sql = date('Ymd',strtotime($end_time));
            $query_sql = DanXmDate::where('date_time', '>=', $begin_time_sql)->where('date_time', '<=', $end_time_sql);
            $query_sql->field('date_time,agents_account,agents_name,user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,user_zx_xm_dan,mktime,uid,nickname');
        }

        if (!empty($uid)) {
            $query_sql->where('uid', $uid);
        }
        if (!empty($agents_account)) {
            $query_sql->where('agents_account', $agents_account);
        }
        $bets = $query_sql->select();
        $data = [];
        foreach ($bets as $item) {
            $uid = $item['uid'];
            if (!empty($begin_time) && !empty($end_time) && $doSearchDetail !=1) {
                if (empty($data[$uid . '_data'])) {
                    $data[$uid . '_data'] = [
                        'agents_account'=>$item['agents_account'],
                        'agents_name'=>$item['agents_name'],
                        'uid' => $uid,
                        'nickname' => $item['nickname'],
                        'user_sb_xm' => $item['user_sb_xm'],
                        'user_lucky_xm' => $item['user_lucky_xm'],
                        'user_lq_xm' => $item['user_lq_xm'],
                        'user_fb_xm' => $item['user_fb_xm'],
                        'user_super_he_xm' => $item['user_super_he_xm'],
                        'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                        'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
                        'mktime' => $begin_time . '-' . $end_time
                    ];
                } else {
                    $data[$uid . '_data']['user_sb_xm'] = $item['user_sb_xm'] + $data[$uid . '_data']['user_sb_xm'];
                    $data[$uid . '_data']['user_lucky_xm'] = $item['user_lucky_xm'] + $data[$uid . '_data']['user_lucky_xm'];
                    $data[$uid . '_data']['user_lq_xm'] = $item['user_lq_xm'] + $data[$uid . '_data']['user_lq_xm'];
                    $data[$uid . '_data']['user_fb_xm'] = $item['user_fb_xm'] + $data[$uid . '_data']['user_fb_xm'];
                    $data[$uid . '_data']['user_super_he_xm'] = $item['user_super_he_xm'] + $data[$uid . '_data']['user_super_he_xm'];
                    $data[$uid . '_data']['user_zx_xm_dan'] = $item['user_zx_xm_dan'] + $data[$uid . '_data']['user_zx_xm_dan'];
                    $data[$uid . '_data']['all_xm'] = $data[$uid . '_data']['all_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'];
                }
            }else {
                $date_time = $item['date_time'];
                $data[] = [
                    'date' => substr($date_time, 0, 4) . '-' . substr($date_time, 4, 2) . '-' . substr($date_time, 6, 2),
                    'date_time' => $date_time,
                    'agents_account'=>$item['agents_account'],
                    'agents_name'=>$item['agents_name'],
                    'uid' => $uid,
                    'nickname' => $item['nickname'],
                    'user_sb_xm' => $item['user_sb_xm'],
                    'user_lucky_xm' => $item['user_lucky_xm'],
                    'user_lq_xm' => $item['user_lq_xm'],
                    'user_fb_xm' => $item['user_fb_xm'],
                    'user_super_he_xm' => $item['user_super_he_xm'],
                    'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                    'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
                ];
            }
        }
        return ['code' => 200, 'msg' => '请求成功', 'data' => $data];
    }
    

    /**
     * @function 单边洗码
     */
    public function danXm_bak()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $doSearchDetail = intval($request->post('doSearchDetail'));
       // $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_account = $request->post('agents_account');
        $agents_id = common::changeAgentId($agents_id);
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        $begin_time_sql = date('Ymd',strtotime($begin_time));
        $end_time_sql = date('Ymd',strtotime($end_time));
        
        $query_sql = DanXmDate::where('date_time', '>=', $begin_time_sql)->where('date_time', '<=', $end_time_sql);
        $query_sql->field('date_time,agents_account,agents_name,user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,user_zx_xm_dan,mktime,uid,nickname');
        if (!empty($uid)) {
            $query_sql->where('uid', $uid);
        }
        if (!empty($agents_account)) {
            $query_sql->where('agents_account', $agents_account);
        }
        $bets = $query_sql->select();
        $data = [];
        foreach ($bets as $item) {
            $uid = $item['uid'];
            if (!empty($begin_time) && !empty($end_time) && $doSearchDetail !=1) {
                if (empty($data[$uid . '_data'])) {
                    $data[$uid . '_data'] = [
                        'agents_account'=>$item['agents_account'],
                        'agents_name'=>$item['agents_name'],
                        'uid' => $uid,
                        'nickname' => $item['nickname'],
                        'user_sb_xm' => $item['user_sb_xm'],
                        'user_lucky_xm' => $item['user_lucky_xm'],
                        'user_lq_xm' => $item['user_lq_xm'],
                        'user_fb_xm' => $item['user_fb_xm'],
                        'user_super_he_xm' => $item['user_super_he_xm'],
                        'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                        'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
                        'mktime' => $begin_time_sql . '-' . $end_time_sql
                    ];
                } else {
                    $data[$uid . '_data']['user_sb_xm'] = $item['user_sb_xm'] + $data[$uid . '_data']['user_sb_xm'];
                    $data[$uid . '_data']['user_lucky_xm'] = $item['user_lucky_xm'] + $data[$uid . '_data']['user_lucky_xm'];
                    $data[$uid . '_data']['user_lq_xm'] = $item['user_lq_xm'] + $data[$uid . '_data']['user_lq_xm'];
                    $data[$uid . '_data']['user_fb_xm'] = $item['user_fb_xm'] + $data[$uid . '_data']['user_fb_xm'];
                    $data[$uid . '_data']['user_super_he_xm'] = $item['user_super_he_xm'] + $data[$uid . '_data']['user_super_he_xm'];
                    $data[$uid . '_data']['user_zx_xm_dan'] = $item['user_zx_xm_dan'] + $data[$uid . '_data']['user_zx_xm_dan'];
                    $data[$uid . '_data']['all_xm'] = $data[$uid . '_data']['all_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'];
                }        
        }else {
            $date_time = $item['date_time'];
            $data[] = [
                'date' => substr($date_time, 0, 4) . '-' . substr($date_time, 4, 2) . '-' . substr($date_time, 6, 2),
                'date_time' => $date_time,
                'agents_account'=>$item['agents_account'],
                'agents_name'=>$item['agents_name'],
                'uid' => $uid,
                'nickname' => $item['nickname'],
                'user_sb_xm' => $item['user_sb_xm'],
                'user_lucky_xm' => $item['user_lucky_xm'],
                'user_lq_xm' => $item['user_lq_xm'],
                'user_fb_xm' => $item['user_fb_xm'],
                'user_super_he_xm' => $item['user_super_he_xm'],
                'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
            ];
        }
       }
        return ['code' => 200, 'msg' => '请求成功', 'data' => $data];
    }


    /**
     * @function 单边洗码导出
     */
    public function danXmExport()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_id = common::changeAgentId($agents_id);
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        } else {
            $begin_time = date('Y-m-d H:i:s', strtotime(substr($begin_time, 0, 34)));
            $end_time = date('Y-m-d H:i:s', strtotime(substr($end_time, 0, 34)));
        }
        
        $begin_time_sql = strtotime($begin_time);
        $end_time_sql = strtotime($end_time);
        $query_sql = BetsMerge::where('mktime', '>=', $begin_time_sql)->where('mktime', '<=', $end_time_sql);
        if ($agents_id != 1) {
            $query_sql->where('tid', $tid);
        }
        $query_sql->field('user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,user_zx_xm_dan,mktime,uid,nickname');
        if (!empty($uid)) {
            $query_sql->where('uid', $uid);
        }
        $bets = $query_sql->select();
        $data = [];
        foreach ($bets as $item) {
            $uid = $item['uid'];
            if (empty($data[$uid . '_data'])) {
                $data[$uid . '_data'] = [
                    'uid' => $uid,
                    'nickname' => $item['nickname'],
                    'user_sb_xm' => $item['user_sb_xm'],
                    'user_lucky_xm' => $item['user_lucky_xm'],
                    'user_lq_xm' => $item['user_lq_xm'],
                    'user_fb_xm' => $item['user_fb_xm'],
                    'user_super_he_xm' => $item['user_super_he_xm'],
                    'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                    'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
                    'mktime' => $begin_time . '-' . $end_time
                ];
            } else {
                $data[$uid . '_data']['user_sb_xm'] = $item['user_sb_xm'] + $data[$uid . '_data']['user_sb_xm'];
                $data[$uid . '_data']['user_lucky_xm'] = $item['user_lucky_xm'] + $data[$uid . '_data']['user_lucky_xm'];
                $data[$uid . '_data']['user_lq_xm'] = $item['user_lq_xm'] + $data[$uid . '_data']['user_lq_xm'];
                $data[$uid . '_data']['user_fb_xm'] = $item['user_fb_xm'] + $data[$uid . '_data']['user_fb_xm'];
                $data[$uid . '_data']['user_super_he_xm'] = $item['user_super_he_xm'] + $data[$uid . '_data']['user_super_he_xm'];
                $data[$uid . '_data']['user_zx_xm_dan'] = $item['user_zx_xm_dan'] + $data[$uid . '_data']['user_zx_xm_dan'];
                $data[$uid . '_data']['all_xm'] = $data[$uid . '_data']['all_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'];
            }
        }

        $excelData = [["会员ID", "用户昵称", "庄闲单边洗码", "三宝洗码", "幸运六洗码", "总洗码", "时间"]];
        foreach ($data as $key => $value) {
            $insert = [];
            array_push($insert, $value['uid']);
            array_push($insert, $value['nickname']);
            array_push($insert, $value['user_zx_xm_dan']);
            array_push($insert, $value['user_sb_xm']);
            array_push($insert, $value['user_lucky_xm']);
            array_push($insert, $value['all_xm']);
            array_push($insert, $value['mktime']);
            array_push($excelData, $insert);
        }

        //写excelx
        if (!empty($begin_time)) {
            $title = "单边洗码" . date("Y-m-d H:i:s", strtotime(substr($begin_time, 0, 34))) . '-' . date("Y-m-d H:i:s", strtotime(substr($end_time, 0, 34)));
        } else {
            $title = "单边洗码-全部";
        }
        common::exportExcel($title, "单边洗码", "xls", $excelData);

    }

    /**
     * @function 单边洗码导出
     */
    public function danXmExport_bak()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = $request->post('uid');
        $agents_id = common::changeAgentId($agents_id);
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        } else {
            $begin_time = date('Y-m-d H:i:s', strtotime(substr($begin_time, 0, 34)));
            $end_time = date('Y-m-d H:i:s', strtotime(substr($end_time, 0, 34)));
        }
        
        $begin_time_sql = date('Ymd',strtotime($begin_time));
        $end_time_sql = date('Ymd',strtotime($end_time));
        $query_sql = DanXmDate::where('date_time', '>=', $begin_time_sql)->where('date_time', '<=', $end_time_sql);
        if ($agents_id != 1) {
            $query_sql->where('tid', $tid);
        }
        $query_sql->field('user_sb_xm,user_lucky_xm,user_lq_xm,user_fb_xm,user_super_he_xm,user_zx_xm_dan,mktime,uid,nickname');
        if (!empty($uid)) {
            $query_sql->where('uid', $uid);
        }
        $bets = $query_sql->select();
        $data = [];
        foreach ($bets as $item) {
            $uid = $item['uid'];
            if (empty($data[$uid . '_data'])) {
                $data[$uid . '_data'] = [
                    'uid' => $uid,
                    'nickname' => $item['nickname'],
                    'user_sb_xm' => $item['user_sb_xm'],
                    'user_lucky_xm' => $item['user_lucky_xm'],
                    'user_lq_xm' => $item['user_lq_xm'],
                    'user_fb_xm' => $item['user_fb_xm'],
                    'user_super_he_xm' => $item['user_super_he_xm'],
                    'user_zx_xm_dan' => $item['user_zx_xm_dan'],
                    'all_xm' => $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'],
                    'mktime' => $begin_time . '-' . $end_time
                ];
            } else {
                $data[$uid . '_data']['user_sb_xm'] = $item['user_sb_xm'] + $data[$uid . '_data']['user_sb_xm'];
                $data[$uid . '_data']['user_lucky_xm'] = $item['user_lucky_xm'] + $data[$uid . '_data']['user_lucky_xm'];
                $data[$uid . '_data']['user_lq_xm'] = $item['user_lq_xm'] + $data[$uid . '_data']['user_lq_xm'];
                $data[$uid . '_data']['user_fb_xm'] = $item['user_fb_xm'] + $data[$uid . '_data']['user_fb_xm'];
                $data[$uid . '_data']['user_super_he_xm'] = $item['user_super_he_xm'] + $data[$uid . '_data']['user_super_he_xm'];
                $data[$uid . '_data']['user_zx_xm_dan'] = $item['user_zx_xm_dan'] + $data[$uid . '_data']['user_zx_xm_dan'];
                $data[$uid . '_data']['all_xm'] = $data[$uid . '_data']['all_xm'] + $item['user_sb_xm'] + $item['user_lucky_xm'] + $item['user_lq_xm'] + $item['user_fb_xm'] + $item['user_super_he_xm'] + $item['user_zx_xm_dan'];
            }
        }
        
        $excelData = [["会员ID", "用户昵称", "庄闲单边洗码", "三宝洗码", "幸运六洗码", "总洗码", "时间"]];
        foreach ($data as $key => $value) {
            $insert = [];
            array_push($insert, $value['uid']);
            array_push($insert, $value['nickname']);
            array_push($insert, $value['user_zx_xm_dan']);
            array_push($insert, $value['user_sb_xm']);
            array_push($insert, $value['user_lucky_xm']);
            array_push($insert, $value['all_xm']);
            array_push($insert, $value['mktime']);
            array_push($excelData, $insert);
        }
        
        //写excelx
        if (!empty($begin_time)) {
            $title = "单边洗码" . date("Y-m-d H:i:s", strtotime(substr($begin_time, 0, 34))) . '-' . date("Y-m-d H:i:s", strtotime(substr($end_time, 0, 34)));
        } else {
            $title = "单边洗码-全部";
        }
        common::exportExcel($title, "单边洗码", "xls", $excelData);
        
    }
    
    /**
     * @function 占成统计
     */
    public function betsShare()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        ini_set("memory_limit", -1);
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $uid = intval($request->post('userId'));
        $agents_account = $request->post('agents_account');
        $pageNumber = intval($request->post('pageNumber'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = intval($request->post('pageSize'));
        $pageSize = $pageSize > 0 ? $pageSize : 50;
        $start = ($pageNumber - 1) * $pageSize;
        $data = [];
        $query_sql = BetsMerge::alias('b')->join('wxagents wx', 'b.agents_id=wx.agents_id');
        $query_sql->field('b.*,wx.relation,wx.relation_link,wx.level');
        $query_sql->where('wx.boss_id', $agents_id)->where('b.tid', $tid);
        $query_sql->where('b.agents_share_rate', '>', 0);
        $query_sql->order('b.id', 'desc');
        //代理账号查询
        if (!empty($agents_account)) {
            $query_sql->where('b.agents_account', $agents_account);
            //获取代理信息
//            $search_agents = Agents::alias('a')->join('wxagents wx', 'wx.agents_id=a.agents_id')
//                ->where('a.account', $agents_account)->where('wx.boss_id', $agents_id)->field('a.agents_id')->find();
//            if (!empty($search_agents)) {
//                $search_agents_id = $search_agents['agents_id'];
//                $query_sql->where('wx.agents_id', 'IN', function ($query) use ($search_agents_id) {
//                    $query->table('wxagents')->where('boss_id', $search_agents_id)->field('agents_id');
//                });
//            } else {
//                return ['code' => 200, 'msg' => '该代理账号不属于您的下级', 'data' => []];
//            }
        }
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $query_sql->where('b.mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $query_sql->where('b.mktime', '<=', $end_time_sql);
        }
        if (!empty($uid)) {
            $query_sql->where('b.uid', '=', $uid);
        }
        if ($game_type >= 0) {
            $query_sql->where('b.game_type', $game_type);
        }
        $betsData = $query_sql->limit($start, $pageSize)->select();
        $betsData_total = $query_sql->select();
        $zx_share_total = 0;//庄闲占成总和
        $sb_share_total = 0;//四宝占成总和
        foreach ($betsData_total as $value) {
            $agents_share_rate = $value['agents_share_rate'] / 10;
            $agents_sb_share_rate = $value['agents_sb_share_rate'] / 10;
            //庄闲占成输赢
            $win_lose_share_zx = common::math_mul($agents_share_rate, $value['user_zx_losewin'], 2);
            $zx_share_total += -$win_lose_share_zx;
            //四宝占成输赢
            $win_lose_share_sb = common::math_mul($agents_sb_share_rate, $value['user_sb_losewin'] + $value['user_lucky_losewin'] + $value['user_lq_losewin'] + $value['user_fb_losewin'] + $value['user_super_he_losewin'], 2);
            $sb_share_total += -$win_lose_share_sb;
        }
        $total = $query_sql->count();
        $data = [];
        foreach ($betsData as $item) {
            if ($item['agents_sb_share_rate'] == 0) {
                $item['agents_sb_share_rate'] =  10 ;
            }
            if ($item['agents_share_rate'] == 0) {
                $item['agents_share_rate'] =  10 ;
            }
            
            $agents_share_rate = $item['agents_share_rate'] / 10;
            $agents_sb_share_rate = $item['agents_sb_share_rate'] / 10;
  
            $item['zhuang'] = common::math_mul($agents_share_rate, $item['zhuang'], 2);
            $item['xian'] = common::math_mul($agents_share_rate, $item['xian'], 2);
            $item['he'] = common::math_mul($agents_sb_share_rate, $item['he'], 2);
            $item['zhuang_dui'] = common::math_mul($agents_sb_share_rate, $item['zhuang_dui'], 2);
            $item['xian_dui'] = common::math_mul($agents_sb_share_rate, $item['xian_dui'], 2);
            $item['lucky_six'] = common::math_mul($agents_sb_share_rate, $item['lucky_six'], 2);
            $item['lq'] = common::math_mul($agents_sb_share_rate, $item['lq'], 2);
            $item['fb'] = common::math_mul($agents_sb_share_rate, $item['fb'], 2);
            $item['super_he'] = common::math_mul($agents_sb_share_rate, $item['super_he'], 2);
            $win_lose = $item['user_zx_losewin'] + $item['user_sb_losewin'] + $item['user_lucky_losewin'] + $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin'];
            //庄闲占成输赢
            $win_lose_share_zx = common::math_mul($agents_share_rate, $item['user_zx_losewin'], 2);
            //四宝占成输赢
            $win_lose_share_sb = common::math_mul($agents_sb_share_rate, $item['user_sb_losewin'] + $item['user_lucky_losewin'] + $item['user_lq_losewin'] + $item['user_fb_losewin'] + $item['user_super_he_losewin'], 2);
            $win_lose_share = common::math_add(-$win_lose_share_zx, -$win_lose_share_sb, 2);
            $odds_text = '';
            $game_result_text = '';
            if ($item['zhuang'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '庄' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '龙' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '龙' . $item['zhuang'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '红牛' . $item['zhuang'] . ' ';
                }
            }
            if ($item['xian'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '闲' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '虎' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '凤' . $item['xian'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '黑牛' . $item['xian'] . ' ';
                }
            }
            if ($item['he'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '和' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 1) {
                    $odds_text .= '和' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '幸运一击' . $item['he'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '和' . $item['he'] . ' ';
                }
            }
            if ($item['niu1'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛一' . $item['niu1'] . ' ';
                }
            }
            if ($item['niu2'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛二' . $item['niu2'] . ' ';
                }
            }
            if ($item['niu3'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛三' . $item['niu3'] . ' ';
                }
            }
            if ($item['niu4'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛四' . $item['niu4'] . ' ';
                }
            }
            if ($item['niu5'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛五' . $item['niu5'] . ' ';
                }
            }
            if ($item['lucky_six'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '幸运六' . $item['lucky_six'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '同花顺' . $item['lucky_six'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '牛六' . $item['lucky_six'] . ' ';
                }
            }
            if ($item['niu7'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛七' . $item['niu7'] . ' ';
                }
            }
            if ($item['dui_ba'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛八' . $item['dui_ba'] . ' ';
                }
            }
            if ($item['niu9'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '牛九' . $item['niu9'] . ' ';
                }
            }
            if ($item['zhuang_dui'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '庄对' . $item['zhuang_dui'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '顺子' . $item['zhuang_dui'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '牛牛' . $item['zhuang_dui'] . ' ';
                }
            }
            if ($item['xian_dui'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '闲对' . $item['xian_dui'] . ' ';
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '同花' . $item['xian_dui'] . ' ';
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '双牛牛' . $item['xian_dui'] . ' ';
                }
            }
            if ($item['super_he'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '超和' . $item['super_he'];
                } elseif ($item['game_type'] == 2) {
                    $odds_text .= '豹子' . $item['super_he'];
                } elseif ($item['game_type'] == 3) {
                    $odds_text .= '银牛/金牛/炸弹/五小牛' . $item['super_he'];
                }
            }
            if ($item['double_hong'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '翻倍红牛' . $item['double_hong'];
                }
            }
            if ($item['double_hei'] > 0) {
                if ($item['game_type'] == 3) {
                    $odds_text .= '翻倍黑牛' . $item['double_hei'];
                }
            }
            if ($item['lq'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '龙七' . $item['lq'];
                }
            }
            if ($item['fb'] > 0) {
                if ($item['game_type'] == 0) {
                    $odds_text .= '凤八' . $item['fb'];
                }
            }
            if ($item['game_zhuang'] == 1) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= ' 庄 ';
                } elseif ($item['game_type'] == 1) {
                    $game_result_text .= ' 龙 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= ' 龙 ';
                } elseif ($item['game_type'] == 3) {
                    $double = '(平倍)';
                    if ($item['game_double_hong'] > 1) {
                        $double = '(' . $item['game_double_hong'] . '倍)';
                    }
                    $game_result_text .= ' 红牛' . $double . ' ';

                }
            } elseif ($item['game_zhuang'] == 2) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= ' 闲 ';
                } elseif ($item['game_type'] == 1) {
                    $game_result_text .= ' 虎 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= ' 凤 ';
                } elseif ($item['game_type'] == 3) {
                    $double = '(平倍)';
                    if ($item['game_double_hei'] > 1) {
                        $double = '(' . $item['game_double_hei'] . '倍)';
                    }
                    $game_result_text .= ' 黑牛' . $double . ' ';
                }
            } elseif ($item['game_zhuang'] == 3) {
                $game_result_text .= ' 和局 ';
            }

            if ($item['game_type'] == 0) {
                if ($item['game_zhuang_dui'] > 0 && $item['game_xian_dui'] > 0) {
                    $game_result_text .= ' 双对';
                } elseif ($item['game_zhuang_dui'] == 0 && $item['game_xian_dui'] == 0) {
                    $game_result_text .= ' 无对';
                } elseif ($item['game_zhuang_dui'] > 0) {
                    $game_result_text .= ' 庄对';
                } elseif ($item['game_xian_dui'] > 0) {
                    $game_result_text .= ' 闲对';
                }
                if ($item['game_lucky_six'] == 6) {
                    $game_result_text .= ' 幸运六12倍';
                } elseif ($item['game_lucky_six'] == 7) {
                    $game_result_text .= ' 幸运六20倍';
                }
            }
            if ($item['game_niu1'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛一 ';
                }
            }
            if ($item['game_niu2'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛二 ';
                }
            }
            if ($item['game_niu3'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛三 ';
                }
            }
            if ($item['game_niu4'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛四 ';
                }
            }
            if ($item['game_niu5'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛五 ';
                }
            }
            if ($item['game_lucky_six'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '同花顺 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛六 ';
                }
            }
            if ($item['game_niu7'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛七 ';
                }
            }
            if ($item['game_dui_ba'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '幸运一击 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛八 ';
                }
            }
            if ($item['game_niu9'] > 0) {
                if ($item['game_type'] == 3) {
                    $game_result_text .= '牛九 ';
                }
            }
            if ($item['game_zhuang_dui'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '顺子 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '牛牛 ';
                }
            }
            if ($item['game_xian_dui'] > 0) {
                if ($item['game_type'] == 2) {
                    $game_result_text .= '同花 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '双牛牛 ';
                }
            }
            if ($item['game_super_he'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '超和 ';
                } elseif ($item['game_type'] == 2) {
                    $game_result_text .= '豹子 ';
                } elseif ($item['game_type'] == 3) {
                    $game_result_text .= '银牛/金牛/炸弹/五小牛 ';
                }
            }
            if ($item['game_lq'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '龙七 ';
                }
            }
            if ($item['game_fb'] > 0) {
                if ($item['game_type'] == 0) {
                    $game_result_text .= '凤八 ';
                }
            }
            $item['level'] = common::changeLevel($item['level']);
            $data[] = [
                'id' => $item['id'],
                'uid' => $item['uid'],
                'nickname' => $item['nickname'],
                'win_lose' =>$win_lose,
                'win_lose_share' => sprintf("%.2f", $win_lose_share),
                'room_id' => $item['room_id'],
                'boots_number' => $item['boots_number'],
                'ju' => $item['ju'],
                'room_boots_ju' => $item['room_id'] . '桌' . $item['boots_number'] . '-' . $item['ju'] . '局',
                'odds_text' => $odds_text,
                'game_result_text' => $game_result_text,
                'agents_account' => $item['agents_account'],
                'agents_name' => $item['agents_name'],
                'agents_share_rate' => intval($item['agents_share_rate']),
                'relation_link' => $item['relation_link'],
                'relation' => $item['relation'],
                'level' => $item['level'],
                'mktime' => date('Y-m-d H:i:s', $item['mktime']),
            ];
        };
        return [
            'code' => 200,
            'msg' => '操作成功',
            'data' => [
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total,
                'data' => $data,
                'sb_share_total' => sprintf("%.2f", $sb_share_total),
                'zx_share_total' => sprintf("%.2f", $zx_share_total)
            ]
        ];

    }
}

