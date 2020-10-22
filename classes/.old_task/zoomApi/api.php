<?php
 /*
 * Library Name: Zoom.us API Integration
 * Description:  This library helps developer to connect zoom APi for managing meetings, live conferences.
 *               On this library, we have taken only Mandatory fields. If you wish to pass more parameter, then refer the links we have put before function
 * Author: KumarShyama
 * Version: 1.0
 */

global $CFG;

//require(dirname(__FILE__) . '/config.php');
require(dirname(__FILE__) . '/JWT.php');

include_once(__DIR__ .'/../../../../../config.php');
include_once(__DIR__ .'/../../../../../lib/filelib.php');

class zoomapi {

    /*The API Key, Secret, & URL will be used in every function.*/
    private $apikey = get_config('local_video_directory' , 'zoomApiKey');
    private $apisecret = get_config('local_video_directory' , 'zoomApiSecret');
    private $apiurl = get_config('local_video_directory' , 'zoomApiUrl');

    function make_call($url, $data = array(), $method = 'get') {

        $url = $this->apiurl . $url;
        $method = strtolower($method);
        $curl = new curl();
        $payload = array(
            'iss' => $this->apikey,
            'exp' => time() + 40
        );
        $token = \Firebase\JWT\JWT::encode($payload, $this->apisecret);

        $curl->setHeader('Authorization: Bearer ' . $token);

        if ($method != 'get') {
            $curl->setHeader('Content-Type: application/json');
            $data = is_array($data) ? json_encode($data) : $data;
        }

        $response = call_user_func_array(array($curl, $method), array($url, $data));

        if ($curl->get_errno()) {
            die;
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
        }

        $response = json_decode($response);

        $httpstatus = $curl->get_info()['http_code'];

        if (isset($response->code) && $response->code == 3301) {
            echo  'ERROR 3301: video not exsist!';
            return 3301;
        }

        if ($httpstatus >= 400) {
            if ($response) {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', $response->message);
            } else {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', "HTTP Status $httpstatus");
            }
        }

        return $response;
    }


    // Function to send HTTP POST Requests Used by every function below to make HTTP POST call
    function sendrequest($calledfunction, $data) {
        /*Creates the endpoint URL*/
        $requesturl = $this->apiurl.$calledfunction;

        /*Adds the Key, Secret, & Datatype to the passed array*/
        $data['api_key'] = $this->apikey;
        $data['api_secret'] = $this->apisecret;
        $data['data_type'] = 'JSON';

        $postfields = http_build_query($data);
        /*Check to see queried fields*/
        /*Used for troubleshooting/debugging*/
        echo $postfields;

        /*Preparing Query...*/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $requesturl);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        //curl_setopt($ch, CURLOPT_POST, 1);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);

        /*Check for any errors*/
        $errormessage = curl_exec($ch);
        echo $errormessage;
        curl_close($ch);

