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
 * CGS feedback block lib
 *
 * @package    block_cgsfeedback
 * @copyright  2023 onwards Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function block_cgsfeedback_get_mentor($profileuserid) {
    global $DB, $USER;
    // Parents are allowed to view block in their mentee profiles.
    $mentorrole = $DB->get_record('role', array('shortname' => 'parent'));
    $mentor = null;

    if ($mentorrole) {

        $sql = "SELECT ra.*, r.name, r.shortname
            FROM {role_assignments} ra
            INNER JOIN {role} r ON ra.roleid = r.id
            INNER JOIN {user} u ON ra.userid = u.id
            WHERE ra.userid = ?
            AND ra.roleid = ?
            AND ra.contextid IN (SELECT c.id
                FROM {context} c
                WHERE c.contextlevel = ?
                AND c.instanceid = ?)";
        $params = array(
            $USER->id, //Where current user
            $mentorrole->id, // is a mentor
            CONTEXT_USER,
            $profileuserid, // of the prfile user
        );

        $mentor = $DB->get_records_sql($sql, $params);
    }

    return $mentor;
}

// Parent view of own child's activity functionality.
function  block_cgsfeedback_can_view_on_profile() {
    global $DB, $USER, $PAGE;

    $userid = $PAGE->url->get_param('id');
    $userid = $userid ? $userid : $USER->id; // Owner of the page.
    // Admin is allowed.
    $profileuser = $DB->get_record('user', ['id' => $userid]);

    if (is_siteadmin($USER) && $profileuser->username != $USER->username) {
        return true;
    }

    // Students are allowed to see block in their own profiles.
    if ($profileuser->username == $USER->username && !is_siteadmin($USER)) {
        return true;
    }

    // Parents are allowed to view block in their mentee profiles.
    $mentorrole = $DB->get_record('role', array('shortname' => 'parent'));

    if ($mentorrole) {

        $sql = "SELECT ra.*, r.name, r.shortname FROM {role_assignments} ra
                INNER JOIN {role} r ON ra.roleid = r.id
                INNER JOIN {user} u ON ra.userid = u.id
                WHERE ra.userid = ? AND ra.roleid = ? AND ra.contextid IN (SELECT c.id
                                                                            FROM {context} c
                                                                            WHERE c.contextlevel = ?
                                                                            AND c.instanceid = ?
                                                                            )";

        $params = array(
                $USER->id, // Where current user.
                $mentorrole->id, // Is a mentor.
                CONTEXT_USER,
                $profileuser->id, // Of the prfile user.
            );

        $mentor = $DB->get_records_sql($sql, $params);

        if (!empty($mentor)) {
            return true;
        }
}
    return false;
}
