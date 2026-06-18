<?php

use think\facade\Route;

$prefix = 'v1/';
//二维码获取
Route::get($prefix . 'qrcode/view', 'Login/qrcode');

Route::post($prefix . 'qrcode/im_login_auth', 'Login/im_login_auth');

//登录
Route::get($prefix . 'login/index', 'Login/index');
Route::post($prefix . 'login/doLogin', 'Login/doLogin');
Route::post($prefix . 'login/isEmp3', 'Login/isEmp3');//判断员工类别
Route::post($prefix . 'login/doEmpLogin', 'Login/doEmpLogin');//员工登录
Route::get($prefix . 'login/logout', 'Login/logout');//退出登录
Route::get($prefix . 'login/getImgCode', 'Login/getImgCode');//获取图形验证码
Route::post($prefix . 'login/checkImgCode', 'Login/checkImgCode');//检测图片验证码
Route::get($prefix . 'agents/sendSmsCode', 'Login/sendSmsCode');//验证码
Route::get($prefix . 'login/agent_auth', 'Login/agent_auth');
Route::get($prefix . 'agent_auth_check', 'Login/agent_auth_check');
Route::post($prefix . 'login/getQunTitle', 'Login/getQunTitle');//获取群昵称

//代理管理

Route::post($prefix . 'agents/unbindwx', 'AgentsManage/unbindwx');//

Route::get($prefix . 'agents/role', 'AgentsManage/AgentsRole');//角色管理web
Route::get($prefix . 'agents/role/add', 'AgentsManage/AgentsRoleAdd');//添加角色web
Route::get($prefix . 'agents/role/update', 'AgentsManage/AgentsRoleUpdate');//修改角色web
Route::post($prefix . 'agents/list', 'AgentsManage/AgentsList');//代理列表web
Route::post($prefix . 'agents/emplist', 'AgentsManage/AgentsEmpList');//员工列表
Route::get($prefix . 'agents/add', 'AgentsManage/AgentsAdd');//添加代理web
Route::get($prefix . 'agents/update', 'AgentsManage/AgentsUpdate');//修改代理web
Route::get($prefix . 'agents/info', 'AgentsManage/agentsInfo');//代理详情web
Route::get($prefix . 'agents/password/edit', 'AgentsManage/agentsPasswordEdit');//修改密码web
Route::post($prefix . 'do/role/add', 'AgentsManage/doRoleAdd');//添加角色
Route::post($prefix . 'do/role/delete', 'AgentsManage/doRoleDelete');//删除角色
Route::post($prefix . 'do/role/update', 'AgentsManage/doRoleUpdate');//更新角色
Route::post($prefix . 'do/agents/add', 'AgentsManage/doAgentsAdd');//添加代理
Route::post($prefix . 'do/agents/addemp', 'AgentsManage/doAgentsAddEmp');//添加员工
Route::post($prefix . 'agents/user', 'AgentsManage/agentsUser');//代理直属会员
Route::post($prefix . 'agents/userLoseWin', 'AgentsManage/agentUserLoseWin');//代理直属会员输赢数
Route::post($prefix . 'agents/relation', 'AgentsManage/changeAgentsRelation');//修改代理关系
Route::post($prefix . 'agents/countProfit', 'AgentsManage/countProfitHand');//结算收益


Route::post($prefix . 'do/agents/stop', 'AgentsManage/doAgentsStop');//停用或启用代理
Route::post($prefix . 'do/agents/delete', 'AgentsManage/doAgentsDelete');//删除代理
Route::post($prefix . 'do/agents/update', 'AgentsManage/doAgentsUpdate');//修改代理
Route::post($prefix . 'do/agents/password/update', 'AgentsManage/doAgentsPasswordUpdate');//修改密码
Route::post($prefix . 'agents/search', 'AgentsManage/AgentsSearch');//修改密码
Route::post($prefix . 'agents/upDowFen', 'AgentsManage/upDowFen');//
Route::post($prefix . 'agents/groupupDowFen', 'AgentsManage/groupupDowFen');//

Route::post($prefix . 'agents/upDowFenUser', 'AgentsManage/upDowFenUser');//

