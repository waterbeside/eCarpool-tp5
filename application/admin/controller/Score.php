<?php

namespace app\admin\controller;

use app\score\model\History as HistoryModel;
use app\score\model\Configs as ScoreConfigsModel;
use app\score\model\AccountMix as AccountMixModel;
use app\score\model\Account as ScoreAccountModel;
use app\common\model\Docs as DocsModel;
use app\common\model\DocsCategory as DocsCategoryModel;
use my\RedisData;

use app\admin\controller\AdminBase;
use think\Db;

/**
 * 积分操作
 * Class Link
 * @package app\admin\controller
 */
class Score extends AdminBase
{


    public $check_dept_setting = [
        "action" => ['config']
    ];


    /**
     * 变更积分
     * @return mixed
     */
    public function change()
    {
        $type         = input('param.type/s');
        $account      = input('param.account/s');
        $account_id   = input('param.account_id/d', 0);
        $accountModel = new AccountMixModel();
        if (($type == '0' || $type == "score") && $account_id) { //直接从积分帐号取
            $accountInfo = $accountModel->getDetailById($account_id);
        } else {
            $accountInfo = $accountModel->getDetailByAccount($type, $account);
        }
        if (!$accountInfo) {
            $this->jsonReturn(-1, '查找不到该账号信息');
        }
        $department_id = $accountInfo['carpool']['department_id'];
        $this->checkDeptAuthByDid($department_id, 1); //检查地区权限

        if ($this->request->isPost()) {
            $datas         = $this->request->post('');
            if ($datas['operand'] == 0) {
                $this->jsonReturn(-1, '请输入分值');
            }
            if (!$datas['operand'] || !$datas['reason']) {
                $this->jsonReturn(-1, '参数错误');
            } else {
                $datas['operand'] = abs(intval($datas['operand']));
            }

            if (($datas['isadd'] && $datas['reason'] < 0) || (!$datas['isadd'] && $datas['reason'] > 0)) {
                $datas['reason']  = -$datas['reason'];
            }
            $datas['region_id'] = $department_id;
            if ($accountModel->updateScore($datas)) {
                $this->log('改分成功', 0);
                $this->jsonReturn(0, '改分成功');
            } else {
                $errorMsg = $accountModel->errorMsg ?  $accountModel->errorMsg : '改分失败';
                $this->log('改分失败' . json_encode($this->request->post()), -1);
                $this->jsonReturn(-1, $errorMsg);
            }
        } else {
            $reasons = config('score.reason');
            $reasons_operable = config('score.reason_operable');
            $reasonsArray = [];
            $lang = $this->activeLang;
            foreach ($reasons as $key => $value) {
                if (in_array($key, $reasons_operable)) {
                    $reasonsArray[] = ['code' => $key, 'title' => lang("sl:{$value}")];
                }
            }

            $returnData = [
                'accountInfo' => $accountInfo,
                'reasons' => $reasons,
                'reasonsArray' => $reasonsArray,
                'type' => $type,
                'account' => $account,
                'account_id' => $account_id,
            ];
            return $this->fetch('change', $returnData);
        }
    }

