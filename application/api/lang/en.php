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
    "No data" => "No data",
    "Parameter error" => "Parameter error",
    "No permission" => "No permission",

    'Please do not repeat the operation' => 'Please do not repeat the operation.',
    'The network is busy, please try again later' => 'The network is busy, please try again later.',

    //jwt 验证
    'Permission denied' => 'Permission denied',
    'You are not logged in' => 'You are not logged in',
    'Login status expired, please login again' => 'Login status expired, please login again',

    //用户相关
    'Please enter user name'  => 'Please enter user name',
    'Please enter your password' => 'Please enter your password',
    'User name or password error' => 'User name or password error',
    'The phone number you entered is incorrect' => 'The phone number you entered is incorrect',
    'Name error' => 'Name error',
    'The user is banned' => 'The user is banned',
    'The user is deleted' => 'The user is deleted',
    'The user has left' => 'The user has left',
    'The phone number you entered is not the phone number of the current account' => 'The phone number you entered is not the phone number of the current account.',

    'Please enter the correct old password' => 'Please enter the correct old password',
    'Two passwords are different' => 'Two passwords are different',
    'The new password should be no less than 6 characters' => 'The new password should be no less than 6 characters',

    'User does not exist' => 'User does not exist',
    'User already exists' => 'User already exists',
    'User does not exist or has resigned' => 'User does not exist or has resigned',

    'Already bound, please enter a new phone number' => 'Already bound, please enter a new phone number',
    'The mobile phone number has been marked with a new account, whether to merge?' => 'The mobile phone number has been marked with a new account, whether to merge?',
    'No need to merge' => 'No need to merge',
    'The phone number has been bound to this account, no need to merge.' => 'The phone number has been bound to this account, no need to merge.',
    'The phone number has been registered for another account' => 'The phone number has been registered for another account',
    'Please log in directly to the employee number to perform the binding operation' => 'Please log in directly to the employee number to perform the binding operation',
    'The mobile phone number has no associated employee account' => 'The mobile phone number has no associated employee account',

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
    "The departure time has passed. Unable to operate" => "The departure time has passed. Unable to operate.",
    "You have already made one trip at {:time}, should not be published twice within the same time" => "You have already made one trip at {:time}, should not be published twice within the same time",
    "You have already made one trip at {:time}, please do not post in a similar time" => "You have already made one trip at {:time}, please do not post in a similar time",
    "You have multiple trips in {:time} minutes, please do not post in a similar time" => "You have multiple trips around {:time} minutes of the departure time, please do not post in a similar time",
    'The number of empty seats cannot be empty' => 'The number of empty seats cannot be empty',
    'The trip has been completed or cancelled. Operation is not allowed' => 'The trip has been completed or cancelled. Operation is not allowed',
    'The trip not started, unable to operate' => 'The trip not started, unable to operate',
    'The trip does not exist' => 'The trip does not exist',
    'You can`t take your own' => 'You can`t take your own',
    'Failed, the owner has cancelled the trip' => 'Failed, the owner has cancelled the trip',
    'Failed, the trip has ended' => 'Failed, the trip has ended',
    'You have already taken this trip' => 'You have already taken this trip',
    'Failed, seat is full' => 'Failed, seat is full',
    'You are not the driver or passenger of this trip' => 'You are not the driver or passenger of this trip',
    'You are not the driver of this trip' => 'You are not the driver of this trip',
    'Not allowed to view other`s location information' => 'Not allowed to view other`s location information',
    'This user has not joined this trip or has cancelled the itinerary' => 'This user has not joined this trip or has cancelled the itinerary',

    "Can't see other people's location information,Because not in the allowed range of time" => "Can't see other people's location information,Because not in the allowed range of time",
    "User has not uploaded location information recently" => "User has not uploaded location information recently",


    '{:name} took your car' => '{:name} took your car',
    '{:name} accepted your ride requst' => '{:name} accepted your ride requst',
    'The driver {:name} cancelled the trip' => 'The driver {:name} cancelled the trip',
    'The passenger {:name} cancelled the trip' => 'The passenger {:name} cancelled the trip',
    'The passenger {:name} has got on your car' => 'The passenger {:name} has got on your car',

    // 班车拼车相关
    'Adding driver data failed' => 'Adding driver data failed.',
    'License plate number cannot be empty' => 'License plate number cannot be empty',
    'Not enough seats' => 'Not enough seats.',
    'Only the driver can change the number of seats' => 'Only the driver can change the number of seats.',
    'Please select a route'=>'Please select a route',
    'Please select a departure time'=>'Please select a departure time',
    'The driver is on your list of travel partners, so the driver cannot add himself as a passenger' => 'Failed, The driver is on your list of travel partners, so the driver cannot add himself as a passenger',
    'The number of seats you set cannot be less than the number of passengers on your trip' => 'The number of seats you set cannot be less than the number of passengers on your trip',
    'The trip has been going on for a while. Operation is not allowed' => 'The trip has been going on for a while. Operation is not allowed',
    'The partner does not exist or has been deleted' => 'The partner does not exist or has been deleted',
    'The request does not exist' => 'The request does not exist',
    'The request has been picked up by the driver. You can not add partners' => 'The request has been picked up by the driver. You can not add partners',
    'The request has been picked up by the driver. You can not operate the partners' => 'The request has been picked up by the driver. You can not operate the partners',
    'The route of trip is different from yours' => 'The route of trip is different from yours.',
    'The route does not exist' => 'The route does not exist.',
    'This trip has expired or ended and cannot be operated' => 'This trip has expired or ended and cannot be operated.',
    'This trip is not a passenger`s trip, and cannot merge'=> 'This trip is not a passenger`s trip, and cannot merge.',
    'This trip is not a driver`s trip, and cannot merge' => 'This trip is not a driver`s trip, and cannot merge.',
    'Trip has ended and cannot be operated' => 'Trip has ended and cannot be operated.',
    'You are among this passenger`s partner, so you cannot add yourself as a passenger' => 'Failed, You are among this passenger`s partner, so you cannot add yourself as a passenger.',
    'You are not the driver or passenger of the trip and cannot operate' => 'You are not the driver or passenger of the trip and cannot operate.',
    'You are too slow, the passenger was snatched by another driver' => 'You are too slow! The passenger was snatched by another driver.',
    'You can not add yourself as a fellow partner' => 'You can not add yourself as a fellow partner.',
    'You can not operate someone else`s trip' => 'You can not operate someone else`s trip.',
    'You cannot cancel this trip that you have not participated in' => 'You cannot cancel this trip that you have not participated in.',
    'You have similar trips that can be merged' => 'You have similar trips that can be merged.',
    'You have joined the trip' => 'You have joined the trip.',
    'Failed, "{:name}" has one or more trips in similar time' => 'Failed, "{:name}" has one or more trips in similar time.',
    'Failed, "{:name}" has been added as a partner by others in a similar time' => 'Failed, "{:name}" has been added as a partner by others in a similar time.',

    //附件相关
    "Wrong format" => 'Wrong format',
    "Upload successful" => 'Upload successful',
    "Attachment information failed to be written" => 'Attachment information failed to be written',
    "Please upload attachments" => 'Please upload attachments',
    "Not image file format" => 'Not image file format',
    'Images cannot be larger than 800K' => 'Images cannot be larger than 800K',
    'Images cannot be larger than {:size}' => 'Images cannot be larger than {:size}',
    "File not found" => 'File not found',
    "This attachment cannot be deleted" => 'This attachment cannot be deleted',
    'The file is too large to upload' => 'The file is too large to upload',

    //评分
    "You can't rate this" => "You can't rate this",
    "You have already rated this" => "You have already rated this",

    //im
    'invites you to join the group' => 'invites you to join the group',

];
