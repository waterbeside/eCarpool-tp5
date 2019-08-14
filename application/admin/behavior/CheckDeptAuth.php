<?php
namespace app\admin\behavior;
use app\user\model\Department;
use think\Request;
use think\Db;

class CheckDeptAuth
{
    public function run(Request $request,$controller=null,$setting=[])
    {
      $userBaseData = $controller->userBaseInfo; //用户信息
      $params = $request->param();
      $action = strtolower($request->action());

      $region_id = isset($params['region_id']) ? $params['region_id'] : (isset($params['p_region_id']) ? $params['p_region_id'] : 0) ;

      $region_id_array = is_array($region_id) ? $region_id : explode(',',$region_id);

      $auth_depts = $userBaseData['auth_depts'];
      $auth_depts_str = $userBaseData['auth_depts_str'];

      $allow_region_ids = [];  //从$region_id里查出允许访问的id
      $filter_region_ids = []; // 输出的，用以筛选的部门信息
      $filter_region_datas = [];
      $region_datas = [];
      $region_path_all_str = "";

      $DepartmentModel = new Department();
      //查找选的地区id，自己是否有权
      if($region_id ){  //
        foreach ($region_id_array as  $rid) {
          if(!is_numeric($rid) && !$rid){
            continue;
          }
          $regionData = $DepartmentModel->getItem($rid);
          $region_datas[] = $regionData;
          if($regionData && isset($regionData['path']) && $regionData['path']){
            $region_path_array[] = $regionData['path'];
            $region_path_all_str .= ($region_path_all_str ? ',' : "" ).$regionData['path'];
            if(!empty(array_intersect($auth_depts, explode(',',($regionData['path'].','.$rid)))) ){
              $allow_region_ids[] = $rid;
            }
          }
        }
      }
      $region_path_all =  array_unique(explode(',',$region_path_all_str));

      if($userBaseData['auth_depts_isAll']){ //是否拥有全地区部门权限
        $allow_region_ids = $region_id_array;
        $filter_region_ids = $region_id_array;
      }else if($region_id == 0){  // 如果所选的地区id为0
        $filter_region_ids = $auth_depts;
      }else{ // 如果所选的地区id > 0
        $filter_region_ids = $allow_region_ids ?  $allow_region_ids :  $auth_depts  ;
        if(!$allow_region_ids){
          $controller->error(lang('The region or department you selected is not within your management, please return'));
        }
      }

      if(empty($filter_region_datas) && $filter_region_ids){
        foreach ($filter_region_ids as $key => $rid) {
          if(!is_numeric($rid) && !$rid){
            continue;
          }
          $regionData = $DepartmentModel->getItem($rid);
          $filter_region_datas[] = $regionData;
        }
      }




      $controller->authDeptData = [
        "region_id" => $region_id,
        "region_datas" => $region_datas,
        "allow_region_ids" => $allow_region_ids,
        "filter_region_ids" => $filter_region_ids,
        "filter_region_datas" => $filter_region_datas,
      ];

      if(!in_array($action,['add','edit'])){
        $region_map_sql = $this->buildRegionMapSql($filter_region_ids);
        $controller->authDeptData["region_map_sql"]  = $region_map_sql;
        $controller->authDeptData["region_map"] = $region_map_sql ? ['','exp', Db::raw($region_map_sql)] : null;
      }


      switch ($action) {
        case 'index':
          // $region_map_sql = $this->buildRegionMapSql($region_id);

          break;
        case 'add':
          if($request->isPost()){

          }else{

          }

        default:
          // code...
          break;
      }



    }

    /**
     * 构造"有权查看的区域的sql"
     * @param  integer $region_id
     * @param  string $as     关联的部门表别名
     */
    public function buildRegionMapSql($region_id,$as = 'd')
    {
      $region_id_array = is_array($region_id) ? $region_id : explode(",", $region_id);
      $region_id_array = array_values(array_unique($region_id_array));
      $region_map_sql = "";
      foreach ($region_id_array as   $value) {
        if(is_numeric($value) && $value > 0){
          $or = $region_map_sql ? " OR " : "";
          $region_map_sql .= $or." ( FIND_IN_SET($value,{$as}.path) OR {$as}.id = $value ) ";
        }
      }
      return $region_map_sql;
    }
}
