<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Converting Task.
 *
 * @package    local_video_directory
 * @copyright  2018 Yedidia Klein OpenApp Israel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_video_directory\task;
defined('MOODLE_INTERNAL') || die();
/**
 * Class for converting videos task.
 * @copyright  2018 Yedidia Klein OpenApp Israel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_task extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('zoomvideosname', 'local_video_directory');
    }

    public function execute() {
        global $CFG, $DB;

        include(dirname(__FILE__).'/zoomApi/api.php');

        $api = new \zoomapi();
        $pathlog = dirname(__FILE__) . '/zoomLog.log';
        $path = $CFG->dataroot . '/local_video_directory_videos/';

        $users = $api->listUsers();
        $counter = 0;

        foreach ($users as $user) {
            file_put_contents( $pathlog, "\n" . "\n" . '--------------------------------- time: ' . time() . ' ---------------------------------', FILE_APPEND);
            $meetings = $api->listRecordinga($user->id);
            file_put_contents( $pathlog, "\n" . 'list videos for user: '. $user->id . ' mail: '. $user->email, FILE_APPEND);

            $owner = $DB->get_field('user', 'id', ['email' => $user->email]);
            $sql = "SELECT 'video_original_name'
            FROM {local_video_directory_zoom} vz
            JOIN {local_video_directory} v ON vz.video_id = v.id
            WHERE 'owner_id' = ?";
            // $exsistname = $DB->get_fieldset_select('local_video_directory_zoom', 'video_original_name', null , array('owner_id' => $owner));
            $exsistname = $DB->get_records_sql($sql, array($owner));

            foreach ($meetings->meetings as $meeting) {
                $newvideoid = 0;
                $recordings = $meeting->recording_files;
                foreach ($recordings as $key => $moovie) {
                    if (strcmp($moovie->file_type, 'MP4') == 0 ) {
                        $duration = date_diff (date_create($moovie->recording_end) , date_create($moovie->recording_start));
                        $minimum = get_config('local_video_directory' , 'minimumtimefromzoom');
                        if ($duration->h > 0 || $duration->i >= $minimum) {
                            $name = $meeting->topic . '_' . $moovie->recording_start . '.mp4';

                            if ($owner == 0) {
                                $name = $meeting->topic .'_' . $user->email . '_' . $moovie->recording_start . '.mp4'; 
                            }

                            $name = str_replace(' ', '_', $name);
                            if (!in_array($name , $exsistname)) {
                                $open = $api->patchmeetingrecordingssettings( $moovie->meeting_id, 1);

                                $newvideo = new \stdClass();
                                $newvideo->orig_filename = $name;
                                $newvideo->orig_filename = str_replace(" ", "-", $newvideo->orig_filename);
                                $newvideo->owner_id = $owner;
                                $newvideo->private = 1;
                                $newvideo->status = 1;
                                $newvideo->uniqid = uniqid('', true);

                                $wget = get_config('local_video_directory' , 'wgeturl');

                                if (file_exists( $wget)) {
                                    $url = $moovie->download_url;
                                    $outputfile = $path . $newvideoid;
                                    $cmd = $wget . " -q \"$url\" -O $outputfile";
                                    exec($cmd);
                                    echo "\n" . 'DID' . $name . "\n";
                                    file_put_contents( $pathlog, "\n" . 'new video added. video id: ' . $newvideoid .' url: ' .$moovie->download_url , FILE_APPEND);

                                    $newvideoid = $DB->insert_record('local_video_directory',  $newvideo);
                                    $newvideozoom = new \stdClass();
                                    $newvideozoom->zoom_meeting_id = $meeting->id;
                                    $newvideozoom->video_id = $newvideoid;
                                    $newvideozoom->video_original_name = $name;
                                    $DB->insert_record('local_video_directory_zoom',  $newvideozoom);

                                    $close = $api->patchmeetingrecordingssettings( $moovie->meeting_id, 0);

                                } else {
                                    $ch = curl_init($moovie->download_url);
                                    curl_setopt($ch, CURLOPT_HEADER, 0);
                                    curl_setopt($ch, CURLOPT_NOBODY, 0);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

                                    $output = curl_exec($ch);
                                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);

                                    if ($status == 200) {
                                        echo "\n" . $name . "\n";

                                        $newvideoid = $DB->insert_record('local_video_directory',  $newvideo);
                                        file_put_contents($path . $newvideoid, $output);
                                        file_put_contents( $pathlog, "\n" . 'new video added. video id: ' . $newvideoid .' url: ' .$moovie->download_url , FILE_APPEND);

                                        $newvideozoom = new \stdClass();
                                        $newvideozoom->zoom_meeting_id = $meeting->id;
                                        $newvideozoom->video_id = $newvideoid;
                                        $DB->insert_record('local_video_directory_zoom',  $newvideozoom);
                                        $counter++;

                                    } else {
                                        file_put_contents( $pathlog, "\n" . 'ERROR adding new video. in meeting id: ' . $meeting->id . ' name: ' . $name . ' status: ' . $status  . " msg:" , FILE_APPEND);
                                    }
                                   $close = $api->patchmeetingrecordingssettings( $moovie->meeting_id, 0);
                                }
                               $close = $api->patchmeetingrecordingssettings( $moovie->meeting_id, 0);
                            } else {
                                file_put_contents( $pathlog, "\n" . 'video is exsist already. video name: ' . $name , FILE_APPEND);
                            }
                        }
                    }
                }
            }
        }
    }
}

