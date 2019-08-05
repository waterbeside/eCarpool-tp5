<?php
return [
  //通用
  '.' => '。',
  ',' => '，',
  'Failed' => '失败',
  'Successfully' => '成功',
  'Fail' => '失败',
  "Can not be empty" => '不能为空',
  "Illegal access" => '禁止非法访问',
  "No data"=>"暂无数据",
  "Parameter error"=>"参数错误",
  "No permission" => "无权操作",

  //jwt 验证
  'You are not logged in' =>'您尚未登入',
  'Login status expired, please login again' =>'登录身份过期，请重新登入',

  //用户相关
  'Please enter user name'  => '请输入用户名',
  'Please enter your password' => '请输入密码',
  'User name or password error' => '用户名或密码错误',
  'The phone number you entered is incorrect' => '您输入的手机号有误',
  'Name error' => '姓名错误',
  'The user is banned' => '该用户被封禁',
  'The user is deleted' => '该用户被封禁',

  'Please enter the correct old password' => '请输入正确的旧密码',
  'Two passwords are different'=>'两次密码不一至',
  'The new password should be no less than 6 characters'=>'密码不能少于6位',

  'User does not exist'=>'用户不存在',
  'User already exists'=>'用户已存在',
  'User does not exist or has resigned'=>'用户不存在或已离职',

  'Already bound, please enter a new phone number' => '已绑定,请输入新的手机号',
  'The mobile phone number has been marked with a new account, whether to merge?' => '该手机号已注新账号，是否合并?',
  'No need to merge' => '无须再合并',
  'The phone number has been bound to this account, no need to merge.' => '该手机号已绑定本帐号,无须再合并',
  'The phone number has been registered for another account'=>'该手机号已被注册其它账号',
  'Please log in directly to the employee number to perform the binding operation' => '目标账号未开通积分账号，请直接登入员工号进行绑定操作',

  //短信
  'Verification code cannot be empty' => '验证码不能为空',
  'Verification code error' => '验证码错误',
  'The number of verification codes sent to this mobile phone number has reached the upper limit today' => '今天对该手机号可发送验证码的次数已达上限',
  'Phone number format is not correct' => '手机号格式不正确',
  
  //字段相关
  'Please select date and time' => '请选择日期时间',
  'Please enter content' => '请填写内容',

  //拼车相关
  "Car pooling" => "拼车",
  "Address name cannot be empty" => '地址名称不能为空',
  "The point of departure must not be empty" => '起点不能为空',
  "The destination cannot be empty" => '终点不能为空',
  "The departure time has passed. Please select the time again" => "出发时间已经过了，请重选时间。",
  "You have already made one trip at {:time}, should not be published twice within the same time"=> "您在 {:time} 已有一趟行程，在相近时间内请勿重复发布",
  "You have already made one trip at {:time}, please do not post in a similar time"=> "您在 {:time} 已有一趟行程，请不要在相近时间多次添加行程",
  'The number of empty seats cannot be empty'=>'空座位个数不能为空',
  'The trip has been completed or cancelled. Operation is not allowed' => '该行程已结束或取消，不允许操作。',
  'The trip not started, unable to operate' => '行程未开始，无法操作',
  'The trip does not exist' => '该行程不存在',
  'You can`t take your own' => '你不能自己搭自己',
  'Failed, the owner has cancelled the trip'=>'搭车失败，车主已取消该行程',
  'Failed, the trip has ended'=>'搭车失败，该行程已结束',
  'You have already taken this trip'=>'您已搭乘过本行程',
  'Failed, seat is full' => '搭车失败，座位已满',
  'You are not the driver or passenger of this trip'=>'你不是本行程的司机或乘客',
  'You are not the driver of this trip'=>'你不是本行程的司机',
  'Not allowed to view other`s location information'=>'不允许查看对方的位置信息',
  'This user has not joined this trip or has cancelled the itinerary' => '该用户没有参与本次行程或已取消了行程',

  "Can't see other people's location information,Because not in the allowed range of time" => "不在允许的时间范围内，无法查询对方的实时位置",
  "User has not uploaded location information recently" => "用户最近没有上传实时位置",


  '{:name} took your car'=> '{:name}搭了你的车',
  '{:name} accepted your ride requst'=>'{:name}接受了你的约车需求',
  'The driver {:name} cancelled the trip' => '司机{:name}取消了行程',
  'The passenger {:name} cancelled the trip' => '乘客{:name}取消了行程',
  'The passenger {:name} has got on your car' => '乘客{:name}上了你的车',

  //附件相关
  "Wrong format"=>'格式不正确',
  "Upload successful" => '上传成功',
  "Attachment information failed to be written" => '附件入库失败',
  "Please upload attachments" => '请上传附件',
  "Not image file format" => '图片格式不正确',
  'Images cannot be larger than 800K' => '图片不能大于800K',
  'Images cannot be larger than {:size}' => '图片不能大于{:size}',
  "File not found"=>'找不到文件',
  "This attachment cannot be deleted" => '该附件不可以删除',

  //评分
  "You can't rate this"=> "你不能对此评分",
  "You have already rated this" => "你已经对此评过分",

  //im
  'invites you to join the group' => '邀请你加入',
];
