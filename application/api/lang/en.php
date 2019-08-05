<?php
return [
  //通用
  '.' => '.',
  ',' => ',',
  'Failed' => 'Failed',
  'Successfully' => 'Successfully',
  'Fail' => 'Fail',
  "Can not be empty" => 'Can not be empty',
  "Illegal access" => 'Illegal access',
  "No data"=>"No data",
  "Parameter error"=>"Parameter error",
  "No permission" => "No permission",

  //jwt 验证
  'You are not logged in' =>'You are not logged in',
  'Login status expired, please login again' =>'Login status expired, please login again',

  //用户相关
  'Please enter user name'  => 'Please enter user name',
  'Please enter your password' => 'Please enter your password',
  'User name or password error' => 'User name or password error',
  'The phone number you entered is incorrect' => 'The phone number you entered is incorrect',
  'Name error' => 'Name error',
  'The user is banned' => 'The user is banned',
  'The user is deleted' => 'The user is deleted',

  'Please enter the correct old password' => 'Please enter the correct old password',
  'Two passwords are different'=>'Two passwords are different',
  'The new password should be no less than 6 characters'=>'The new password should be no less than 6 characters',

  'User does not exist'=>'User does not exist',
  'User already exists'=>'User already exists',
  'User does not exist or has resigned'=>'User does not exist or has resigned',
  
  'Already bound, please enter a new phone number' => 'Already bound, please enter a new phone number',
  'The mobile phone number has been marked with a new account, whether to merge?' => 'The mobile phone number has been marked with a new account, whether to merge?',
  'No need to merge' => 'No need to merge',
  'The phone number has been bound to this account, no need to merge.' => 'The phone number has been bound to this account, no need to merge.',
  'The phone number has been registered for another account'=>'The phone number has been registered for another account',
  'Please log in directly to the employee number to perform the binding operation' => 'Please log in directly to the employee number to perform the binding operation',

  //短信
  'Verification code cannot be empty' => 'Verification code cannot be empty',
  'Verification code error' => 'Verification code error',
  'The number of verification codes sent to this mobile phone number has reached the upper limit today' => 'The number of verification codes sent to this mobile phone number has reached the upper limit today',
  'Phone number format is not correct' => 'Phone number format is not correct',


  //字段相关
  'Please select date and time' => 'Please select date and time',
  'Please enter content' => 'Please enter content',

  //拼车相关
  "Car pooling" => "Car pooling",
  "Address name cannot be empty" => 'Address name cannot be empty',
  "The point of departure must not be empty" => 'The point of departure must not be empty',
  "The destination cannot be empty" => 'The destination cannot be empty',
  "The departure time has passed. Please select the time again" => 'The departure time has passed. Please select the time again.',
  "You have already made one trip at {:time}, should not be published twice within the same time"=> "You have already made one trip at {:time}, should not be published twice within the same time",
  "You have already made one trip at {:time}, please do not post in a similar time"=> "You have already made one trip at {:time}, please do not post in a similar time",
  'The number of empty seats cannot be empty'=>'The number of empty seats cannot be empty',
  'The trip has been completed or cancelled. Operation is not allowed' => 'The trip has been completed or cancelled. Operation is not allowed',
  'The trip not started, unable to operate' => 'The trip not started, unable to operate',
  'The trip does not exist' => 'The trip does not exist',
  'You can`t take your own' => 'You can`t take your own',
  'Failed, the owner has cancelled the trip'=>'Failed, the owner has cancelled the trip',
  'Failed, the trip has ended'=>'Failed, the trip has ended',
  'You have already taken this trip'=>'You have already taken this trip',
  'Failed, seat is full' => 'Failed, seat is full',
  'You are not the driver or passenger of this trip'=>'You are not the driver or passenger of this trip',
  'You are not the driver of this trip'=>'You are not the driver of this trip',
  'Not allowed to view other`s location information'=>'Not allowed to view other`s location information',
  'This user has not joined this trip or has cancelled the itinerary' => 'This user has not joined this trip or has cancelled the itinerary',

  "Can't see other people's location information,Because not in the allowed range of time" => "Can't see other people's location information,Because not in the allowed range of time",
  "User has not uploaded location information recently" => "User has not uploaded location information recently",


  '{:name} took your car'=> '{:name} took your car',
  '{:name} accepted your ride requst'=>'{:name} accepted your ride requst',
  'The driver {:name} cancelled the trip' => 'The driver {:name} cancelled the trip',
  'The passenger {:name} cancelled the trip' => 'The passenger {:name} cancelled the trip',
  'The passenger {:name} has got on your car' => 'The passenger {:name} has got on your car',

  //附件相关
  "Wrong format"=>'Wrong format',
  "Upload successful" => 'Upload successful',
  "Attachment information failed to be written" => 'Attachment information failed to be written',
  "Please upload attachments" => 'Please upload attachments',
  "Not image file format" => 'Not image file format',
  'Images cannot be larger than 800K' => 'Images cannot be larger than 800K',
  'Images cannot be larger than {:size}' => 'Images cannot be larger than {:size}',
  "File not found"=>'File not found',
  "This attachment cannot be deleted" => 'This attachment cannot be deleted',

  //评分
  "You can't rate this"=> "You can't rate this",
  "You have already rated this" => "You have already rated this",

  //im
  'invites you to join the group' => 'invites you to join the group',

];
