<?php

return [
  // 'grade_start_date' =>  "2019-03-18 00:00:00"
  'grade_switch' => [
    "1" => [
      'start_date' =>  "2119-03-20 00:00:00",
      'end_date' =>  "2120-03-20 00:00:00"
    ],
    "2" => [
      'start_date' =>  "2119-03-20 00:00:00",
      'end_date' =>  "2120-03-20 00:00:00"
    ],
  ],
  'gps_interval' => '30',
  'shuttle_trip_launch_date' => '2080-05-02 00:00:00',
  'trip_matching_radius' => 300, // 行程匹配半径(米为单位)
  'trip_max_timeoffset' => 3600, // 行程可选的最大偏移值(值为单位)
  'matching_degree_der' => 20000 // 计算行程匹配度的分母距离(米为单位) 20 * 1000 米
];