    /**
     * 更新积分(取消使用，已改用AccountMix模型里的方法)
     * @param  array $datas 更新的数据
     */
    public function updateScore($datas)
    {

        $type         = $datas['type'];
        $account_id   = isset($datas['account_id']) ? $datas['account_id'] : null;
        $account      = isset($datas['account']) ? $datas['account'] : null;
        $isadd        = $datas['isadd'];

        $data['operand']      = $datas['operand'];
        $data['reason']       = $datas['reason'];

        $accountType = config("score.account_type");
        $accountField = '';
        foreach ($accountType as $key => $value) {
            if ((is_numeric($type) && $key ==  $type) || $value['name'] == $type) {
                $accountField = $value['field'];
                break;
            }
        }

        if (!$accountField && !$account_id  && !$account) {
            // $this->jsonReturn(-1,'参数错误');
            return false;
        }
        Db::connect('database_score')->startTrans();
        try {
            //查找是否已开通拼车帐号，拼整理data
            $accountDetial = null;
            if (($type == '0' || $type == "score")  &&  $account_id) { //直接从积分帐号取
                $accountDetial = ScoreAccountModel::where([['id', '=', $account_id], ['is_delete', '=', Db::raw(0)]])->lock(true)->find();
            } elseif ($accountField) {
                $accountDetial = ScoreAccountModel::where([[$accountField, '=', $account], ['is_delete', '=', Db::raw(0)]])->lock(true)->find();
            }
            if ($accountDetial && $accountDetial['id']) {
                $data['account_id'] = $accountDetial['id'];
                $updateAccountMap = [
                    'id' => $accountDetial['id'],
                    'update_time' => $accountDetial['update_time']
                ];
                if ($isadd && $data['reason'] > 0) {
                    $data['result']    = intval($accountDetial['balance']) +  $data['operand'];
                    $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setInc('balance', $data['operand']);
                }
                if (!$isadd && $data['reason'] < 0) {
                    $data['result']    = intval($accountDetial['balance']) -  $data['operand'];
                    $upAccountStatus = ScoreAccountModel::where($updateAccountMap)->setDec('balance', $data['operand']);
                }
                if (!$upAccountStatus) {
                    throw new \Exception("更新分数失败");
                }
            } else {
                $data[$accountField] = $account;
                $data['result'] = 0;
            }
            $data['extra_info'] = '{}';
            $data['is_delete'] = 0;
            $data['time'] =  date('Y-m-d H:i:s');
            $historyModel =   new HistoryModel;
            $upHistoryStatus = $historyModel->save($data);
            if (!$upHistoryStatus) {
                throw new \Exception("更新分数失败");
            }
            // 提交事务
            Db::connect('database_score')->commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::connect('database_score')->rollback();
            $logMsg = '改分失败，请稍候再试' . json_encode($this->request->post());
            $this->log($logMsg, -1);
            return false;
        }
        $this->log('改分成功，' . json_encode($this->request->post()), 0);
        return true;
    }




