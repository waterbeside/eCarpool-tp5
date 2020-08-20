<?php
namespace app\api\controller\v1\content;

use think\Db;
use app\api\controller\ApiBase;
use app\content\model\Idle as IdleModel;
use app\content\model\IdleCategory as IdleCategoryModel;
use app\content\model\Colletion as ColletionModel;
use app\content\model\Like;
use my\RedisData;

/**
 * 二手市场公开信息
 * Class Idle
 * @package app\api\controller
 */
class Idle extends ApiBase
{


    /**
     * 我的发布
     * @param  integer $pagesize  每页条数
     */
    public function my($pagesize = 24)
    {
        $this->checkPassport(1);
        $uid = $this->userBaseInfo['uid'];

        $filter['keyword']      = trim(input('param.keyword'));

        $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time, t.polish_time";
        $map = [
            ["t.is_delete", "=", Db::raw(0)],
            ["t.user_id", "=", $uid],
            // ["show_level",">",0],
        ];

        if ($filter['keyword']) {
            $map[] = ['t.title|t.desc', 'like', '%' . $filter['keyword'] . '%'];
        }

        $join = [
            ['user u', 'u.uid = t.user_id', 'left'],
            ['(select max(category_id) as category_id ,idle_id from t_idle_category group by idle_id) ic', 'ic.idle_id = t.id', 'left']
        ];
        $orderby = "t.status DESC, polish_time DESC, post_time DESC";
        $results = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->order($orderby)->paginate($pagesize, false, ['query' => request()->param()])->toArray();

        $datas = $results['data'];
        if (!$datas) {
            $this->jsonReturn(20002, [], 'No Data');
        }
        // dump($datas);exit;
        foreach ($datas as $key => $value) {
            // $datas[$key]['post_time']   =
            $datas[$key]['time']   = strtotime($value['post_time']);
            $datas[$key]['polish_time']   = strtotime($value['polish_time']);
            $datas[$key]['thumb']       = isset($value['images'][0]->path) ? $value['images'][0]->path : '';
            $datas[$key]['thumb']       = str_replace('http:/g', 'http://g', $datas[$key]['thumb']);
            unset($datas[$key]['images']);
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
     * 我的收藏
     *
     */
    public function collections($pagesize = 24)
    {
        $this->checkPassport(1);
        $uid = $this->userBaseInfo['uid'];
        $filter['keyword']      = trim(input('param.keyword'));

        $fields = "t.id,t.user_id,t.title,t.desc,t.images,t.location,t.price,t.status,t.is_seller,t.post_time,t.polish_time,
        u.name,u.phone,u.loginname, c.create_time as collect_time";
        $map = [
            ["t.is_delete", "=", Db::raw(0)],
            ["c.is_delete", "=", Db::raw(0)],
            ["c.type", "=", 100],
            ["c.user_id", "=", $uid],
            // ["show_level",">",0],
        ];

        if ($filter['keyword']) {
            $map[] = ['t.title|t.desc', 'like', '%' . $filter['keyword'] . '%'];
        }

        $join = [
            ['user u', 'u.uid = t.user_id', 'left'],
            ['t_colletion c', 'c.object_id = t.id', 'left'],
        ];
        $orderby = "t.polish_time DESC, c.create_time DESC, t.status DESC, t.post_time DESC";
        $results = IdleModel::alias('t')->field($fields)->json(['images'])->where($map)->join($join)->order($orderby)->paginate($pagesize, false, ['query' => request()->param()])->toArray();

        $datas = $results['data'];
        if (!$datas) {
            $this->jsonReturn(20002, [], 'No Data');
        }
        foreach ($datas as $key => $value) {
            $datas[$key]['time']   = strtotime($value['post_time']);
            $datas[$key]['polish_time']   = strtotime($value['polish_time']);
            $datas[$key]['collect_time']   = strtotime($value['collect_time']);
            $datas[$key]['thumb']       = isset($value['images'][0]->path) ? $value['images'][0]->path : '';
            $datas[$key]['thumb']       = str_replace('http:/g', 'http://g', $datas[$key]['thumb']);
            unset($datas[$key]['images']);
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
     * 更改收藏
     *
     */
    public function collect($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        
        $ColletionModel = new ColletionModel();

        $isCollected= $ColletionModel->isCollected($id, $uid, 100, true); // 查询是否已收藏过

        if ($isCollected) { // 如果已收藏，则取消收藏
            $results = $ColletionModel->unCollect($id, $uid, 100, false);
            if ($results) {
                return $this->jsonReturn(0, ['isCollected' => false], '取消收藏成功');
            }
            return $this->jsonReturn(-1, ['isCollected' => true], 'Failed');
        }
        // 如果未收藏，则添加收藏
        $insertId = $ColletionModel->collect($id, $uid, 100, false);
        if ($insertId) {
            return $this->jsonReturn(0, ['isCollected' => true], '收藏成功');
        }
        return $this->jsonReturn(-1, ['isCollected' => false], 'Failed');
    }

    /**
     * 赞
     *
     */
    public function like($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $Like = new Like();
        $insertId = $Like->like($id, $uid, 100);
        if (!$insertId) { // 如果已点赞
            $error = $Like->getError();
            if ($error['code'] === 0) {
                return $this->jsonReturn(0, $error['msg']);
            }
            return $this->jsonReturn(-1, 'Failed');
        }
        return $this->jsonReturn(0, '点赞成功');
    }

    /**
     * 取得该商品是否与用户有关联，（是否已收藏或点赞）
     *
     * @param integer $id 二手商品id
     */
    public function related($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];

        $Like = new Like();
        $isLiked= $Like->isLiked($id, $uid, 100); // 查询是否赞过

        $ColletionModel = new ColletionModel();
        $isCollected= $ColletionModel->isCollected($id, $uid, 100); // 查询是否已收藏过

        $returnData = [
            'isLiked' => $isLiked ? true : false,
            'isCollected' => $isCollected ? true : false,
        ];
        return $this->jsonReturn(0, $returnData, 'Successful');
    }


    /**
     * 更新字段
     *
     * @param integer $id 二手商品id
     */
    public function change($id)
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        
        $Idle = IdleModel::get($id);
        if (!$Idle) {
            return $this->jsonReturn(20002, '数据不存在或已删除');
        }
        if ($uid !== $Idle->user_id) {
            return $this->jsonReturn(30001, '你不能操作别人发布的数据');
        }

        $run = input('post.run');
        if (!in_array($run, ['polish', 'sold_out', 'delete'])) {
            return $this->jsonReturn(30001, lang('Illegal access'));
        }
        if ($run === 'polish') {
            $successMsg = "刷亮成功";
            $Idle->polish_time = date('Y-m-d H:i:s');
        } elseif ($run === 'sold_out') {
            $successMsg = "修改成功";
            $Idle->statue = -1;
        } elseif ($run === 'delete') {
            $successMsg = "删除成功";
            $Idle->is_delete = 1;
        }
        $res = $Idle->save();
        if ($res) {
            return $this->jsonReturn(0, $successMsg);
        }
        return $this->jsonReturn(0, lang('failed'));
    }



    /**
     * 发布
     *
     */
    public function save()
    {
        $userData = $this->getUserData(1);
        $uid = $userData['uid'];
        $company_id = $userData['company_id'];
        $data = $this->getPostData();
        $data['post_time'] = date('Y-m-d H:i:s');
        $data['polish_time'] = date('Y-m-d H:i:s');
        $data['user_id'] = $uid;
        $data['company_id'] = $company_id;

        $validate = new \app\content\validate\Idle;
        $checkResult = $validate->check($data);
        if (!$checkResult) {
            return $this->jsonReturn(992, $validate->getError());
        }
        Db::connect('database_carpool')->startTrans();
        try {
            $IdleModel = new IdleModel();
            $IdleModel->allowField(true)->save($data);
            $id = $IdleModel->id;
            $cateData = [];
            foreach ($data['categories'] as $key => $cate_id) {
                if (is_numeric($cate_id) && $cate_id > 0) {
                    $cateData[] = [
                        'idle_id' => $id,
                        'category_id' => $cate_id
                    ];
                }
            }
            if (empty($cateData)) {
                throw new \Exception("分类参数不正确");
            }
            IdleCategoryModel::insertAll($cateData);

            Db::connect('database_carpool')->commit();
        } catch (\Exception $e) {
            Db::connect('database_carpool')->rollback();
            $errorMsg = $e->getMessage();
            return $this->jsonReturn(-1, null, '保存失败', ['errMsg'=>$errorMsg]);
        }
        return $this->jsonReturn(0, ['id'=>$id], lang('Successfully'));
    }


    /**
     * 取得发布或修改时所提交的数据
     *
     * @return array
     */
    protected function getPostData()
    {
        $data['title'] = input('post.title');
        $data['desc'] = input('post.desc');
        $data['images'] = input('post.images');
        $data['is_seller'] = input('post.is_seller');
        $data['price'] = input('post.price');
        $data['categories'] = input('post.categories');

        $data['status'] = 0;
        $data['show_level'] = 0;
        $id = input('post.id/d', 0);
        if ($id) {
            $data['id'] = $id;
        }
        return $data;
    }
}
