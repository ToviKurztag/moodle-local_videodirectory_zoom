<?php
use \Firebase\JWT\JWT;

define('API_KEY', '-5VJNVz7TSSI9iDV4iQvIA');

define('API_SECRET', 'aYFGXJMC3cWw54LLkVszhtyyABljhF7Wsr6K');

define('API_URL', 'https://api.zoom.us/v2/');


class Zoom_Api {
    private $zoom_api_key = API_KEY;
    private $zoom_api_secret = API_SECRET;

    protected function sendRequest($data) {
        $requesturl = 'https://api.zoom.us/v2/users/';
        $headers = array(
            'authorization' => 'Bearer ' . $this->generateJWTKey(),
            'content-type'  => 'content-type: application/json'
        );
        $postfields = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $requesturl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if (!$response) {
            return $err;
        }
        return json_decode($response);
    }

    // function to generate JWT
    private function generateJWTKey() {
        $key = $this->zoom_api_key;
        $secret = $this->zoom_api_secret;
        $token = array(
            "iss" => $key,
            "exp" => time() + 3600 //60 seconds as suggested
        );
        return JWT::encode( $token, $secret );
    }

    public function createAMeeting( $data = array() ) {
        $post_time  = $data['start_date'];
        $start_time = gmdate("Y-m-d\TH:i:s", strtotime($post_time));
        $createAMeetingArray = array();
        if (! empty($data['alternative_host_ids'])) {
            if (count($data['alternative_host_ids']) > 1) {
                $alternative_host_ids = implode(",", $data['alternative_host_ids']);
            } else {
                $alternative_host_ids = $data['alternative_host_ids'][0];
            }
        }
        $createAMeetingArray['topic']      = $data['meetingTopic'];
        $createAMeetingArray['agenda']     = ! empty($data['agenda']) ? $data['agenda'] : "";
        $createAMeetingArray['type']       = ! empty($data['type']) ? $data['type'] : 2; //Scheduled
        $createAMeetingArray['start_time'] = $start_time;
        $createAMeetingArray['timezone']   = $data['timezone'];
        $createAMeetingArray['password']   = ! empty($data['password']) ? $data['password'] : "";
        $createAMeetingArray['duration']   = ! empty($data['duration']) ? $data['duration'] : 60;
        $createAMeetingArray['settings']   = array(
            'join_before_host'  => ! empty($data['join_before_host']) ? true : false,
            'host_video'        => ! empty($data['option_host_video']) ? true : false,
            'participant_video' => ! empty($data['option_participants_video']) ? true : false,
            'mute_upon_entry'   => ! empty($data['option_mute_participants']) ? true : false,
            'enforce_login'     => ! empty($data['option_enforce_login']) ? true : false,
            'auto_recording'    => ! empty($data['option_auto_recording']) ? $data['option_auto_recording'] : "none",
            'alternative_hosts' => isset($alternative_host_ids) ? $alternative_host_ids : ""
        );
        return $this->sendRequest($createAMeetingArray);
    }


}

$zoom = new Zoom_Api();


/*try{
$z = $zoom_meeting->createAMeeting(
	array(
		'start_date'=>date("Y-m-d h:i:s", strtotime('tomorrow')),
		'topic'=>'Example Test Meeting'
	)
);
echo $z->message;
} catch (Exception $ex) {
echo $ex;
}*/