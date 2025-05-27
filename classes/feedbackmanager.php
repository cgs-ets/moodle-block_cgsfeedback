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
use gradereport_user;

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

        $sql = "SELECT c.id AS 'courseid', COUNT(gi.id) AS 'grades'
            FROM mdl_grade_items gi
            JOIN mdl_grade_grades gg 
                ON gi.id = gg.itemid
            JOIN mdl_course c 
                ON gi.courseid = c.id
            WHERE gi.courseid IN ($courseids) 
            AND  gg.userid = ?
            AND gg.hidden = 0
            AND gg.finalgrade IS NOT NULL
            AND (gi.itemtype = 'mod' OR (gi.itemtype = 'manual' AND gi.idnumber IS NOT NULL))
            GROUP BY c.fullname, c.id;";
                

        $params = ['userid' => $userid];
        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }

    /**
     * A grade item can be in a grade category. We need it to filter what the parent can see sees.
     * @courseids
     * @gradecategories are the names of the grade categories set in the edit_form.php
     */
    public function cgsfeedback_get_courses_grade_categories($courseids, $gradecategories) {
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
        // $courseswithgrades = array_keys($this->cgsfeedback_get_courses_with_grades($user->id, $courseids));
        
        // Show subjects before feedback is released. Ticket 77814
        $courseswithgrades = array_keys($usercourses);
        
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

        $senacadix = $this->findCourseIndexByName($courses, 'Senior Academic');
        if ($senacadix !== -1) {
        //    $courses[$senacadix]->coursename = 'Senior Pastoral';
            unset($courses[$senacadix]);
            $courses = array_values($courses);
        }

        return array(
            'courses' => $courses,
        );
    }

    function findCourseIndexByName($courses, $name) {
        foreach ($courses as $index => $obj) {
            if ($obj->shortname === $name) {
                return $index;
            }
        }
        return -1;
    }

    /**
     *  Function called by the WS.
     *
     */
    public function get_course_modules_context($courseid, $userid, $yearlevel, $learningpathway) {
        global $CFG, $USER, $DB;

        // Get course by courseid
        $course = get_course($courseid);
        //$course = new stdClass();
        //$course->id = $courseid;
        $modinfo = new \course_modinfo($course, $userid);
        $context = \context_course::instance($course->id);

        $coursedata = new stdClass();
        $coursedata->courseid = $course->id;
        $coursedata->userid = $userid;
        $coursedata->isyear12andhsc = $yearlevel == 12  && $learningpathway == 'HSC' &&  $CFG->block_cgsfeedback_show_rank ? true : false;
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

                 error_log(print_r($isingradecategory, true));
                 error_log(print_r($gradinginfo->items[0], true));

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
                // $module->rank =  $this->get_rank($courseid, $userid, ($gradinginfo->items[0]->grades[$userid])->grade,  $gradinginfo->items[0]->id);  TODO: In prod is failing when logged in as

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
                } else if($pluginname == 'quiz'){   

                    $fb = $this->get_quiz_feedback(($gradinginfo->items[0]->grades[$userid])->grade, $instanceid);
                    
                    $module->feedback = $fb;
                }


                $cd->modules[] = $module; // Only add the assessment that have  a grade.
                
                $data['courses'][$course->id] = $cd;

            
            }
        }





















        $year = date('Y');
        $istwoyearcourse = false;
        if(
            strpos($course->fullname, $year+1) !== false || 
            strpos($course->fullname, " IB ") !== false ||
            strpos($course->fullname, " HSC ") !== false ||
            strpos($course->fullname, " IBDP ") !== false
        ) {
            $istwoyearcourse = true;
        }
        $plusyear = 0;
        if(strpos($course->fullname, $year+1)){
            $plusyear = 1;
        }
        $config = get_config('block_cgsfeedback');
        if ($istwoyearcourse) {
            if ($plusyear) {
                // E.g. it is 2025 now and this course runs over 2025 and 2026
                $displayT1 = time() > strtotime( $year . '-' . $config->displayT1 );
                $displayT2 = time() > strtotime( $year . '-' . $config->displayT2 );
                $displayT3 = time() > strtotime( $year . '-' . $config->displayT3 );
                $displayT4 = time() > strtotime( $year . '-' . $config->displayT4 );
                $displayT5 = false;
                $displayT6 = false;
                $displayT7 = false;
            } else {
                // E.g. it is 2025 and this course ran over 2024 and 2025.
                $displayT1 = true;
                $displayT2 = true;
                $displayT3 = true;
                $displayT4 = true;
                $displayT5 = time() > strtotime( $year . '-' . $config->displayT5 );
                $displayT6 = time() > strtotime( $year . '-' . $config->displayT6 );
                $displayT7 = time() > strtotime( $year . '-' . $config->displayT7 );
            }
        } else {
            // One year course.
            $displayT1 = time() > strtotime( $year . '-' . $config->displayT1 );
            $displayT2 = time() > strtotime( $year . '-' . $config->displayT2 );
            $displayT3 = time() > strtotime( $year . '-' . $config->displayT3 );
            $displayT4 = time() > strtotime( $year . '-' . $config->displayT4 );
        }
        























        // Get effort
        $modules = [];
        $cd = $data["courses"][$course->id];
        $coursesgradecategory = $this->cgsfeedback_get_courses_grade_categories($courseid, 'CGS Effort');
        if (isset($coursesgradecategory[$courseid])) {
            $cgseffortitems = $this->get_course_modules_in_grade_category($coursesgradecategory[$courseid][0]->categoryid, $course->id);
            $module = new stdClass();
            $module->modulename = 'Effort';
            $module->itemid = 0;
            $module->finalgrade = null;
            $module->colspan = 2;
            $effortdata = [];
            $year = date('Y');

            foreach ($cgseffortitems as $itemid) {
                $item = $this->get_grade_item($itemid);
                if ($item->hidden) {
                    continue;
                }
                $grade = $this->get_grade_item_grade($item->id, $userid);
                if (!$grade->finalgrade) {
                    continue;
                }

                $pattern = '/(Term \d+) (.+)/';
                preg_match($pattern, $item->itemname, $matches);
                if (!$matches) {
                    continue;
                }

                $term = $matches[1]; // $matches[1] contains "Term 1"
                $effortparam = $matches[2]; // $matches[2] contains "Approach"


                // If this term is not supposed to be visible yet, skip it.
                if ($term == 'Term 1' && !$displayT1) {
                    continue;
                } else if ($term == 'Term 2' && !$displayT2) {
                    continue;
                } else if ($term == 'Term 3' && !$displayT3) {
                    continue;
                } else if ($term == 'Term 4' && !$displayT4) {
                    continue;
                } else if ($term == 'Term 5' && !$displayT5) {
                    continue;
                } else if ($term == 'Term 6' && !$displayT6) {
                    continue;
                } else if ($term == 'Term 7' && !$displayT7) {
                    continue;
                }


                // Do not show Learning Progress effort type.
                if (strpos($item->itemname, 'Learning Progress') !== false) {
                    continue;
                }

                // Do not show Term 1 Deadlines for Year 7 courses.
                if (strpos($course->fullname, "7 $year") !== false) {
                    if ($item->itemname === 'Term 1 Deadlines') {
                        continue;
                    }
                }

                $scale = $this->get_grade_scale($item->scaleid); 
                $scalearr = explode(",", $scale->scale);
                $scalereverse = $scalearr;
                $scalereverse = array_reverse($scalereverse);
                $scalehtml = implode("<br>", $scalereverse);
                $gradeindex = (int) $grade->finalgrade;
                $scaleword = $scalearr[$gradeindex-1];

                if (!isset($effortdata[$term])) {
                    $effortdata[$term] = array();
                }

                $effortdata[$term][$effortparam] = array(
                    'itemid' => $itemid,
                    'letter' => $item->itemname,
                    'tip' => "<strong>$item->itemname</strong>",
                    'scaletip' => "<strong>Scale:</strong><br> $scalehtml",
                    'grade' => $grade->finalgrade,
                    'scaleword' => $scaleword,
                    'thclasses' => 'th-effort',
                );
            }
            if (count($effortdata)) { // Prepare effort data for template.
                $criteria = [
                    ['name' => 'Punctuality', 'desc' => 'Punctuality and Organisation includes prompt arrival to class, bringing the correct equipment and responding to correspondence from staff.'], 
                    ['name' => 'Classwork', 'desc' => 'Effective use of class time and technology includes working constructively, engaging in class discussions, listening well, taking notes, working collaboratively and utilising a mobile device effectively.'], 
                    ['name' => 'Approach', 'desc' => 'Independent approach to learning emphasises self-discipline and active learning, for example, drafting work for peer/teacher feedback or persisting with tasks when concepts are challenging or reading more broadly on topics. Students are responsible for their learning.'], 
                    ['name' => 'Deadlines', 'desc' => 'Meeting deadlines includes effective time management and thorough completion of homework and assignment tasks.'], 
                ];
                $terms = ['Term 1', 'Term 2', 'Term 3', 'Term 4', 'Term 5', 'Term 6', 'Term 7'];;
                $templateitems = [];
                foreach ($criteria as $criterion) {
                    $cn = $criterion['name'];
                    $item = [
                        'criterion' => $cn,
                        'tooltip' => $criterion['desc']
                    ];
                    foreach ($terms as $term) {
                        $item[strtolower(str_replace(' ', '', $term))] = $effortdata[$term][$cn]['scaleword'] ?? '';
                    }
                    $templateitems[] = $item;
                }

                $module->effortitems = $templateitems;
                $module->iseffort = true;
                $modules[] = $module;
                
                $module->istwoyearcourse = $istwoyearcourse;
      

            } 
            //var_export($module->effortitems); exit;
        }
        $data['courses'][$course->id]->modules = array_merge($modules, $data['courses'][$course->id]->modules);


        // Get MYP Semester grades.
        $modules = [];
        $cd = $data["courses"][$course->id];
        $coursesgradecategory = $this->cgsfeedback_get_courses_grade_categories($courseid, 'Semester Grades');
        if (isset($coursesgradecategory[$courseid]) &&  $yearlevel >= 7 && $yearlevel <= 9) {
            $items = $this->get_course_modules_in_grade_category($coursesgradecategory[$courseid][0]->categoryid, $course->id);
            $module = new stdClass();
            $module->modulename = 'MYP Grades';
            $module->itemid = 0;
            $module->finalgrade = null;
            $module->hasoutcomes = true;
            $module->colspan = 2;
            $outcomes = [];
            foreach ($items as $itemid) {
                $item = $this->get_grade_item($itemid);
                if ($item->hidden) {
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
                    'letter' => $item->itemname,
                    'tip' => "<strong>$item->itemname</strong>",
                    'scaletip' => "<strong>Scale:</strong><br> $scalehtml",
                    'grade' => $grade->finalgrade,
                    'scaleword' => $scaleword,
                    'thclasses' => 'th-mypgrade',
                );
            }
            if (count($outcomes)) {
                $module->outcomes = $outcomes;
                $module->ismypgrade = true;
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

    private function get_quiz_feedback($grade, $quizid) {
        global $DB;
        $grade = max($grade, 0);

        $feedback = $DB->get_record_select('quiz_feedback',
                'quizid = ? AND mingrade <= ? AND ? < maxgrade', [$quizid, $grade, $grade]);
      
        return $feedback->feedbacktext;
    }

    public function get_rank($courseid, $userid, $finalgrade, $gradeitemid) {
        global $DB;
        $context = \context_course::instance($courseid);
        $user = new gradereport_user\report\user($courseid, null, $context, $userid);
        $rank = '';
        // Find the number of users with a higher grade.
        $sql = "SELECT COUNT(DISTINCT(userid))
        FROM {grade_grades}
        WHERE finalgrade > ?
                AND itemid = ?
                AND hidden = 0";


        $r = $DB->count_records_sql($sql, [$finalgrade, $gradeitemid]) + 1;

        $numbusers = $user->get_numusers(false);

        if ($numbusers > 0) {

            $rank = "$r/$numbusers";
        }

        return $rank;

    }

}
