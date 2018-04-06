<?php
namespace app\admin\controller;

use app\carpool\model\Company as CompanyModel;
use app\common\controller\AdminBase;
use think\Config;
use think\Db;
use think\Cache;
/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class Company extends AdminBase
{
    protected $company_model;

    protected function _initialize()
    {
        parent::_initialize();
        $this->company_model = new CompanyModel();
    }

    /**
     * 部门管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1)
    {
        $map = [];
        if ($keyword) {
            $map['company_name|short_name'] = ['like', "%{$keyword}%"];
        }
        $lists = $this->company_model->where($map)->order('company_id ASC , company_name ')->paginate(50, false, ['page' => $page]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword]);
    }



    public function public_lists(){
      $lists_cache = Cache::tag('public')->get('companys');
      if($lists_cache){
        $lists = $lists_cache;
      }else{
        $lists = $this->company_model->order('company_id ASC , company_name ')->select();
        if($lists){
          Cache::tag('public')->set('companys',$lists,3600);
        }
      }
      $returnLists = [];
      foreach($lists as $key => $value) {
        $returnLists[] = [
          'id'=>$value['company_id'],
          'name'=>$value['company_name'],
          'status'=>$value['status'],
        ];
      }
      return json(['data'=>['lists'=>$returnLists],'code'=>0,'desc'=>'success']);
    }

    /**
     * 添加部门
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\carpool\validate\Company');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
              if ($this->company_model->allowField(true)->save($data)) {
                  Cache::tag('public')->rm('companys');
                  $this->success('保存成功');
              } else {
                  $this->error('保存失败');
              }
          }
      }else{
        return $this->fetch();
      }
    }

    /**
     * 编辑部门
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          $validate_result = $this->validate($data, 'app\carpool\validate\Company');
          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
              if ($this->company_model->allowField(true)->save($data, ['company_id'=>$id]) !== false) {
                  Cache::tag('public')->rm('companys');
                  $this->success('更新成功');
              } else {
                  $this->error('更新失败');
              }
          }
       }else{
         $datas = $this->company_model->find($id);
         return $this->fetch('edit', ['datas' => $datas]);
       }

    }


    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        if ($this->company_model->destroy($id)) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
