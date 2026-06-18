<?php

namespace app\index\controller;

use think\facade\Request;
use think\Db;
use app\index\model\Domain;

class WxopenManage
{
    /**
     * @function 公众号管理
     * @return \think\response\View
     */
    public function wxopen_view() {
       $data =  Db::name('wxopen')->field('id,account,mima,name,type,status')->where('type',0)->select();
       return  view('wxopen_manage/wxopen',['data'=>$data]);
    }
    
    /**
     * @function 更换公众号
     */
    public function changeOpen() {
        $request = Request::instance();
        $id = $request->post('id');
        $data =  Db::name('wxopen')->where('id',$id)->field('appid,appsecret,authdomain1,authdomain2')->find();
        Domain::where('type',21)->update(['domain'=>$data['authdomain1']]);
        Domain::where('type',22)->update(['domain'=>$data['appid']]);
        Domain::where('type',23)->update(['domain'=>$data['appsecret']]);
        Db::name('wxopen')->where('status',1)->update(['status'=>2]);
        Db::name('wxopen')->where('id',$id)->update(['status'=>1]);
        return ['code'=>200,'msg'=>'操作成功'];
    }
}