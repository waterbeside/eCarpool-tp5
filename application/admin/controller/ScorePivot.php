<?php

namespace app\admin\controller;

use think\facade\Env;
use app\admin\controller\AdminBase;
use app\carpool\model\User as UserModel;
use app\carpool\model\Company as CompanyModel;
use app\user\model\Department as DepartmentModel;
use app\score\model\AccountMix as AccountMixModel;
use app\score\model\Order as OrderModel;
use app\score\model\Goods as GoodsModel;
use app\score\model\OrderGoods as OrderGoodsModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use think\Db;

/**
 * 积分透视
 * Class ScoreGoods
 * @package app\admin\controller
 */
class ScorePivot extends AdminBase
{

    public $check_dept_setting = [
        "action" => ['order_goods',]
    ];

    /**
     * 订单-商品透视
     * @return mixed
     */
    public function order_goods($filter = ['status' => 0], $page = 1, $pagesize = 20, $export = 0)
    {
        // ********** stop - 0 预处理筛选参数；
        $mapBase = [];
        $mapBase[] = ['o.is_delete', '=', Db::raw(0)];

        //筛选状态
        if (is_numeric($filter['status'])) {
            $mapBase[] = ['o.status', '=', $filter['status']];
        } elseif ($filter['status'] == "all_01") {
            $mapBase[] = ['o.status', 'between', [0, 1]];
        }
        $joinBase = [
            ['carpool.t_department d', 'o.region_id = d.id', 'left']
        ];

        //地区排查 检查管理员管辖的地区部门
        $authDeptData = $this->authDeptData;
        if (isset($authDeptData['region_id']) && $authDeptData['region_map']) {
            $mapBase[] = $authDeptData['region_map'];
        }
        // dump($authDeptData['region_map']);

        //筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d H:i:s', 'd');
        if (count($time_arr) > 1) {
            $mapBase[] = ['o.creation_time', '>=', $time_arr[0]];
            $mapBase[] = ['o.creation_time', '<', $time_arr[1]];
        }

        // step - 1 算出当前筛选的所有商品
        $fieldsGoods = "t.gid,g.id, g.name, g.price, g.amount, sum(t.gid) as num";
        $mapGoods = [];
        $mapGoods[] = ['t.is_delete', '=', Db::raw(0)];
        $joinGoods = [
            ['goods g', 'g.id = t.gid', 'left'],
            ['order o', 'o.id = t.oid', 'left'],
        ];
        $joinGoods = array_merge($joinGoods, $joinBase);
        $mapGoods = array_merge($mapBase, $mapGoods);
        $goods_list = OrderGoodsModel::alias('t')->field($fieldsGoods)->join($joinGoods)->where($mapGoods)->group('t.gid')->select();
        $goodsList = []; //商品缓存

        foreach ($goods_list as $key => $value) {
            $goodsList[$value['gid']] = $value->toArray();
        }

        // dump($goodsList);exit;

        // step - 2 算出各订单对应的商品数
        $fields = "o.id, o.region_id, o.uuid, o.creator, o.platform, o.creation_time, o.content, o.status, o.total, 
            ac.carpool_account,
            d.fullname as full_department";
        $join = [
            ['account ac', 'o.creator = ac.id', 'left'],
        ];
        $join = array_merge($join, $joinBase);

        $ModelBase = OrderModel::alias('o')->field($fields)->join($join)->where($mapBase);
        if ($export) {
            $lists = $ModelBase->select();
        } else {
            $lists = $ModelBase
                ->paginate($pagesize, false, ['query' => request()->param()]);
        }
        $UserModel = new UserModel();
        $GoodsModel = new GoodsModel();

        foreach ($lists as $key => $value) {
            $goods = []; //商品
            $content = json_decode($value['content'], true);
            if ($content) {
                foreach ($content as $gid => $num) {
                    if (isset($goodsList[$gid])) {
                        $good = $goodsList[$gid];
                    } else {
                        $good = $GoodsModel->getItem($gid);
                        $goodsList[$gid] =  $good ? $good : [];
                    }
                    if (!$good) {
                        $good['id'] = $gid;
                        $good['name'] = "#" . $gid;
                    }
                    $good['num'] = $num;
                    $goods[$gid] =  $good;
                }
                $lists[$key]['goods'] = $goods;
            }
            $userData = $UserModel->getDetail($value['carpool_account']);
            $lists[$key]['user'] = $userData ? $userData : [
                'uid' => 0,
                'name' => '-',
                'nativename' => '-',
                'loginname' => '-',
                'phone' => '-',
                'companyname' => '-',
                'department_fullname' => '-',
            ];
            if ($value['region_id'] === 0 && $userData['department_id'] > 0) {
                OrderModel::where([['id', '=', $value['id']]])->update(['region_id' => $userData['department_id']]);
            }
        }
        $statusList = config('score.order_status');
        $returnData = [
            'filter' => $filter,
            'pagesize' => $pagesize,
            'status' => $filter['status'],
            'statusList' => $statusList,
            'goodsList' => $goodsList,
            'lists' => $lists,
        ];

        $template = $export ? '../application/admin/view/score_pivot/order_goods_export.php' : 'order_goods';
        return $this->fetch($template, $returnData);
    }
}