    /**
     * 积分配置
     */
    public function config()
    {
        # 控制积分兑换&&积分抽奖&&实物抽奖开关
        // "order_switch" : 1,
        // "lottery_integral_switch" : 1,
        // "lottery_material_switch" : 1,

        $configs = $this->systemConfig;

        if ($this->request->isPost()) {
            $region_id =  $this->request->param('region_id');
            $datas          = $this->request->post('');
            $order_date     = explode(',', $datas['order_date']);
            $exchange_date  = explode(',', $datas['exchange_date']);
            if (!is_numeric($region_id)) {
                $this->error("请选择地区");
            }

            $data['order_date'] = $order_date;
            if ($datas['order_date'] == "*") {
                $soreSettingData['order_date'] = "*";
            } else {
                $soreSettingData['order_date'] = [];
                foreach ($order_date as $key => $value) {
                    if (is_numeric($value)) {
                        $soreSettingData['order_date'][] = intval($value);
                    }
                }
            }



            $data['exchange_date'] = $exchange_date;
            if ($datas['exchange_date'] == "*") {
                $soreSettingData['exchange_date'] = "*";
            } else {
                $soreSettingData['exchange_date'] = [];
                foreach ($exchange_date as $key => $value) {
                    if (is_numeric($value)) {
                        $soreSettingData['exchange_date'][] = intval($value);
                    }
                }
            }

            $soreSettingData['order_switch']              = isset($datas['order_switch'])            ? 1 : 0;
            $soreSettingData['lottery_integral_switch']   = isset($datas['lottery_integral_switch']) ? 1 : 0;
            $soreSettingData['lottery_material_switch']   = isset($datas['lottery_material_switch']) ? 1 : 0;

            if (!is_numeric($datas['lottery_integral_price']) || $datas['lottery_integral_price'] < 1) {
                $this->error("积分抽奖价必须为数字,并且要大于0");
            }
            $soreSettingData['lottery_integral_price']    = $datas['lottery_integral_price'];


            $map = [
                ['p_region_id', '=', $region_id],
                ['name', '=', 'integral_config'],
            ];
            $ScoreConfigsModel = new ScoreConfigsModel();
            $res = $ScoreConfigsModel->where($map)->find();

            $data = null;
            if ($res) {
                $res = $res->toArray();
                $data = json_decode($res['value'], true);
            }
            $soreSettingData = is_array($data) ? array_merge($data, $soreSettingData) : $soreSettingData;
            $soreSettingDataStr = json_encode($soreSettingData);

            $updataData = [
                'p_region_id' => $region_id,
                'name' => 'integral_config',
                'value' => $soreSettingDataStr,
                'title' => '积分配置',
            ];

            if ($res && $res['id']) {
                $map[] = ['id', "=", $res['id']];
                $updateRes = $ScoreConfigsModel->where($map)->update($updataData);
                $updateid =  $updateRes !== false ? $res['id'] : false;
            } else {
                $updateid = $ScoreConfigsModel->insertGetId($updataData);
            }
            if (!$updateid) {
                return $this->jsonReturn(-1, '更新失败');
            }

            $redis = new RedisData();
            $redis->delete("CONFIG_SETTING:" . $region_id);


            $this->log('修改积分配置成功', 0);
            $this->success("修改成功");
        } else {
            $region_id =  $this->request->param('region_id', 1);

            //地区排查 检查管理员管辖的地区部门
            $authDeptData = $this->authDeptData;
            // dump($authDeptData);exit;
            if (empty($authDeptData['allow_region_ids']) && !$this->userBaseInfo['auth_depts_isAll']) {
                $region_id = $authDeptData['filter_region_ids'][0];
            }
            $regionData = isset($authDeptData['filter_region_datas'][0]) ? $authDeptData['filter_region_datas'][0] : null;

            $map = [
                'p_region_id' => $region_id,
                'name' => 'integral_config'
            ];
            $res = ScoreConfigsModel::where($map)->find();
            $data = null;
            if ($res) {
                $res = $res->toArray();
                $data = json_decode($res['value'], true);
                $data['order_date_str'] =  is_array($data['order_date']) ? implode(',', $data['order_date']) : $data['order_date'];
                $data['exchange_date_str'] = is_array($data['exchange_date']) ? implode(',', $data['exchange_date']) : $data['exchange_date'];
            }
            //
            /*$data['(order_switch)']             =  isset($data['order_switch'])             ? $data['order_switch']            : 0  ;
        $data['lottery_integral_switch']  =  isset($data['lottery_integral_switch'])  ? $data['lottery_integral_switch'] : 0  ;
        $data['lottery_material_switch']  =  isset($data['lottery_material_switch'])  ? $data['lottery_material_switch'] : 0  ;
        $data['lottery_integral_price']   =  isset($data['lottery_integral_price'])   ? $data['lottery_integral_price']  : 0  ;*/

            $returnData = [
                'regionData' => isset($regionData) ? $regionData : null,
                'region_id' => $region_id,
                'res' => $res,
                'data' => $data
            ];
            return $this->fetch('config', $returnData);
        }
    }


