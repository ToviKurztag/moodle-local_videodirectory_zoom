<?php


namespace local_video_directory\task;
defined('MOODLE_INTERNAL') || die();

class enrolteachers_task extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('enrolteachers', 'local_video_directory');
    }

    public function execute() {
        global $CFG , $DB, $PAGE;
        require_once($CFG->dirroot . '/local/video_directory/locallib.php');
        require_once($CFG->dirroot . '/local/video_directory/lib.php');

        $teachers = [];

        $roleidupdate = 3;
        $sql = "SELECT userid
                FROM {role_assignments}
                WHERE roleid = ?";
        $teachers = $DB->get_fieldset_sql($sql, array($roleidupdate ));

        $roleid = $DB->get_field('role', 'id', array( 'name' => 'local_video_directory'));
        $sql = "SELECT userid
                FROM {role_assignments}
                WHERE roleid = ?";
        $userexsists = $DB->get_fieldset_sql($sql, array($roleid ));

        $role = new \stdClass();
        $role->roleid = $roleid;
        $role->contextid = 1;
        $role->timemodified = time();

        // Add to role.
        foreach ($teachers as $teacher) {
            if ( !in_array($teacher, $userexsists)) {
                $role->userid = $teacher;
                $id = $DB->insert_record('role_assignments', $role);
            }
        }
        // Remove from role.

        /*foreach ($userexsists as $exsists) {
            if ( !in_array($exsists, $teachers)) {
                $id = $DB->delete_records('role_assignments', array('userid' => $exsists));
                print_r($id);
            }
        }*/
    }
}
