<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_account extends Api_v1 {
    public function account_export(){ $id=$this->uid();api_json_response(200,['export'=>$this->User_model->export_data($id)]);}
    public function account_delete(){ $id=$this->uid();$record=$this->User_model->find_by_id($id);$password=(string)($this->body()['current_password']??'');if(!$record||(!empty($record['password_hash'])&&!password_verify($password,$record['password_hash'])))api_json_response(401,null,'INVALID_PASSWORD','Enter your current password to delete your account.');if(($record['role']??'')==='admin'&&$this->User_model->count_admins()<=1)api_json_response(422,null,'LAST_ADMIN','The last administrator account cannot be deleted.');$this->User_model->delete_user($id);api_refresh_cookie('',true);api_json_response(200,['deleted'=>true]);}
}
