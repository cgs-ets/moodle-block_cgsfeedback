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
 *
 * @package    block_cgsfeedback
 * @copyright  2023 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_cgsfeedback;
require_once($CFG->dirroot . '/lib/gradelib.php ');
require_once($CFG->dirroot . '/grade/lib.php ');

use context;
use moodle_url;
use stdClass;

class cgsfeedbackmanager {

    /**
     * Get the courses the student is enrolled and the
     * activities that are graded, released (in case workflow is in use)
     * check availability
     */
    private function get_student_enrollments($userid, $idonly = true) {
        global $DB;
        $now = new \DateTime("now", \core_date::get_server_timezone_object());
        $year = $now->format('Y');
        // TODO: Update the URL to pick up
        $sql = "SELECT c.id, c.fullname, c.idnumber
                FROM mdl_user_enrolments ue
                INNER JOIN mdl_enrol e ON ue.enrolid = e.id
                INNER JOIN mdl_course c ON c.id = e.courseid
                WHERE userid = $userid; "; // and e.status = 0 and c.idnumber like '%$year%'  status = 0 --> Active participation.

        $paramsarray = ['userid' => $userid /*, 'idnumber' => $year*/];
        $r = $DB->get_records_sql($sql, $paramsarray);

        $results = $idonly ? array_keys($r) : $r;

        return $results;
    }

    public function get_courses_activities($userid) {
        global $DB;
        // The courses the student is enrolled.
        $courses = $this->get_student_enrollments($userid, false);
        foreach ($courses as $cid => $course) {
            $modinfo = new \course_modinfo($course, $userid);
            $context = \context_course::instance($course->id);
            $coursedata = new stdClass();
            $coursedata->coursename = $course->fullname;
            $coursedata->courseid = $course->id;
            $coursedata->userid = $userid;
            $coursedata->modules = [];

            $data['courses'][$course->id] = $coursedata;

            foreach ($modinfo->get_used_module_names() as $pluginname => $d) {
                foreach ($modinfo->get_instances_of($pluginname) as $instanceid => $instance) {
                    $gradinginfo = grade_get_grades($course->id, 'mod', $pluginname, $instanceid, $userid);
                    if ($instance->get_user_visible() &&  count($gradinginfo->items) > 0) {
                        // error_log(print_r($instance->id, true));
                        $cd = ($data["courses"])[$course->id];
                        $module = new stdClass();
                        $module->id = $instance->id;
                        $module->modulename = $instance->get_formatted_name();
                        $module->moduleurl = new \moodle_url("/local/parentview/get.php", ['addr' => $instance->get_url(), 'user' => $userid]);
                        $module->moduleiconurl = $instance->get_icon_url();
                        $module->finalgrade = ($gradinginfo->items[0]->grades[$userid])->str_long_grade;
                        if (($gradinginfo->items[0]->grades[$userid])->feedback) {
                            // The grade component makes a copy of the file from the mod feedback and keeps it in the feedback filearea.
                            // We need to get the context and the instance id for the copied file.
                            $ctx = $this->get_context($course->id, ($gradinginfo->items[0])->itemmodule, ($gradinginfo->items[0])->iteminstance);
                            $instid = $DB->get_record('grade_grades', ['itemid' => ($gradinginfo->items[0])->id, 'userid' => $userid], 'id');
                            $feedback = file_rewrite_pluginfile_urls(
                                ($gradinginfo->items[0]->grades[$userid])->feedback,
                                'pluginfile.php',
                                $ctx->id,
                                GRADE_FILE_COMPONENT,
                                GRADE_FEEDBACK_FILEAREA,
                                $instid->id
                            );

                            $module->feedback = format_text($feedback, ($gradinginfo->items[0]->grades[$userid])->feedbackformat,
                            ['context' => $context->id]);
                            $cd->modules[] = $module; // Only add the assessment if it has feedback.
                        }
                        $data['courses'][$course->id] = $cd;

                    }
                }

            }
            if (count($data['courses'][$course->id]->modules) < 1) { // Remove courses that have no submissions.
                unset($data['courses'][$course->id]);
            }
        }

        $aux = $data['courses'];
        $data['courses'] = array_values($aux);

        return $data;

    }

    /**
     * Helper function to get the accurate context for this grade column.
     *
     * @return context
     */
    public function get_context($courseid, $itemmodule, $iteminstance) {
        $modinfo = get_fast_modinfo($courseid);
            // Sometimes the course module cache is out of date and needs to be rebuilt.
        if (!isset($modinfo->instances[$itemmodule][$iteminstance])) {
            rebuild_course_cache($courseid, true);
            $modinfo = get_fast_modinfo($courseid);
        }
            // Even with a rebuilt cache the module does not exist. This means the
            // database is in an invalid state - we will log an error and return
            // the course context but the calling code should be updated.
        if (!isset($modinfo->instances[$itemmodule][$iteminstance])) {
            mtrace(get_string('moduleinstancedoesnotexist', 'error'));
            $context = \context_course::instance($courseid);
        } else {
            $cm = $modinfo->instances[$itemmodule][$iteminstance];
            $context = \context_module::instance($cm->id);
        }

        return $context;
    }

}