Route::post($prefix . 'agents/updateAgentInfo', 'AgentsManage/updateAgentInfo');//修改代理信息
Route::post($prefix . 'agents/agentsShare', 'AgentsManage/agentsShare');//代理占成
Route::post($prefix . 'agents/agentsShareSelf', 'AgentsManage/agentsShareSelf');//代理占成（自己）
Route::post($prefix . 'agents/scorelog', 'AgentsManage/user_score_log');//额度调整记录
Route::post($prefix . 'agents/agentsscorelog', 'AgentsManage/agents_score_log');//额度调整记录
Route::post($prefix . 'agents/updowinfo', 'AgentsManage/updowinfo');//代理余分
Route::post($prefix . 'agents/groupupdowinfo', 'AgentsManage/groupupdowinfo');//各个群代理余分

Route::post($prefix . 'agents/agentinfo', 'AgentsManage/agent_info');//代理信息
Route::post($prefix . 'agents/agentxh', 'AgentsManage/agent_xh_config');//代理限红配置
Route::post($prefix . 'agents/agentdc', 'AgentsManage/agent_dc_report');//对冲报表
Route::post($prefix . 'agents/agentsLoseWin', 'AgentsManage/agentsLoseWin');//代理输赢列表
Route::post($prefix . 'agents/agentsLoseWinExport', 'AgentsManage/agentsLoseWinExport');//代理输赢列表
Route::post($prefix . 'agents/userupdowFenLog', 'AgentsManage/updowFenLog');//代理输赢列表
Route::post($prefix . 'agents/bossList', 'AgentsManage/bossList');//代理输赢列表


Route::get($prefix . 'agents/wxagents', 'AgentsManage/wxagents');
Route::post($prefix . 'agents/profit', 'AgentsManage/AgentsProfit');//代理收益
Route::post($prefix . 'agents/profitExport', 'AgentsManage/AgentsProfitExport');//代理收益

Route::post($prefix . 'agents/profitDetail', 'AgentsManage/AgentsProfitDetail');//代理收益明细

//会员管理
Route::post($prefix . 'user/token', 'UserManage/token');//会员列表web
Route::post($prefix . 'user/list', 'UserManage/userList');//会员列表web
Route::post($prefix . 'user/detail', 'UserManage/userDetail');//会员详情web
Route::get($prefix . 'user/doExcel', 'UserManage/doExcel');//会员详情web
Route::get($prefix . 'user/deal', 'UserManage/deal');//会员详情web
Route::post($prefix . 'user/inte_rate', 'UserManage/inte_rate');//会员详情web
Route::get($prefix . 'user/add', 'UserManage/userAdd'); //增加会员
Route::post($prefix . 'do/user/add', 'UserManage/doUserAdd');
Route::post($prefix . 'user/update_pwd', 'UserManage/update_pwd');
Route::post($prefix . 'user/upDowFen', 'UserManage/upDowFen');
Route::post($prefix . 'user/updowFenLog', 'UserManage/updowFenLog');
Route::post($prefix . 'user/updateUserInfo', 'UserManage/updateUserInfo');
Route::post($prefix . 'user/userLoseWin', 'UserManage/userLoseWin'); //会员输赢数
Route::post($prefix . 'user/userLoseWinExport', 'UserManage/userLoseWinExport'); //会员输赢数
Route::post($prefix . 'user/getUserDetail', 'UserManage/getUserDetail'); //会员详情
Route::post($prefix . 'user/getUserInfo', 'UserManage/getUserInfo'); //会员详情
Route::post($prefix . 'user/delete', 'UserManage/user_delete'); //删除会员
Route::post($prefix . 'user/forbidden', 'UserManage/user_forbidden'); //禁用会员
Route::post($prefix . 'user/say', 'UserManage/user_no_say'); //禁言会员
Route::post($prefix . 'user/updowinfo', 'UserManage/updowinfo'); //上下分信息
Route::post($prefix . 'user/useragent', 'UserManage/user_agent_info'); //用户上级信息
Route::post($prefix . 'user/logininfo', 'UserManage/user_login_info'); //用户登录信息
Route::post($prefix . 'user/clearLoginLog', 'UserManage/clearLoginLog'); //清空会员上线记录
Route::Any($prefix . 'user/UploadChatImage', 'UserManage/UploadChatImage');//用户上传头像
Route::post($prefix . 'user/addRobot', 'UserManage/addRobot');//增加机器人
Route::post($prefix . 'user/updateAllshare', 'UserManage/updateAllshare');//增加机器人
Route::get($prefix . 'user/makeAccount', 'UserManage/makeAccount');//生成账号
Route::post($prefix . 'user/updateUserPwd', 'UserManage/updateUserPwd');//修改密码
Route::post($prefix . 'user/updateZc', 'UserManage/updateZc');//批量修改占成
Route::post($prefix . 'user/updateInteRate', 'UserManage/updateInteRate');//批量修改积分比例
//红包群
Route::post($prefix . 'user/listUserByJifen', 'UserManage/listUserByJifen');//通过积分添加群成员
Route::post($prefix . 'user/listUserByJId', 'UserManage/listUserById');//通过ID添加群成员
Route::post($prefix . 'user/getLastHbSetting', 'UserManage/getLastHbSetting');//获取历史红包设置
Route::post($prefix . 'user/getHbHis', 'UserManage/getHbHis');//获取红包历史记录
Route::post($prefix . 'user/getHbDetail', 'UserManage/getHbDetail');//获取红包领取详情
Route::post($prefix . 'user/getHbHisByUid', 'UserManage/getHbHisByUid');//获取红包领取详情



