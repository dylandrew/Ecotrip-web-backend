<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_reminders extends Api_v1 {
    public function reminders_list(){ $uid=$this->uid();api_json_response(200,['trips'=>$this->Reminder_model->trips_for_user($uid),'reminders'=>$this->Reminder_model->all_for_user($uid)]); }
    private function validated_reminder($body) {
        $tripId = filter_var($body['trip_id'] ?? null, FILTER_VALIDATE_INT);
        $rawMessage = $body['message'] ?? null;
        $rawSendAt = $body['send_at'] ?? null;
        if ($tripId === false || $tripId < 1 || !is_string($rawMessage) || !is_string($rawSendAt)) {
            return ['error' => 'Choose your trip, enter a future time, and a valid message.'];
        }

        $message = trim(strip_tags($rawMessage));
        $sendAt = DateTime::createFromFormat('!Y-m-d\TH:i', $rawSendAt);
        $dateErrors = DateTime::getLastErrors();
        if (
            $message === ''
            || mb_strlen($message) > 255
            || !$sendAt
            || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            || $sendAt->format('Y-m-d\TH:i') !== $rawSendAt
            || $sendAt <= new DateTime()
        ) {
            return ['error' => 'Choose your trip, enter a future time, and a valid message.'];
        }

        return [
            'trip_id' => $tripId,
            'message' => $message,
            'send_at' => $sendAt->format('Y-m-d H:i:s'),
        ];
    }
    public function reminders_create() {
        $uid = $this->uid();
        $reminder = $this->validated_reminder($this->body());
        if (isset($reminder['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $reminder['error']);
        }
        if (!$this->Reminder_model->belongs_to_user($reminder['trip_id'], $uid)) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Choose one of your trips.');
        }

        $id = $this->Reminder_model->create_reminder(
            $reminder['trip_id'],
            $reminder['message'],
            $reminder['send_at']
        );
        api_json_response(201, ['reminder_id' => $id]);
    }
    public function reminders_update($id) {
        $uid = $this->uid();
        $existing = $this->Reminder_model->find_for_user((int) $id, $uid);
        if (!$existing) api_json_response(404, null, 'NOT_FOUND', 'Reminder not found.');
        if ($existing['status'] !== 'pending') {
            api_json_response(409, null, 'REMINDER_NOT_PENDING', 'Only pending reminders can be changed.');
        }

        $body = $this->body();
        $body['trip_id'] = $body['trip_id'] ?? $existing['trip_id'];
        $reminder = $this->validated_reminder($body);
        if (isset($reminder['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $reminder['error']);
        }
        if (!$this->Reminder_model->belongs_to_user($reminder['trip_id'], $uid)) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Choose one of your trips.');
        }
        if (!$this->Reminder_model->update_for_user(
            (int) $id,
            $uid,
            $reminder['trip_id'],
            $reminder['message'],
            $reminder['send_at']
        )) {
            api_json_response(409, null, 'REMINDER_NOT_PENDING', 'This reminder can no longer be changed.');
        }

        api_json_response(200, ['updated' => true]);
    }
    public function reminders_delete($id) {
        $uid = $this->uid();
        $existing = $this->Reminder_model->find_for_user((int) $id, $uid);
        if (!$existing) api_json_response(404, null, 'NOT_FOUND', 'Reminder not found.');
        if ($existing['status'] !== 'pending') {
            api_json_response(409, null, 'REMINDER_NOT_PENDING', 'Only pending reminders can be deleted.');
        }
        if (!$this->Reminder_model->delete_for_user((int) $id, $uid)) {
            api_json_response(409, null, 'REMINDER_NOT_PENDING', 'This reminder can no longer be deleted.');
        }

        api_json_response(200, ['deleted' => true]);
    }
}
