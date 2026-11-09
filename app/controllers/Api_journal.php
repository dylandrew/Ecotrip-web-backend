<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_journal extends Api_v1 {
    public function journal_list(){ $uid=$this->uid();api_json_response(200,['trips'=>$this->Journal_model->trips_for_user($uid),'entries'=>$this->Journal_model->entries_for_user($uid)]); }
    public function journal_upload(){ $uid=$this->uid();$tripId=(int)$this->request->post('trip_id',0);if(!$this->Journal_model->trip_belongs_to_user($tripId,$uid))api_json_response(422,null,'VALIDATION_ERROR','Choose one of your trips.');$stored=api_storage_store_upload($this->request->file('photo')??[]);if(isset($stored['error']))api_json_response(422,null,'VALIDATION_ERROR',$stored['error']);$caption=mb_substr(trim(strip_tags((string)$this->request->post('caption',''))),0,255);$this->Journal_model->create_entry($tripId,$stored['relative_path'],$caption);$entries=$this->Journal_model->entries_for_user($uid);api_json_response(201,['photo_url'=>$stored['url'],'entries'=>$entries]);}
    public function journal_update($id) {
        $uid = $this->uid();
        $body = $this->body();
        $caption = $body['caption'] ?? null;

        if (!is_string($caption) || mb_strlen($caption) > 255) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Enter a caption of 255 characters or fewer.');
        }
        if (!$this->Journal_model->find_owned_entry((int) $id, $uid)) {
            api_json_response(404, null, 'NOT_FOUND', 'Journal photo not found.');
        }
        if (!$this->Journal_model->update_caption((int) $id, $uid, trim(strip_tags($caption)))) {
            api_json_response(500, null, 'UPDATE_FAILED', 'The journal caption could not be updated.');
        }

        api_json_response(200, ['updated' => true]);
    }
    public function journal_delete($id){$uid=$this->uid();$entry=$this->Journal_model->delete_entry((int)$id,$uid);if(!$entry)api_json_response(404,null,'NOT_FOUND','Journal photo not found.');api_storage_delete_upload($entry['photo_path']);api_json_response(200,['deleted'=>true]);}
}
