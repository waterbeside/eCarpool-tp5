<?php

namespace app\admin\controller;

use app\carpool\model\UpdateVersion as VersionModel;
use app\carpool\model\VersionDetails as VersionnDetailsModel;
use app\admin\controller\AdminBase;
use think\Db;
use my\RedisData;

/**
 * app版本管理
 * Class AppVersion
 * @package app\admin\controller
 */
class AppVersion extends AdminBase
{

    /**
     * 设置
     * @return mixed
     */
    public function index($app_id = 1, $version_code = 0, $platform = 0)
    {

        $list_android = [];
        $list_ios = [];
        $lists  = VersionnDetailsModel::group('platform,version_code,app_id')
            ->where("app_id", $app_id)
            ->field('platform,version_code,app_id,MIN(create_time) as time')
            ->order('time DESC')
            // ->fetchSQL(true)
            ->select();
        foreach ($lists as $key => $value) {
            if ($value['platform'] == "Android") {
                $list_android[] = $value;
            }
            if ($value['platform'] == "iOS") {
                $list_ios[] = $value;
            }
        }

        $dafaultValue = [
            'latest_version' => '000',
            'current_versioncode' => '000',
            'update_time' => '000',
            'max_versioncode' => '000',
            'min_versioncode' => '000',
            'update_version_id' => 0,
        ];

        $lists_current = VersionModel::where("app_id", $app_id)->select();
        $current_ios = $dafaultValue;
        $current_android = $dafaultValue;
        foreach ($lists_current as $key => $value) {
            if ($value['platform'] == "Android") {
                $current_android = $value ? $value : $current_android;
                continue;
            }
            if ($value['platform'] == "iOS") {
                $current_ios = $value ? $value : $current_ios;
                continue;
            }
        }


        $returnData = [
            "list_android" => $list_android,
            "list_ios" => $list_ios,
            "current_ios" => $current_ios,
            "current_android" => $current_android,
            "app_id_list"  => config('others.app_id_list'),
            "app_id"  => $app_id,
        ];




        return $this->fetch('index', $returnData);
    }


    /**
     * 发布版本
     */
    public function publish($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate = new \app\carpool\validate\UpdateVersion;
            if (!$validate->check($data)) {
                return $this->jsonReturn(-1, $validate->getError());
            }

            $VersionModel = new VersionModel();
            $data['update_version_id'] = $id;
            $model = $VersionModel->get($id);
            $platform = $model['platform'];

            $white_list_array = array_filter(explode(',', $data['white_list']));
            if (!in_array($data['current_versioncode'], $white_list_array)) {
                $white_list_array[] = $data['current_versioncode'];
            }

            $data['white_list'] = implode(',', $white_list_array);
            if ($model->save($data) !== false) {
                // $newData = $model->toArray();
                // $detailData = VersionnDetailsModel::where([['platform','=',$platform],['version_code','=', $newData['latest_version']]])
                //     ->select();
                // $newData['details'] = null;
                // foreach ($detailData as $key => $value) {
                //     $newData['details'][$value['language_code']] = $value;
                // }
                $VersionModel->DeleteCacheByPlatform($platform);
                $VersionModel->findByPlatform($platform);

                $this->log('更新版本成功' . json_encode($this->request->post()), 0);
                $this->jsonReturn(0, '更新成功');
            } else {
                $this->log('更新版本失败' . json_encode($this->request->post()), -1);
                $this->jsonReturn(-1, '更新失败');
            }
        } else {
            $data = VersionModel::find($id);
            return $this->fetch('publish', ['data' => $data, 'id' => $id]);
        }
    }
}
