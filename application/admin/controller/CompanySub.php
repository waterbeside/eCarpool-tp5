<?php
namespace app\admin\controller;

use app\carpool\model\CompanySub as CompanySubModel;
use think\Validate;

use app\common\controller\AdminBase;
use think\Config;
use think\Db;
use think\Cache;

/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class CompanySub extends AdminBase
{
    protected $company_sub_model;

    protected function _initialize()
    {
        parent::_initialize();
        $this->company_sub_model = new CompanySubModel();
    }

    /**
     * 子公司管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($keyword = '', $page = 1 )
    {
        $map = [];
        if ($keyword) {
            $map['sub_company_name'] = ['like', "%{$keyword}%"];
        }
        $join = [
          ['company c','s.company_id = c.company_id','left'],
        ];
        $lists = $this->company_sub_model->field('s.sub_company_id,s.company_id,s.status,s.sub_company_name,c.company_name,c.short_name')->alias('s')->join($join)->where($map)->order('sub_company_id ASC , sub_company_name ')->paginate(50, false, ['query'=>['keyword'=>$keyword]]);
        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword]);
    }

    /**
     * 用于选择列表
     */
    public function public_lists(){
      $cid = $this->request->get('cid/d');
      $lists_cache = Cache::tag('public')->get('sub_companys');
      if($lists_cache){
        $lists = $lists_cache;
      }else{
        $lists = $this->company_sub_model->where('status',1)->order('sub_company_id ASC ')->select();
        if($lists){
          Cache::tag('public')->set('sub_companys',$lists,3600);
        }
      }
      $returnLists = [];
      foreach($lists as $key => $value) {
        if(!$cid || ($cid > 0 && $cid == $value['company_id'])){
          $returnLists[] = [
            'id'=>$value['sub_company_id'],
            'name'=>$value['sub_company_name'],
            'status'=>$value['status'],
          ];
        }
      }
      return json(['data'=>['lists'=>$returnLists],'code'=>0,'desc'=>'success']);
    }

    /**
     * 添加子公司
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          // $validate_result = $this->validate($data, 'CompanySub');
          $validate_result = $this->validate($data,'app\carpool\validate\CompanySub');

          if ($validate_result !== true) {
              $this->error($validate_result);
          } else {
              if ($this->company_sub_model->allowField(true)->save($data)) {
                Cache::tag('public')->rm('sub_companys');
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
     * 编辑子公司
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {
      if ($this->request->isPost()) {
          $data            = $this->request->param();
          // $validate_result = $this->validate($data, 'CompanySub');
          $validate_result = $this->validate($data,'app\carpool\validate\CompanySub.edit');

          if ($validate_result !== true) {
              $this->error($validate_result);
          }

          /*//验证名称是否重复
          $validate   = Validate::make(['sub_company_name'  => 'unique:carpool/CompanySub,sub_company_name,'.$id],['sub_company_name.unique' => '分厂名已存在']);
          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
              $this->error($validate->getError());
          }*/

          if ($this->company_sub_model->allowField(true)->save($data, ['sub_company_id'=>$id]) !== false) {
            Cache::tag('public')->rm('sub_companys');
              $this->success('更新成功');
          } else {
              $this->error('更新失败');
          }

       }else{
         $datas = $this->company_sub_model->find($id);
         return $this->fetch('edit', ['datas' => $datas]);
       }

    }


    /**
     * 删除
     * @param $id
     */
    public function delete($id)
    {
        if ($this->company_sub_model->destroy($id)) {
            $this->success('删除成功');
        } else {
            $this->error('删除失败');
        }
    }
}
