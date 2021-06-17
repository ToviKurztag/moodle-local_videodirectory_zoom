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
 * Delete video.
 *
 * @package    local_video_directory
 * @copyright  2017 Yedidia Klein <yedidia@openapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once( __DIR__ . '/../../config.php');
require_login();
defined('MOODLE_INTERNAL') || die();
require_once('locallib.php');
require_once('cloud/locallib.php');


$settings = get_settings();

if (!CLI_SCRIPT) {
    require_login();

    // Check if user have permissionss.
    $context = context_system::instance();

    if (!has_capability('local/video_directory:video', $context) && !is_video_admin($USER)) {
        die("Access Denied. You must be a member of the designated cohort. Please see your site admin.");
    }

}

require_once("$CFG->libdir/formslib.php");

$streamingurl = get_settings()->streaming;
$dirs = get_directories();

$id = optional_param('video_id', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('edit', 'local_video_directory'));
$PAGE->set_title(get_string('edit', 'local_video_directory'));
$PAGE->set_url('/local/video_directory/edit.php');
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add(get_string('pluginname', 'local_video_directory'), new moodle_url('/local/video_directory/'));
$PAGE->navbar->add(get_string('edit', 'local_video_directory'));
class delete_form extends moodleform {
    // Add elements to form.
    public function definition() {
        global $CFG, $DB;
        $id = optional_param('video_id', 0, PARAM_INT);
        $mform = $this->_form;
        if ($id != 0) {
            $video = $DB->get_record('local_video_directory', array('id' => $id));
            $mform->addElement('html', $video->orig_filename);
            $mform->addElement('hidden', 'thumb', $video->thumb);
        } else {
            $mform->addElement('hidden', 'thumb', "");
        }
        $mform->setType('thumb', PARAM_RAW);
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $buttonarray = array();
        $buttonarray[] =& $mform->createElement('submit', 'submitbutton', get_string('yes'));
        $buttonarray[] =& $mform->createElement('cancel', 'cancel', get_string('no'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
    }

    public function validation($data, $files) {
        return array();
    }
}

$mform = new delete_form();

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/video_directory/list.php');
} else if ($fromform = $mform->get_data()) {
    
    $seconds = array(1, 3, 7, 12, 20, 60, 120);
    $thumbnailseconds = get_config('local_video_directory', 'thumbnail_seconds');
    if (is_numeric($thumbnailseconds)) {
        $seconds[] = $thumbnailseconds;
    }
    $filename = local_video_directory_get_filename($fromform->id);
    $where = array("video_id" => $fromform->id);
    $multifilenames = $DB->get_records('local_video_directory_multi' , $where);
    $versions = $DB->get_records('local_video_directory_vers' , array("file_id" => $fromform->id));

    $where = array("id" => $fromform->id);
    $v = $DB->get_record('local_video_directory', $where);
    trigger_deletion_event($v);

    
    $deleted = $DB->delete_records('local_video_directory', $where);

    if (get_config('local_video_directory_cloud', 'cloudtype') != 'None') {

        // Delete files by hash, only if there is no another same video.
        $videoconverted = $filename . '.mp4';
        $samevideos = $DB->get_records('local_video_directory' , ['filename' => $filename]);
        if ($samevideos == array()) {
            delete_from_cloud($fromform->id, $videoconverted);
            if (get_config('local_video_directory_cloud', 'cloudtype') != 'Vimeo') {
                foreach ($seconds as $second) {
                    if ($second != $fromform->thumb) {
                        $file = $filename . '-'. $second . '.png';
                        $minifile = $filename . '-'. $second . '-mini.png';
                        delete_from_cloud($fromform->id, $file);
                        delete_from_cloud($fromform->id, $minifile);
                    }
                }
            }
        }
    } else {
        // Delete files by id.
        $thumb = str_replace($streamingurl, $dirs['converted'], $fromform->thumb);
        if (file_exists($thumb)) {
            unlink($thumb);
        }
        // Delete files by hash, only if there is no another same video.
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

        //Delete versions, only if there is no another same video.
        foreach ($versions as $version) {
            $samevideosversion = $DB->get_records('local_video_directory_vers' , ['filename' => $version->filename]);
            $samevideosvideo = $DB->get_records('local_video_directory' , ['filename' => $version->filename]);
            $videoversion = $dirs['converted'] . $version->filename . '.mp4';

            if (file_exists($videoversion) && count($samevideosversion) == 1  && $samevideosvideo == array()) {
                unlink($videoversion);
            }
        }
    }

    // Delete zoom.
    $where = array('video_id' => $fromform->id);
    $DB->delete_records('local_video_directory_zoom', $where);

    // Delete versions.
    $where = array('file_id' => $fromform->id);
    $DB->delete_records('local_video_directory_vers', $where);

    // Delete tags.
    $where = array("itemid" => $fromform->id, "itemtype" => 'local_video_directory');
    $deleted = $DB->delete_records('tag_instance', $where);
    redirect($CFG->wwwroot . '/local/video_directory/list.php');

} else {
    echo $OUTPUT->header();
    echo get_string("are_you_sure", 'local_video_directory');
    $mform->display();
}

echo $OUTPUT->footer();
