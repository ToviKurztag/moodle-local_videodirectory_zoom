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

defined('MOODLE_INTERNAL') || die();
$tasks = array(
    array(
        'classname' => 'local_video_directory\task\converting_task' ,
        'blocking' => 0 ,
        'minute' => '*' ,
        'hour' => '*' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    ),
    array(
        'classname' => 'local_video_directory\task\googlespeech_task' ,
        'blocking' => 0 ,
        'minute' => '*/5' ,
        'hour' => '*' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    ),
    array(
        'classname' => 'local_video_directory\task\enrolteachers_task' ,
        'blocking' => 0 ,
        'minute' => '*' ,
        'hour' => '*' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    ),
    array(
        'classname' => 'local_video_directory\task\zoom_task' ,
        'blocking' => 0 ,
        'minute' => '00' ,
        'hour' => '00' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    ),
    array(
        'classname' => 'local_video_directory\task\deletion_task' ,
        'blocking' => 0 ,
        'minute' => '00' ,
        'hour' => '1' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    ),
    array(
        'classname' => 'local_video_directory\task\redownload_from_zoom_task' ,
        'blocking' => 0 ,
        'minute' => '00' ,
        'hour' => '1' ,
        'day' => '*' ,
        'dayofweek' => '*' ,
        'month' => '*'
    )
);
