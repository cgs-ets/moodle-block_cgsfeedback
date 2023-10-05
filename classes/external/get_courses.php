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
trait get_courses {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */

    public static function get_courses_parameters() {
        return new external_function_parameters(
            array(
                'user' => new external_value(PARAM_RAW, 'user id'),
            )
        );
    }

    /**
     * Return context.
     */
    public static function get_courses($user) {
        global $USER, $DB;

        $context = \context_user::instance($USER->id);

        self::validate_context($context);
        // Parameters validation.
        self::validate_parameters(self::get_courses_parameters(), array('user' => $user));

        // Get the context for the template.
        $manager = new cgsfeedbackmanager();

        $profileuser = $DB->get_record('user', ['id' => $user]);
        profile_load_custom_fields($profileuser);
         // Avoid Unable to obtain session lock error.
        session_write_close();
        $ctx = json_encode($manager->cgsfeedback_get_student_courses($profileuser));
        return array(
            'ctx' => $ctx,
        );
    }

    /**
     * Describes the structure of the function return value.
     * @return external_single_structures
     */
    public static function get_courses_returns() {
        return new external_single_structure(array(
            'ctx' => new external_value(PARAM_RAW, 'Context for the template'),
        ));
    }
}
