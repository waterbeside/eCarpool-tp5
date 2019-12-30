<?php
namespace app\carpool\service\shuttle;

use app\common\service\Service;
use app\carpool\model\User as UserModel;
use app\carpool\model\ShuttleTripPartner;

use my\RedisData;
use my\Utils;
use think\Db;

class Partner extends Service
{

    public $defaultUserFields = [
        'uid', 'loginname', 'name','nativename', 'phone', 'mobile', 'Department', 'sex',
        'company_id', 'department_id', 'companyname', 'imgpath', 'carcolor', 'im_id'
    ];


    /**
     * 预处理接收到的partnerid数据
     *
     * @param mixed $data
     * @return array
     */
    public function partnerIdToArray($data)
    {
        $data = Utils::getInstance()->stringSetToArray($data, 'intval');
        return $data;
    }


    /**
     * 通过partners用户id列，取得用户信息列表
     *
     * @param mixed $ids [uid,uid];
     * @param array $exclude 被排除的uid
     * @return array
     */
    public function getPartnersUserData($uids, $exclude = [])
    {
        $uids = Utils::getInstance()->stringSetToArray($uids, 'intval');
        $exclude = Utils::getInstance()->stringSetToArray($exclude, 'intval');
        $returnData = [];
        $UserModel = new UserModel();
        foreach ($uids as $key => $value) {
            if (in_array($value, $exclude)) {
                continue;
            }
            $userData = $UserModel->getItem($value, $this->defaultUserFields);
            $returnData[] = $userData;
        }
        return $returnData;
    }

    
    /**
     * 生成插入同伴表的批量数据
     *
     * @param array $partners 同伴的用户信息列表
     * @param array $tripData 行程基本信息, $rqData['line_data']
     * @return array
     */
    public function buildInsertBatchData($partners, $tripData)
    {
        $defaultData = [
            'status' => 0,
            'is_delete' => 0,
            'creater_id' => $tripData['uid'],
            'line_type' => $tripData['line_type'],
            'trip_id' => $tripData['id'],
            'time' => $tripData['time']
        ];
        $batchDatas = [];
        foreach ($partners as $key => $value) {
            $upUserData = [
                'uid' => $value['uid'],
                'name' => $value['name'],
                'department_id' => $value['department_id'],
                'sex' => $value['sex'],
            ];
            $batchDatas[] = array_merge($defaultData, $upUserData);
        }
        return $batchDatas;
    }

    /**
     * 生成插入同伴表的批量数据
     *
     * @param array $partners 同伴的用户信息列表
     * @param array $tripData 行程基本信息, $rqData['line_data']
     * @return array
     */
    public function insertPartners($partners, $tripData)
    {
        $batchData = $this->buildInsertBatchData($partners, $tripData);
        return ShuttleTripPartner::insertAll($batchData);
    }
}
