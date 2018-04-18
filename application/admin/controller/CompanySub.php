<?php
namespace app\admin\controller;

use app\carpool\model\CompanySub as CompanySubModel;
use think\facade\Validate;

use app\common\controller\AdminBase;
use think\facade\Config;
use think\Db;
use think\facade\Cache;

/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class CompanySub extends AdminBase
{
    protected $company_sub_model;

    protected function initialize()
    {
        parent::initialize();
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
            $map[] = ['sub_company_name','like', "%{$keyword}%"];
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
      $this->jsonReturn(0,['lists'=>$returnLists],'success');
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
              $this->jsonReturn(1,$validate_result);
          } else {
              if ($this->company_sub_model->allowField(true)->save($data)) {
                  Cache::tag('public')->rm('sub_companys');
                  $pk = $this->company_sub_model->sub_company_id; //插入成功后取得id
                  $this->log('新加分厂成功，id='.$pk,0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('新加分厂失败',1);
                  $this->jsonReturn(1,'保存失败');
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
              $this->jsonReturn(1,$validate_result);
          }

          /*//验证名称是否重复
          $validate   = Validate::make(['sub_company_name'  => 'unique:carpool/CompanySub,sub_company_name,'.$id],['sub_company_name.unique' => '分厂名已存在']);
          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
              $this->error($validate->getError());
          }*/

          if ($this->company_sub_model->allowField(true)->save($data, ['sub_company_id'=>$id]) !== false) {
              Cache::tag('public')->rm('sub_companys');
              $this->log('更新分厂成功，id='.$id,0);
              $this->jsonReturn(0,'更新成功');
          } else {
              $this->log('更新分厂失败，id='.$id,1);
              $this->jsonReturn(1,'更新失败');
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
            Cache::tag('public')->rm('sub_companys');
            $this->log('删除分厂成功，id='.$id,0);
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->log('删除分厂失败，id='.$id,1);
            $this->jsonReturn(1,'删除失败');
        }
    }
}