        /*Will print back the response from the call*/
        /*Used for troubleshooting/debugging		*/
        echo $requesturl;
        var_dump($data);
        var_dump($response);
        if (!$response) {
            return false;
        }
        /*Return the data in JSON format*/
        return json_encode($response);
    }


    // Functions for management of users (Ref: https://support.zoom.us/hc/en-us/articles/201363033-REST-User-API)

    public function createAUser($useremail, $usertype) {
        $createuserarray = array();
        $createuserarray['email'] = $useremail;
        $createuserarray['type'] = $usertype;
        return $this->sendrequest('user/create', $createuserarray);
    }
    
    public function patchmeetingrecordingssettings($meetingid, $status) {
        $data = array();
        $method = 'patch';
        $data['viewer_download'] = $status;
        return $this->make_call('meetings/'.$meetingid .'/recordings/settings', $data, $method);
    }

    function autoCreateAUser($useremail, $usertype, $userpassword) {
        $autoCreateAUserArray = array();
        $autoCreateAUserArray['email'] = $useremail;
        $autoCreateAUserArray['type'] = $usertype;
        $autoCreateAUserArray['password'] = $userpassword;
        return $this->sendrequest('user/autocreate', $autoCreateAUserArray);
    }

    function custCreateAUser($useremail, $usertype) {
        $custCreateAUserArray = array();
        $custCreateAUserArray['email'] = $useremail;
        $custCreateAUserArray['type'] = $usertype;
        return $this->sendrequest('user/custcreate', $custCreateAUserArray);
    }

    function deleteAUser($userid) {
        $deleteuserarray = array();
        $deleteuserarray['id'] = $userid;
        return $this->sendrequest('user/delete', $deleteuserarray);
    }

    function listUsers_multy($i) {
        $listusersarray = array();
        return $this->make_call('/users?status=active&page_number=' .$i. '&page_size=100', $listusersarray, 'get');
    }

    function listUsers() {
        // Any requst can puul data for 100 users.
        // So, the loop runs num of accounts / 100 + 1 times.
        $numofaccounts = get_config('local_video_directory' , 'numofzoomusers');
        $loop = $numofaccounts / 100 + 1;
        $users = array();
        $listusersarray = array();
        for ($i = 1; $i < $loop; $i ++) {
            $data = $this->listUsers_multy($i);
            if ( !empty($data->users)) {
                $users = array_merge($users, $data->users);

            } else {
                return $users;
            } // There is no more users to bring data to.
            if (count($data->users) != 100 ) {
                return $users;
            }
        }
        echo count($users);

        return $users;
    }

    function listPendingUsers() {
        $listpendingusersarray = array();
        return $this->sendrequest('user/pending', $listpendingusersarray);
    }

    function getuserinfo($userid) {
        $getuserinfoarray = array();
        $getuserinfoarray['id'] = $userid;
        return $this->make_call('users/' . $userid, [], 'get');
        // return $this->sendrequest('user/get',$getUserInfoArray);
    }

    function getUserInfoByEmail($useremail, $userlogintype) {
        $getuserinfobyemailarray = array();
        $getuserinfobyemailarray['email'] = $useremail;
        $getuserinfobyemailarray['login_type'] = $userlogintype;
        return $this->sendrequest('user/getbyemail', $getuserinfobyemailarray);
    }

    function updateUserInfo($userid) {
        $updateuserinfoarray = array();
        $updateuserinfoarray['id'] = $userid;
        return $this->sendrequest('user/update', $updateuserinfoarray);
    }

    function updateUserPassword($userid, $usernewpassword) {
        $updateuserpasswordarray = array();
        $updateuserpasswordarray['id'] = $userid;
        $updateuserpasswordarray['password'] = $usernewpassword;
        return $this->sendrequest('user/updatepassword', $updateuserpasswordarray);
    }

    function setUserAssistant($userid, $useremail, $assistantemail) {
        $setuserassistantarray = array();
        $setuserassistantarray['id'] = $userid;
        $setuserassistantarray['host_email'] = $useremail;
        $setuserassistantarray['assistant_email'] = $assistantemail;
        return $this->sendrequest('user/assistant/set', $setuserassistantarray);
    }

    function deleteUserAssistant($userid, $useremail, $assistantemail) {
        $deleteuserassistantarray = array();
        $deleteuserassistantarray['id'] = $userid;
        $deleteuserassistantarray['host_email'] = $useremail;
        $deleteuserassistantarray['assistant_email'] = $assistantemail;
        return $this->sendrequest('user/assistant/delete', $deleteuserassistantarray);
    }

    function revokeSSOToken($userid, $useremail) {
        $revokeSSOTokenArray = array();
        $revokeSSOTokenArray['id'] = $userid;
        $revokeSSOTokenArray['email'] = $useremail;
        return $this->sendrequest('user/revoketoken', $revokeSSOTokenArray);
    }

    function deleteUserPermanently($userid, $useremail) {
        $deleteUserPermanentlyArray = array();
        $deleteUserPermanentlyArray['id'] = $userid;
        $deleteUserPermanentlyArray['email'] = $useremail;
        return $this->sendrequest('user/permanentdelete', $deleteUserPermanentlyArray);
    }



    // Functions for management of meetings (Ref: https://support.zoom.us/hc/en-us/articles/201363053-REST-Meeting-API)

    function createAMeeting($userid, $meetingtopic, $meetingtype) {
        $createAMeetingArray = array();
        $createAMeetingArray['host_id'] = $userid;
        $createAMeetingArray['topic'] = $meetingtopic;
        $createAMeetingArray['type'] = $meetingtype;
        return $this->sendrequest('meeting/create', $createAMeetingArray);
    }

    function deleteAMeeting($meetingid, $userid) {
        $deleteAMeetingArray = array();
        $deleteAMeetingArray['id'] = $meetingid;
        $deleteAMeetingArray['host_id'] = $userid;
        return $this->sendrequest('meeting/delete', $deleteAMeetingArray);
    }

    function listmeetings($userid) {
        $listmeetingsarray = array();
        //$listmeetingsarray['host_id'] = $userid;
        return $this->make_call('users/'. $userid .'/meetings?type=scheduled&page_size=100', $listmeetingsarray);

        //return $this->sendrequest('meeting/list', $listmeetingsarray);
    }

    function getMeetingInfo() {
        $getMeetingInfoArray = array();
        $getMeetingInfoArray['id'] = $_POST['meetingid'];
        $getMeetingInfoArray['host_id'] = $_POST['userid'];
        return $this->sendrequest('meeting/get', $getMeetingInfoArray);
    }

    function updateMeetingInfo($meetingid, $userid) {
        $updateMeetingInfoArray = array();
        $updateMeetingInfoArray['id'] = $meetingid;
        $updateMeetingInfoArray['host_id'] = $userid;
        return $this->sendrequest('meeting/update', $updateMeetingInfoArray);
    }

    function endAMeeting($meetingid, $userid) {
        $endAMeetingArray = array();
        $endAMeetingArray['id'] = $meetingid;
        $endAMeetingArray['host_id'] = $userid;
        return $this->sendrequest('meeting/end', $endAMeetingArray);
    }

    function listRecording($userid) {
        $listRecordingArray = array();
        $listRecordingArray['host_id'] = $userid;
        return $this->sendrequest('recording/list', $listRecordingArray);
    }

    function listRecordinga($userid) {
        $listrecordingarray = array();
        // $listRecordingArray['host_id'] = $userid;
        $month = get_config('local_video_directory' , 'monthtopull');
        if ($month == 'current') {
            $from = date("Y-m-d", strtotime("-1 month"));
            $to = date("Y-m-d");
        } else {
            $from = date("Y") . '-' . $month . '-01';
            $to = date("Y") . '-' . ($month + 1) . '-01';
        }
        return $this->make_call('users/'.$userid .'/recordings?from='. $from . '&to='. $to .'&page_size=100', $listrecordingarray);
}

    public function getmeetingrecordings($meetingid) {
        $listrecordingarray = array();
        return $this->make_call('meetings/'.$meetingid .'/recordings', $listrecordingarray);
    }

    // Functions for management of webinars (Ref: https://support.zoom.us/hc/en-us/articles/204484645-REST-Webinar-API).

    function createAWebinar($userid, $topic) {
        $createAWebinarArray = array();
        $createAWebinarArray['host_id'] = $userid;
        $createAWebinarArray['topic'] = $topic;
          $createAWebinarArray['option_audio'] = 'both';
          $createAWebinarArray['type'] = '5';
        return $this->sendrequest('webinar/create', $createAWebinarArray);
    }

    function deleteAWebinar($webinarId, $userid) {
        $deleteAWebinarArray = array();
        $deleteAWebinarArray['id'] = $webinarId;
        $deleteAWebinarArray['host_id'] = $userid;
        return $this->sendrequest('webinar/delete', $deleteAWebinarArray);
    }

    function listWebinars($userid) {
        $listWebinarsArray = array();
        $listWebinarsArray['host_id'] = $userid;
        return $this->sendrequest('webinar/list', $listWebinarsArray);
    }

    function getWebinarInfo($webinarId, $userid) {
        $getWebinarInfoArray = array();
        $getWebinarInfoArray['id'] = $webinarId;
        $getWebinarInfoArray['host_id'] = $userid;
        return $this->sendrequest('webinar/get', $getWebinarInfoArray);
    }

	function updateWebinarInfo($webinarId, $userid) {
        $updateWebinarInfoArray = array();
        $updateWebinarInfoArray['id'] = $webinarId;
        $updateWebinarInfoArray['host_id'] = $userid;
        return $this->sendrequest('webinar/update',$updateWebinarInfoArray);
	}

	function endAWebinar($webinarId, $userid) {
        $endAWebinarArray = array();
        $endAWebinarArray['id'] = $webinarId;
        $endAWebinarArray['host_id'] = $userid;
        return $this->sendrequest('webinar/end',$endAWebinarArray);
	}
        
    // Functions for management of Dashboard (Ref: https://support.zoom.us/hc/en-us/articles/208403693-REST-Dashboard-API)
        
    function getMeetingList($type=1, $from, $to) {
        $createADashboardArray = array();
        $createADashboardArray['type'] = $type;
        $createADashboardArray['from'] = $from;
          $createADashboardArray['to'] = $to;
        return $this->sendrequest('metrics/meetings',$createADashboardArray);
	}
        
    function getMeetingDetails($meeting_id, $type) {
        $createADashboardArray = array();
        $createADashboardArray['meeting_id'] = $meeting_id;
        $createADashboardArray['type'] = $type;
        return $this->sendrequest('metrics/meetingdetail',$createADashboardArray);
	}

    function getWebnairList($type=1, $from, $to) {
        $createADashboardArray = array();
        $createADashboardArray['type'] = $type;
        $createADashboardArray['from'] = $from;
        $createADashboardArray['to'] = $to;
        return $this->sendrequest('metrics/webinars',$createADashboardArray);
    }

    function getWebnairDetails($meeting_id, $type) {
        $createADashboardArray = array();
        $createADashboardArray['meeting_id'] = $meeting_id;
        $createADashboardArray['type'] = $type;
        return $this->sendrequest('metrics/webinardetail',$createADashboardArray);
    }

    function getUserQoS($meeting_id, $type, $user_id) {
        $createADashboardArray = array();
        $createADashboardArray['meeting_id'] = $meeting_id;
        $createADashboardArray['type'] = $type;
        $createADashboardArray['user_id'] = $user_id;          
        return $this->sendrequest('metrics/qos',$createADashboardArray);
    }

    function zoomRoomList() {
        return $this->sendrequest('metrics/zoomrooms');
    }

    function getCRCPortUsage($from, $to) {
        $createADashboardArray = array();
        $createADashboardArray['from'] = $from;
        $createADashboardArray['to'] = $to;        
        return $this->sendrequest('metrics/crc',$createADashboardArray);
    }

    // Functions for management of Report (Ref: https://support.zoom.us/hc/en-us/articles/201363083-REST-Report-API)

    function getDailyReport($year, $month) {
        $createAccountReportArray = array();
        $createAccountReportArray['year'] = $year;
        $createAccountReportArray['month'] = $month;        
        return $this->sendrequest('report/getdailyreport',$createAccountReportArray);
    }

    function getAccountReport($from, $to) {
        $createAccountReportArray = array();
        $createAccountReportArray['from'] = $from;
        $createAccountReportArray['to'] = $to;        
        return $this->sendrequest('report/getaccountreport',$createAccountReportArray);
    }

    function getUserReport($user_id, $from, $to) {
        $createAccountReportArray = array();
        $createAccountReportArray['user_id'] = $user_id;
        $createAccountReportArray['from'] = $from;
        $createAccountReportArray['to'] = $to;        
        return $this->sendrequest('report/getuserreport',$createAccountReportArray);
    }

    function getAudioReport($from, $to) {
        $createAccountReportArray = array();
        $createAccountReportArray['from'] = $from;
        $createAccountReportArray['to'] = $to;        
        return $this->sendrequest('report/getaudioreport',$createAccountReportArray);
    }
        
    // Functions for management of Archived Chat Messages (Ref: https://support.zoom.us/hc/en-us/articles/208064196-REST-Archived-Chat-Messages-API)
        
    function getChatHistoryList($access_token, $from, $to) {
        $createChattArray = array();
        $createChattArray['access_token'] = $access_token;
        $createChattArray['from'] = $from;
        $createChattArray['to'] = $to;        
        return $this->sendrequest('chat/list',$createChattArray);
    }

    function getChatMessage($access_token, $session_id, $from, $to) {
        $createChattArray = array();
        $createChattArray['access_token'] = $access_token;
        $createChattArray['session_id'] = $session_id;
        $createChattArray['from'] = $from;
        $createChattArray['to'] = $to;        
        return $this->sendrequest('chat/get',$createChattArray);
    }
        
        // Functions for management of Archived Chat Messages (Ref: https://support.zoom.us/hc/en-us/articles/208064196-REST-Archived-Chat-Messages-API)
        
    function getIMGroupsList() {
        return $this->sendrequest('im/group/list');
    }
        
    function getIMGroupsInfo($group_id) {
        $createIMArray = array();
        $createIMArray['id'] = $group_id;
        return $this->sendrequest('im/group/get',$createIMArray);
    }
        
    function createIMGroup($name) {
        $createIMArray = array();
        $createIMArray['name'] = $name;
                  
        return $this->sendrequest('im/group/create',$createIMArray);
    }
        
    function editIMGroup($group_id) {
        $createIMArray = array();
        $createIMArray['id'] = $group_id;
        return $this->sendrequest('im/group/edit',$createIMArray);
    }
    function deleteIMGroup($group_id) {
        $createIMArray = array();
        $createIMArray['id'] = $group_id;
        return $this->sendrequest('im/group/delete',$createIMArray);
    }
    function AddIMGroupMember($group_id, $member_ids) {
        $createIMArray = array();
        $createIMArray['id'] = $group_id;
          $createIMArray['member_ids'] = $member_ids;
        return $this->sendrequest('im/group/member/add',$createIMArray);
    }

    function deleteIMGroupMember($group_id, $member_ids) {
        $createIMArray = array();
        $createIMArray['id'] = $group_id;
          $createIMArray['member_ids'] = $member_ids;  

        return $this->sendrequest('im/group/member/delete',$createIMArray);
    }

        // Functions for management of Cloud Recording API (Ref: https://support.zoom.us/hc/en-us/articles/206324325-REST-Cloud-Recording-API)

    function getRecordingList($host_id) {
          $createCloudRecordingArray = array();
          $createCloudRecordingArray['host_id'] = $host_id;
        return $this->sendrequest('recording/list', $createCloudRecordingArray);
    }
    function getRecordingForMachine($host_id) {
          $createCloudRecordingArray = array();
          $createCloudRecordingArray['host_id'] = $host_id;
          
        return $this->sendrequest('mc/recording/list', $createCloudRecordingArray);
    }
        
    function getRecording($meeting_id) {
          $createCloudRecordingArray = array();
          $createCloudRecordingArray['meeting_id'] = $meeting_id;
          
        return $this->sendrequest('recording/get', $createCloudRecordingArray);
    }
        
    function deleteRecording($meeting_id) {
          $createCloudRecordingArray = array();
          $createCloudRecordingArray['meeting_id'] = $meeting_id;
          
        return $this->sendrequest('recording/delete', $createCloudRecordingArray);
    }


    public function getmeetingrecord($meetingid) {
        
    }
}

?> 
