<?php
return [
  //通用
  '.' => '.',
  ',' => ',',
  'Failed' => 'Thất bại',
  'Successfully' => 'Thành công',
  'Fail' => 'Thất bại',
  "Can not be empty" => 'không thể để trống',
  "Illegal access" => 'Truy cập bất hợp pháp',
  "No data"=>"Chưa có dữ liệu",
  "Parameter error"=>"Lỗi tham số",
  "No permission" => "Không cho phép",


  //jwt 验证
  'You are not logged in' =>'Bạn chưa đăng nhập',
  'Login status expired, please login again' =>'Tình trạng đăng nhập đã hết hạn, vui lòng đăng nhập lại',

  //用户相关
  'Please enter user name'  => 'Vui lòng nhập tên người dùng',
  'Please enter your password' => 'Vui lòng nhập mật khẩu của bạn',
  'User name or password error' => 'Tên người dùng hoặc mật khẩu không chính xác',
  'The phone number you entered is incorrect' => 'Số điện thoại bạn nhập không chính xác',
  'Name error' => 'Tên không chính xác',
  'The user is banned' => 'Người dùng bị cấm',

  'Please enter the correct old password' => 'Vui lòng nhập mật khẩu cũ chính xác',
  'Two passwords are different'=>'Hai mật khẩu khác nhau',
  'The new password should be no less than 6 characters'=>'Mật khẩu mới không được ít hơn 6 ký tự',

  'User does not exist'=>'Người dùng không tồn tại',
  'User already exists'=>'Người dùng đã tồn tại',
  'Already bound, please enter a new phone number' => 'Đã bị ràng buộc, vui lòng nhập số điện thoại mới',
  'The mobile phone number has been marked with a new account, whether to merge?' => 'Số điện thoại di động đã được đánh dấu bằng một tài khoản mới, hợp nhất không?',
  'No need to merge' => 'Không cần phải hợp nhất',
  'The phone number has been bound to this account, no need to merge.' => 'Số điện thoại đã bị ràng buộc vào tài khoản này, không cần phải hợp nhất',
  'The phone number has been registered for another account'=>'Số điện thoại đã được đăng ký một tài khoản khác',
  'Please log in directly to the employee number to perform the binding operation' => 'Vui lòng đăng nhập trực tiếp vào số nhân viên để ràng buộc',

  //短信
  'Verification code cannot be empty' => 'Mã xác minh không thể để trống',
  'Verification code error' => 'Lỗi mã xác minh',


  //字段相关
  'Please select date and time' => 'Vui lòng chọn ngày và giờ',
  'Please enter content' => 'Vui lòng nhập nội dung',

  //拼车相关
  "Car pooling" => "Đi chung xe",
  "Address name cannot be empty" => 'Địa chỉ không được để trống',
  "The point of departure must not be empty" => 'Điểm bắt đầu không được để trống',
  "The destination cannot be empty" => 'Điểm kết thúc không được để trống',
  "The departure time has passed. Please select the time again" => "Thời gian khởi hành đã quá hạn, vui lòng chọn lại thời gian",
  "You have already made one trip at {:time}, should not be published twice within the same time"=> "Bạn có chuyến đi lúc {:time}, vui lòng không lặp lại trong thời gian tương tự",
  'The number of empty seats cannot be empty'=>'Số lượng chỗ ngồi trống không được để trống',
  'The trip has been completed or cancelled. Operation is not allowed' => 'Chuyến đi đã hoàn thành hoặc hủy. Thao tác không được phép',
  'The trip not started, unable to operate' => 'Chuyến đi chưa bắt đầu, không thể thao tác',
  'The trip does not exist' => 'Chuyến đi không tồn tại',
  'You can`t take your own' => 'Bạn không thể tự mình làm',
  'Failed, the owner has cancelled the trip'=>'Thất bại, chủ sở hữu đã hủy chuyến đi',
  'Failed, the trip has ended'=>'Thất bại, chuyến đi đã kết thúc',
  'Failed, seat is full' => 'Thất bại, chỗ ngồi đã đầy',
  'You have already taken this trip'=>'Bạn không phải là tài xế hoặc hành khách của chuyến đi này',
  'You are not the driver or passenger of this trip'=>'Bạn không phải là tài xế hoặc hành khách của chuyến đi này',
  'You are not the driver of this trip'=>'Bạn không phải là người lái xe của chuyến đi này',

  'Not allowed to view  other`s location information'=>'Không được phép xem thông tin vị trí của người khác',
  'This user has not joined this trip or has cancelled the itinerary' => 'Người dùng này chưa tham gia chuyến đi này hoặc đã hủy hành trình',

  "Can't see other people's location information,Because not in the allowed range of time" => "Không thể xem thông tin vị trí của người khác. Vì không nằm trong phạm vi thời gian cho phép",
  "User has not uploaded location information recently" => "Người dùng chưa tải lên thông tin vị trí gần đây",

  '{:name} took your car'=> '{:name} đã lấy xe của bạn ',
  '{:name} accepted your ride requst'=>'{:name} đã chấp nhận yêu cầu xe của bạn',
  'The driver {:name} cancelled the trip' => 'Tài xế {:name} đã hủy chuyến đi',
  'The passenger {:name} cancelled the trip' => 'Hành khách {:name} đã hủy chuyến đi',
  'The passenger {:name} has got on your car' => 'Hành khách {:name} đã lên xe của bạn',

  //附件相关
  "Wrong format"=>'Sai định dạng',
  "Upload successful" => 'Tải lên thành công',
  "Attachment information failed to be written" => 'thông tin tập tin đính kèm không được viết',
  "Please upload attachments" => 'Vui lòng tải lên tệp đính kèm',
  "Not image file format" => 'Sai định dạng hình ảnh',
  'Images cannot be larger than 800K' => 'Hình ảnh không được lớn hơn 800KB',
  'Images cannot be larger than {:size}' => 'Hình ảnh không được lớn hơn {:size}',
  "File not found"=>'Không tìm thấy tệp',
  "This attachment cannot be deleted" => 'Không thể xóa tệp đính kèm này',

  //评分
  "You can't rate this"=> "Bạn không thể đánh giá điều này",
  "You have already rated this" => "Bạn đã đánh giá điều này",
];
