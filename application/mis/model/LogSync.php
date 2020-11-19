<?php
namespace app\mis\model;

use think\Db;
use app\common\model\BaseModel;

class LogSync extends BaseModel
{
    protected $connection = 'database_mis';
    protected $table = 't_log_sync';
    protected $pk = 'id';

    /**
     * 取得最后一次数据
     *
     * @param [String] $name 数据名称
     * @param [Boolean] $useCache 是否从缓存取数
     * @return [array]
     */
    public function getLast($name, $useCache = false)
    {
        $nameX = str_replace(':', ',', $name);
        $cacheKey = "mis:logSync:name:$nameX";
        $redis = $this->redis();
        if ($useCache) {
            $data = $redis->cache($cacheKey);
        }

        if (empty($data)) {
            $where = [
                ['status', '=', Db::raw(1)],
                ['name', '=', $name],
            ];
            $data = $this->where($where)->order('time Desc')->find();
            if (!empty($data)) {
                $redis->cache($cacheKey, $data, 5);
            }
        }
        return $data;
    }

    /**
     * 取得最后一次时间
     *
     * @param [String] $name 数据名称
     * @return [String]
     */
    public function getLastTime($name)
    {
        $data = $this->getLast($name);
        if (empty($data)) {
            return null;
        }
        return $data['time'] ?: null;
    }
}
