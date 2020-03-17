<?php

namespace app\admin\controller\npd;

use app\carpool\model\User as CarpoolUser;
use app\npd\model\CpaUser as CpaUserModel;
use app\admin\controller\AdminBase;
use think\Db;

/**
 * CpaUser NPD允许登入的Carpool账号管理
 * Class CpaUser
 * @package app\admin\controller
 */

class CpaUser extends AdminBase
{

    public function index($filter = [], $page = 1, $pagesize = 15)
    {
        $map   = [];
        // $map[] = ['t.is_delete', '=', Db::raw(0)];

        $field = 't.*';

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['loginname|nativename', 'like', "%{$filter['keyword']}%"];
        }
        
        //筛选是否被删的用户
        $is_delete = isset($filter['is_delete']) && $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $map[] = ['is_delete', '=', $is_delete];

        //筛选状态用户
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $status = $filter['status'] ? Db::raw(1) : Db::raw(0);
            $map[] = ['status', '=', $status];
        }
        $CarpoolUser = new CarpoolUser();

        $lists  = CpaUserModel::field($field)->alias('t')->where($map)->order('t.create_time DESC, t.id DESC')
            ->paginate($pagesize, false, ['page' => $page])->each(function ($item, $key) use ($CarpoolUser) {
                // $item['userData'] = $CarpoolUser->getItem($item['uid']);
                return $item;
            });

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize'=>$pagesize
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加用户
     * @return mixed
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $step        = $this->request->param('step', 0);
            $data            = $this->request->post();
            $loginname        = $data['loginname'] ?? '';
            $loginname = str_replace(["\r\n", "\r", "\n"], " ", $loginname);
            $loginname = preg_replace("/\s(?=\s)/", "", $loginname);
            $loginname = str_replace([" ", "，", ", "], ',', $loginname);
            $loginnameArray = explode(',', $loginname);
            $loginnameArray = array_values(array_unique(array_diff($loginnameArray, [''])));
            $userList = [];
            $allowAccount = [];
            $allowAccountList = [];
            $CarpoolUser = new CarpoolUser();
            foreach ($loginnameArray as $key => $value) {
                $userDetail = $CarpoolUser->getDetail($value);
                if ($userDetail) {
                    $userDetail['status_str'] = '可添加';
                    $userDetail['can_be_add'] = true;
                    if ($userDetail['is_delete']) {
                        $userDetail['status_str'] = '该员工已离职，或已删除';
                        $userDetail['can_be_add'] = false;
                    } else {
                        $allowAccount[] = $userDetail['loginname'];
                        $allowAccountList[] = $userDetail;
                    }
                } else {
                    $userDetail = [
                        'uid' => '-',
                        'loginname' => $value,
                        'nativename' => '-',
                        'status_str' => '查不到该账号',
                        'can_be_add' => false,
                        'department_fullname' => '-',
                    ];
                }
                $userList[] = $userDetail;
            }

            // step 0
            if (!$step) {
                $returnData = [
                    'list' => $userList,
                    'allowAccount' => $allowAccount,
                ];
                return $this->jsonReturn(0, $returnData, '查得以下账号，请再次确认提交');
            }

            // step 1
            $CpaUserModel = new CpaUserModel();
            $res = $CpaUserModel->addByDataList($allowAccountList);
            
            if ($res) {
                $this->log('NPD添加Carpool授权用户成功', 0);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('NPD添加Carpool授权用户失败', -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->fetch('add');
        }
    }

    /**
     * 更改状态
     *
     * @param integer $id 主键
     * @param integer $status 状态
     */
    public function change_status($id, $status)
    {
        $status = $status ? 1 : 0;
        $CpaUserModel = new CpaUserModel();
        $res = $CpaUserModel->changeStatus($id, $status);
        if ($res === false) {
            return $this->jsonReturn(-1, '改变状态失败');
        }
        $data = $CpaUserModel->find($id);
        $CpaUserModel->delItemUidCache($data['uid']);
        return $this->jsonReturn(0, '改变状态成功');
    }

    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        $CpaUserModel = new CpaUserModel();
        $data = $CpaUserModel->find($id);
        if ($CpaUserModel->where('id', $id)->update(['is_delete' => 1])) {
            $this->log('NPD:删除Carpool用户授权成功，id=' . $id, 0);
            $CpaUserModel->delItemUidCache($data['uid']);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('NPD:删除Carpool用户授权失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }

    /**
     * 明细
     */
    public function public_detail($id)
    {
        if (!$id) {
            return $this->error('Lost id');
        }
        $userInfo = CpaUserModel::find($id);
        $returnData = [
            'data' => $userInfo,
        ];
        return $this->fetch('detail', $returnData);
    }
}