//首页
Route::get($prefix . 'index/index', 'Index/index');
Route::get($prefix . 'index/welcome', 'Index/welcome');
Route::get($prefix . 'index/deleted_zy_tongji', 'Index/deleted_zy_tongji');
Route::get($prefix . 'index/doExcel', 'Index/doExcel');

//牌局管理
Route::post($prefix . 'game/list', 'GameManage/gameList'); //牌局列表web
Route::post($prefix . 'game/chat', 'GameManage/getChatContent'); //牌局列表web
Route::post($prefix . 'game/zhuang_ts', 'GameManage/zhuang_ts'); //庄退水
Route::post($prefix . 'game/zts_detail', 'GameManage/zts_detail'); //庄退水详情
Route::post($prefix . 'game/delete', 'GameManage/gameDelete'); //删除牌局
Route::post($prefix . 'game/update', 'GameManage/gameUpdate'); //修改牌局
Route::post($prefix . 'game/add', 'GameManage/gameAdd'); //增加牌局
Route::post($prefix . 'game/deletechatmsg', 'GameManage/deletechatmsg'); //删除群消息
Route::post($prefix . 'game/cancelGame', 'GameManage/cancelGame'); //取消牌局

//下注管理
Route::post($prefix . 'bet/list', 'BetManage/betList'); //下注列表web
Route::post($prefix . 'bet/danXm', 'BetManage/danXm'); //单边洗码

Route::post($prefix . 'bet/danXmExport', 'BetManage/danXmExport'); //单边洗码
Route::post($prefix . 'bet/share', 'BetManage/betsShare'); //占成统计

//余分管理
Route::post($prefix . 'score/list', 'ScoreManage/scoreList'); //余分列表
Route::get($prefix . 'score/doExcel', 'ScoreManage/doExcel'); //下载报表
Route::post($prefix . 'score/userScoreLog', 'ScoreManage/userScoreLog'); //余分列表
Route::post($prefix . 'score/agentScoreLog', 'ScoreManage/agentScoreLog'); //余分列表
Route::get($prefix . 'score/delScoreLog', 'ScoreManage/delScoreLog'); //追龙删除流水记录
Route::get($prefix . 'score/delBetLog', 'ScoreManage/delBetLog'); //追龙删除下注记录

//系统管理
Route::post($prefix . 'system/clearSystemLog', 'SystemManage/clearSystemLog');//清空操作日志
Route::get($prefix . 'system/gamePWD', 'SystemManage/gamePwd');//牌局密码
Route::post($prefix . 'system/updateGamePwd', 'SystemManage/updateGamePwd');//修改牌局密码
Route::post($prefix . 'system/systemLog', 'SystemManage/systemLog');//修改牌局密码
Route::post($prefix . 'domain/list', 'SystemManage/domain');//域名列表
Route::post($prefix . 'domain/forbid', 'SystemManage/domainForbid');//域名禁用


