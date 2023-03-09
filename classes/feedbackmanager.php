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

namespace block_cgsfeedback\cgsfeedbackmanager;

require_once($CFG->dirroot . '/lib/gradelib.php ');
require_once($CFG->dirroot . '/grade/lib.php ');

use context;
use grade_item;
use moodle_url;
use stdClass;

define("SENIOR_ACADEMIC", "SEN-ACADEMIC");
define("PRIMARY_ACADEMIC", "PRI-ACADEMIC");

class cgsfeedbackmanager {

    /**
     * By getting the courses by category we ensure we are collecting the current courses
     * structure:
     *  SENIOR SCHOOL
     *     SENIOR ACADEMIC
     *          GEOGRAPHY
     *                  Courses
     *              ....
     *
     * PRIMARY SCHOOL
     *      PRIMARY ACADEMIC
     *          Courses
     *
     * @campus senior or primary
     */
    private function cgsfeedback_get_courses_by_category($campus) {
        global $DB;

        if ($campus == 'Senior School:Students') {
            $academiccategoryid = $DB->get_field("course_categories", "id", ['idnumber' => SENIOR_ACADEMIC]);
        } else {
            $academiccategoryid = $DB->get_field("course_categories", "id", ['idnumber' => PRIMARY_ACADEMIC]);
        }

        $like = $DB->sql_like('path', ':path');
        $sql = "SELECT *
                FROM mdl_course
                WHERE category IN (SELECT id AS 'categoryID'
                                   FROM mdl_course_categories
                                   WHERE $like)
                AND visible = 1 AND enddate = 0;";
        $params = ['path' => '%' . $academiccategoryid . '%'];
        $courses = $DB->get_records_sql($sql, $params);

        return $courses;
    }

    // Count the activities that have a grade.
    private function cgsfeedback_count_grades($userid, $courseid) {
        global $DB;

        $sql = "SELECT COUNT(gi.id) FROM mdl_grade_items gi
                JOIN mdl_grade_grades gg ON gi.id = gg.itemid
                WHERE gi.courseid = ? AND  gg.userid = ?
                AND gg.hidden = ?  AND gg.rawgrade IS NOT NULL";
        $params = ['courseid' => $courseid, 'userid' => $userid, 'hidden' => 0];

        return $DB->count_records_sql($sql, $params);

    }

    public function cgsfeedback_get_student_courses($user) {
        global $DB;
        error_log(print_r("cgsfeedback_get_student_courses", true));
        error_log(print_r($user, true));

        // The courses the student is enrolled.
        $courses = $this->cgsfeedback_get_courses_by_category($user->profile['CampusRoles']);
        foreach ($courses as $course) {
            $modinfo = new \course_modinfo($course, $user->id);
            $countgrades = $this->cgsfeedback_count_grades($user->id, $course->id);

            if (count($modinfo->get_used_module_names()) == 0 || $countgrades == 0) {
                continue;
            }
            $coursedata = new stdClass();
            $coursedata->coursename = $course->fullname;
            $coursedata->courseid = $course->id;
            $coursedata->userid = $user->id;

            $data['courses'][$course->id] = $coursedata;

        }

        $aux = $data['courses'];
        $data['courses'] = array_values($aux);
        error_log(print_r($data, true));
        return $data;

    }


    /**
     *  Function called by the WS
     */
    public function get_course_modules_context($courseid, $userid) {

        global $DB;
        $course = new stdClass();
        $course->id = $courseid;
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
                    }

                    if (($gradinginfo->items[0]->grades[$userid])->grade != '') {

                        $cd->modules[] = $module; // Only add the assessment that have  a grade.
                    }

                    $data['courses'][$course->id] = $cd;

                }
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
