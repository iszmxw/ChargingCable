<?php

namespace app\admin\controller;

use Doctrine\Common\Cache\Cache as CacheInterface;
use think\Cache;

class WechatCache implements CacheInterface
{
    public function fetch($id)
    {
        // 你自己从你想实现的存储方式读取并返回
        return Cache::get($id);
    }

    public function contains($id)
    {
        // 同理 返回存在与否 bool 值
        if (Cache::get($id, '')) {
            return true;
        } else {
            return false;
        }
    }

    public function save($id, $data, $lifeTime = 0)
    {
        // 用你的方式存储该缓存内容即可
        Cache::set($id, $data);
    }

    public function delete($id)
    {
        // 删除并返回 bool 值
        if (Cache::rm($id)) {
            return true;
        } else {
            return false;
        }
    }

    public function getStats()
    {
        // 这个你可以不用实现，返回 null 即可
        return null;
    }
}