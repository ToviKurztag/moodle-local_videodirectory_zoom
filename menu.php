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
 * Main menu of system.
 *
 * @package    local_video_directory
 * @copyright  2017 Yedidia Klein <yedidia@openapp.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once( __DIR__ . '/../../config.php');
require_login();
require_once('locallib.php');
defined('MOODLE_INTERNAL') || die();

$settings = get_settings();

if (!CLI_SCRIPT) {
    require_login();

    // Check if user have permissionss.
    $context = context_system::instance();

    if (!has_capability('local/video_directory:video', $context) && !is_video_admin($USER)) {
        die("Access Denied. You must have rights. Please see your site admin.");
    }

}

$selected = basename($_SERVER['SCRIPT_NAME']);
$settings = get_config('local_video_directory');
$menu = array('list', 'portal', 'upload', 'mass', 'wget');
$tabs = array();

foreach ($menu as $item) {
    array_push($tabs, array('name' => $item,
                            'selected' => ($item . '.php' != $selected),
                            'str' => get_string($item, 'local_video_directory')));
}
$dir = $CFG->dataroot . '/local_video_directory_videos/converted/';
echo $OUTPUT->render_from_template('local_video_directory/menu',
                                    ['size' => local_video_directory_human_filesize(
                                        disk_free_space($dir), 2, $settings->df),
                                     'selected' => $selected,
                                     'menu' => $tabs,
                                     'admin' => is_siteadmin($USER),
                                     'videoadmin' => is_video_admin($USER)
                                    ]);
