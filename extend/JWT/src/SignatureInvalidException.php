<?php
namespace Firebase\JWT;

class SignatureInvalidException extends \UnexpectedValueException
{
  public $msg ;
  function __construct($msg){
    $this->msg =  $msg;
  }
  public function init(){
    echo json_encode(array('code'=>1,'msg'=>$this->msg));
    exit;
  }

}
