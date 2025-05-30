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
 * Feedback block.
 *
 * @package    block_feedback
 * @copyright  2023 onwards Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_cgsfeedback\cgsfeedbackmanager\cgsfeedbackmanager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/cgsfeedback/classes/feedbackmanager.php');
require_once($CFG->dirroot . '/blocks/cgsfeedback/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

class block_cgsfeedback extends block_base {
    /**
     * Core function used to initialize the block.
     */
    public function init() {
        $this->title = get_string('cgsfeedback', 'block_cgsfeedback');
    }

    /**
     * Set where the block should be allowed to be added
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'user-profile'   => true,
        );
    }

     /**
      * Controls whether multiple instances of the block are allowed on a page
      *
      * @return bool
      */
    public function instance_allow_multiple() {
        return false;
    }

     /**
      * Used to generate the content for the block.
      * @return object
      */
    public function get_content() {
        global $OUTPUT, $DB, $USER, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        // Add extra js.
        $this->page->requires->js(new moodle_url($CFG->wwwroot . '/blocks/cgsfeedback/ext/tippy/popper.min.js'), true );
        $this->page->requires->js(new moodle_url($CFG->wwwroot . '/blocks/cgsfeedback/ext/tippy/tippy.min.js'), true );
        $this->page->requires->css(new moodle_url($CFG->wwwroot . '/blocks/cgsfeedback/ext/tippy/tippy_light.css'));

        $this->content = new stdClass;
        $this->content->text = null;

        if (block_cgsfeedback_can_view_on_profile()) {

            $userid = $this->page->url->get_param('id');
            $userid = $userid ? $userid : $USER->id; // Owner of the page.
            $profileuser = $DB->get_record('user', ['id' => $userid]);
            profile_load_custom_fields($profileuser);
            $campusrole = $profileuser->profile['CampusRoles'];
            // Only display block if its a student profile.
            //if (preg_match('/\b(Parents|parents|Primary|primary)\b/', $campusrole) != 1) {
            if (strpos($campusrole, 'Senior School:Students') !== false) {
                // Limit to courses (for Pilot).
                if (!empty($CFG->block_cgsfeedback_limitedcourses)) {    
                    // Check if this student is enrolled in one of the courses.
                    $limitedcourses = array_map('trim', explode(",", $CFG->block_cgsfeedback_limitedcourses));
                    $usercourses = enrol_get_all_users_courses($userid, true);
                    $usercourseids = array_keys($usercourses);
                    if (!array_intersect($limitedcourses, $usercourseids)) {
                        return null;
                    }
                }

                $data = new stdClass();
                $data->userid = $userid;
                $data->instanceid = $this->instance->id;
                $data->hasinstructions = false;
                $data->yearlevel =$profileuser->profile['Year'];
                $data->learningpathway = $profileuser->profile['LearningPathway'];

                if (!empty($CFG->block_cgsfeedback_instruc_def)) {
                    $data->instructions = $CFG->block_cgsfeedback_instruc_def;
                    $data->hasinstructions = true;
                }

                $this->content->text  = $OUTPUT->render_from_template('block_cgsfeedback/loading_courses', $data);
            }
        }

        return  $this->content->text;
    }

    public function has_config() {
        return true;
    }


}
