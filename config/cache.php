<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------
$domain = $_SERVER['HTTP_HOST'];
$redis_config = [
    'localhost:9527' => [
        // 驱动方式
        'type' => 'Redis',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => '',
        'timeout' => 3600,
    ],
    'shangshui.zhuyouji.com.cn' => [
        // 驱动方式
        'type' => 'Redis',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
        'host' => '127.0.0.1',
        'port' => '3769',
        'password' => '',
        'timeout' => 3600,
    ],
    'agent_shangshui.bbvcka.cn' => [
        // 驱动方式
        'type' => 'Redis',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
        'host' => '127.0.0.1',
        'port' => '3769',
        'password' => '',
        'timeout' => 3600,
    ],
];
if (!empty($redis_config[$domain])) {
    return $redis_config[$domain];
} else {
    return [
        // 驱动方式
        'type' => 'Redis',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
        'host' => '127.0.0.1',
        'port' => '3769',
        'password' => '',
        'timeout' => 3600,
    ];
}
