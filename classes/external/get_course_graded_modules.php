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
 *  Web service to get the modules the student has a grade on.
 *
 * @package   block_cgsfeedback
 * @copyright 2023 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_cgsfeedback\external;

defined('MOODLE_INTERNAL') || die();

use block_cgsfeedback\cgsfeedbackmanager\cgsfeedbackmanager;
use external_function_parameters;
use external_value;
use external_single_structure;

require_once($CFG->dirroot . '/blocks/cgsfeedback/classes/feedbackmanager.php');

require_once($CFG->libdir . '/externallib.php');

/**
 * Trait implementing the external function block_cgsfeedback
 */
trait get_course_graded_modules {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */

    public static function get_course_graded_modules_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_RAW, 'course id'),
                'userid' => new external_value(PARAM_RAW, 'user id'),
            )
        );
    }

    /**
     * Return context.
     */
    public static function get_course_graded_modules($courseid, $userid) {
        global $USER, $PAGE;

        $context = \context_user::instance($USER->id);

        self::validate_context($context);
        // Parameters validation.
        self::validate_parameters(self::get_course_graded_modules_parameters(), array('courseid' => $courseid, 'userid' => $userid));

        // Get the context for the template.
        $manager = new cgsfeedbackmanager();
        // Avoid Unable to obtain session lock error.
        session_write_close();
        $ctx = $manager->get_course_modules_context($courseid, $userid);
        sleep(4);

        if (empty($ctx)) {
            $html = get_string('nodataavailable', 'report_mystudent');
        } else {
            $output = $PAGE->get_renderer('core');
            $html = $output->render_from_template('block_cgsfeedback/modules_table', $ctx);
        }

        return array(
            'html' => $html,
        );
    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function get_course_graded_modules_returns() {
        return new external_single_structure(array(
            'html' => new external_value(PARAM_RAW, 'HTML with the moodle modules in the course'),
        ));
    }
}
