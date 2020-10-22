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
 * Cut videos.
 *
 * @package    local_video_directory
 * @copyright  2017 Yedidia Klein <yedidia@openapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once( __DIR__ . '/../../config.php');
require_login();
defined('MOODLE_INTERNAL') || die();
require_once('locallib.php');
require_once("$CFG->libdir/formslib.php");

$id = optional_param('video_id', 0, PARAM_INT);

$PAGE->requires->js('/local/video_directory/js/cut.js');
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('studio', 'local_video_directory'));
$PAGE->set_title(get_string('studio', 'local_video_directory'));
$PAGE->set_url('/local/video_directory/studio_cut.php?video_id=' . $id);
$PAGE->navbar->add(get_string('pluginname', 'local_video_directory'), new moodle_url('/local/video_directory/'));
$PAGE->navbar->add(get_string('studio', 'local_video_directory'), new moodle_url('/local/video_directory/studio.php?video_id=' .
    $id));
$PAGE->navbar->add(get_string('cut', 'local_video_directory'));

class videocut_form extends moodleform {
    // Add elements to form.
    public function definition() {

        global $DB;
        $mform = $this->_form; // Don't forget the underscore!

        $id = optional_param('video_id', 0, PARAM_INT);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        for ($i = 0; $i < 200; $i++) {
            $seconds[] = $i;
        }

        $video = $DB->get_record('local_video_directory', array("id" => $id));
        $length = $video->length;
        $arrytime = explode(":", $length);
        $timeseconds = $arrytime[0] * 3600 + $arrytime[1] * 60 + $arrytime[2];
        $time = $timeseconds;

        $mform->addElement('html', '<section class="range-slider"><br>
        <div id="from">'. get_string('cutfrom', 'local_video_directory') . '</div>
        <input id = "rangebefore"  name ="rangebefore" style="width: 625px; direction: ltr" value="0"  min="0" max="'.$time.'" step="1" type="range">
        <button id="btnrange1" type="button" class="btn btn-secondary">'. get_string('copyvideovalue', 'local_video_directory') . '</button>
        <br>
        <div id="to">'. get_string('cutto', 'local_video_directory') . '</div>
        <input id = "rangeafter" name ="rangeafter" style="width: 625px; direction: ltr"  value="'.$time.'"  min="0" max="'.$time.'" step="1" type="range">
        <button id="btnrange2" type="button" class="btn btn-secondary">'. get_string('copyvideovalue', 'local_video_directory') . '</button>
        <br><br>
        <span style="margin-right: 2.3rem;">'. get_string('cuttingrange', 'local_video_directory') .'</span>
        <span id="rangeValues"></span></section><br>'
    );

        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'type', '', get_string('cutsides', 'local_video_directory'), 1, null);
        $radioarray[] = $mform->createElement('radio', 'type', '', get_string('cutmiddlepart', 'local_video_directory'), 0, null);
        $mform->addGroup($radioarray, 'cuttype', get_string('cuttype', 'local_video_directory'), ' ', false);
        $mform->addHelpButton('cuttype', 'cuttype', 'local_video_directory');

        $mform->addElement('select', 'save', get_string('save', 'moodle'),
            ['version' => get_string('newversion', 'local_video_directory'),
            'new' => get_string('newvideo', 'local_video_directory')
            ]);

        $buttonarray = array();
        $buttonarray[] =& $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] =& $mform->createElement('cancel', 'cancel', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

    }
    // Custom validation should be added here.
    public function validation($data, $files) {
        return array();
    }
}

// Instantiate simplehtml_form.
$mform = new videocut_form();

// Form processing and displaying is done here.
if ($mform->is_cancelled()) {
    // Handle form cancel operation, if cancel button is present on form.
    redirect($CFG->wwwroot . '/local/video_directory/studio.php?video_id=' . $id);
} else if ($fromform = $mform->get_data()) {
    // In this case you process validated data. $mform->get_data() returns data posted in form.
    $now = time();
    $type = $fromform->type == 0 ? "middle" : "sides";
// <Tovi
        $record = new stdClass();
        $record = array("video_id" => $fromform->id,
                            "user_id" => $USER->id,
                            "save" => $fromform->save,
                            "state" => 0,
                            "datecreated" => $now,
                            "datemodified" => $now,
                            "secbefore" => $_POST['rangebefore'],
                            "secafter" => $_POST['rangeafter'],
                            "cuttype" => $type);

    $id = $DB->insert_record("local_video_directory_cut", $record);
    redirect($CFG->wwwroot . '/local/video_directory/studio.php?video_id=' . $fromform->id,
                get_string('inqueue', 'local_video_directory'));
} else {
    echo $OUTPUT->header();

    $video = $DB->get_record('local_video_directory', array("id" => $id));
    $videoname = $video->orig_filename;
    echo $OUTPUT->heading(get_string('cut', 'local_video_directory') .
    ' - <span class="videoname">' . $videoname . '</span>');
    $strtime = $video->length;
    $strtime = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $strtime);
    sscanf($strtime, "%d:%d:%d", $hours, $minutes, $seconds);
    $timeseconds = $hours * 3600 + $minutes * 60 + $seconds;
    $time = $timeseconds;
    $hours = floor($time / 60);
    $minutes = ($time % 60);
    $streaming = get_streaming_server_url() . local_video_directory_get_filename($id) . ".mp4";

    $width = 640;
    $height = $width / ($video->width / $video->height);
    if ($streaming = get_streaming_server_url()) {
        $url = $streaming . "/" . local_video_directory_get_filename($id) . ".mp4";
    } else {
        $url = $CFG->wwwroot . "/local/video_directory/play.php?video_id=" . $id;
    }

    echo $OUTPUT->render_from_template('local_video_directory/studio_cut',
    ['wwwroot' => $CFG->wwwroot,
     'url' => $url,
     'id' => $id,
     'thumb' => str_replace("-", "&second=", $video->thumb),
     'height' => $height,
     'width' => $width]);

    echo "<div>";
    $mform->display();
    echo "</div>";
}


echo $OUTPUT->footer();
