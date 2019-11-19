<?php

namespace app\api\controller\v2;

use app\api\controller\ApiBase;
use app\content\model\CommonNotice as NoticeModel;
use app\carpool\model\UpdateVersion as VersionModel;
use app\carpool\model\VersionDetails as VersionDetailsModel;
use app\content\model\Ads as AdsModel;
use app\common\model\Apps as AppsModel;
use app\carpool\model\Grade as GradeModel;
use app\carpool\model\Configs as ConfigsModel;
use my\RedisData;

use app\common\model\I18nLang as I18nLangModel;
use think\Db;

/**
 * 通知
 * Class Notice
 * @package app\api\controller
 */
class AppInitiate extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 启动时调用接口
     * @return mixed
     */
    public function index($app_id = 1, $platform = 0, $version_code = 0)
    {
        $lang = (new I18nLangModel())->formatLangCode($this->language);
        $lang = $lang ? $lang : "en";
        $platform_list = config('others.platform_list');
        $userData = $this->getUserData();
        // dump($userData);
        if ($userData) {
            $department_id = $userData['department_id'];
        }

        /**
         * 通知列表
         */
        $field = 't.id,t.title,t.content,t.type,t.start_time,t.end_time,t.create_time,t.refresh_time,t.sort,t.status,t.lang';
        $map   = [];
        $map[] = ['status', '=', 1];
        $map[] = ['type', '=', 1];
        $map[] = ['lang', '=', $lang];
        $map[] = ['end_time', '>=', date("Y-m-d H:i:s")];
        $map[] = ['start_time', '<', date("Y-m-d H:i:s")];
        $whereExp = '';
        $whereExp .= " FIND_IN_SET($app_id,app_ids) ";
        $whereExp .= " AND FIND_IN_SET($platform,platforms) ";

        $notices  = NoticeModel::field($field)->alias('t')->where($map)->where($whereExp)->order('t.sort DESC , t.id DESC')
            // ->fetchSql(true)
            ->select();
        // dump($notices);exit;

        foreach ($notices as $key => $value) {
            $notices[$key]['token'] = md5(strtotime($value['refresh_time']));
        }

        /**
         * 启屏广告图
         */
        $adsData = [];
        if ($userData) {
            $map  = [];
            $map[] = ['status', '=', 1];
            $map[]  = ['is_delete', "=", Db::raw(0)];
            $map[] = ['type', '=', 1];


            $whereExp = '';
            $whereExp .= " FIND_IN_SET($app_id,app_ids) ";
            $whereExp .= " AND FIND_IN_SET($platform,platforms) ";
            $whereExp .= " AND  (lang = '$lang' OR lang = '')";

            $adsRes  = AdsModel::where($map)->where($whereExp)->json(['images'])->order(['sort' => 'DESC', 'id' => 'DESC'])->select();
            foreach ($adsRes as $key => $value) {
                if ($this->checkDeptAuth($department_id, $value['region_id'])) {
                    $adsData[] = [
                        "id" => $value["id"],
                        "title" => $value["title"],
                        "images" => $value["images"],
                        "link_type" => $value["link_type"],
                        "link" => $value["link"],
                        "create_time" => $value["create_time"],
                        "type" => $value["type"],
                    ];
                }
            }
        }






        /**
         * 检查更新
         */
        $version = intval($version_code);
        // dump($version);exit;
        $platform_str = isset($platform_list[$platform]) ? $platform_list[$platform] : '';
        // dump($platform_str);exit;

        $VersionModel = new VersionModel();
        $versionData  = $VersionModel->findByPlatform($platform_str);

        $returnVersionData = [
            // 'forceUpdate' => 'N',
            'is_update' => 0,
            'latest_version' => $versionData['latest_version'] ? $versionData['latest_version'] : '',
            'latest_version_code' => $versionData['current_versioncode'] ? $versionData['current_versioncode'] : '',
            'desc' => '',
            'md5' => $versionData['md5']
        ];

        if ($versionData) {
            $VersionDetailsModel = new VersionDetailsModel();
            $versionDescription  = $VersionDetailsModel->findByVer($versionData['latest_version'], $platform_str, $lang);
            // dump($versionData['white_list']);
            $returnVersionData['desc'] = $versionDescription['description'] ? $versionDescription['description'] : "";
            if ($versionData['is_new']) {
                $isInWhiteList = in_array($version, explode(',', $versionData['white_list']));
                if (!$isInWhiteList && $version != $versionData['current_versioncode']) {
                    $returnVersionData['is_update'] = 2;
                } elseif ($version < $versionData['current_versioncode']) {
                    $returnVersionData['is_update'] = 1;
                }
            }
        }

        /**
         * 取得carpool配置
         */
        $ConfigsModel = new ConfigsModel();
        $configs = $ConfigsModel->getList(0);

        /**
         * 按ip取得城市信息
         */
        $ip = request()->ip();
        // $ip_res = $this->clientRequest("http://ip.taobao.com/service/getIpInfo.php?ip=".$ip,[],'GET');
        // $ip_data = ['ip'=>$ip];
        // if($ip_res){
        //   if($ip_res['code'] === 0){
        //     $ip_data['country'] = $ip_res['data']['country'];
        //     $ip_data['region'] = $ip_res['data']['region'];
        //     $ip_data['city'] = $ip_res['data']['city'];
        //     $ip_data['country_id'] = $ip_res['data']['country_id'];
        //   }
        // }
        $GradeModel = new GradeModel();
        $isGrade = $GradeModel->isGrade('trips', $app_id);
        $gps_interval = config('trips.gps_interval') ? intval(config('trips.gps_interval'))  : 0;
        $returnData = [
            'configs' => $configs,
            'notices' => $notices,
            'ads' =>  $adsData,
            'version' => $returnVersionData,
            'ip' => $ip,
            'grade_switch' => $isGrade ? 1 : 0, //是否使用评分系统
            'gps_interval' => $gps_interval ? $gps_interval  : 30,
        ];
        // dump($lists);
        return $this->jsonReturn(0, $returnData, lang('Successfully'));
    }


    /**
     * 取得app的api域名
     */
    public function get_url($app_id = 1, $platform = 0)
    {
        $AppsModel = new AppsModel();
        $redData = $AppsModel->itemCache($app_id);

        if ($redData && $redData['domain']) {
            $app = $redData;
        }
        if (!isset($app) || !$app) {
            $app = $AppsModel->get($app_id);
        }
        if (!$app) {
            $this->jsonReturn(20002, [], lang('No data'));
        }
        $redDataStr = json_encode($app);
        $AppsModel->itemCache($app_id, $redDataStr);
        $app['app_url'] = $app['is_ssl'] ? "https://" . $app['domain'] : "http://" . $app['domain'];
        $app['islatest']  = false;
        $this->jsonReturn(0, $app, 'success');
    }
}
