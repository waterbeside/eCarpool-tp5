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
    "No data" => "Chưa có dữ liệu",
    "Parameter error" => "Lỗi tham số",
    "No permission" => "Không cho phép",

    'Please do not repeat the operation' => 'Xin vui lòng không lặp lại hoạt động.',
    'The network is busy, please try again later' => 'Mạng đang bận, vui lòng thử lại sau.',

    //jwt 验证
    'You are not logged in' => 'Bạn chưa đăng nhập',
    'Login status expired, please login again' => 'Tình trạng đăng nhập đã hết hạn, vui lòng đăng nhập lại',

    //用户相关
    'Please enter user name'  => 'Vui lòng nhập tên người dùng',
    'Please enter your password' => 'Vui lòng nhập mật khẩu của bạn',
    'User name or password error' => 'Tên người dùng hoặc mật khẩu không chính xác',
    'The phone number you entered is incorrect' => 'Số điện thoại bạn nhập không chính xác',
    'Name error' => 'Tên không chính xác',
    'The user is banned' => 'Người dùng bị cấm',
    'The user is deleted' => 'The user is deleted',
    'The user has left' => 'The user has left',
    'The phone number you entered is not the phone number of the current account' => 'Số điện thoại bạn đã nhập không phải là số điện thoại của tài khoản hiện tại.',

    'Please enter the correct old password' => 'Vui lòng nhập mật khẩu cũ chính xác',
    'Two passwords are different' => 'Hai mật khẩu khác nhau',
    'The new password should be no less than 6 characters' => 'Mật khẩu mới không được ít hơn 6 ký tự',

    'User does not exist' => 'Người dùng không tồn tại',
    'User already exists' => 'Người dùng đã tồn tại',
    'User does not exist or has resigned' => 'Người dùng không tồn tại hoặc đã từ chức',

    'Already bound, please enter a new phone number' => 'Đã bị ràng buộc, vui lòng nhập số điện thoại mới',
    'The mobile phone number has been marked with a new account, whether to merge?' => 'Số điện thoại di động đã được đánh dấu bằng một tài khoản mới, hợp nhất không?',
    'No need to merge' => 'Không cần phải hợp nhất',
    'The phone number has been bound to this account, no need to merge.' => 'Số điện thoại đã bị ràng buộc vào tài khoản này, không cần phải hợp nhất',
    'The phone number has been registered for another account' => 'Số điện thoại đã được đăng ký một tài khoản khác',
    'Please log in directly to the employee number to perform the binding operation' => 'Vui lòng đăng nhập trực tiếp vào số nhân viên để ràng buộc',
    'The mobile phone number has no associated employee account' => 'Số điện thoại di động không có tài khoản nhân viên liên quan',

    //短信
    'Verification code cannot be empty' => 'Mã xác minh không thể để trống',
    'Verification code error' => 'Lỗi mã xác minh',
    'The number of verification codes sent to this mobile phone number has reached the upper limit today' => 'Số mã xác minh được gửi đến số điện thoại di động này đã đạt đến giới hạn trên ngày hôm nay.',
    'Phone number format is not correct' => 'Định dạng số điện thoại không chính xác',


    //字段相关
    'Please select date and time' => 'Vui lòng chọn ngày và giờ',
    'Please enter content' => 'Vui lòng nhập nội dung',

    //拼车相关
    "Car pooling" => "Đi chung xe",
    "Address name cannot be empty" => 'Địa chỉ không được để trống',
    "The point of departure must not be empty" => 'Điểm bắt đầu không được để trống',
    "The destination cannot be empty" => 'Điểm kết thúc không được để trống',
    "The departure time has passed. Please select the time again" => "Thời gian khởi hành đã quá hạn, vui lòng chọn lại thời gian",
    "The departure time has passed. Unable to operate" => "Thời gian khởi hành đã trôi qua và không thể được vận hành.",
    "You have already made one trip at {:time}, should not be published twice within the same time" => "Bạn có chuyến đi lúc {:time}, vui lòng không lặp lại trong thời gian tương tự",
    "You have already made one trip at {:time}, please do not post in a similar time" => "Bạn có chuyến đi lúc {:time}, Đừng thêm các chuyến đi nhiều lần trong thời gian tương tự",
    "You have multiple trips in {:time} minutes, please do not post in a similar time" => "Bạn có nhiều chuyến đi trong {:time} phút, Đừng thêm các chuyến đi nhiều lần trong thời gian tương tự",
    'The number of empty seats cannot be empty' => 'Số lượng chỗ ngồi trống không được để trống',
    'The trip has been completed or cancelled. Operation is not allowed' => 'Chuyến đi đã hoàn thành hoặc hủy. Thao tác không được phép',
    'The trip not started, unable to operate' => 'Chuyến đi chưa bắt đầu, không thể thao tác',
    'The trip does not exist' => 'Chuyến đi không tồn tại',
    'You can`t take your own' => 'Bạn không thể tự mình làm',
    'Failed, the owner has cancelled the trip' => 'Thất bại, chủ sở hữu đã hủy chuyến đi',
    'Failed, the trip has ended' => 'Thất bại, chuyến đi đã kết thúc',
    'Failed, seat is full' => 'Thất bại, chỗ ngồi đã đầy',
    'You have already taken this trip' => 'Bạn không phải là tài xế hoặc hành khách của chuyến đi này',
    'You are not the driver or passenger of this trip' => 'Bạn không phải là tài xế hoặc hành khách của chuyến đi này',
    'You are not the driver of this trip' => 'Bạn không phải là người lái xe của chuyến đi này',

    'Not allowed to view  other`s location information' => 'Không được phép xem thông tin vị trí của người khác',
    'This user has not joined this trip or has cancelled the itinerary' => 'Người dùng này chưa tham gia chuyến đi này hoặc đã hủy hành trình',

    "Can't see other people's location information,Because not in the allowed range of time" => "Không thể xem thông tin vị trí của người khác. Vì không nằm trong phạm vi thời gian cho phép",
    "User has not uploaded location information recently" => "Người dùng chưa tải lên thông tin vị trí gần đây",

    '{:name} took your car' => '{:name} đã lấy xe của bạn ',
    '{:name} accepted your ride requst' => '{:name} đã chấp nhận yêu cầu xe của bạn',
    'The driver {:name} cancelled the trip' => 'Tài xế {:name} đã hủy chuyến đi',
    'The passenger {:name} cancelled the trip' => 'Hành khách {:name} đã hủy chuyến đi',
    'The passenger {:name} has got on your car' => 'Hành khách {:name} đã lên xe của bạn',

    // 班车拼车相关
    'Adding driver data failed' => 'Thêm dữ liệu trình điều khiển không thành công.',
    'License plate number cannot be empty' => 'Số biển số xe không được để trống.',
    'Not enough seats' => 'Số lượng ghế trống không đủ.',
    'Only the driver can change the number of seats' => 'Chỉ người lái xe mới có thể thay đổi số lượng ghế trong hành trình.',
    'Please select a route'=>'Vui lòng chọn một tuyến đường.',
    'Please select a departure time'=>'Vui lòng chọn thời gian khởi hành.',
    'The driver is on your list of travel partners, so the driver cannot add himself as a passenger' => 'Failed. The driver is on your list of travel partners, so the driver cannot add himself as a passenger',
    'The number of seats you set cannot be less than the number of passengers on your trip' => 'Bạn không thể đặt ít chỗ hơn số lượng hành khách đã lên.',
    'The trip has been going on for a while. Operation is not allowed' => 'Chuyến đi đã bắt đầu một thời gian, bạn không thể hoạt động.',
    'The partner does not exist or has been deleted' => 'Đối tác ngang hàng không tồn tại hoặc đã bị xóa',
    'The request does not exist' => 'Nhu cầu đi xe không tồn tại.',
    'The request has been picked up by the driver. You can not add partners' => 'Yêu cầu đã được chọn bởi trình điều khiển. Bạn không thể thêm đối tác.',
    'The request has been picked up by the driver. You can not operate the partners' => 'Yêu cầu đã được chọn bởi trình điều khiển. Bạn không thể vận hành các đối tác.',
    'The route of trip is different from yours' => 'Lộ trình của chuyến đi khác với bạn.',
    'The route does not exist' => 'Tuyến đường không tồn tại.',
    'This trip has expired or ended and cannot be operated' => 'Chuyến đi này đã hết hạn hoặc kết thúc và không thể được vận hành',
    'This trip is not a passenger`s trip, and cannot merge'=> 'Chuyến đi này không phải là chuyến đi của hành khách, và không thể hợp nhất.',
    'This trip is not a driver`s trip, and cannot merge' => 'Chuyến đi này không phải là chuyến đi của tài xế. và không thể hợp nhất.',
    'Trip has ended and cannot be operated' => 'Chuyến đi đã kết thúc và không thể được vận hành.',
    'You are among this passenger`s partner, so you cannot add yourself as a passenger' => 'Hoạt động thất bại，Bạn là một trong những đối tác của hành khách này. vì vậy bạn không thể thêm mình như một hành khách.',
    'You are not the driver or passenger of the trip and cannot operate' => 'Bạn không phải là tài xế hoặc hành khách của chuyến đi và không thể vận hành.',
    'You are too slow, the passenger was snatched by another driver' => 'Bạn quá chậm, hành khách đã bị một tài xế khác cướp mất!',
    'You can not add yourself as a fellow partner' => 'Bạn không thể thêm mình là một đối tác đồng nghiệp.',
    'You can not operate someone else`s trip' => 'Bạn không thể điều hành chuyến đi của người khác.',
    'You cannot cancel this trip that you have not participated in' => 'Bạn không có quyền hủy chuyến đi không liên quan gì đến bạn.',
    'You have similar trips that can be merged' => 'Bạn có những chuyến đi tương tự có thể được hợp nhất.',
    'You have joined the trip' => 'Bạn đã tham gia chuyến đi.',
    'Failed, "{:name}" has one or more trips in similar time' => 'Thất bại. "{:name}" có một hoặc nhiều chuyến đi trong thời gian tương tự.',
    'Failed, "{:name}" has been added as a partner by others in a similar time' => 'Không thành công. "{:name}" đã được thêm vào làm đối tác trong những lần tương tự.',


    //附件相关
    "Wrong format" => 'Sai định dạng',
    "Upload successful" => 'Tải lên thành công',
    "Attachment information failed to be written" => 'thông tin tập tin đính kèm không được viết',
    "Please upload attachments" => 'Vui lòng tải lên tệp đính kèm',
    "Not image file format" => 'Sai định dạng hình ảnh',
    'Images cannot be larger than 800K' => 'Hình ảnh không được lớn hơn 800KB',
    'Images cannot be larger than {:size}' => 'Hình ảnh không được lớn hơn {:size}',
    "File not found" => 'Không tìm thấy tệp',
    "This attachment cannot be deleted" => 'Không thể xóa tệp đính kèm này',
    'The file is too large to upload' => 'Tệp quá lớn',

    //评分
    "You can't rate this" => "Bạn không thể đánh giá điều này",
    "You have already rated this" => "Bạn đã đánh giá điều này",

    //im
    'invites you to join the group' => 'mời bạn tham gia nhóm',

];
