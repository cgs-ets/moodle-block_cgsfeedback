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
 * Settings for the assignfeedback_download report
 *
 * @package    block_cgsfeedback
 * @copyright  2023 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtextarea(
        'block_cgsfeedback_instruc_def', 
        get_string('cgsfeedbackinstructionname', 'block_cgsfeedback'),
        get_string('cgsfeedbackinstructionnamedesc', 'block_cgsfeedback'), 
        '', 
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_cgsfeedback_grade_category', 
        get_string('cgsfeedbackgradecategory', 'block_cgsfeedback'),
        get_string('cgsfeedbackgradecategorydesc', 'block_cgsfeedback'), 
        '', 
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_cgsfeedback_limitedcourses', 
        get_string('cgsfeedbacklimitedcourses', 'block_cgsfeedback'),
        get_string('cgsfeedbacklimitedcoursesdesc', 'block_cgsfeedback'), 
        '', 
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_cgsfeedback_limitedcoursecats', 
        get_string('cgsfeedbacklimitedcoursecats', 'block_cgsfeedback'),
        get_string('cgsfeedbacklimitedcoursecatsdesc', 'block_cgsfeedback'), 
        '', 
        PARAM_RAW
    ));
    

    $name = 'block_cgsfeedback/displayT1';
    $title = "month-day to display T1 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);

    $name = 'block_cgsfeedback/displayT2';
    $title = "month-day to display T2 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);
    
    $name = 'block_cgsfeedback/displayT3';
    $title = "month-day to display T3 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);

    $name = 'block_cgsfeedback/displayT4';
    $title = "month-day to display T4 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);

    $name = 'block_cgsfeedback/displayT5';
    $title = "month-day to display T5 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);
    
    $name = 'block_cgsfeedback/displayT6';
    $title = "month-day to display T6 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);

    $name = 'block_cgsfeedback/displayT7';
    $title = "month-day to display T7 efforts";
    $setting = new admin_setting_configtext($name, $title, '', '');
    $settings->add($setting);

    //  Rank.

    $settings->add(new admin_setting_configcheckbox(
        'block_cgsfeedback_show_rank',
        get_string('cgsfeedbackrank', 'block_cgsfeedback'),
        '',
        0  // Default value (0 for unchecked, 1 for checked)
    ));

}
