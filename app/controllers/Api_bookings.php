<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_bookings extends Api_v1 {
    public function bookings_list(){ $uid=$this->uid();api_json_response(200,['bookings'=>$this->Booking_model->all_for_user($uid)]); }
    public function bookings_checkout() {
        $uid = $this->uid();
        $body = $this->body();
        $trip = $this->Trip_model->find_for_user((int) ($body['trip_id'] ?? 0), $uid);
        $category = (string) ($body['category'] ?? (isset($body['offset_amount']) ? 'carbon_offset' : ''));
        $amount = filter_var($body['amount'] ?? $body['offset_amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $description = is_string($body['description'] ?? null)
            ? trim(strip_tags($body['description']))
            : (isset($body['offset_amount']) ? 'Carbon offset contribution' : '');
        $categories = [
            'carbon_offset' => 'carbon offset',
            'transport' => 'transport',
            'activity' => 'activity or tour',
            'other' => 'trip expense',
        ];
        if (!$trip || !isset($categories[$category]) || $amount === false || $amount < 1 || $amount > 1000000) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Choose a trip, valid payment category, and amount between PHP 1 and PHP 1,000,000.');
        }
        if ($description === '' || mb_strlen($description) > 150) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Enter a payment description of 1 to 150 characters.');
        }
        if (!integration_paymongo_secret_ready()) {
            api_json_response(503, null, 'PAYMONGO_NOT_CONFIGURED', 'PayMongo is not configured for this environment.');
        }

        $amount = round($amount, 2);
        $booking = $this->Booking_model->create_trip_expense(
            (int) $trip['id'],
            $category,
            $description,
            $amount
        );
        $secret = trim((string) config_item('paymongo_secret_key'));
        $reference = 'ECO-' . $booking . '-' . bin2hex(random_bytes(4));
        $payload = ['data' => ['attributes' => [
            'amount' => (int) round($amount * 100),
            'currency' => 'PHP',
            'description' => 'EcoTrip ' . $categories[$category] . ': ' . $description,
            'reference_number' => $reference,
            'remarks' => 'Payment for EcoTrip trip ' . $trip['id'],
        ]]];
        $response = integration_http_request(
            'https://api.paymongo.com/v1/links',
            'POST',
            ['Authorization: Basic ' . base64_encode($secret . ':'), 'Content-Type: application/json'],
            json_encode($payload)
        );
        $result = json_decode($response['body'], true);
        $url = $result['data']['attributes']['checkout_url'] ?? '';
        $ref = $result['data']['id'] ?? '';
        if ($response['status'] < 200 || $response['status'] >= 300 || $url === '' || $ref === '') {
            $this->Booking_model->set_payment_reference($booking, 'failed-' . $booking);
            $this->Booking_model->set_status_by_id($booking, 'failed');
            api_json_response(502, null, 'PAYMONGO_ERROR', 'PayMongo could not create checkout.');
        }
        $this->Booking_model->set_payment_reference($booking, $ref);
        api_json_response(200, ['booking_id' => $booking, 'checkout_url' => $url]);
    }
    public function bookings_verify($id){$uid=$this->uid();$booking=$this->Booking_model->find_for_user((int)$id,$uid);if(!$booking)api_json_response(404,null,'NOT_FOUND','Booking not found.');$paid=str_starts_with((string)$booking['paymongo_ref'],'cs_')?integration_paymongo_checkout_paid((string)$booking['paymongo_ref'],$booking['amount']):integration_paymongo_link_paid((string)$booking['paymongo_ref'],$booking['amount']);if(!$paid){api_json_response(202,['verified'=>false,'payment_status'=>$booking['payment_status']]);}$this->Booking_model->set_status_by_reference((string)$booking['paymongo_ref'],'paid');api_json_response(200,['verified'=>true,'payment_status'=>'paid','receipt_url'=>rtrim((string)config_item('frontend_url'),' /').'/bookings/'.(int)$id.'/receipt']);}
    public function bookings_receipt($id){$uid=$this->uid();$booking=$this->Booking_model->find_for_user((int)$id,$uid);if(!$booking||$booking['payment_status']!=='paid')api_json_response(404,null,'RECEIPT_NOT_AVAILABLE','A receipt is available after payment is confirmed.');api_json_response(200,['receipt'=>$booking]);}
    public function paymongo_webhook(){
        $secret=trim((string)config_item('paymongo_webhook_secret'));$raw=$this->request->raw_body();$signature=(string)$this->request->header('Paymongo-Signature');$parts=[];foreach(explode(',',$signature)as $part){$pair=explode('=',trim($part),2);if(count($pair)==2)$parts[$pair[0]]=$pair[1];}$timestamp=$parts['t']??'';$key=(strtolower((string)(config_item('environment')?:'development'))==='production')?'li':'te';$received=$parts[$key]??'';$expected=$secret!==''?hash_hmac('sha256',$timestamp.'.'.$raw,$secret):'';if($secret===''||$timestamp===''||$received===''||!hash_equals($expected,$received))api_json_response(401,null,'INVALID_WEBHOOK_SIGNATURE','Invalid webhook signature.');$event=json_decode($raw,true);if(!is_array($event))api_json_response(400,null,'INVALID_WEBHOOK_PAYLOAD','Invalid webhook payload.');$attrs=$event['data']['attributes']??[];$type=$attrs['type']??'';$eventId=$event['data']['id']??'';$reference=$attrs['data']['id']??'';if($type==='checkout_session.payment.paid'&&is_array($attrs['data']['attributes']??null))$reference=$attrs['data']['attributes']['checkout_session_id']??$reference;if(!in_array($type,['link.payment.paid','checkout_session.payment.paid'],true))api_json_response(200,['received'=>true]);if(!is_string($reference)||$reference===''||!is_string($eventId)||$eventId==='')api_json_response(400,null,'INVALID_PAYMENT_EVENT','Invalid payment event.');if($this->Booking_model->webhook_event_exists($eventId))api_json_response(200,['received'=>true,'duplicate'=>true]);$booking=$this->Booking_model->find_by_reference($reference);if(!$booking){$this->Booking_model->record_webhook_event($eventId,$type);api_json_response(200,['received'=>true]);}$verified=str_starts_with($reference,'cs_')?integration_paymongo_checkout_paid($reference,$booking['amount']):integration_paymongo_link_paid($reference,$booking['amount']);if(!$verified)api_json_response(503,null,'PAYMENT_PENDING','Payment verification is pending.');$this->Booking_model->set_status_by_reference($reference,'paid');$this->Booking_model->record_webhook_event($eventId,$type);api_json_response(200,['received'=>true,'paid'=>true]);
    }
}
