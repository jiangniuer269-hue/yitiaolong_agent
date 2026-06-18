<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\9\15 0015
 * Time: 23:09
 */

namespace app\index\controller;

use app\index\model\Agents;
use app\index\model\BetsLog;
use app\index\model\BetsMerge;
use app\index\model\CardGame;
use app\index\model\CardGameReport;
use app\index\model\LhCardGame;
use app\index\model\LhCardGameReport;
use app\index\model\teamroom;
use app\index\model\Wxagents;
use think\Db;
use think\facade\Request;
use app\index\common;
use app\index\model\ZjhCardGame;
use app\index\model\NnCardGame;

class GameManage
{
    public function __construct()
    {
        common::checkLogin();
    }

    /**
     * @function 牌局记录
     *
     * @return \think\response\View
     */
    public function gameList()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $begin_time = $request->post('begin_time');
        $end_time = $request->post('end_time');
        $boots_number = $request->post('boots_number');
        $ju = $request->post('ju');
        $room_id = $request->post('room_id');
        $pageNumber = intval($request->post('pageNumber'));
        $pageSize = intval($request->post('pageSize'));
        $pageNumber = $pageNumber > 0 ? $pageNumber : 1;
        $pageSize = $pageSize > 0 ? $pageSize : 30;
        $start = ($pageNumber - 1) * $pageSize;
        $report_sql = CardGameReport::field('tm,tmyk,khyk,sb_total,sbyk,luckysix_yk,zxyk_zc');       
        $sql = CardGame::alias('c')->join('team_room t', 'c.groupid=t.groupid');
        if ($game_type == 0) {
            $sql = CardGame::alias('c')->join('team_room t', 'c.groupid=t.groupid');
        } elseif ($game_type == 1) {
            $sql = LhCardGame::alias('c')->join('team_room t', 'c.groupid=t.groupid');
        } elseif ($game_type == 2) {
            $sql = ZjhCardGame::alias('c')->join('team_room t', 'c.groupid=t.groupid');
        } elseif ($game_type == 3) {
            $sql = NnCardGame::alias('c')->join('team_room t', 'c.groupid=t.groupid');
        }
        if ($game_type == 0) {
            $sql->field('t.mark,c.state,c.id,c.room_id,c.boots_number,c.ju,c.mktime,c.text,c.zhuang,c.zhuang_dui,c.xian_dui,c.lucky_six,c.lq,c.fb,c.super_he');
        } else {
            $sql->field('t.mark,c.state,c.id,c.room_id,c.boots_number,c.ju,c.mktime,c.text,c.zhuang,c.zhuang_dui,c.xian_dui,c.lucky_six');
        }
        $sql->order('c.id', 'desc')->where('c.tid', $tid);
        if (intval($ju) > 0) {
            $sql->where('c.ju', $ju);
            $report_sql->where('ju',$ju);
        }
        if ($boots_number > 0) {
            $sql->where('c.boots_number', $boots_number);
            $report_sql->where('boots_number',$boots_number);
        }
        if (!empty($room_id)) {
            $sql->where('c.room_id', $room_id);
            $report_sql->where('room_id',$room_id);
        }
        //默认显示本周的数据
        if (empty($begin_time) && empty($end_time)) {
            $getTime = common::getWeek();
            $begin_time = $getTime[0]['date'];
            $end_time = $getTime[6]['date'];
        }
        if (!empty($begin_time)) {
            $begin_time_sql = strtotime($begin_time);
            $sql->where('c.mktime', '>=', $begin_time_sql);
            $report_sql->where('mktime', '>=', $begin_time_sql);
        }
        if (!empty($end_time)) {
            $end_time_sql = strtotime($end_time);
            $sql->where('c.mktime', '<=', $end_time_sql);
            $report_sql->where('mktime', '<=', $end_time_sql);
        }
        $reportData = $report_sql->select();
        $gameData = $sql->whereIn('c.state',[2,3])->limit($start, $pageSize)->select();
        $total = $sql->count();
        $data = [];
        $report = [];
        $total_tm = 0;
        $total_tmyk = 0;
        $total_khyk = 0;
        $total_sb_total = 0;
        $total_sbyk = 0;
        $total_zxyk_zc = 0;
        foreach ($reportData as $item_report){
            $total_tm += $item_report['tm'];
            $total_tmyk += $item_report['tmyk'];
            $total_khyk += $item_report['khyk']-$item_report['zxyk_zc'];
            $total_sb_total += $item_report['sb_total'];
            $total_sbyk += $item_report['sbyk'];
        }
        foreach ($gameData as $item) {
            $tm = 0;
            $tmyk = 0;
            $khyk = 0;
            $sb_total = 0;
            $sbyk = 0;
            $zxyk_zc = 0;
            if ($game_type == 0) {
                $report = CardGameReport::where('card_game_id', $item['id'])->field('tm,tmyk,khyk,sb_total,sbyk,luckysix_yk,zxyk_zc')->find();
            } elseif ($game_type == 1) {
                $report = LhCardGameReport::where('card_game_id', $item['id'])->field('tm,tmyk,khyk,sb_total,sbyk,luckysix_yk,zxyk_zc')->find();
            }
            if (!empty($report)) {
                $tm = $report['tm'];
                $tmyk = $report['tmyk'];
                $zxyk_zc = $report['zxyk_zc'];
                $khyk = $report['khyk'] - $report['zxyk_zc'];
                $sb_total = $report['sb_total'];
                $sbyk = $report['sbyk']+ $report['luckysix_yk'];
            }

            $banker = '';
            $player = '';
            $card_game_text = json_decode($item['text'], true);
            if(empty($card_game_text['zhuang_dian']) ||  empty($card_game_text['xian_dian'])){
                $card_game_text['zhuang_dian'] = '';
                $card_game_text['xian_dian'] = '';
            }
            if ($item['state'] == 3) {
                $card_game_text['win_msg'] = '取消此局';
                $card_game_text['zhuang_dian'] = '';
                $card_game_text['xian_dian'] = '';
            }
            if ($item['state'] < 2) {
                $card_game_text['win_msg'] = '未结算';
                $card_game_text['zhuang_dian'] = '';
                $card_game_text['xian_dian'] = '';
            }
           /* if (!empty($card_game_text['p1']) && !empty($card_game_text['p2'])) {
                //闲牌
                if ($game_type == 0) {
                    $player = $this->get_pai($card_game_text['p1']) . '+' . $this->get_pai($card_game_text['p2']);
                    if ($card_game_text['p5'] > 0) {
                        $player .= '+' . $this->get_pai($card_game_text['p5']);
                    }
                    //庄牌
                    $banker = $this->get_pai($card_game_text['p3']) . '+' . $this->get_pai($card_game_text['p4']);
                    if ($card_game_text['p6'] > 0) {
                        $banker .= '+' . $this->get_pai($card_game_text['p6']);
                    }
                } elseif ($game_type == 1) {
                    $player = $this->get_pai($card_game_text['p2']);
                    $banker = $this->get_pai($card_game_text['p1']);
                } elseif ($game_type == 2) {
                    $player = $this->get_pai($card_game_text['p1']) . '+' . $this->get_pai($card_game_text['p2']) . '+' . $this->get_pai($card_game_text['p5']);
                    $banker = $this->get_pai($card_game_text['p3']) . '+' . $this->get_pai($card_game_text['p4']) . '+' . $this->get_pai($card_game_text['p6']);
                } elseif ($game_type == 3) {
                    $player = $this->get_pai($card_game_text['p1']) . '+' . $this->get_pai($card_game_text['p2']) . '+' . $this->get_pai($card_game_text['p3']) . '+' . $this->get_pai($card_game_text['p4']) . '+' . $this->get_pai($card_game_text['p5']);
                    $banker = $this->get_pai($card_game_text['p6']) . '+' . $this->get_pai($card_game_text['p7']) . '+' . $this->get_pai($card_game_text['p8']) . '+' . $this->get_pai($card_game_text['p9']) . '+' . $this->get_pai($card_game_text['p10']);
                }
            }*/

           /* if ($game_type == 2 or $game_type == 3) {
                $card_game_text['zhuang_dian'] = $card_game_text['l_msg'];
                $card_game_text['xian_dian'] = $card_game_text['f_msg'];
            }*/

            $data[] = [
                'card_game_id' => $item['id'],
                'room_id' => $item['room_id'],
                'groupid' => $item['room_id'],
                'boots_number' => $item['boots_number'],
                'ju' => $item['ju'],
                'game_result' => $card_game_text['win_msg'],
                'mktime' => date('Y-m-d H:i:s', $item['mktime']),
                'zhuang_dian' => $card_game_text['zhuang_dian'],
                'xian_dian' => $card_game_text['xian_dian'],
                'player' => $banker,
                'banker' => $player,
                'zhuang' => $item['zhuang'],
                'zhuang_dui' => $item['zhuang_dui'],
                'xian_dui' => $item['xian_dui'],
                'lucky_six' => $item['lucky_six'],
                'lq' => $item['lq'],
                'fb' => $item['fb'],
                'super_he' => $item['super_he'],
                'tm' => $tm,
                'tmyk' => -$tmyk,
                'khyk' => sprintf("%.2f", $khyk),
                'zxyk_zc'=> sprintf("%.2f", $zxyk_zc),
                'sb_total' => $sb_total,
                'sbyk' => $sbyk,
                'state' =>$item['state'],
                'mark'=>$item['mark'],     
            ];
        }
        return ['code' => 200, 'msg' => '操作成功', 'data' =>
            [
                'data' => $data,
                'pageNumber' => $pageNumber,
                'pageSize' => $pageSize,
                'total' => $total,
                'total_tm'=>sprintf("%.2f",$total_tm),
                'total_tmyk'=>sprintf("%.2f",$total_tmyk),
                'total_khyk'=>sprintf("%.2f",$total_khyk),
                'total_sb_total'=>sprintf("%.2f",$total_sb_total),
                'total_sbyk'=>sprintf("%.2f",$total_sbyk),
                'total_zxyk_zc'=>sprintf("%.2f",$total_zxyk_zc),  
            ]
        ];
    }


    /**
     * @function 获取聊天内容
     */
    public function getChatContent()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $cur_agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $game_type = intval($request->post('game_type'));
        $card_game_id = $request->post('card_game_id');
        $db_name = 'chat_packet';
        if ($game_type == 0) {
            $db_name = 'chat_packet';
        } elseif ($game_type == 1) {
            $db_name = 'lh_chat_packet';
        } elseif ($game_type == 2) {
            $db_name = 'zjh_chat_packet';
        } elseif ($game_type == 3) {
            $db_name = 'nn_chat_packet';
        }
        $data = Db::name($db_name)->where('card_game_id', $card_game_id)
            ->field('createtime,fromuid,fromuser,message,msgtype,id,only_uid,group_info,groupid')
            ->where('tid', $tid)
            ->order('id', 'desc')->select();
        $message = [];
        foreach ($data as $item) {
            $fromuser = json_decode($item['fromuser'], true);
            $message[] = [
                'fromuser' => [
                    'nickname' => !empty($fromuser['name']) ? $fromuser['name'] : '',
                    'headimage' => !empty($fromuser['head']) ? $fromuser['head'] : '',
                    'score' => !empty($fromuser['score']) ? $fromuser['score'] : 0,
                ],
                'groupid' => $item['groupid'],
                'fromuid' => $item['fromuid'],
                'createtime' => date('m-d H:i', $item['createtime']),
                'msgtype' => $item['msgtype'],
                'msg' => $item['message'],
                'id' => $item['id']
            ];
        }
        return ['code' => 200, 'msg' => '操作成功', 'data' => $message];
    }

    /**
     * @function 获取牌型
     */
    function get_pai($pai)
    {
        $dian = $pai % 13;
        if ($dian > 10) {
            if ($dian == 11) {
                return 'J';
            } elseif ($dian == 12) {
                return 'Q';
            }
        } else {
            if ($dian == 0) {
                return 'K';
            } else {
                return $dian;
            }
        }
    }

    /**
     * @function 下注类型
     *
     * @param $type
     * @return string
     */
    public function getOddsType($type)
    {
        switch ($type) {
            case 1:
                return '庄';
            case 2:
                return '闲';
            case 3:
                return '和';
            case 4:
                return '庄对';
            case 5:
                return '闲对';
            case 7:
                return '幸运六';
        }
    }


    /**
     * @function 删除牌局
     */
    public function gameDelete()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
