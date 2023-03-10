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
 * Provides the block_cgsfeedback/control module
 *
 * @package   block_cgsfeedback
 * @category  output
 * @copyright 2023 onwards Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module block_cgsfeedback/control
 */
define(['core/url', 'core/ajax', 'core/log', 'core/templates'],
    function (URL, Ajax, Log, Templates) {
        'use strict';

        /**
         * Initializes the block controls.
         */
        function init(instanceid) {
            Log.debug('block_cgsfeedback/control: initializing controls of the cgsfeedback block');
            console.log(instanceid);
            var section = document.getElementById(instanceid)

            if (section == null) {
                Log.debug('block_cgsfeedback/control: section not found!');
                return;
            }

            // Change the images URLS to be picked up the parentview plugin
            encodeURL(instanceid);
            //
            const expandIcon = document.querySelectorAll('.cgsfeedback-expand-icon');
            expandIcon.forEach(icon => icon.addEventListener('click', expandTable));

            const collapseIcon = document.querySelectorAll('.cgsfeedback-collapse-icon');
            collapseIcon.forEach(icon => icon.addEventListener('click', collapseTable));

            // Show table
            function expandTable(e) {
                courseid = e.target.getAttribute('data-courseid');
                userid = document.querySelector(".cgsfeedback-course-container").getAttribute('data-userid');
                const table = document.getElementById(`cgsfeedback-activities-container-${courseid}`);

                if (table == null) {
                    // Show the loading
                    document.querySelector(`.loading-course-${courseid}-modules`).removeAttribute('hidden');
                    // Get the assessments dinamically.
                    Ajax.call([
                        {
                            methodname: 'block_cgsfeedback_get_modules',
                            args: {
                                courseid: courseid,
                                userid: userid,
                            },
                            done: function (response) {
                                $(`.loading-course-${courseid}-modules`).replaceWith(response.html);
                            },
                            fail: function (reason) {
                                console.log(reason);
                            }
                        }
                    ])
                } else {
                    document.getElementById(`cgsfeedback-activities-container-${courseid}`).removeAttribute('hidden');
                }

                // Hide expand icon
                document.querySelector(`.cgsfeedback-expand-icon[data-courseid="${courseid}"]`).setAttribute('hidden', true);
                // Show collapse icon
                document.querySelector(`.cgsfeedback-collapse-icon[data-courseid="${courseid}"]`).removeAttribute('hidden');
            }

            // Hide table
            function collapseTable(e) {
                console.log("collapseTable");
                console.log(e.target.getAttribute('data-courseid'));
                courseid = e.target.getAttribute('data-courseid');
                const table = document.getElementById(`cgsfeedback-activities-container-${courseid}`);
                table.setAttribute('hidden', true); // Hide table
                document.querySelector(`.cgsfeedback-collapse-icon[data-courseid="${courseid}"]`).setAttribute('hidden', true);
                // Show collapse icon
                document.querySelector(`.cgsfeedback-expand-icon[data-courseid="${courseid}"]`).removeAttribute('hidden');
            }

        }

        return {
            init: init
        };
    });