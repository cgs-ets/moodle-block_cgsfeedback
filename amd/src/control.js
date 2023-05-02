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
define(['core/ajax', 'core/log'],
    function (Ajax, Log) {
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

            // Add the expand functionality
            document.querySelectorAll('.expansible-container').forEach(el => el.addEventListener('click', expandTable));


            // Show table
            function expandTable(e) {
                const courseid = e.target.getAttribute('data-courseid');
                console.log(courseid);
                userid = document.querySelector(".cgsfeedback-course-container").getAttribute('data-userid');
                const table = document.getElementById(`cgsfeedback-activities-container-${courseid}`);

                if (table == null) {
                    // Show the loading

                    if (document.querySelector(`.loading-course-${courseid}-modules`) != null) {
                        document.querySelector(`.loading-course-${courseid}-modules`).removeAttribute('hidden');
                    }

                    // Get the assessments dinamically.
                    Ajax.call([
                        {
                            methodname: 'block_cgsfeedback_get_modules',
                            args: {
                                courseid: courseid,
                                userid: userid,
                            },
                            done: function (response) {
                                console.log(courseid);
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
                // remove the expansible-container class to the header
                document.querySelector(`.cgsfeedback-container-${courseid}`).classList.remove('expansible-container');
                // add the collapsible-container and event listeners
                document.querySelector(`.cgsfeedback-container-${courseid}`).classList.add('collapsible-container');

                document.querySelectorAll(`.cgsfeedback-container-${courseid}.collapsible-container`).forEach(el => el.addEventListener('click', collapseTable));
            }


            // Hide table
            function collapseTable(e) {
                courseid = e.target.getAttribute('data-courseid');
                const table = document.getElementById(`cgsfeedback-activities-container-${courseid}`);
                table.setAttribute('hidden', true); // Hide table
                document.querySelector(`.cgsfeedback-collapse-icon[data-courseid="${courseid}"]`).setAttribute('hidden', true);
                // Show collapse icon
                document.querySelector(`.cgsfeedback-expand-icon[data-courseid="${courseid}"]`).removeAttribute('hidden');

                // remove the collapsible-container class to the header
                document.querySelectorAll(`.cgsfeedback-container-${courseid}.collapsible-container`).forEach(el => el.removeEventListener('click', collapseTable));
                document.querySelector(`.cgsfeedback-container-${courseid}`).classList.remove('collapsible-container');
                // add the expansible-container and event listeners
                document.querySelector(`.cgsfeedback-container-${courseid}`).classList.add('expansible-container');

                document.querySelectorAll(`.cgsfeedback-container-${courseid}.expansible-container`).forEach(el => el.addEventListener('click', expandTable));
            }

        }

        return {
            init: init
        };
    });