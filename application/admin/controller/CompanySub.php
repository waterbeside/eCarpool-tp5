<?php
namespace app\admin\controller;

use app\carpool\model\CompanySub as CompanySubModel;
use think\Validate;

use app\common\controller\AdminBase;
use think\Config;
use think\Db;

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
    public function index($keyword = '', $page = 1,$pagesize = 50)
    {
        $map = [];
        if ($keyword) {
            $map['sub_company_name'] = ['like', "%{$keyword}%"];
        }
        $join = [
          ['company c','s.company_id = c.company_id'],
        ];
        $lists = $this->company_sub_model->alias('s')->join($join)->where($map)->order('sub_company_id ASC , sub_company_name ')->paginate($pagesize, false, ['page' => $page]);

        return $this->fetch('index', ['lists' => $lists, 'keyword' => $keyword,'pagesize'=>$pagesize]);
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

          //验证名称是否重复
          $validate   = Validate::make(['sub_company_name'  => 'unique:carpool/CompanySub,sub_company_name,'.$id],['sub_company_name.unique' => '分厂名已存在']);

          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
              $this->error($validate->getError());
          }

          if ($this->company_sub_model->allowField(true)->save($data, ['sub_company_id'=>$id]) !== false) {
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