//积分管理
Route::post($prefix . 'integral/integralDate', 'IntegralManage/integralDate'); //日积分
Route::post($prefix . 'integral/integralLog', 'IntegralManage/integralLog'); //积分流水
Route::post($prefix . 'integral/doExcel', 'IntegralManage/doExcel'); //导出excel
Route::post($prefix . 'integral/exchange', 'IntegralManage/integral_hand');//积分兑换
Route::get($prefix . 'integral/userintegral', 'IntegralManage/user_integral');//同步积分
Route::get($prefix . 'integral/agentsintegral', 'IntegralManage/agents_integral');//代理积分任务
Route::post($prefix . 'integral/integralExport', 'IntegralManage/integralExport'); //积分流水

//运营统计
Route::get($prefix . 'mba/bigdata', 'MbaManage/bigdata');//按天统计
Route::get($prefix . 'mba/bigdataUser', 'MbaManage/bigdataUser');//累计统计
Route::get($prefix . 'mba/doExcel', 'MbaManage/doExcel');//excel
Route::get($prefix . 'mba/doExcelDate', 'MbaManage/doExcelDate');//excel
//码粮结算
Route::post($prefix . 'yard/count', 'YardManage/count_yard');//积分一键上表
Route::post($prefix . 'yard/list', 'YardManage/yardList');//积分一键上表
Route::post($prefix . 'yard/getYardExport', 'YardManage/getYardExport');//积分一键上表

Route::post($prefix . 'yard/doExcel', 'YardManage/doExcel');//下载报表
//房间列表
Route::post($prefix . 'room/list', 'RoomManage/roomList');//房间列表
//自营报表
Route::post($prefix . 'profit/winlose', 'ProfitManage/winlose');//输赢数
Route::post($prefix . 'profit/guiling', 'ProfitManage/profitGuiling');//归零
Route::post($prefix . 'profit/history', 'ProfitManage/profitHistory');//历史报表

//结算报表
Route::post($prefix . 'zy/report', 'ZyManage/report');
//生成mp3
Route::get($prefix . 'voice/make', 'VoiceManage/makeVoice');

//数据核查
Route::get($prefix . 'data/updateReportAgain', 'DataManage/updateReportAgain');//重新统计检查报表
Route::post($prefix . 'data/checkdata', 'DataManage/checkdata');
Route::post($prefix . 'data/checkInte', 'DataManage/checkInte');
Route::post($prefix . 'data/checkLosewin', 'DataManage/checkLosewin');
Route::post($prefix . 'data/checkUserScoreLog', 'DataManage/checkUserScoreLog');//检查用户余分流水
Route::post($prefix . 'data/checkUserWin', 'DataManage/checkUserWin');//检查用户输赢
Route::get($prefix . 'data/zx_zc_losewin', 'DataManage/zx_zc_losewin');//检查用户占成输赢
Route::get($prefix . 'data/checkScore', 'DataManage/checkScore');//检查用户余分
Route::get($prefix . 'data/checkReport', 'DataManage/checkReport');//检查报表
Route::get($prefix . 'data/updateReport', 'DataManage/updateReport');//检查报表
Route::get($prefix . 'data/updateReportHQ', 'DataManage/updateReportHQ');//对冲盈亏
Route::post($prefix . 'data/tongjiReport', 'DataManage/tongjiReport');//计算报表
Route::post($prefix . 'data/shanData', 'DataManage/shanData');
Route::post($prefix . 'data/shanTongji', 'DataManage/shanTongji');
Route::post($prefix . 'data/checkBets', 'DataManage/checkBets');
Route::post($prefix . 'data/updateUnionid', 'DataManage/updateUnionid');
Route::get($prefix . 'data/domysqlzhucong', 'DataManage/domysqlzhucong'); //数据库主从
Route::get($prefix . 'data/mysqlzhucong', 'DataManage/mysqlzhucong'); //获取数据库主从最后日期
//board
Route::post($prefix . 'board/listAgentsUser', 'BoardManage/listAgentsUser');
Route::post($prefix . 'board/listUserWinLostRank', 'BoardManage/listUserWinLostRank');
Route::post($prefix . 'board/listshuyinguser', 'BoardManage/listshuyinguser');
Route::post($prefix . 'board/upfenlistchat', 'BoardManage/upfenlistchat');
Route::post($prefix . 'board/downfenlistchat', 'BoardManage/downfenlistchat');
Route::post($prefix . 'board/xmlistchat', 'BoardManage/xmlistchat');

