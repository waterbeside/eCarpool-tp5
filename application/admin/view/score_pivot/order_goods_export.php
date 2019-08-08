<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Csv;


$encoding = input('param.encoding');
$filename =  md5(json_encode($filter)) . '_' . $status . '_' . time() . ($encoding ? '.xls' : '.csv');


$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$start_ab = 10;
$start_ab_c = $start_ab;

/*设置表头*/
$sheet->setCellValue('A1', lang('Order number'))
  ->setCellValue('B1', lang('Name'))
  ->setCellValue('C1', lang('Phone'))
  ->setCellValue('D1', lang('Account'))
  ->setCellValue('E1', lang('Department'))
  ->setCellValue('F1', lang('Department').'_x')
  ->setCellValue('G1', lang('Branch'))
  ->setCellValue('H1', lang('Order time'))
  ->setCellValue('I1', lang('Prize name'))
  ->setCellValue('J1', lang('Status'));
// 
// 
//把商品设到表头
foreach ($goodsList as $k => $vo) {
  $sheet->setCellValue(getAlphabet($start_ab_c) . '1', $vo['name'] . "#" . $vo['id']);
  $start_ab_c++;
}

foreach ($lists as $key => $value) {
  $rowNum = $key + 2;
  $goodStr = '';

  foreach ($value['goods'] as $k => $good) {
    $goodStr .= $good['name'] . '×' . $good['num'] . PHP_EOL;
  }

  $sheet->setCellValue('A' . $rowNum, iconv_substr($value['uuid'], 0, 8) . '/' . $value['id'])
    ->setCellValue('B' . $rowNum, (isset($value['user']['nativename']) ? $value['user']['nativename'] : $value['user']['name'] ). " ,#" . $value['user']['uid'] )
    ->setCellValue('C' . $rowNum, ' '.($value['user']['phone']))
    ->setCellValue('D' . $rowNum, $value['user']['loginname'])
    ->setCellValue('E' . $rowNum, $value['full_department'])
    ->setCellValue('F' . $rowNum, $value['user']['department_fullname'])
    ->setCellValue('G' . $rowNum, $value['user']['companyname'])
    ->setCellValue('H' . $rowNum, $value['creation_time'])
    ->setCellValue('I' . $rowNum, $goodStr)
    ->setCellValue('J' . $rowNum, $value['status']);
  $sheet->getStyle('I' . $rowNum)->getAlignment()->setWrapText(true);
  $start_ab_c = $start_ab;
  foreach ($goodsList as $k => $vo) {
    $goodNum = 0;
    if (isset($value['goods'][$vo['id']])) {
      $goodNum =  $value['goods'][$vo['id']]['num'];
    }
    $sheet->setCellValue(getAlphabet($start_ab_c) . $rowNum, $goodNum);
    $start_ab_c++;
  }
}
/*$value = "Hello World!" . PHP_EOL . "Next Line";
      $sheet->setCellValue('A1', $value)；
      $sheet->getStyle('A1')->getAlignment()->setWrapText(true);*/

$writer = $encoding ? new Xls($spreadsheet) : new Csv($spreadsheet);
if ($encoding) {
  // header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header("Content-Type: application/vnd.ms-excel; charset=GBK");
}
header('Content-Disposition: attachment;filename="' . $filename . '"'); //告诉浏览器输出浏览器名称
header('Cache-Control: max-age=0'); //禁止缓存
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
// dump($lists);
exit;
