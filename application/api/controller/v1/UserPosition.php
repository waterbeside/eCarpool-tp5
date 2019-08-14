<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\UserPosition as UserPositionModel;
use app\carpool\model\User as UserModel;
use app\carpool\model\Company as CompanyModel;

use think\Db;

/**
 * 文章接口
 * Class Docs
 * @package app\api\controller
 */
class UserPosition extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    /**
     *
     *
     * @param  int  $id   $用户id,  当为0时 返回当前登入用户上传的座标， 如果用户没上传座标，则返回公司默认座标。
     * @return \think\Response
     */
    public function read($id = 0)
    {
        $this->checkPassport(1);
        $type = $id > 0 ? 0 : 1;
        if (is_numeric($id)) {
            if ($id == 0) {
                $id = $this->userBaseInfo['uid'];
            }
            if (!$id) {
                $this->jsonReturn(992, lang('Parameter error'));
            }
            $returnData = [];
            $res = UserPositionModel::find($id);
            $update_time = 0;
            if ($res) {
                $res = array_change_key_case($res->toArray(), CASE_LOWER);
                $update_time = $res['update_time'] ? strtotime($res['update_time']) : 0;
            }
            if ((time() - $update_time > 86400 * 360 || !$res || (intval($res['longitude']) < 1 && intval($res['latitude']) < 1))  && $type == 1) {
                $company_id   = UserModel::where("uid", $id)->value('company_id');
                $company_data = CompanyModel::field("x(position) as longitude, y(position) as latitude")->find($company_id);
                if ($company_data) {
                    $res = ['longitude' => $company_data['longitude'], 'latitude' => $company_data['latitude']];
                }
            }
            if ($res) {
                $returnData = [
                    'longitude' => floatval($res['longitude']),
                    'latitude'  => floatval($res['latitude']),
                    'update_time'  => intval($update_time),
                    'now'  => time(),
                ];
                $this->jsonReturn(0, $returnData, 'success');
            } else {
                $this->jsonReturn(20002, lang('No data'));
            }
        }
    }



    /**
     * 保存或更新用户坐标
     *
     * @param  int  $id
     * @return \think\Response
     */
    public function save($id)
    {
        $this->checkPassport(1);
        $uid = $this->userBaseInfo['uid'];
        $lat = input('post.latitude') ? input('post.latitude') : input('post.lat');
        $lng = input('post.longitude') ? input('post.longitude') : input('post.lng');
        if (!$lat || !$lng) {
            $this->jsonReturn(992, lang('Parameter error'));
        }

        $Position = UserPositionModel::get($uid);
        if (!$Position) {
            $Position = new UserPositionModel();
        }
        $Position->uid = $uid;
        $Position->Latitude = $lat;
        $Position->Longitude = $lng;
        $Position->update_time = date("Y-m-d H:i:s");
        $res = $Position->save();
        if ($res) {
            $this->jsonReturn(0, 'success');
        } else {
            $this->jsonReturn(-1, lang('Fail'));
        }
    }
}
