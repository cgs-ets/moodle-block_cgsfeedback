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

require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use context;
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

        $sql = "SELECT id, fullname
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
    private function cgsfeedback_get_courses_with_grades($userid, $courseids) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        /*$sql = "SELECT c.id AS 'courseid', COUNT(gi.id) AS 'grades'
                FROM mdl_grade_items gi
                JOIN mdl_grade_grades gg 
                    ON gi.id = gg.itemid
                JOIN mdl_course c 
                    ON gi.courseid = c.id
                WHERE gi.courseid IN ($courseids) 
                AND  gg.userid = ?
                AND gg.hidden = ?
                AND gg.rawgrade IS NOT NULL
                GROUP BY c.fullname, c.id;";*/

        $sql = "SELECT c.id AS 'courseid', COUNT(gi.id) AS 'grades'
            FROM mdl_grade_items gi
            JOIN mdl_grade_grades gg 
                ON gi.id = gg.itemid
            JOIN mdl_course c 
                ON gi.courseid = c.id
            WHERE gi.courseid IN ($courseids) 
            AND  gg.userid = ?
            AND gg.hidden = ?
            AND gg.finalgrade IS NOT NULL
            AND gi.itemtype = 'mod'
            AND gi.categoryid IN (SELECT id FROM mdl_grade_categories WHERE courseid = c.id AND (fullname = 'SEMESTER 1' OR fullname = 'SEMESTER 2' OR fullname = 'SEMESTER 3'))
            GROUP BY c.fullname, c.id;";
                

        $params = ['userid' => $userid, 'hidden' => 0];
        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }

    /**
     * A grade item can be in a grade category. We need it to filter what the parent can see sees.
     * @courseids
     * @gradecategories are the names of the grade categories set in the edit_form.php
     */
    private function cgsfeedback_get_courses_grade_categories($courseids, $gradecategories) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        $gradecategories = str_replace(', ', ',', $gradecategories);
        $gradecategorynames = explode(",", $gradecategories);

        list($insql, $inparams) = $DB->get_in_or_equal($gradecategorynames);

        $sql = "SELECT  gc.id AS 'categoryid', gc.courseid, gc.fullname AS 'categoryname'
                FROM mdl_grade_categories gc
                WHERE gc.courseid 
                    IN ($courseids) 
                    AND gc.fullname $insql
                ORDER BY gc.courseid";

        $results = $DB->get_records_sql($sql, $inparams);
        $raux = [];

        foreach ($results as $r) {
            $raux[$r->courseid][] = $r;
        }

        return $raux;

    }

    public function cgsfeedback_get_student_courses($user) {
        global $DB, $CFG, $USER;
        
        // Get all of the user's courses.
        $usercourses = enrol_get_all_users_courses($user->id, true);

        // Limit to courses (for Pilot).
        if (!empty($CFG->block_cgsfeedback_limitedcourses)) {    
            // Check if this student is enrolled in one of the courses.
            $limitedcourses = array_map('trim', explode(",", $CFG->block_cgsfeedback_limitedcourses));
            $usercourses = array_filter(
                $usercourses,
                function ($usercourse) use($limitedcourses) {
                    return in_array($usercourse->id, $limitedcourses);
                }
            );
        }

        if (!empty($CFG->block_cgsfeedback_limitedcoursecats)) {    
            // Check if this student is enrolled in one of the courses.
            $limitedcats = array_map('trim', explode(",", $CFG->block_cgsfeedback_limitedcoursecats));
            $limusercourses = array();
            foreach ($limitedcats as $catidnum) {
                $cat = $DB->get_record('course_categories', array('idnumber' => $catidnum));
                if ($cat) {
                    // Get all courses under this category (recursive) to filter $usercourses.
                    $cat = \core_course_category::get($cat->id, IGNORE_MISSING, true);
                    $coursesinfo = $cat->get_courses(['recursive'=>true]);
                    $filterbycourses = array_values(array_map(function($ci) {
                       return $ci->id;
                    }, $coursesinfo));
                    $limusercourses[] = array_intersect_key($usercourses, array_flip($filterbycourses));
                }
            }
            $result = array_merge(...$limusercourses);
            $ids = array_values(array_column($result, 'id'));
            $usercourses = array_combine($ids, $result);
        }

        $courseids = implode(',', array_keys($usercourses));

        // Filter courses where the user has a grade.
        $courseswithgrades = array_keys($this->cgsfeedback_get_courses_with_grades($user->id, $courseids));
        
        $gradecategories = '';
        if (!empty($CFG->block_cgsfeedback_grade_category)) {
            $gradecategories = $CFG->block_cgsfeedback_grade_category;
        }

        // filter courses having a valid grade category.
        $coursesgradecategory = $this->cgsfeedback_get_courses_grade_categories($courseids, $gradecategories);

        // Only include relevant courses.
        $courses = array();
        foreach($usercourses as $course) {
            if (!in_array($course->id, $courseswithgrades)) {
                // This course did not have any valid grade categories (see block_cgsfeedback_grade_category setting), skip.
                continue;
            }
            if (!in_array($course->id, array_keys($coursesgradecategory))) {
                // The user does not have any grades in this course, skip.
                continue;
            }
            // Show this course.
            $course->coursename = $course->fullname;
            $course->courseid = $course->id;
            $course->userid = $user->id;
            //$course->whoareyou = $whoareyou;
            //$course->coursegradecategories = json_encode($coursesgradecategory[$course->id]);
            $courses[] = $course;
        }

        return array(
            'courses' => $courses,
        );
    }

    /**
     *  Function called by the WS.
     *
     */
    public function get_course_modules_context($courseid, $userid) {
        global $CFG, $USER, $DB;

        $course = new stdClass();
        $course->id = $courseid;
        $modinfo = new \course_modinfo($course, $userid);
        $context = \context_course::instance($course->id);

        $coursedata = new stdClass();
        $coursedata->courseid = $course->id;
        $coursedata->userid = $userid;
        $coursedata->modules = [];

        $modulesingradecategory = '';
        $alphabet = range('A', 'Z');

        $data['courses'][$course->id] = $coursedata;

        if (!empty($CFG->block_cgsfeedback_grade_category)) 
        {
            $coursesgradecategory = $this->cgsfeedback_get_courses_grade_categories($courseid, $CFG->block_cgsfeedback_grade_category);
            $categoryids = implode(',', array_column($coursesgradecategory[$courseid], 'categoryid'));
            $modulesingradecategory = $this->get_course_modules_in_grade_category($categoryids, $course->id);
        }

        foreach ($modinfo->get_used_module_names() as $pluginname => $d) {
            foreach ($modinfo->get_instances_of($pluginname) as $instanceid => $instance) {

                // If the mod instance is not visible, do not show.
                if (!$instance->get_user_visible()) {
                    continue;
                }

                $gradinginfo = grade_get_grades($course->id, 'mod', $pluginname, $instanceid, $userid);

                // If no grades, do not show.
                if (count($gradinginfo->items) == 0) {
                    continue;
                }

                // If not in allowed grade category, do not show.
                $isingradecategory = $modulesingradecategory != '' ? in_array($gradinginfo->items[0]->id, $modulesingradecategory) : false;
                if (!$isingradecategory) {
                    continue;
                }

                // If grade is hidden, do not show.
                if ($gradinginfo->items[0]->hidden) {
                    continue;
                }
                
                // If there is a grade, but it is NULL, do not show.
                $hasnullgrades = false;
                foreach ($gradinginfo->items[0]->grades as $grade) {
                    if ($grade->grade == NULL) {
                        $hasnullgrades = true;
                        break;
                    }
                }
                if ($hasnullgrades) {
                    continue;
                }


                // Check workflow state.
                if ($pluginname == 'assign') {
                    $sql = "SELECT markingworkflow FROM mdl_assign WHERE id = $instance->instance";
                    $markingworkflow = $DB->get_field_sql($sql);
                    if ($markingworkflow == '1') {
                        $sql = "SELECT * 
                                FROM mdl_assign_user_flags
                                WHERE assignment = $instance->instance
                                AND userid = $userid";
                        $flags = $DB->get_record_sql($sql);
                        if ($flags == false || $flags->workflowstate != 'released') {
                            // Workflow is enabled, and not released yet for this user/assign.
                            continue;
                        }
                    }
                }

                $cd = ($data["courses"])[$course->id];
                $module = new stdClass();
                $module->id = $instance->id;
                $module->itemid = $gradinginfo->items[0]->id;
                $module->modulename = $instance->get_formatted_name();
                $module->iteminstance = isset($gradinginfo->items[0]->iteminstance) ? $gradinginfo->items[0]->iteminstance : '';

                if ($USER->id != $userid && $pluginname != 'giportfolio') {
                    $module->moduleurl = new \moodle_url("/local/parentview/get.php",
                    ['addr' => $instance->get_url(),
                      'user' => $userid,
                      'title' => $module->modulename,
                      'activityid' => $instanceid,
                      'iteminstance' => $module->iteminstance
                    ]);
                } else if ($USER->id != $userid && $pluginname == 'giportfolio') {
                    // Set the page to viewcontribute. As this page only shows the chapter and contributions.
                    $aux = $instance->get_url();
                    $cmid = $aux->params()['id'];
                    $params = array('id' => $cmid, 'fpv' => 1, 'pid' => $USER->id, 'userid' => $userid);
                    $gpurl = new \moodle_url('/mod/giportfolio/viewcontribute.php', $params);
                    $module->moduleurl = new \moodle_url("/local/parentview/get.php", ['addr' => $gpurl, 'user' => $userid, 'title' => $module->modulename, 'activityid' => $instanceid, 'iteminstance' => $module->iteminstance]);
                } else {
                    $module->moduleurl = $instance->get_url();
                }

                $module->moduleiconurl = $instance->get_icon_url();
                $module->finalgrade = ($gradinginfo->items[0]->grades[$userid])->str_long_grade;
                $module->finalgrade = str_replace('.00', '', $module->finalgrade);

                // Determine if this is an frubric with outcomes.
                // If it is, show the outcome grid instead of a final grade.
                if (!empty($gradinginfo->outcomes)) {
                    $module->finalgrade = null;
                    $module->hasoutcomes = true;
                    $outcomes = array();
                    $i = 0;
                    foreach($gradinginfo->outcomes as $outcome) {
                        $grade = array_pop($outcome->grades);
                        if (empty($grade->dategraded)) {
                            continue;
                        }
                        
                        // Get the outcome title and description.
                        $outcomeid = $DB->get_field('grade_items', 'outcomeid', array('id' => $outcome->id));
                        $outcomedata = $DB->get_record('grade_outcomes', array('id' => $outcomeid));

                        $scale = $DB->get_field('scale', 'scale', array('id' => $outcomedata->scaleid));
                        $scale = explode(",", $scale);
                        $scalereverse = $scale;
                        $scalereverse = array_reverse($scalereverse);
                        $scalehtml = implode("<br>", $scalereverse);

                        $gradeindex = $grade->grade;
                        // Check if the scale starts with a level 0. If it does, then don't minus one to account for php zero index because [0] = 0
                        if (count($scale) && $scale[0] != '0') {
                            $gradeindex = $grade->grade - 1; // -1 to account for zero index
                        }
                        
                        $scaleword = $scale[$gradeindex];

                        $outcomes[] = array(
                            'itemid' => $outcome->id,
                            'letter' => $outcomedata->shortname ? $outcomedata->shortname : $alphabet[$i],
                            'title' => $outcomedata->fullname,
                            'desc' => $outcomedata->description,
                            'tip' => "<strong>$outcomedata->fullname</strong> $outcomedata->description",
                            'scaletip' => "<strong>Scale:</strong><br> $scalehtml",
                            'grade' => $grade->grade,
                            'scaleword' => $scaleword,
                        );
                        $i++;
                    }
                    $module->outcomes = $outcomes;
                }
     
                if (($gradinginfo->items[0]->grades[$userid])->feedback) {
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

                    if ($USER->id != $userid) {
                        $this->cgsfeedback_change_links($feedback, $userid);
                    }

                    $module->feedback = format_text($feedback, ($gradinginfo->items[0]->grades[$userid])->feedbackformat,
                    ['context' => $context->id]);
                }
                $cd->modules[] = $module; // Only add the assessment that have  a grade.
                $data['courses'][$course->id] = $cd;
            
            }
        }



            $modules = [];
            $cd = $data["courses"][$course->id];
            $coursesgradecategory = $this->cgsfeedback_get_courses_grade_categories($courseid, 'CGS Effort');
            if (isset($coursesgradecategory[$courseid])) {
                $cgseffortitems = $this->get_course_modules_in_grade_category($coursesgradecategory[$courseid][0]->categoryid, $course->id);
                $module = new stdClass();
                $module->modulename = 'Effort';
                $module->itemid = 0;
                $module->finalgrade = null;
                $module->hasoutcomes = true;
                $outcomes = [];
                foreach ($cgseffortitems as $itemid) {
                    $item = $this->get_grade_item($itemid);
                    if ($item->idnumber != 'T1P' &&
                        $item->idnumber != 'T1C' &&
                        $item->idnumber != 'T1A' &&
                        $item->idnumber != 'T1D'
                    ) {
                        continue;
                    }
                    $grade = $this->get_grade_item_grade($item->id, $userid);
                    if (!$grade->finalgrade) {
                        continue;
                    }
                    $scale = $this->get_grade_scale($item->scaleid); 
                    $scalearr = explode(",", $scale->scale);
                    $scalereverse = $scalearr;
                    $scalereverse = array_reverse($scalereverse);
                    $scalehtml = implode("<br>", $scalereverse);
                    $gradeindex = (int) $grade->finalgrade;
                    $scaleword = $scalearr[$gradeindex-1];
                    $outcomes[] = array(
                        'itemid' => $itemid,
                        'letter' => $item->idnumber,
                        'title' => $item->itemname,
                        'tip' => "<strong>$item->itemname</strong>",
                        'scaletip' => "<strong>Scale:</strong><br> $scalehtml",
                        'grade' => $grade->finalgrade,
                        'scaleword' => $scaleword,
                    );
                }
                if (count($outcomes)) {
                    $module->outcomes = $outcomes;
                    $modules[] = $module;
                } 
            }
            $data['courses'][$course->id]->modules = array_merge($modules, $data['courses'][$course->id]->modules);



        $aux = $data['courses'];
        $data['courses'] = array_values($aux);

        return $data;
    }

    // Find the file urls and change them so the parentview plugin can use it.
    private function cgsfeedback_change_links(&$feedback, $userid) {
        $pattern = '/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';

        if (preg_match_all($pattern, $feedback, $out)) {

            foreach ($out[0] as $i => $url) {
                preg_match($pattern, $url, $matches);;

                if ($matches[0] != '') {
                    $assignmenturlparent = new moodle_url("/local/parentview/get.php", ['addr' => $url, 'user' => $userid]);
                    $feedback = str_replace($matches[0], $assignmenturlparent, $feedback);
                }

            }
        }

    }
    /**
     * Helper function that only brings the item ids that are part of the
     * categories set in the gradebook.
     */
    private function get_course_modules_in_grade_category($categoryids, $courseid) {
        global $DB;

        $sql = "SELECT id
                FROM mdl_grade_items
                WHERE courseid = $courseid AND categoryid in ($categoryids)";

        $results = array_keys($DB->get_records_sql($sql));

        return $results;

    }

    /**
     * Helper function that only brings the item ids that are part of the
     * categories set in the gradebook.
     */
    private function get_grade_item($itemid) {
        global $DB;

        $sql = "SELECT *
                FROM mdl_grade_items
                WHERE id = $itemid";

        $result = $DB->get_record_sql($sql);

        return $result;
    }

    private function get_grade_item_grade($itemid, $userid) {
        global $DB;

        $sql = "SELECT *
                FROM mdl_grade_grades
                WHERE itemid = $itemid 
                AND userid = $userid";

        $result = $DB->get_record_sql($sql);

        return $result;
    }

    private function get_grade_scale($scaleid) {
        global $DB;
        return $DB->get_record('scale', array('id' => $scaleid));
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