//        $agents_id = common::changeAgentId($agents_id);
//        if ($agents_id != 1) {
//            return ['code' => 500, 'msg' => '没有权限操作'];
//        }
        $request = Request::instance();
        $card_game_id = $request->post('card_game_id');
        $game_type = $request->post('game_type');
        if ($game_type == 0) {
            CardGame::destroy(['id' => $card_game_id, 'tid' => $tid]);
        } elseif ($game_type == 1) {
            LhCardGame::destroy(['id' => $card_game_id, 'tid' => $tid]);
        } elseif ($game_type == 2) {
            ZjhCardGame::destroy(['id' => $card_game_id, 'tid' => $tid]);
        }

        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function 修改牌局
     */
    public function gameUpdate()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::changeAgentId($agents_id);
        $request = Request::instance();
        $card_game_id = $request->post('card_game_id');
        $game_type = $request->post('game_type');
        $zhuang = intval($request->post('zhuang'));
        $zhuang_dui = intval($request->post('zhuang_dui'));
        $xian_dui = intval($request->post('xian_dui'));
        $lucky_six = intval($request->post('lucky_six'));
        $update_data = [
            'zhuang' => $zhuang,
            'zhuang_dui' => $zhuang_dui,
            'xian_dui' => $xian_dui,
            'lucky_six' => $lucky_six
        ];
        $win_msg = '';
        if ($game_type == 0) {
            if ($zhuang == 1) {
                $win_msg .= '庄赢';
            } elseif ($zhuang == 2) {
                $win_msg .= '闲赢';
            } elseif ($zhuang == 3) {
                $win_msg .= '和局';
            }
            if ($zhuang_dui == 1 && $xian_dui == 1) {
                $win_msg .= '双对';
            } elseif ($zhuang_dui == 1 && $xian_dui == 0) {
                $win_msg .= '庄对';
            } elseif ($zhuang_dui == 0 && $xian_dui == 1) {
                $win_msg .= '闲对';
            } else {
                $win_msg .= '无对';
            }
            if ($lucky_six == 6) {
                $win_msg .= '幸运六12倍';
            } elseif ($lucky_six == 7) {
                $win_msg .= '幸运六20倍';
            }
            $update_data['text'] = json_encode(['win_msg' => $win_msg, 'zhuang_dian' => 0, 'xian_dian' => 0]);
            CardGame::where('id', $card_game_id)->update($update_data);
        } elseif ($game_type == 1) {
            if ($zhuang == 1) {
                $win_msg .= '龙赢';
            } elseif ($zhuang == 2) {
                $win_msg .= '虎赢';
            } elseif ($zhuang == 3) {
                $win_msg .= '和局';
            }
            $update_data['text'] = json_encode(['win_msg' => $win_msg, 'zhuang_dian' => 0, 'xian_dian' => 0]);
            LhCardGame::where('id', $card_game_id)->where('tid', $tid)->update($update_data);
        }

        return ['code' => 200, 'msg' => '修改成功'];
    }

    /**
     * @function 增加牌局
     */

    public function gameAdd()
    {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $agents_id = common::checkLogin();
        if (is_array($agents_id)) {
            return ['code' => 400, 'msg' => '登录失效'];
        }
        $agents_id = common::changeAgentId($agents_id);
        if ($agents_id != 1) {
            return ['code' => 500, 'msg' => '没有权限操作'];
        }
        $request = Request::instance();
        $game_type = $request->post('game_type');
        $zhuang = intval($request->post('zhuang'));
        $zhuang_dui = intval($request->post('zhuang_dui'));
        $xian_dui = intval($request->post('xian_dui'));
        $lucky_six = intval($request->post('lucky_six'));
        $room_id = intval($request->post('room_id'));
        $boots_number = intval($request->post('boots_number'));
        $ju = intval($request->post('ju'));
        $now_time = time();
        $insert_data = [
            'room_id' => $room_id,
            'boots_number' => $boots_number,
            'ju' => $ju,
            'zhuang' => $zhuang,
            'zhuang_dui' => $zhuang_dui,
            'xian_dui' => $xian_dui,
            'lucky_six' => $lucky_six,
            'uptime' => $now_time,
            'mktime' => $now_time,
            'state' => 2,
            'groupid' => $room_id,
            'ukey' => $room_id . '_' . $boots_number . '_' . $ju . '_' . $game_type . '-' . time(),
            'tid' => $tid
        ];
        $win_msg = '';
        if ($game_type == 0) {
            if ($zhuang == 1) {
                $win_msg .= '庄赢';
            } elseif ($zhuang == 2) {
                $win_msg .= '闲赢';
            } elseif ($zhuang == 3) {
                $win_msg .= '和局';
            }
            if ($zhuang_dui == 1 && $xian_dui == 1) {
                $win_msg .= '双对';
            } elseif ($zhuang_dui == 1 && $xian_dui == 0) {
                $win_msg .= '庄对';
            } elseif ($zhuang_dui == 0 && $xian_dui == 1) {
                $win_msg .= '闲对';
            } else {
                $win_msg .= '无对';
            }
            if ($lucky_six == 6) {
                $win_msg .= '幸运六12倍';
            } elseif ($lucky_six == 7) {
                $win_msg .= '幸运六20倍';
            }
            $insert_data['text'] = json_encode(['win_msg' => $win_msg, 'zhuang_dian' => 0, 'xian_dian' => 0]);
            CardGame::create($insert_data);
        } elseif ($game_type == 1) {
            if ($zhuang == 1) {
                $win_msg .= '龙赢';
            } elseif ($zhuang == 2) {
                $win_msg .= '虎赢';
            } elseif ($zhuang == 3) {
                $win_msg .= '和局';
            }
            $insert_data['text'] = json_encode(['win_msg' => $win_msg, 'zhuang_dian' => 0, 'xian_dian' => 0]);
            LhCardGame::create($insert_data);
        }

        return ['code' => 200, 'msg' => '操作成功'];
    }

    /**
     * @function 删除聊天记录
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function deletechatmsg()
    {
        
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $game_type = $request->post('game_type');
        $msg_id = $request->post('msg_id');
        $db_name = NULL;
        if ($game_type == 0) {
            $db_name = 'chat_packet';
        } elseif ($game_type == 1) {
            $db_name = 'lh_chat_packet';
        } elseif ($game_type == 2) {
            $db_name = 'zjh_chat_packet';
        } elseif ($game_type == -1) {
            $db_name = 'chat_packet';
        } else if ($game_type == 3) {
            $db_name = 'nn_chat_packet';
        }

        if (!empty($db_name)) {
            $chat_log = Db::name($db_name)->where('id', $msg_id)->where('tid', $tid)->delete();
            return ['code' => 200, 'msg' => '操作成功'];
        }
        return ['code' => 500, 'msg' => '操作失败'];
    }
    
    /**
     * 
     * @function 取消此局
     */
    public function cancelGame() {
        $loginData = common::checkLogin();
        if ($loginData['code'] == 400) {
            return ['code' => 400, 'msg' => '登录失效'];
        } else {
            $agents_id = $loginData['data']['agents_id'];
            $tid = $loginData['data']['tid'];
        }
        $request = Request::instance();
        $card_game_id = $request->post('card_game_id');
        $data = CardGame::where('id',$card_game_id)->field('id,state,tid')->find();
        if ($data['state'] != 3) {
            CardGame::where('id',$card_game_id)->update(['state'=>3]);
            $note = '牌局ID' . $card_game_id . '取消此局，数据处理异常';
            common::system_log($agents_id, '系统错误日志', 0, $note, '', '', $tid);
        }
        BetsLog::where('card_game_id',$card_game_id)->update(['state'=>3]);
        return ['code' => 200, 'msg' => '操作成功'];                
    }

}