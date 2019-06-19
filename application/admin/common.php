<?php

use org\Auth;
use think\Loader;
use think\Response;
use think\Db;
use think\facade\Session;

function checkAuth($rule){
   $admin_id = Session::get('admin_id');
   if(!$admin_id){
     return false;
   }
   if($admin_id === 1){
     return true;
   }
   $auth = new Auth();
   return $auth->check($rule, $admin_id);
}