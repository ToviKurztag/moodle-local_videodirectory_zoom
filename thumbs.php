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
 * Video thumbnail selection page.
 *
 * @package    local_video_directory
 * @copyright  2017 Yedidia Klein <yedidia@openapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once( __DIR__ . '/../../config.php');
require_once('locallib.php');
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot . '/local/video_directory/cloud/locallib.php');


$settings = get_settings();

if (!CLI_SCRIPT) {
    require_login();

    // Check if user have permissionss.
    $context = context_system::instance();

    if (!has_capability('local/video_directory:video', $context) && !is_video_admin($USER)) {
        die("Access Denied. Please see your site admin.");
    }

}

$dirs = get_directories();
$ffmpeg = $settings->ffmpeg;
$streamingurl = $settings->streaming.'/';
$cloudtype = get_config('local_video_directory_cloud', 'cloudtype');
if ($cloudtype != 'None') {
    $streamingdir = get_config('local_video_directory', 'streaming') . '/';
} else {
    $streamingdir = $dirs['converted'];
}

$id = optional_param('id', 0, PARAM_INT);
$seconds = array(1, 3, 7, 12, 20, 60, 120);

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('thumb', 'local_video_directory'));
$PAGE->set_title(get_string('thumb', 'local_video_directory'));
$PAGE->set_url('/local/video_directory/thumbs.php');
$PAGE->set_pagelayout('standard');
$PAGE->requires->css('/local/video_directory/style.css');
$PAGE->requires->strings_for_js(
    array_keys(
        get_string_manager()->load_component_strings('local_video_directory', current_language())
    ),
    'local_video_directory'
);

$PAGE->navbar->add(get_string('thumb', 'local_video_directory'));

class thumbs_form extends moodleform {
    public function definition() {
        global $CFG, $DB, $seconds, $streamingdir, $OUTPUT;
        $mform = $this->_form;

        // LOOP from array seconds
        $radioarray = array();
        $id = optional_param('id', 0, PARAM_INT);
        $length = $DB->get_field('local_video_directory', 'length', array('id' => $id));
        $length = $length ? $length : '3:00:00'; // In case present but falseish.
        $length = strtotime("1970-01-01 $length UTC");

        foreach ($seconds as $index => $second) {
            if ($second < $length) {
                $radioarray[] = $mform->createElement('radio', 'thumb', '', $second . ' '
                    . get_string('seconds') . "<div id='video_thumb_$second'></div>", $second);
            }
        }

        $mform->addGroup($radioarray, 'radioar', '', array(' '), false);

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $buttonarray = array();
        $buttonarray[] =& $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] =& $mform->createElement('cancel', 'cancel', get_string('cancel'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

    }

    public function validation($data, $files) {
        return array();
    }
}

$mform = new thumbs_form();

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/local/video_directory/list.php');
} else if ($fromform = $mform->get_data()) {
    $id = $fromform->id;
    $record = array("id" => $id, "thumb" => $id . "-" . $fromform->thumb);
    $update = $DB->update_record("local_video_directory", $record);

    $video = $DB->get_record('local_video_directory', ['id' => $id]);
    if ($video->filename != $id . '.mp4') {
        $filename = $video->filename;
    } else {
        $filename = $id;
    }

    // Generate the big thumb and rename the small one.
    if ($cloudtype == 'None') {
        rename($streamingdir . $filename . "-" . $fromform->thumb . ".png", $streamingdir . $filename . "-" . $fromform->thumb . "-mini.png");
    }
    $timing = gmdate("H:i:s", $fromform->thumb );
    // Check that $ffmpeg is a file.
    if (file_exists($ffmpeg)) {
        $thumb = '"' . $ffmpeg . '" -i ' . escapeshellarg($streamingdir . $filename . ".mp4") . " -ss " . escapeshellarg($timing)
            . " -vframes 1 " . escapeshellarg($streamingdir . $filename . "-" . $fromform->thumb . ".png") . " -y";
        $output = exec($thumb);
    }
    // Delete all other thumbs
    foreach ($seconds as $second) {
        if ($second != $fromform->thumb) {
            $file = $streamingdir . $filename . "-" . $second . '.png';

            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
    // Delete orig thumb.
    $file = $streamingdir . $filename . '.png';

    if (file_exists($file)) {
        unlink($file);
    }
    redirect($CFG->wwwroot . '/local/video_directory/list.php');
} else {
    echo $OUTPUT->header();
    echo get_string('choose_thumb', 'local_video_directory') . '<br>';
    $mform->display();
}
echo $OUTPUT->footer();
?>
<script>
require(['jquery','local_video_directory/thumbs'], function($, P) { 
    return;
    });
local_video_directory_vars = {id: <?php echo $id ?>, seconds: <?php echo json_encode($seconds) ?>,
    errorcreatingthumbat: '<?php echo get_string('errorcreatingthumbat', 'local_video_directory') ?>',
    secondsstring: '<?php echo get_string('seconds') ?>'};
</script>
