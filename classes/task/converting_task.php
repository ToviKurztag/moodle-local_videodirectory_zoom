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
class converting_task extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('pluginname', 'local_video_directory');
    }

    public function execute() {
        global $CFG , $DB;
        require_once($CFG->dirroot . '/local/video_directory/locallib.php');
        require_once($CFG->dirroot . '/local/video_directory/lib.php');

        $dirs = get_directories();

        $settings = get_settings();
        $streamingurl = $settings->streaming . '/';
        $ffmpeg = $settings->ffmpeg;
        $ffprobe = $settings->ffprobe;
        $ffmpegsettings = '-strict -2 -c:v libx264 -profile:v high -pix_fmt yuv420p -crf 22 -c:a aac -movflags faststart -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2"';
        $thumbnailseconds = $settings->thumbnail_seconds;
        $php = $settings->php;
        $multiresolution = $settings->multiresolution;
        $resolutions = $settings->resolutions;
        $origdir = $dirs['uploaddir'];
        $streamingdir = $dirs['converted'];

        // Check if we've to convert videos.
        $videos = $DB->get_records('local_video_directory' , array("convert_status" => 1));
        // Move all video that have to be converted to Waiting.. state (4) just to make sure that there is not
        // multiple cron that converts same files.
        $wait = $DB->execute('UPDATE {local_video_directory} SET convert_status = 4 WHERE convert_status = 1');

        foreach ($videos as $video) {
            // Update convert_status to 2 (Converting....).
            $record = array("id" => $video->id , "convert_status" => "2");
            $update = $DB->update_record("local_video_directory" , $record);
            // If we have a previous version - save the version before encoding.
            if (file_exists($streamingdir . local_video_directory_get_filename($video->id) . ".mp4")) {
                $time = time();
                $newfilename = local_video_directory_get_filename($video->id) . "_" . $time . ".mp4";
                rename($streamingdir . local_video_directory_get_filename($video->id) . ".mp4", $streamingdir . $newfilename);
                // Delete Thumbs.
                array_map('unlink', glob($streamingdir . local_video_directory_get_filename($video->id) . "*.png"));
                // Delete Multi resolutions.
                array_map('unlink', glob($dirs['multidir'] . local_video_directory_get_filename($video->id) . "_*.mp4"));
                // Delete from multi table.
                $DB->execute('DELETE FROM {local_video_directory_multi} WHERE video_id = ?', [$video->id]);
                // Write to version table.
                $record = array('datecreated' => $time, 'file_id' => $video->id, 'filename' => $newfilename);
                echo "[local_video_directory] Inserting version $time to local_video_directory_vers for id: $video->id \n";
                $insert = $DB->insert_record('local_video_directory_vers', $record);
            }
            if (file_exists($ffmpeg)) {
                if (file_exists($ffprobe)) {
                    // Get video length.
                    $videocodec = $ffprobe ." -v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1" .
                        ":nokey=1 " . escapeshellarg($origdir . $video->id );
                    $audiocodec = $ffprobe ." -v error -select_streams a:0 -show_entries stream=codec_name -of default=noprint_wrappers=1" .
                        ":nokey=1 " . escapeshellarg($origdir . $video->id );
                    $firefoxbug = $ffprobe ." -v error -select_streams v:0 -show_entries stream=profile -of default=noprint_wrappers=1" .
                        ":nokey=1 " . escapeshellarg($origdir . $video->id );
                    $videocodecval = exec($videocodec);
                    $audiocodecval = exec($audiocodec);
                    $firefoxbugval = exec( $firefoxbug);
                    if (strpos($videocodecval , '264') !== false && $audiocodecval == 'aac' && strpos($firefoxbugval, 'Predictive') === false) {
                        echo 'not need to convert';
                        if (copy( $origdir . $video->id , $streamingdir . $video->id . ".mp4")) {
                            unlink($origdir . $video->id );
                        } else {
                            $convert = '"' .$ffmpeg. '" -hide_banner -loglevel warning -i ' .escapeshellarg($origdir.$video->id).' '
                            . $ffmpegsettings . ' '
                            . escapeshellarg($streamingdir . $video->id . ".mp4");
                            $exec = exec($convert);
                            echo " converted";
                        }
                    } else {
                        $convert = '"' .$ffmpeg. '" -hide_banner -loglevel warning -i ' .escapeshellarg($origdir . $video->id) . ' '
                        . $ffmpegsettings . ' '
                        . escapeshellarg($streamingdir . $video->id . ".mp4");
                        $exec = exec($convert);
                        echo " converted!!";

                    }
                } else {
                    echo "Ffprobe is not configured well, No such file : " . $ffprobe . "\n";
                }
                // Convert encoded file to hashed name and directory.
                $contenthash = sha1_file($streamingdir . $video->id . ".mp4");

                $hashdirectory = substr($contenthash, 0, 2);
                if (!is_dir($streamingdir . $hashdirectory)) {
                    mkdir($streamingdir . $hashdirectory);
                }
                $hashedfile = $streamingdir . $hashdirectory . '/' . $contenthash . '.mp4';
                // If this video already exist - just delete the encoded file.
                if (!is_file($hashedfile)) {
                    if (copy($streamingdir . $video->id . ".mp4", $hashedfile)) {
                        unlink($streamingdir . $video->id . ".mp4");
                    }
                } else {
                    unlink($streamingdir . $video->id . ".mp4");
                }
            } else {
                echo "Ffmpeg is not configured well, No such file : " . $ffmpeg . "\n";
            }
            // Check if was converted.
            if (file_exists($hashedfile)) {
                // Get Video Thumbnail.
                if (is_numeric($thumbnailseconds)) {
                    $timing = gmdate("H:i:s", $thumbnailseconds);
                } else {
                    $timing = "00:00:01";
                }

                if (file_exists($ffmpeg)) {
                    $thumb = '"' . $ffmpeg . '" -hide_banner -loglevel warning -i ' . $hashedfile .
                            " -ss " . escapeshellarg($timing) . " -vframes 1 " .
                            escapeshellarg($streamingdir . $hashdirectory . '/' . $contenthash . ".png");
                    $thumbmini = '"' . $ffmpeg . '" -hide_banner -loglevel warning -i ' . $hashedfile .
                            " -ss " . escapeshellarg($timing) . " -vframes 1 -vf scale=100:-1 " .
                            escapeshellarg($streamingdir . $hashdirectory . '/' . $contenthash . "-mini.png");

                    $exec = exec($thumb);
                    $exec = exec($thumbmini);
                } else {
                    echo "Ffmpeg is not configured well, No such file : " . $ffmpeg . "\n";
                }
                if (file_exists($ffprobe)) {
                    // Get video length.
                    $lengthcmd = $ffprobe ." -v error -show_entries format=duration -sexagesimal -of default=noprint_wrappers=1" .
                        ":nokey=1 " . escapeshellarg($hashedfile);
                    $lengthoutput = exec( $lengthcmd );
                    // Remove data after .
                    $arraylength = explode(".", $lengthoutput);
                    $length = $arraylength[0];
                } else {
                    echo "Ffprobe is not configured well, No such file : " . $ffprobe . "\n";
                }
                $metadata = array();
                $metafields = array("height" => "stream=height", "width" => "stream=width", "size" => "format=size");
                foreach ($metafields as $key => $value) {
                    $metadata[$key] = exec($ffprobe . " -v error -show_entries " . $value .
                        " -of default=noprint_wrappers=1:nokey=1 " . $hashedfile);
                }

                // Update that converted and streaming URL.
                $record = array("id" => $video->id,
                                "convert_status" => "3",
                                "streamingurl" => $streamingurl . $video->id . ".mp4",
                                "filename" => $hashdirectory . '/' . $contenthash,
                                "thumb" => $video->id,
                                "length" => $length,
                                "height" => $metadata['height'],
                                "width" => $metadata['width'],
                                "size" => $metadata['size'],
                                "timecreated" => time(),
                                "timemodified" => time()
                                );

                $update = $DB->update_record("local_video_directory", $record);
                // Delete original uploaded file.
                // Tami 13-06-20.
                if (file_exists($origdir . $video->id)) {
                    unlink($origdir . $video->id);
                }//>

                // Sent notification email - Tami.
                if (get_config('local_video_directory' , 'sendemailwhenready')) {
                    $userid = $DB->get_field("local_video_directory" , 'owner_id', array('id' => $video->id) );
                    $user = $DB->get_record('user', array('id' => $userid));
                    $from = get_config('noreplyaddress');
                    $subject = get_string('emailsubject' , 'local_video_directory' );
                    $msg = get_string('emailmsg', 'local_video_directory' );
                    if ($user) {
                        email_to_user( $user,  $from,   $subject,  $msg  .$video->id );
                    }
                }//>

            } else {
                // Update that converted and streaming URL.
                $record = array("id" => $video->id, "convert_status" => "5");
                $update = $DB->update_record("local_video_directory", $record);
            }
        }

        // Take care of wget table.
        $wgets = $DB->get_records('local_video_directory_wget', array("success" => 0));

        if ($wgets) {
            foreach ($wgets as $wget) {
                $record = array('id' => $wget->id, 'success' => 1);
                $update = $DB->update_record("local_video_directory_wget", $record);
                $filename = basename($wget->url);

                if (!filter_var($wget->url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                if ((strstr($wget->url, 'youtube')) || (strstr($fromform->url, 'youtu.be'))) {
                    $uniqid = uniqid('', true);
                    mkdir($dirs['wgetdir'] . "/" . $uniqid);
                    exec($settings->youtubedl . " -q -o " . $dirs['wgetdir'] . "/" . $uniqid . "/'%(title)s.%(ext)s' "
                        . $wget->url);
                    $files = scandir($dirs['wgetdir'] . "/" . $uniqid, 1);
                    $filename = $files[0];
                    $record = array('orig_filename' => $filename, 'owner_id' => $wget->owner_id, 'uniqid' => uniqid('', true),
                        'private' => 1);
                    $lastinsertid = $DB->insert_record('local_video_directory', $record);
                    if (copy($dirs['wgetdir'] . "/" . $uniqid . "/" . $filename, $dirs['uploaddir'] . $lastinsertid)) {
                        unlink($dirs['wgetdir'] . "/" . $uniqid . "/" . $filename);
                        rmdir($dirs['wgetdir'] . "/" . $uniqid);
                        $sql = "UPDATE {local_video_directory_wget} SET success = 2 WHERE url = ?";
                        $DB->execute($sql, array($wget->url));
                    }
                } else {
                    echo "Downloading $wget->url to" . $dirs['wgetdir'];
                    echo "Filename is $filename";
                    file_put_contents($dirs['wgetdir'] . $filename, fopen($wget->url, 'r'));

                    // Move to mass directory once downloaded.
                    if (copy($dirs['wgetdir'] . $filename, $dirs['massdir'] . $filename)) {
                        unlink($dirs['wgetdir'] . $filename);
                        $sql = "UPDATE {local_video_directory_wget} SET success = 2 WHERE url = ?";
                        $DB->execute($sql, array($wget->url));
                    }
                }
                // Doing one download per cron.
                break;
            }
        }
        if ($multiresolution) {
            // Create multi resolutions streams.
            $videos = $DB->get_records("local_video_directory", array('convert_status' => 3));
            foreach ($videos as $video) {
                local_video_directory_create_dash($video->id, $dirs['converted'], $dirs['multidir'], $ffmpeg, $resolutions);
            }
        }
        // CROPs.
        $crops = $DB->get_records("local_video_directory_crop", array('state' => 0));
        local_video_directory_studio_action($crops, "crop");
        // Merge.
        $merge = $DB->get_records("local_video_directory_merge", array('state' => 0));
        local_video_directory_studio_action($merge, "merge");
        // Cut.
        $cut = $DB->get_records("local_video_directory_cut", array('state' => 0));
        local_video_directory_studio_action($cut, "cut");
        // Cat.
        $cat = $DB->get_records("local_video_directory_cat", array('state' => 0));
        local_video_directory_studio_action($cat, "cat");
        // Speed.
        $speed = $DB->get_records("local_video_directory_speed", array('state' => 0));
        local_video_directory_studio_action($speed, "speed");
    }
}