Route::Any($prefix . 'user/UploadImageChat', 'UserManage/UploadImageChat');//聊天图片
//计划任务
Route::get($prefix . 'task/losewin', 'TaskManage/losewinTask');//统计用户输赢
Route::get($prefix . 'task/integral', 'TaskManage/integral_task');//积分同步
Route::get($prefix . 'task/updowFen', 'TaskManage/updowFenTask');//统计用户上下分
Route::get($prefix . 'task/dateGameReport', 'TaskManage/dateGameReportTask');//统计结算报表
Route::get($prefix . 'tongji/agent', 'TongjiManage/tongji');//代理统计
Route::get($prefix . 'tongji/extra', 'TongjiManage/extra_share_score');//额外抽水
Route::get($prefix . 'lhtongji/agent', 'LhTongjiManage/tongji');//龙虎代理统计
Route::get($prefix . 'zjhtongji/agent', 'ZjhTongjiManage/tongji');//炸金花代理统计
Route::get($prefix . 'nntongji/agent', 'NnTongjiManage/tongji');//牛牛代理统计
Route::get($prefix . 'task/videoUrl', 'TaskManage/videoUrlTask');//改变视频地址
Route::get($prefix . 'task/updateVideo', 'TaskManage/updateVideoTask');//改变视频地址
Route::get($prefix . 'task/makeImage', 'TaskManage/makeImage');//生成图片
Route::get($prefix . 'task/checkDomain', 'TaskManage/checkDomain');//域名检测
Route::get($prefix . 'task/ludanImage', 'TaskManage/ludanImage');//卤蛋图片
Route::get($prefix . 'task/ludanTask', 'TaskManage/ludanTask');//卤蛋task
Route::get($prefix . 'task/delTouristTask', 'TaskManage/delTouristTask');//清除游客
Route::get($prefix . 'task/delTourist', 'TaskManage/delTourist');//清除游客
Route::get($prefix . 'task/delchatimg', 'TaskManage/delchatimg');//删除聊天图片
Route::get($prefix . 'task/delchatimghuojian', 'TaskManage/delchatimghuojian');//删除火箭聊天图片
Route::get($prefix . 'task/tmColor', 'TaskManage/tmColor');//改变推码颜色
Route::get($prefix . 'task/closeyk', 'TaskManage/closeyk');//开启或关闭游客功能
Route::get($prefix . 'task/dataCount', 'TaskManage/dataCount');//后台数据统计



//聊天
Route::post($prefix . 'chat/getchat', 'ChatManage/getchat');//获取聊天信息
Route::post($prefix . 'chat/getchatkf', 'ChatManage/getchatkf');//获取聊天信息
Route::post($prefix . 'chat/getchatsx', 'ChatManage/getchatsx');//获取聊天信息
Route::post($prefix . 'chat/getunreadmessage', 'ChatManage/getunreadmessage');//获取聊天信息
Route::get($prefix . 'chat/initchat', 'ChatManage/initchat');//初始化用户
Route::post($prefix . 'chat/getChatUrl', 'ChatManage/getChatUrl');//初始化用户

//房间设置
Route::post($prefix . 'room/getfasttext', 'RoomConfigManage/getfasttext');//获取快捷消息
Route::post($prefix . 'room/config', 'RoomConfigManage/RoomConfig');//获取a房间配置
Route::post($prefix . 'room/update', 'RoomConfigManage/updateRoomConfig');//房间配置修改
//团队管理
Route::post($prefix . 'teamleader/add', 'TeamManage/teamLeaderAdd');//添加群主
Route::post($prefix . 'teamleader/list', 'TeamManage/teamLeaderList');//群主列表
Route::post($prefix . 'teamleader/update', 'TeamManage/updateTeamLeader');//修改群主


