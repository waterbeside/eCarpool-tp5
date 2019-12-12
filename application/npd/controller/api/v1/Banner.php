<?php

namespace app\npd\controller\api\v1;

use think\Db;
use app\npd\controller\api\NpdApiBase;
use app\npd\model\Banner as BannerModel;
use app\common\model\I18nLang as I18nLangModel;
use my\RedisData;

/**
 * Api Banner
 * Class Banner
 * @package app\npd\controller\api\v1
 */
class Banner extends NpdApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 取得Banner列表
     */
    public function index($type = 1)
    {
        $lang = (new I18nLangModel())->formatLangCode($this->language);
        $lang = $lang ? $lang : "en";

        $cacheKey  = "npd:banner:type_$type:lang_$lang";

        $redis = new RedisData();
        $res = $redis->cache($cacheKey);
        if (!$res) {
            $map  = [];
            $map[] = ['status', '=', 1];
            $map[]  = ['is_delete', "=", Db::raw(0)];
            $map[] = ['type', '=', $type];

            $whereExp = '';
            $whereExp .= " (lang = '$lang' OR lang = '')";

            $res  = BannerModel::where($map)->where($whereExp)->order(['sort' => 'DESC', 'id' => 'DESC'])->select();
            if ($res) {
                $redis->cache($cacheKey, $res->toArray(), 60);
            }
        }

        if (!$res) {
            return $this->jsonReturn(20002, [], lang('No data'));
        }
        $res_filt = [];
        foreach ($res as $key => $value) {
            $res_filt[] = [
                "id" => $value["id"],
                "title" => $value["title"],
                "image" => $this->replaceAttachmentDomain($value["image"]),
                "link_type" => $value["link_type"],
                "link" => $value["link"],
                "create_time" => $value["create_time"],
                "type" => $value["type"],
            ];
        }
        $returnData = [
            'list' => $res_filt,
        ];
        return $this->jsonReturn(0, $returnData, 'success');
    }
}
