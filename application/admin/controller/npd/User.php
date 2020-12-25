<?php

namespace app\admin\controller\npd;

use app\npd\model\User as UserModel;
use app\carpool\model\User as CarpoolUserModel;
use app\npd\validate\User as UserValidate;

use app\admin\controller\npd\NpdAdminBase;
use think\Db;

/**
 * User NPD自定义用户管理
 * Class User
 * @package app\admin\controller\npd
 */

class User extends NpdAdminBase
{

    public function index($filter = [], $page = 1, $pagesize = 15)
    {

        $where   = [];
        $siteIdwhere = $this->authNpdSite['sql_site_map'];
        $siteListIdMap = $this->getSiteListIdMap();

        if (!empty($siteIdwhere)) {
            $siteIdwhere[0] = 't.site_id';
            $where[] = $siteIdwhere;
        }

        $field = 't.*';

        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword']) {
            $where[] = ['account', 'like', "%{$filter['keyword']}%"];
        }

        //筛选用户邮箱信息
        if (isset($filter['email']) && $filter['email']) {
            $where[] = ['email', 'like', "%{$filter['email']}%"];
        }
        
        //筛选是否被删的用户
        $is_delete = isset($filter['is_delete']) && $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $where[] = ['is_delete', '=', $is_delete];

        //筛选状态用户
        if (isset($filter['status']) && is_numeric($filter['status'])) {
            $status = $filter['status'] ? Db::raw(1) : Db::raw(0);
            $where[] = ['status', '=', $status];
        }

        $lists  = UserModel::field($field)->alias('t')->where($where)->order('t.create_time DESC, t.id DESC')
            ->paginate($pagesize, false, ['query' => request()->param()])
            ->each(function ($item, $key) use ($siteListIdMap) {
                $siteData = $siteListIdMap[$item->site_id] ?? [];
                $item->site_name = $siteData['title'] ?? '';
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
            $data            = $this->request->post();
            $this->checkItemSiteAuth($data, 1); //检查权限

            if (empty($data['site_id'])) {
                return $this->jsonReturn(-1, '请选择站点');
            }

            $user = new UserModel();
            $validate   = new UserValidate();
            $validate_result = $validate->check($data);
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate->getError());
            }
            $passwordData = $user->createPassword(trim($data['password']), 1);
            $data['password'] = $passwordData['password'];
            $data['salt'] = $passwordData['salt'];

            // 查询该账号名是否和Carpool账号名冲突
            // $resCpUser = CarpoolUserModel::where('loginname', $data['account'])->find();
            // if (!empty($resCpUser)) {
            //     return $this->jsonReturn(-1, '请不要用Carpool用户名作为自定义授权名');
            // }

            if ($user->allowField(true)->save($data)) {
                $uid_n = $user->id; //插入成功后取得id
                $this->log('新加NPD用户成功，id=' . $uid_n, 0);
                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('新加NPD用户失败', -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->addPage(null, true, false);
        }
    }

    /**
     * 编辑用户
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        $user = new UserModel();

        if ($this->request->isPost()) {
            $itemRes = $this->getItemAndCheckAuthSite($user, $id);
            if (!$itemRes['auth']) {
                $this->jsonReturn(-1, '没有权限');
            }
            $data               = $this->request->post();
            $data['status']  = $this->request->post('status/d', 0);
            //开始验证字段
            $validate   = new UserValidate();
            if (!empty($data['password'])) {
                if (!$validate->scene('edit_change_password')->check($data)) {
                    return $this->jsonReturn(-1, $validate->getError());
                }
                $passwordData = $user->createPassword(trim($data['password']), 1);
                $data['password'] = $passwordData['password'];
                $data['salt'] = $passwordData['salt'];
            } else {
                if (!$validate->scene('edit')->check($data)) {
                    return $this->jsonReturn(-1, $validate->getError());
                }
                unset($data['password']);
            }
            // 查询该账号名是否和Carpool账号名冲突
            // $resCpUser = CarpoolUserModel::where('loginname', $data['account'])->find();
            // if (!empty($resCpUser)) {
            //     return $this->jsonReturn(-1, '请不要用Carpool用户名作为自定义授权名');
            // }

            $user = $user->find($id);

            if ($user->allowField(true)->save($data, ['id' => $id]) !== false) {
                $this->log('保存NPD用户成功，id=' . $id, 0);
                $user->deleteDetailCache($id, true);

                return $this->jsonReturn(0, '保存成功');
            } else {
                $this->log('保存NPD用户失败，id=' . $id, -1);
                return $this->jsonReturn(-1, '保存失败');
            }
        } else {
            return $this->editPage($user, $id, null, true);
            // $user = UserModel::find($id);
            // return $this->fetch('edit', ['data' => $user]);
        }
    }

    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        $dataModel = new UserModel();
        return $this->checkAuthAndDelete($dataModel, $id, true, '删除NPD用户');
    }

    /**
     * 明细
     */
    public function public_detail($id)
    {
        if (!$id) {
            return $this->error('Lost id');
        }
        $data = UserModel::find($id);
        $siteListIdMap = $this->getSiteListIdMap();
        $siteData = $siteListIdMap[$data['site_id']] ?? [];
        $data['site_name'] = $siteData['title'] ?? '';

        $returnData = [
            'data' => $data,
        ];
        return $this->fetch('detail', $returnData);
    }
}