//群配置
Route::post($prefix . 'teamconfig/get', 'TeamManage/getTeamConfig');//获取群配置
Route::post($prefix . 'teamconfig/update', 'TeamManage/updateTeamConfig');//修改群配置
Route::post($prefix . 'teamconfig/setStartWorkTime', 'TeamManage/setStartWorkTime');//设置开工时间

//群域名
Route::post($prefix . 'teamdomain/add', 'TeamManage/addTeamDomain');//添加群域名
Route::post($prefix . 'teamdomain/list', 'TeamManage/domainList');//域名列表
//群房间
Route::post($prefix . 'teamroom/add', 'TeamManage/addTeamRoom');//添加群房间


//百度编辑器上传oss
Route::get($prefix . 'userManage/uploadoss', 'UserManage/uploadoss');//添加群房间

//公告
Route::post($prefix . 'gonggao/get', 'AgentsManage/getgonggao');//
Route::post($prefix . 'gonggao/set', 'AgentsManage/setgonggao');//
Route::post($prefix . 'gonggao/setedit', 'AgentsManage/setgonggaoedit');//
Route::post($prefix . 'gonggao/getall', 'AgentsManage/getgonggaoall');//
Route::post($prefix . 'gonggao/deleteGonggao', 'AgentsManage/deleteGonggao');//



//单边限红
Route::get($prefix . 'xian/getbetstotal', 'XianHongManage/getBetsTotal');//获取下注总额

//管理面板
Route::get($prefix . 'control/addTime', 'ControlManage/addTime');//增加使用时间
Route::get($prefix . 'control/changeQrDomain', 'ControlManage/changeQrDomain');//更换二维码域名
Route::get($prefix . 'control/changeDwDomain', 'ControlManage/changeDwDomain');//更换对外域名
Route::get($prefix . 'control/getQrDomain', 'ControlManage/getQrDomain');//获取二维码域名
Route::get($prefix . 'control/getDwDomain', 'ControlManage/getDwDomain');//获取对外域名
Route::get($prefix . 'control/checkQrDomain', 'ControlManage/checkQrDomain');//检测二维码域名
Route::get($prefix . 'control/checkDwDomain', 'ControlManage/checkDwDomain');//检测对外域名
Route::get($prefix . 'control/getWxConfig', 'ControlManage/getWxConfig');//获取公众号配置
Route::get($prefix . 'control/changeWxconfig', 'ControlManage/changeWxconfig');//更换公众号配置
Route::get($prefix . 'control/getImDomain', 'ControlManage/getImDomain');//获取聊天室域名
Route::get($prefix . 'control/changeImdomain', 'ControlManage/changeImdomain');//更换聊天室域名
Route::get($prefix . 'control/updateWxconfig', 'ControlManage/updateWxconfig');//更换公众号配置
Route::get($prefix . 'control/changeWs', 'ControlManage/changeWs');//更换ws

//公众号管理
Route::get($prefix . 'wxopen/wxopen_view', 'WxopenManage/wxopen_view');//公众号列表
Route::post($prefix . 'wxopen/changeOpen', 'WxopenManage/changeOpen');//更换公众号

//机器人下注
Route::get($prefix . 'robotBets/doRobots', 'RobotBets/doRobots');//机器人下注
Route::get($prefix . 'robotBets/todorobot', 'RobotBets/todorobot');//机器人下注
Route::get($prefix . 'robotBets/yytodorobot', 'RobotBets/yytodorobot');//yy机器人下注
Route::get($prefix . 'robotBets/todorobot9654', 'RobotBets/todorobot9654');//9654机器人下注
Route::get($prefix . 'robotBets/todorobot9605', 'RobotBets/todorobot9605');//9605机器人下注
Route::get($prefix . 'robotBets/todorobot9602', 'RobotBets/todorobot9602');//9602机器人下注
Route::get($prefix . 'robotBets/todorobot9678', 'RobotBets/todorobot9678');//9678机器人下注
Route::get($prefix . 'robotBets/todorobot9669', 'RobotBets/todorobot9669');//9669机器人下注


