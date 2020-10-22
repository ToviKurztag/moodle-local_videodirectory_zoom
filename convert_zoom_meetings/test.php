<?php

define('CLI_SCRIPT',1);
include(dirname(__FILE__).'/api.php');
global $CFG, $DB;

$api = new zoomapi();
$pathlog = dirname(__FILE__) . '/zoomLog.log';
$path = $CFG->dataroot . '/local_video_directory_videos/';

$users = $api->listUsers();
$uu = 0;
$counter = 0;

foreach ($users as $user) {
    $uu ++;
    echo "user: " . $uu ;
    file_put_contents( $pathlog, "\n" . "\n" . '--------------------------------- time: ' . time() . ' ---------------------------------', FILE_APPEND);
    // $meetings = $api->listmeetings($user->id);
    $meetings = $api->listRecordinga($user->id);

    
    file_put_contents( $pathlog, "\n" . 'list videos for user: '. $user->id . ' mail: '. $user->email, FILE_APPEND);

    $owner = $DB->get_field('user', 'id', ['email' => $user->email]);
    $sql = "SELECT 'orig_filename'
    FROM {local_video_directory}
    WHERE 'owner_id' = ?";
    $exsistname = $DB->get_fieldset_select('local_video_directory', 'orig_filename', null , array('owner_id' => $owner));
    foreach ($meetings->meetings as $meeting) {
        $newvideoid = 0;
       

      //  $recordings = new stdClass();
        $status = 0;
        // try {
        //     $recording = $api->getmeetingrecordings(urlencode($meeting->id));
        //     echo print_r( $recording);die;
        // } catch (Exception $e) {
        //     file_put_contents( $pathlog, "\n" . 'there are no recordings for this meeting. meeting id: ' . $meeting->id , FILE_APPEND);
        // }
        $recordings = $meeting->recording_files;
        
        // if ($recording != new stdClass()) {
       // if ($recordings != array()) {
            // foreach($recording->recording_files as $key => $moovie){
            foreach($recordings as $key => $moovie){

                if (strcmp($moovie->file_type, 'MP4') == 0) {
                    echo ' * ';
                    $name = $meeting->topic . '_' . $moovie->recording_start . '.mp4';

                    if ($owner == 0) {
                        $name = $meeting->topic .'_' . $user->email . '_' . $moovie->recording_start . '.mp4'; 
                    }

                    $name = str_replace(' ', '_', $name);
                    if (!in_array($name , $exsistname)) {
                       // echo print_r( $meeting);
                        echo ' | ';

                        $newvideo = new stdClass();
                        $newvideo->orig_filename = $name;
                        $newvideo->orig_filename = str_replace(" ", "-", $newvideo->orig_filename);
                        $newvideo->owner_id = $owner;
                        $newvideo->private = 1;
                        $newvideo->status = 1;
                        $newvideo->uniqid = uniqid('', true);

                        $ch = curl_init($moovie->download_url);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_NOBODY, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
                        //curl_setopt($ch, CURLOPT_TIMEOUT, 100);

                        $output = curl_exec($ch);
                        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($status == 200 && $moovie->file_size > 0) {
                            echo "\n" . $name . "\n";

                            $newvideoid = $DB->insert_record('local_video_directory',  $newvideo);
                            file_put_contents($path . $newvideoid, $output);
                            file_put_contents( $pathlog, "\n" . 'new video added. video id: ' . $newvideoid .' url: ' .$moovie->download_url , FILE_APPEND);

                            $newvideozoom = new stdClass();
                            $newvideozoom->zoom_meeting_id = $meeting->id;
                            $newvideozoom->video_id = $newvideoid;
                            $DB->insert_record('local_video_directory_zoom',  $newvideozoom);
                            $counter++;

                        } else {
                            file_put_contents( $pathlog, "\n" . 'ERROR adding new video. in meeting id: ' . $meeting->id . ' name: ' . $name . ' status: ' . $status  . " msg: " . curl_errno($ch), FILE_APPEND);
                        }
                    } else {
                        file_put_contents( $pathlog, "\n" . 'video is exsist already. video name: ' . $name , FILE_APPEND);
                    }
                }
            }
       // }
    }
}
print_r("end. ". $uu);
print_r("********* counter ". $counter);