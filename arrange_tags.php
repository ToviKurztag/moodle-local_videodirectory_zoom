<?php

define('CLI_SCRIPT', 1);

require_once('../../config.php');

global $DB;

$context = context_system::instance();
$videos = $DB->get_records('local_video_directory');
foreach($videos as $video) {
        if(strpos($video->orig_filename, '||') !== false) {
                $arr = explode('||', $video->orig_filename);
                $end = $arr[1];
                $arr2 = explode('.', $end);
                $tags_string = $arr2[0];
                $tags = explode(',', $tags_string);

                if($tags[0] != '') {
                        core_tag_tag::set_item_tags('local_video_directory', 'local_video_directory', $video->id, $context, $tags);
                }

                //update video description
                $video->orig_filename = $arr[0] . '.' . $arr2[1];
                $DB->update_record('local_video_directory', $video);
        }
}
