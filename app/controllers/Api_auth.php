<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_auth extends Api_v1 {
    public function register() {
        $b=$this->body(); $name=trim(strip_tags((string)($b['name']??''))); $email=strtolower(trim((string)($b['email']??''))); $password=(string)($b['password']??''); $address=mb_substr(trim(strip_tags((string)($b['address']??''))),0,255);
        if($name===''||mb_strlen($name)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12||strlen($password)>200||$address==='') api_json_response(422,null,'VALIDATION_ERROR','Enter your name, address, valid email, and a password with at least 12 characters.');
        if($this->User_model->find_by_email($email)) api_json_response(409,null,'EMAIL_EXISTS','An account with that email already exists.');
        $id=$this->User_model->create_user($name,$email,password_hash($password,PASSWORD_DEFAULT),'','traveler',$address); api_audit_log($this->db,(int)$id,'user.created','user',(int)$id); $tokens=$this->issue_tokens((int)$id);
        api_json_response(201,['user'=>$this->public_user($this->User_model->find_by_id((int)$id))]+$tokens);
    }
    public function login() {
        $b=$this->body(); $email=strtolower(trim((string)($b['email']??''))); $password=(string)($b['password']??''); $rateKey='login:'.$email.':'.integration_client_ip(); $rate=integration_rate_limit_check($this->db,'login-failures',$rateKey,5,900);
        if(!empty($rate['unavailable']))api_json_response(503,null,'RATE_LIMIT_UNAVAILABLE','Request throttling is temporarily unavailable.');
        if(!$rate['allowed']) { header('Retry-After: '.max(1,(int)$rate['retry_after'])); api_json_response(429,null,'RATE_LIMITED','Too many failed login attempts. Try again later.'); }
        $user=$this->User_model->find_by_email($email);
        if(!$user||empty($user['password_hash'])||!password_verify($password,$user['password_hash'])) { if(!integration_rate_limit_hit($this->db,'login-failures',$rateKey,900))api_json_response(503,null,'RATE_LIMIT_UNAVAILABLE','Request throttling is temporarily unavailable.'); api_json_response(401,null,'INVALID_CREDENTIALS','Email or password is incorrect.'); }
        integration_rate_limit_reset($this->db,'login-failures',$rateKey);
        api_json_response(200,['user'=>$this->public_user($user)]+$this->issue_tokens((int)$user['id']));
    }
    public function logout() {
        $refresh=$this->request->cookie('refresh_token',''); if(is_string($refresh)&&$refresh!=='') $this->api->revoke_refresh_token($refresh); api_refresh_cookie('',true); api_json_response(200,['logged_out'=>true]);
    }
    public function refresh() {
        $refresh=$this->request->cookie('refresh_token',''); $payload=is_string($refresh)&&$refresh!==''?$this->api->validate_jwt($refresh,'refresh'):false;
        if(!$payload) api_json_response(403,null,'INVALID_REFRESH_TOKEN','Refresh token expired or revoked.');
        $hashed=hash_hmac('sha256',$refresh,trim((string)config_item('refresh_token_key')));
        if(!$this->Refresh_token_model->exists_for_hash($hashed)) api_json_response(403,null,'INVALID_REFRESH_TOKEN','Refresh token expired or revoked.');
        $this->api->revoke_refresh_token($refresh); $user=$this->User_model->find_by_id((int)$payload['sub']); if(!$user) api_json_response(403,null,'USER_NOT_FOUND','User not found.'); api_json_response(200,['user'=>$this->public_user($user)]+$this->issue_tokens((int)$user['id']));
    }
    private function issue_tokens($id) {
        $user=$this->User_model->find_by_id($id); $tokens=$this->api->issue_tokens(['id'=>(int)$id,'role'=>$user['role']??'traveler','scopes'=>($user['role']??'traveler')==='admin'?['read','write','delete','admin']:['read','write','delete']]); api_refresh_cookie($tokens['refresh_token']); unset($tokens['refresh_token']); return $tokens;
    }
    public function forgot_password() {
        $b=$this->body(); $email=strtolower(trim((string)($b['email']??''))); $user=filter_var($email,FILTER_VALIDATE_EMAIL)?$this->User_model->find_by_email($email):null;
        if($user){$previous=$this->Password_reset_model->find_by_email($email); $now=time(); if(!$previous||strtotime($previous['requested_at'])<=($now-60)){ $otp=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT); $this->Password_reset_model->save_otp($email,(int)$user['id'],password_hash($otp,PASSWORD_DEFAULT),date('Y-m-d H:i:s',$now+600),date('Y-m-d H:i:s',$now)); $frontend=rtrim((string)(config_item('frontend_url')?:'http://localhost:5173'),' /'); $link=$frontend.'/reset-password?email='.rawurlencode($email).'&otp='.rawurlencode($otp); integration_send_email($email,'Your EcoTrip password reset link','<p>Reset your password: <a href="'.htmlspecialchars($link).'">Reset password</a></p><p>This link is valid for 10 minutes.</p>'); }}
        api_json_response(200,['sent'=>true]);
    }
    public function reset_password() {
        $b=$this->body(); $email=strtolower(trim((string)($b['email']??''))); $otp=trim((string)($b['otp']??'')); $password=(string)($b['password']??''); $confirm=(string)($b['confirm_password']??$b['password_confirmation']??''); $reset=filter_var($email,FILTER_VALIDATE_EMAIL)?$this->Password_reset_model->find_by_email($email):null;
        if(!$reset||strtotime($reset['expires_at'])<time()||(int)$reset['attempts']>=5||!preg_match('/^\d{6}$/',$otp)||!password_verify($otp,$reset['otp_hash'])){if($reset&&(int)$reset['attempts']<5)$this->Password_reset_model->increment_attempts($email); api_json_response(400,null,'INVALID_RESET','The email or reset code is invalid or expired.');}
        if(strlen($password)<12||strlen($password)>200||$password!==$confirm) api_json_response(422,null,'VALIDATION_ERROR','Use a password of at least 12 characters and enter it the same way twice.'); $this->User_model->update_password((int)$reset['user_id'],password_hash($password,PASSWORD_DEFAULT)); $this->Password_reset_model->delete_for_email($email); api_json_response(200,['reset'=>true]);
    }
    public function change_password() {
        $id=$this->uid(); $b=$this->body(); $record=$this->User_model->find_by_id($id); $current=(string)($b['current_password']??''); $new=(string)($b['new_password']??''); $confirm=(string)($b['confirm_password']??''); if(!$record||(!empty($record['password_hash'])&&!password_verify($current,$record['password_hash']))) api_json_response(401,null,'INVALID_PASSWORD','Current password is incorrect.'); if(strlen($new)<12||strlen($new)>200||$new!==$confirm||password_verify($new,$record['password_hash'])) api_json_response(422,null,'VALIDATION_ERROR','Use a new password of at least 12 characters and enter it the same way twice.'); $this->User_model->update_password($id,password_hash($new,PASSWORD_DEFAULT)); api_json_response(200,['changed'=>true]);
    }
    public function update_address() { $id=$this->uid(); $address=mb_substr(trim(strip_tags((string)($this->body()['address']??''))),0,255); if($address==='') api_json_response(422,null,'VALIDATION_ERROR','Enter a complete home address.'); $this->User_model->update_address($id,$address); api_json_response(200,['address'=>$address]); }
    public function google_start() {
        if(!integration_google_configured()) api_json_response(503,null,'GOOGLE_NOT_CONFIGURED','Google sign-in is not configured.'); $nonce=bin2hex(random_bytes(32)); $verifier=rtrim(strtr(base64_encode(random_bytes(64)),'+/','-_'),'='); $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='); $payload=['nonce'=>$nonce,'verifier'=>$verifier,'exp'=>time()+600]; $raw=base64_encode(json_encode($payload)); $encodedState=rtrim(strtr($raw,'+/','-_'),'='); $state=$encodedState.'.'.hash_hmac('sha256',$encodedState,(string)config_item('jwt_secret')); $redirect=rtrim((string)config_item('google_redirect_uri'),'/'); $query=http_build_query(['client_id'=>trim((string)config_item('google_client_id')),'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'nonce'=>$nonce,'code_challenge'=>$challenge,'code_challenge_method'=>'S256']); api_json_response(200,['authorization_url'=>'https://accounts.google.com/o/oauth2/v2/auth?'.$query]);
    }
    public function google_callback() {
        $state=(string)$this->request->get('state',''); $parts=explode('.',$state,2); if(count($parts)!==2||!hash_equals(hash_hmac('sha256',$parts[0],(string)config_item('jwt_secret')),$parts[1])) api_json_redirect_error('invalid_state'); $raw=$parts[0]; if(strlen($raw)>9000) api_json_redirect_error('invalid_state'); $payload=json_decode(base64_decode(strtr($raw,'-_','+/')),true); if(!is_array($payload)||!isset($payload['nonce'],$payload['verifier'],$payload['exp'])||$payload['exp']<time()) api_json_redirect_error('invalid_state'); $code=(string)$this->request->get('code',''); if($code===''||strlen($code)>4096) api_json_redirect_error('invalid_code');
        $tokens_response=integration_http_request('https://oauth2.googleapis.com/token','POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query(['code'=>$code,'client_id'=>trim((string)config_item('google_client_id')),'client_secret'=>trim((string)config_item('google_client_secret')),'redirect_uri'=>rtrim((string)config_item('google_redirect_uri'),'/'),'grant_type'=>'authorization_code','code_verifier'=>$payload['verifier']]),15); $tokens=json_decode($tokens_response['body'],true); if($tokens_response['status']!==200||!is_string($tokens['id_token']??null)) api_json_redirect_error('exchange_failed');
        $keys=integration_http_request('https://www.googleapis.com/oauth2/v3/certs','GET',[],null,15); $keyset=json_decode($keys['body'],true); try{$claims=\Firebase\JWT\JWT::decode($tokens['id_token'],\Firebase\JWT\JWK::parseKeySet($keyset));}catch(\Throwable $e){api_json_redirect_error('invalid_token');}
        $audiences=is_array($claims->aud??null)?$claims->aud:[$claims->aud??'']; if(!in_array(trim((string)config_item('google_client_id')),$audiences,true)||empty($claims->sub)||empty($claims->email)||($claims->email_verified??false)!==true||!hash_equals((string)$payload['nonce'],(string)($claims->nonce??''))) api_json_redirect_error('invalid_token'); $email=strtolower((string)$claims->email); $user=$this->User_model->find_by_email($email); if($user){if(empty($user['google_sub']))$this->User_model->link_google_sub((int)$user['id'],(string)$claims->sub);}else{$id=$this->User_model->create_google_user(trim((string)($claims->name??$email)),filter_var($email,FILTER_VALIDATE_EMAIL)?$email:'', (string)$claims->sub); $user=$this->User_model->find_by_id((int)$id);}
        $oneTime=bin2hex(random_bytes(32)); $hash=hash('sha256',$oneTime); if(!$this->Auth_code_model->create_code((int)$user['id'],$hash,date('Y-m-d H:i:s',time()+300))){error_log('Google sign-in code could not be stored.');api_json_redirect_error('exchange_failed');} $front=rtrim((string)(config_item('frontend_url')?:'http://localhost:5173'),' /'); header('Location: '.$front.'/auth/google/callback?code='.rawurlencode($oneTime),true,302); exit;
    }
    public function google_exchange() {
        $body = $this->body();
        $code = is_string($body['code'] ?? null) ? trim($body['code']) : '';
        if ($code === '' || strlen($code) > 256) {
            api_json_response(401, null, 'INVALID_ONE_TIME_CODE', 'The Google sign-in code is invalid or expired.');
        }

        $userId = $this->Auth_code_model->consume_code(hash('sha256', $code));
        if (!$userId) {
            api_json_response(401, null, 'INVALID_ONE_TIME_CODE', 'The Google sign-in code is invalid or expired.');
        }

        $user = $this->User_model->find_by_id($userId);
        if (!$user) {
            api_json_response(401, null, 'INVALID_ONE_TIME_CODE', 'The Google sign-in code is invalid or expired.');
        }

        api_json_response(200, ['user' => $this->public_user($user)] + $this->issue_tokens($userId));
    }
}