    /**
     * 文档管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function doc($cate = 'score', $keyword = '', $page = 1)
    {

        $map   = [];
        $field = 't.id,t.title,t.cid,t.update_time,t.create_time,t.listorder,t.status,t.lang,c.name as cate, c.title as cate_title';

        if ($cate) {
            $map[] = ['c.name', '=', $cate];
        }

        if (!empty($keyword)) {
            $map[] = ['title', 'like', "%{$keyword}%"];
        }

        $join = [
            ['docs_category c', 't.cid = c.id', 'left'],
        ];
        $lists  = DocsModel::field($field)->alias('t')->join($join)
            ->where($map)
            ->order('t.cid DESC , t.create_time DESC')
            ->paginate(15, false, ['page' => $page]);
        // $category_list = $this->category_model->field('id,name,title')->where([['is_delete', '=', Db::raw(0)]])->select();
        $category_list = DocsCategoryModel::column('title', 'name');
        return $this->fetch('doc', ['lists' => $lists, 'category_list' => $category_list, 'cate' => $cate, 'keyword' => $keyword]);
    }


    /**
     * 添加文档
     * @return mixed
     */
    public function doc_add($cate = 'score')
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'Docs');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                $docsModel = new DocsModel();
                if ($docsModel->allowField(true)->save($data)) {
                    $this->log('保存文档成功', 0);
                    $this->jsonReturn(0, '保存成功');
                } else {
                    $this->log('保存文档失败', -1);
                    $this->jsonReturn(-1, '保存失败');
                }
            }
        } else {
            $category_list = DocsCategoryModel::where([['is_delete', '=', Db::raw(0)]])->select();
            $cate_data = '';
            foreach ($category_list as $key => $value) {
                if ($value['name'] == $cate) {
                    $cate_data = $value;
                }
            }

            return $this->assign('category_list', $category_list)
                ->assign('cate_data', $cate_data)
                ->assign('cate', $cate)
                ->fetch();
        }
    }



    /**
     * 编辑文档
     * @param $id
     * @return mixed
     */
    public function doc_edit($id)
    {
        if ($this->request->isPost()) {
            $data            = $this->request->param();
            $validate_result = $this->validate($data, 'Docs');
            $data['description'] = $data['description'] ? iconv_substr($data['description'], 0, 250) : '';
            if ($validate_result !== true) {
                $this->jsonReturn(-1, $validate_result);
            } else {
                unset($data['cid']);
                $docsModel = new DocsModel();
                if ($docsModel->allowField(true)->save($data, $id) !== false) {
                    $this->log('编辑文档成功', 0);
                    $this->jsonReturn(0, '修改成功');
                } else {
                    $this->log('编辑文档失败', -1);
                    $this->jsonReturn(-1, '修改失败');
                }
            }
        } else {
            $field = 't.*,c.name as cate, c.title as cate_title';
            $join = [
                ['docs_category c', 't.cid = c.id', 'left'],
            ];
            $data = DocsModel::field($field)->alias('t')->join($join)->find($id);
            $category_list = DocsCategoryModel::where([['is_delete', '=', Db::raw(0)]])->select();

            return $this->fetch('doc_edit', ['data' => $data, 'category_list' => $category_list]);
        }
    }




    /**
     * 积分导入
     * @param  integer $page [description]
     * @return [type]        [description]
     */
    public function test_multi_balance($page = 1)
    {
        $lists = Db::connect('database_carpool')->table('temp_carpool_score')->page($page, 1)->select();
        exit;
        if (count($lists) > 0) {
            $msg =   "";
            foreach ($lists as $key => $value) {
                $data = [
                    'type' => 'carpool',
                    'account' => $value['loginname'],
                    'operand' =>  $value['balance'],
                    'reason' => 1,
                    'isadd' => 1,
                ];
                if ($value['status'] > 0) {
                    $msg .=  "id:" . $value['id'] . ";" .
                        "account:" . $data['account'] . ";" .
                        "operand:" . $data['operand'] . ";" .
                        "   Has finished <br />";
                    continue;
                }
                // dump($data);
                $is_ok = $this->updateScore($data);
                // dump($is_ok);exit;
                if ($is_ok) {
                    $st = Db::connect('database_carpool')->table('temp_carpool_score')->where("id", $value['id'])->update(['status' => 1]);
                    $msg .=  "id:" . $value['id'] . ";" . "account:" . $data['account'] . ";" . "operand:" . $data['operand'] . ";" . "   OK   ";
                } else {
                    $msg .=  "id:" . $value['id'] . ";" . "account:" . $data['account'] . ";" . "operand:" . $data['operand'] . ";" . "   fail ";
                }
            }
            $page = $page + 1;
            $url  = url('admin/Score/test_multi_balance', ['page' => $page]);
        } else {
            $url  = '';
            $msg = "完成全部操作";
        }

        return $this->fetch('index/multi_jump', ['url' => $url, 'msg' => $msg]);
    }
}
