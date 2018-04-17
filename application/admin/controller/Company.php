<?php
namespace app\admin\controller;

use app\carpool\model\Company as CompanyModel;
use app\common\controller\AdminBase;
use think\facade\Validate;
use think\facade\Config;
use think\Db;
use think\facade\Cache;
/**
 * 公司管理
 * Class Department
 * @package app\admin\controller
 */
class Company extends AdminBase
{
    protected $company_model;

    protected function initialize()
    {
        parent::initialize();
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
            $map[] = ['company_name|short_name','like', "%{$keyword}%"];
        }
        $lists = $this->company_model->where($map)->order('company_id ASC , company_name ')->paginate(50, false,['query'=>['keyword'=>$keyword]]);

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
      $this->jsonReturn(0,['lists'=>$returnLists],'success');
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
              $this->jsonReturn(1,$validate_result);
          } else {
              if ($this->company_model->allowField(true)->save($data)) {
                  Cache::tag('public')->rm('companys');
                  $pk = $this->company_model->company_id; //插入成功后取得id
                  $this->log('添加公司成功，id='.$pk,0);
                  $this->jsonReturn(0,'保存成功');
              } else {
                  $this->log('添加公司失败',1);
                  $this->jsonReturn(1,'保存失败');
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
          $validate_result = $this->validate($data, 'app\carpool\validate\Company.edit');
          if ($validate_result !== true) {
              $this->jsonReturn(1,$validate_result);
          }

          $validate   = Validate::make(['company_name'  => 'unique:carpool/Company,company_name,'.$id],['company_name.unique' => '公司名已存在']);
          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
              $this->jsonReturn(1,$validate->getError());
          }


          if ($this->company_model->allowField(true)->save($data, ['company_id'=>$id]) !== false) {
              Cache::tag('public')->rm('companys');
              $this->log('更新公司成功，id='.$id,0);
              $this->jsonReturn(0,'更新成功');
          } else {
              $this->log('更新公司失败，id='.$id,1);
              $this->jsonReturn(1,'更新失败');
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
            $this->log('删除公司成功，id='.$id,0);
            $this->jsonReturn(0,'删除成功');
        } else {
            $this->log('删除公司失败，id='.$id,1);
            $this->jsonReturn(1,'删除失败');
        }
    }
}
