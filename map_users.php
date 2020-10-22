<?php

define('CLI_SCRIPT', 1);

require_once('/var/www/moodle37/config.php');
// script for associate user to video in video_directory by csv file.
// here is by username and videoname fields.
global $DB;

$row = 1;
if (($handle = fopen("/var/www/moodle37/video.csv", "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;
        $username = $data[0];
        $videoname = $data[2];
        if ($videoname == "Column2") {
            continue;
        }
        $sql = "select u.id, u.username, u.lastname 
        from {user} as u where u.username like ?";
        $user = $DB->get_record_sql($sql, ["$username"], $strictness = IGNORE_MISSING);

        // if not found user associate the video to specific admin.
        if (!isset($user) || empty($user)) {
            $userid = 29920;
        } else {
            $userid = $user->id;
        }
        $videoname = $videoname . ".%";
        print_r("userid: " .$userid ."---" . "videoname: " . $videoname ."\n");
        $sql = "SELECT *
        FROM {local_video_directory}
        WHERE orig_filename LIKE ? ORDER BY orig_filename DESC limit 1";
        $video = $DB->get_record_sql($sql, [$videoname], $strictness = IGNORE_MISSING);
        if (!isset($video) || empty($video)) {
            // If not found the video in DB - save in array for testing.
            $videonotfound[$userid] = $videoname;
            continue;
        } else if ($video->owner_id == $userid) {
            // If the video associated already to user - continue (in case there are multiple videos).
            continue;
        }
        $videoid = $video->id;
        $video = array_values($video);
        print_r("videoid" .$videoid ."\n");

        $sql = "update mdl_local_video_directory set owner_id = ? where id = ?";
        $DB->execute($sql, [$userid, $videoid]);
    }
    fclose($handle);
}

// videos that not found in DB.
print_r($videonotfound);

