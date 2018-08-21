<?php
namespace app\admin\controller;

use app\carpool\model\User as UserModel;
use app\carpool\model\CompanySub as CompanySubModel;
use app\carpool\model\Department as DepartmentModel;
use app\carpool\model\Company as CompanyModel;
use app\admin\controller\AdminBase;
use think\facade\Config;
use think\facade\Validate;
use think\Db;

/**
 * 用户管理
 * Class AdminUser
 * @package app\admin\controller
 */
class User extends AdminBase
{
    protected $user_model;

    protected function initialize()
    {
        parent::initialize();
        $this->user_model = new UserModel();
    }

    /**
     * 用户管理
     * @param string $keyword
     * @param int    $page
     * @return mixed
     */
    public function index($filter = [], $page = 1,$pagesize = 50)
    {
        $map = [];
        //筛选用户信息
        if (isset($filter['keyword']) && $filter['keyword'] ){
          $map[] = ['u.loginname|u.phone|u.name','like', "%{$filter['keyword']}%"];
        }
        //筛选部门
        if (isset($filter['keyword_dept']) && $filter['keyword_dept'] ){
          $map[] = ['u.Department|u.companyname|c.company_name','like', "%{$filter['keyword_dept']}%"];
        }
        $join = [
          ['company c','u.company_id = c.company_id','left'],
        ];
        $user_list = $this->user_model->alias('u')->join($join)->where($map)->order('uid DESC')->paginate($pagesize, false,  ['query'=>request()->param()]);
        return $this->fetch('index', ['user_list' => $user_list, 'filter' => $filter,'pagesize'=>$pagesize]);
    }

    /**
     * 明细
     */
    public function public_detail($id){
      if(!$id){
        return $this->error('Lost id');
      }
      $userInfo = $this->user_model->find($id);
      if($userInfo){
        $userInfo->avatar = $userInfo->imgpath ? config('app.avatarBasePath').$userInfo->imgpath : config('app.avatarBasePath')."im/default.png";
      }
      $companyLists = (new CompanyModel())->getCompanys();
      $companys = [];
      foreach($companyLists as $key => $value) {
        $companys[$value['company_id']] = $value['company_name'];
      }

      $returnData = [
        'data'=>$userInfo,
        'companys'=>$companys,
      ];
      return $this->fetch('detail', $returnData);

    }


