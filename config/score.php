<?php
return [
    "reason" => [
      "-99"=>"管理员操作",
      "-100"=>"拼车不合法",
      "-200"=>"商品消费",
      "-300"=>"系统操作",
      "1"=>"旧积分补入",
      "99"=>"管理员操作",
      "100"=>"拼车合法",
      "200"=>"取消商品兑换",
      "300"=>"系统操作",
    ],
    "reason_operable" =>['-99','1','99'],
    'platform' => [
      'Unknow','iOS','Android','H5','master'
    ],
    "accountType" => [
      ["name"=>"score","field"=>"account"],
      ["name"=>"phone","field"=>"phone"],
      ["name"=>"carpool","field"=>"carpool_account"],
    ]
];
