<?php

namespace app\api\controller\v1\publics;

use app\api\controller\ApiBase;
use app\content\model\Comment as CommentModel;
use my\RedisData;


use think\Db;

/**
 * 评论
 * Class Comments
 * @package app\api\controller
 */
class Comments extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    /**
     * 列表
     * @param  integer $pagesize  每页条数
     */
    public function index($type = 0, $oid = 0, $pagesize = 24)
    {
        $filter['keyword']      = trim(input('param.keyword'));
        $filter['pid']          = input('param.pid', 0);
        $filter['is_delete']     = input('param.is_delete', 0);


        $fields = "t.id,t.user_id,t.parent_id,t.content,t.object_id,t.type,t.is_delete,t.post_time,u.name,u.loginname";
        $map = [];

        if ($oid) {
            $map[] = ["t.object_id", "=", $oid];
        }
        if ($type) {
            $map[] = ["t.type", "=", $type];
        }
        if (is_numeric($filter['is_delete']) && $filter['is_delete'] > -1) {
            $map[] = ['t.is_delete', "=", Db::raw($filter['is_delete'])];
        }
        if ($filter['pid']) {
            $map[] = ["t.parent_id", "=", $filter['pid']];
        } else {
            $map[] = ["t.parent_id", "exp", Db::raw("IS NULL")];
        }




        if ($filter['keyword']) {
            $map[] = ['t.title|t.desc', 'like', '%' . $filter['keyword'] . '%'];
        }

        $join = [
            ['user u', 'u.uid = t.user_id', 'left'],
        ];
        $orderby = "post_time ASC";
        $results = CommentModel::alias('t')->field($fields)
        ->json(['images'])
        ->where($map)
        ->join($join)
        ->order($orderby)
        ->paginate($pagesize, false, ['query' => request()->param()])->toArray();

        $datas = $results['data'];
        if (!$datas) {
            $this->jsonReturn(20002, [], 'No Data');
        }
        // dump($datas);exit;
        foreach ($datas as $key => $value) {
            // $datas[$key]['post_time']   =
            $datas[$key]['time']   = strtotime($value['post_time']);
        }
        $pageData = [
            'total' => $results['total'],
            'pageSize' => $results['per_page'],
            'lastPage' => $results['last_page'],
            'currentPage' => intval($results['current_page']),
        ];
        $returnData = [
            'lists' => $datas,
            'page' => $pageData,
            'filter' => $filter
        ];
        $this->jsonReturn(0, $returnData, 'success');
    }



    /**
     * 详情
     * @param  integer id  id
     */
    public function read($id)
    {
        $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,t.is_delete,u.name,u.phone,u.loginname";
        $map = [
            ["t.id", "=", $id],
        ];
        $join = [
            ['user u', 'u.uid = t.user_id', 'left']
        ];
        $orderby = "t.status DESC, post_time DESC";
        $datas = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->find();
        if (!$datas || $datas['is_delete'] == 1) {
            $this->jsonReturn(20002, [], 'No Data');
        }
        $datas['time']   = strtotime($datas['post_time']);
        $datas['images'] = json_decode(json_encode($datas['images']), true);
        $imagesList = [];
        foreach ($datas['images'] as $key => $value) {
            if (isset($value['path'])) {
                $value['path'] = str_replace('http:/g', 'http://g', $value['path']);
            }
            $imagesList[] = $value;
        }
        $datas['images'] = $imagesList;
        $datas['thumb']       = isset($datas['images'][0]['path']) ? $datas['images'][0]['path'] : '';
        unset($datas['is_delete']);
        $this->jsonReturn(0, $datas, 'success');
    }
}
