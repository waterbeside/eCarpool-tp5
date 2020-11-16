<?php

namespace app\admin\controller\mis\wip;

use app\mis\model\LogTempData as TempDatalModel;

use app\admin\controller\AdminBase;
use think\Db;

use function GuzzleHttp\json_decode;

/**
 * 数码印花列表管理
 * Class LogTempData
 * @package app\admin\controller
 */

class LogTempData extends AdminBase
{

    /**
     * 数据管理
     * @param int    $cid     分类ID
     * @param string $keyword 关键词
     * @param int    $page
     * @return mixed
     */
    public function index($cid = 0, $filter = ['keyword' => ''], $page = 1)
    {
        $map   = [];
        $map[] = ['t.status', '=', Db::raw(1)];
        $field = 't.*';
        $pagesize = 20;
        $TempDatalModel = new TempDatalModel();

        if (isset($filter['keyword']) && $filter['keyword']) {
            $map[] = ['sql_name', 'like', "%{$filter['keyword']}%"];
        }
        // /筛选时间
        if (!isset($filter['time']) || !$filter['time']) {
            $filter['time'] =  $this->getFilterTimeRangeDefault('Y-m-d', 'm');
        }
        $time_arr = $this->formatFilterTimeRange($filter['time'], 'Y-m-d 00:00:00', 'd');
        if (count($time_arr) > 1) {
            $map[] = ['t.time', '>=', $time_arr[0]];
            $map[] = ['t.time', '<', $time_arr[1]];
        }



        $lists  = TempDatalModel::field($field)->alias('t')->where($map)->order(' t.time DESC, t.create_date DESC, t.id DESC ')
            // ->fetchSql()->select();
            ->paginate($pagesize, false, ['page' => $page]);

        foreach ($lists as $key => $value) {
            try {
                $lists[$key]['jsonData'] = json_decode($value['data'], true);

            } catch (\Throwable $th) {
                $lists[$key]['jsonData'] = null;
            }
        }

        $returnData = [
            'lists' => $lists,
            'filter' => $filter,
            'pagesize' => $pagesize,
        ];


        return $this->fetch('index', $returnData);
    }




    /**
     * 明细
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $TempDatalModel = new TempDatalModel();
        $dataDetail     = $TempDatalModel->find($id);
        try {
            $dataDetail['jsonData'] = json_decode($dataDetail['data'], true);
        } catch (\Throwable $th) {
            $dataDetail['jsonData'] = null;
        }
        $fields = [];
        if ($dataDetail['jsonData']) {
            foreach ($dataDetail['jsonData'][0] as $field => $value) {
                $fields[] = $field;
            }
        }
        return $this->fetch('show', ['data' => $dataDetail, 'fields' => $fields]);
    }



    /**
     * 删除数据
     * @param $id
     */
    public function delete($id)
    {
        if (TempDatalModel::where('id', $id)->delete()) {
            $this->log('删除数据成功', 0);
            $this->jsonReturn(0, '删除成功');
        } else {
            $this->log('删除数据失败', -1);
            $this->jsonReturn(-1, '删除失败');
        }
    }
}
