<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\WallReply;


use think\Db;

/**
 * 行程评论
 * Class TripComments
 * @package app\api\controller
 */
class TripComments extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        $this->checkPassport(1);
    }


    public function index($id, $pagesize = 0, $num = 0, $getcount = 0, $order = "ASC")
    {
        if (!$id) {
            return $this->jsonReturn(992, [], lang('Parameter error'));
        }
        $map = [
            ['love_wall_ID', '=', $id]
        ];
        if (!in_array(mb_strtolower($order), ['asc', 'desc'])) {
            $order = 'ASC';
        }
        $orderby = "t.reply_time {$order}";
        $join = [
            ['user u', 'u.uid = t.uid', 'left']
        ];

        // $sql = $modelObj->fetchSql()->select();
        if ($getcount > 0) {
            $total = WallReply::alias('t')->where($map)->count();
            return $this->jsonReturn(0, ['total' => $total], "success");
        }
        $pageData = null;
        $modelObj =  WallReply::alias('t')->field('t.* , u.name, u.loginname, u.Department as department,imgpath')
            ->join($join)
            ->where($map)
            ->order($orderby);

        if ($pagesize > 0) {
            $results =    $modelObj->paginate($pagesize, false, ['query' => request()->param()])->toArray();
            if (!$results['data']) {
                return $this->jsonReturn(20002, $results, lang('No data'));
            }
            $datas = $results['data'];
            $pageData = [
                'total' => $results['total'],
                'pageSize' => $results['per_page'],
                'lastPage' => $results['last_page'],
                'currentPage' => intval($results['current_page']),
            ];
            $total = $pageData['total'];
        } elseif ($num > 0) {
            $total = WallReply::alias('t')->where($map)->count();
            $datas =    $modelObj->limit($num)->select();
        } else {
            $datas =    $modelObj->select();
        }

        if (!$datas) {
            return $this->jsonReturn(20002, $results, lang('No data'));
        }
        foreach ($datas as $key => $value) {
            $value['id'] = $value['love_wall_reply_id'];
            $value['time']  = strtotime($value['reply_time']);
            unset($value['love_wall_reply_id']);
            $datas[$key]   = $value;
        }
        $total = isset($total) ? $total : count($datas);
        $returnData = [
            'lists' => $datas,
            'page' => $pageData,
            'total' => $total,
        ];
        $this->jsonReturn(0, $returnData, "success");
    }


    /**
     * 新建评论
     * @param  integer $id love_wall_ID
     * @return [type]     [description]
     */
    public function save($id)
    {
        $content    = input('post.content');
        $uid        = $this->userBaseInfo['uid'];
        if (trim($content) == '') {
            $this->jsonReturn(992, [], lang('Please enter content'));
        }
        $data = [
            'uid' => $uid,
            'love_wall_ID' => $id,
            'content' => $content,
            'reply_time' => date('Y-m-d H:i:s', time()),
        ];
        // $result = false;
        $result = WallReply::insertGetId($data);
        if ($result) {
            $this->jsonReturn(0, ['id' => $result], "success");
        } else {
            $this->jsonReturn(-1, lang('Fail'));
        }
    }
}
