<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_dashboard extends Api_v1 {
    public function dashboard(){ $uid=$this->uid();api_json_response(200,['stats'=>$this->Trip_model->stats($uid),'upcoming'=>$this->Trip_model->upcoming($uid)]); }
    public function assistant(){
        $uid=$this->uid();$b=$this->body();$message=$b['message']??'';
        if(!is_string($message))api_json_response(422,null,'VALIDATION_ERROR','Enter a message of 1,500 characters or fewer.');
        $message=trim($message);
        if($message===''||mb_strlen($message)>1500)api_json_response(422,null,'VALIDATION_ERROR','Enter a message of 1,500 characters or fewer.');
        $this->consume_rate_limit('assistant-message','user:'.$uid.':ip:'.integration_client_ip(),20,3600,'Too many assistant requests. Please try again later.');
        if(trim((string)config_item('gemini_api_key'))==='')api_json_response(503,null,'GEMINI_NOT_CONFIGURED','Gemini is not configured.');
        $history=$b['history']??[];
        if(is_string($history)){
            $decodedHistory=json_decode($history,true);
            $history=is_array($decodedHistory)?$decodedHistory:[];
        }
        $conversation=[];
        foreach(array_slice(is_array($history)?$history:[],-6)as $turn){
            if(!is_array($turn)||!in_array($turn['role']??'',['user','model'],true))continue;
            $text=$turn['text']??'';
            if(!is_string($text))continue;
            $text=trim(strip_tags($text));if($text==='')continue;
            $conversation[]=(($turn['role']==='user')?'Traveler':'EcoTrip').': '.mb_substr($text,0,1000);
        }
        $conversation[]='Traveler: '.$message;
        $response=integration_gemini_interact(implode("\n\n",$conversation),'You are EcoTrip, a concise and friendly sustainable-travel assistant. Help users plan practical, lower-carbon trips, compare transport modes, estimate budgets, and choose eco-conscious stays. Do not invent operators, schedules, fares, weather, or booking status.',['max_output_tokens'=>600,'thinking_level'=>'low']);
        if($response['status']<200||$response['status']>=300||trim($response['text'])===''){
            $detail = preg_replace('/\s+/', ' ', trim((string) ($response['error'] ?? '')));
            error_log(sprintf(
                'Gemini assistant request failed (HTTP %d, model %s, temporary %s): %s',
                (int) ($response['status'] ?? 0),
                (string) ($response['model'] ?? 'unknown'),
                !empty($response['temporary']) ? 'yes' : 'no',
                $detail !== '' ? mb_substr($detail, 0, 300) : 'No response text returned.'
            ));
            if(($response['status']??0)===429)$error='Gemini is busy right now. Wait a moment and try again.';
            elseif(!empty($response['temporary']))$error='Gemini is temporarily busy. Please try again shortly.';
            else$error='The EcoTrip assistant could not respond. Check Gemini API access and try again.';
            api_json_response(502,null,'ASSISTANT_UNAVAILABLE',$error);
        }
        api_json_response(200,['reply'=>$response['text']]);
    }
}
