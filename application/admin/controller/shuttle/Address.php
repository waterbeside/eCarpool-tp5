<?php

namespace app\admin\controller\shuttle;

use app\carpool\model\User as UserModel;
use app\user\model\Department;
use app\carpool\model\ShuttleTime as ShuttleTimeModel;
use app\carpool\model\Address as AddressModel;
use app\admin\controller\AdminBase;
use think\facade\Validate;
use my\Utils;
use think\Db;

/**
 * 班车可选时间管理
 * Class Address
 * @package app\admin\controller
 */
class Address extends AdminBase
{
    /**
     *
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $page = 1, $pagesize = 50)
    {
        $map = [];
        $is_delete = isset($filter['is_delete']) &&  $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $map[] = ['is_delete', '=', $is_delete];
        if (isset($filter['type']) && $filter['type']) {
            $map[] = ['address_type', '=', $filter['type']];
        } else {
            $map[] = ['address_type', '>', 2];
        }
        if (isset($filter['keyword']) && $filter['keyword']) {
            $keyword = $filter['keyword'];
            $keywordSplit = explode('&&', $keyword);
            foreach ($keywordSplit as $key => $value) {
                $keywordSplit_2 = explode(':', $value);
                if (count($keywordSplit_2) < 2) {
                    $map[] = ['', 'exp', Db::raw("addressname like '%{$keyword}%' or city = '{$keyword}' ")];
                } else {
                    $fields = $keywordSplit_2[0];
                    $fieldsSplit = explode('|', $fields);
                    $fieldsArray = array_intersect($fieldsSplit, ['id','addressname','city','address','district','status']);
                    $field = implode('|', $fieldsArray);
                    $v = $keywordSplit_2[1];
                    // if (in_array($fields[0], ['addressname','city','address','dist'])) {
                    // }
                    if ($v == 'null') {
                        $map[] = ['', 'exp', Db::raw("$field is null ")];
                    } elseif (count($fieldsArray) === 1 && in_array($field, ['id', 'status'])) {
                        $map[] = [$field, '=', $v];
                    } else {
                        $map[] = [$field, 'like', "%{$v}%"];
                    }
                }
            }
        }
        

        $fields = '*, longtitude as longitude';
        $list = AddressModel::field($fields)->where($map)->order('ordernum DESC, create_time DESC , addressid DESC ')
            ->paginate($pagesize, false, ['query' => request()->param()]);
        $returnData = [
            'list' => $list,
            'filter' => $filter,
            'pagesize' => $pagesize,
        ];
        return $this->fetch('index', $returnData);
    }


    /**
     * 添加地址
     * @return mixed
     */
    public function add($data = null)
    {
        if ($this->request->isPost()) {
            $admin_id = $this->userBaseInfo['uid'];
            $data = $data ? $data : $this->request->param();
            $data['create_uid'] = -1 * $admin_id;
            if (isset($data['batch']) && $data['batch']) {
                return $this->batch_add();
            }
            $data['longtitude'] = $data['longitude'];
            $validate_result = $this->validate($data, 'app\carpool\validate\address');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            $AddressModel = new AddressModel();
            $res = $AddressModel->allowField(true)->save($data);
            if (!$res) {
                return  $this->jsonReturn(-1, '保存失败');
            }
            $id = $AddressModel->addressid;
            return  $this->jsonReturn(0, ['id'=>$id], '保存成功');
        } else {
            $tpl = input('param.batch/d', 0) == 1 ? 'batch_add' : 'add';
            return $this->fetch($tpl);
        }
    }

    public function batch_add()
    {
        # code...
    }


    /**
     * 编辑时间
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $validate_result = $this->validate($data, 'app\carpool\validate\Address');
            if ($validate_result !== true) {
                return $this->jsonReturn(-1, $validate_result);
            }
            $data['longtitude'] = $data['longitude'];
            $AddressModel = new AddressModel();
            $res = $AddressModel->allowField(true)->save($data, ['addressid'=>$id]);
            if ($res === false) {
                $this->log('修改站点失败，id=' . $id, -1);
                return $this->jsonReturn(-1, '保存失败');
            }
            $this->log('修改站点成功，id=' . $id, 0);
            return $this->jsonReturn(0, '保存成功');
        } else {
            $fields = "t.*";
            $data = AddressModel::alias('t')->field($fields)->find($id);
            $this->assign('data', $data);
            return $this->fetch('edit');
        }
    }



    /**
     * 删除时间
     * @param $id 数据id
     */
    public function delete($id)
    {
        $ShuttleTime = new ShuttleTimeModel();
        $data = $ShuttleTime->alias('t')->find($id);
        if ($ShuttleTime->where('id', $id)->update(['is_delete' => 1]) !== false) {
            $ShuttleTime->delListCache($data['type']);
            $ShuttleTime->delListCache(-1);
            $this->log('删除班车时刻成功，id=' . $id, 0);
            return $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除班车时刻失败，id=' . $id, -1);
            return $this->jsonReturn(-1, '删除失败');
        }
    }

    public function public_selects($filter = null, $is_ajax = false, $fun = "FORM_PAGE_EXEC.selectAddressItem")
    {
        $map = [];
        $is_delete = isset($filter['is_delete']) &&  $filter['is_delete'] ? Db::raw(1) : Db::raw(0);
        $map[] = ['is_delete', '=', $is_delete];
        if (isset($filter['type']) && $filter['type']) {
            $map[] = ['address_type', '=', $filter['type']];
        } else {
            $map[] = ['address_type', '>', 2];
        }
        if (isset($filter['id']) && $filter['id']) {
            $map[] = ['addressid', '=', $filter['id']];
        }
        if (isset($filter['keyword']) && $filter['keyword']) {
            $keyword = $filter['keyword'];
            $map[] = ['addressname|city', 'like', "%{$keyword}%"];
        }
        $fields = 'addressname, addressid, latitude, longtitude as longitude, is_delete, status';
        $ctor = AddressModel::field($fields)->where($map)->order('ordernum DESC, create_time DESC , addressid DESC ');
        $returnData = Utils::getInstance()->getListDataByCtor($ctor, 20);
        if ($is_ajax) {
            $returnData['pagination'] = $returnData['page'];
            $returnData['filter'] = $filter;
            $returnData['fun'] = $fun;
            $returnData['param'] = input('param.');
            return $this->jsonReturn(0, $returnData);
        } else {
            $this->assign('lists', $returnData['lists']);
            $this->assign('pagination', $returnData['page']);
            $this->assign('filter', $filter);
            $this->assign('fun', $fun);
            $this->assign('param', input('param.'));
        }
        
        return $this->fetch('public_selects');
        
    }
}
