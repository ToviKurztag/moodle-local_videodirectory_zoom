<?php

define('CLI_SCRIPT', true);

// define('AJAX_SCRIPT', true);

include_once(__DIR__ .'/../../../../../config.php');
include_once(__DIR__ .'/../../../locallib.php');

global $DB;

$videos = $DB->get_records('local_video_directory', ['convert_status' => '5']);
$dirs = get_directories();
foreach ($videos as $video) {
    $filename = local_video_directory_get_filename($video->id);
    $where = array("video_id" => $video->id);
    $multifilenames = $DB->get_records('local_video_directory_multi' , $where);

    $where = array("id" => $video->id);
    $deleted = $DB->delete_records('local_video_directory', $where);

    $videoconverted = $dirs['converted'] . $filename . '.mp4';

    $samevideos = $DB->get_records('local_video_directory' , ['filename' => $filename]);
    if (file_exists($videoconverted) && $samevideos == array()) {
        unlink($videoconverted);
    }
    foreach ($multifilenames as $multi) {
        $videomulti = $dirs['multidir'] .  $multi->filename;
        if (file_exists($videomulti) && $samevideos == array()) {
            unlink($videomulti);
        }
    }

    // Delete zoom.
    $where = array('video_id' => $video->id);
    $DB->delete_records('local_video_directory_zoom', $where);
}

/*$zooms = $DB->get_records('local_video_directory_zoom', []);

foreach ($zooms as $zoom) {

    $video = $DB->get_record('local_video_directory', ['id' => $zoom->video_id]);
    if(isset($video)) {
        $zoom->video_original_name = $video->orig_filename;
        $DB->update_record("local_video_directory_zoom" , $zoom);
    }
}*/