    /**
     * 添加用户
     * @return mixed
     */
    public function add()
    {
      if ($this->request->isPost()) {
          $data            = $this->request->post();
          unset($data['md5password']);

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['companyname'] = $sub_company_name ? $sub_company_name : '';
          $department_name = DepartmentModel::where(['departmentid'=>$data['departmentid']])->value('department_name');
          $data['Department'] = $department_name ? $department_name : '';

          $validate_result = $this->validate($data, 'app\carpool\validate\User');
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate_result);
          }
          $data['password'] = trim($data['password']);
          $data['md5password'] = md5($data['password']);
          $data['indentifier'] = uuid_create();
          if ($this->user_model->allowField(true)->save($data)) {
              $uid_n = $this->user_model->uid; //插入成功后取得id
              $this->log('新加用户成功，id='.$uid_n,0);
              return $this->jsonReturn(0,'保存成功');
          } else {
              $this->log('新加用户失败',-1);
              return $this->jsonReturn(-1,'保存失败');
          }

      }else{
        $avatar =  config('app.avatarBasePath')."im/default.png";
        return $this->fetch('add', ['avatar' => $avatar]);
      }

    }


    /**
     * 编辑用户
     * @param $id
     * @return mixed
     */
    public function edit($id)
    {

      if ($this->request->isPost()) {

          $data               = $this->request->post();
          $data['is_active']  = $this->request->post('is_active/d',0);
          unset($data['md5password']);

          $sub_company_name = CompanySubModel::where(['sub_company_id'=>$data['sub_company_id']])->value('sub_company_name');
          $data['companyname'] = $sub_company_name ? $sub_company_name : '';
          $department_name = DepartmentModel::where(['departmentid'=>$data['departmentid']])->value('department_name');
          $data['Department'] = $department_name ? $department_name : '';

          //开始验证字段
          $validate = new \app\carpool\validate\User;

          if (!empty($data['password'])) {
            if (!$validate->scene('edit_change_password')->check($data)) {
              return $this->jsonReturn(-1,$validate->getError());
            }
            $data['md5password'] = md5($data['password']);
          }else{
            if (!$validate->scene('edit')->check($data)) {
              return $this->jsonReturn(-1,$validate->getError());
            }
            unset($data['password']);
          }

          // 验证手机号和帐号名是否重复
          $rule = [
            'loginname'  => 'unique:carpool/User,loginname,'.$id,
            'phone'       => 'unique:carpool/User,phone,'.$id,
          ];
          $msg = [
            'loginname.unique' => '该用户名已被占用',
            'phone.unique' => '电话已被使用',
          ];
          $validate   = Validate::make($rule,$msg);
          $validate_result = $validate->check($data);
          if ($validate_result !== true) {
            return $this->jsonReturn(-1,$validate->getError());
          }
          if ($this->user_model->allowField(true)->save($data, ['uid'=>$id]) !== false) {
              $this->log('保存用户成功，id='.$id,0);
              return $this->jsonReturn(0,'保存成功');
          } else {
              $this->log('保存用户失败，id='.$id,-1);
              return $this->jsonReturn(-1,'保存失败');
          }

      }else{
        $user = $this->user_model->find($id);
        $user->avatar = $user->imgpath ? config('app.avatarBasePath').$user->imgpath : config('app.avatarBasePath')."im/default.png";

        return $this->fetch('edit', ['user' => $user]);
      }

    }



    /**
     * 删除用户
     * @param $id
     */
    public function delete($id)
    {
        if ($this->user_model->destroy($id)) {
            $this->log('删除用户成功，id='.$id,0);
            return $this->jsonReturn(0,'删除成功');
        } else {
            $this->log('删除用户失败，id='.$id,-1);
            return $this->jsonReturn(-1,'删除失败');
        }
    }


    public function test_import_user($page=1){
      $lists = Db::table('temp_user_unicom_20180531')->page($page,1)->select();
      $url  = '';
      $msg = '';
      dump(uuid_create());
      dump($lists);exit;
      if(count($lists)>0){
        foreach ($lists as $key => $value) {

          $data = [
            'indentifier'=>uuid_create(),
            'name' => $value['name'],
            'nativename' => $value['name'],
            'phone' => $value['phone'],
            'loginname' => $value['phone'],
            'sex' => $value['sex'],
            'company_id' => 10,
            'companyname' => '联通-佛山市分公司',
            'is_active' => 1 ,
            'home_address_id' => 0,
            'company_address_id' => 0 ,
            'md5password' => md5($value['phone']) ,
          ];
          if($value['status']!==0){
            $msg =  "id:".$value['id'].";"."name:".$data['name'].";"."phone:".$data['phone'].";"."   Has finished";
            continue;
          }
          // dump($data);
          $item = UserModel::whereOr([['phone','=',$value['phone']],['loginname','=',$value['phone']]])->find();
          if($item){
            $st = Db::table('temp_user_unicom_20180531')->where("id",$value['id'])->update(['status'=>-1]);
            $msg =  "id:".$value['id'].";"."name:".$data['name'].";"."phone:".$data['phone'].";"."uid:".$item['uid'].";"."  fail";
            continue;

          }
          $is_ok = UserModel::insertGetId($data);
          // dump($is_ok);exit;
          if($is_ok){
            $st = Db::table('temp_user_unicom_20180531')->where("id",$value['id'])->update(['status'=>1]);
            $msg =  "id:".$value['id'].";"."name:".$data['name'].";"."phone:".$data['phone'].";"."  OK";
          }else{
            $msg =  "id:".$value['id'].";"."name:".$data['name'].";"."phone:".$data['phone'].";"."   fail";
          }
        }

        $page = $page+1;
        $url  = url('admin/user/test_import_user',['page'=>$page]);

      }else{
        $msg = "完成全部操作";

      }

      return $this->fetch('index/multi_jump',['url'=>$url,'msg'=>$msg]);

    }

}
