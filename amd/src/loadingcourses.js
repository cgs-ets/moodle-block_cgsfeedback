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
 * @module block_cgsfeedback/loadingcourses
 */
define(['core/url', 'core/ajax', 'core/log', 'core/templates'],
    function (URL, Ajax, Log, Templates) {
        'use strict';

        /**
         * Initializes the block controls.
         */
        function init(user) {
            Log.debug('block_cgsfeedback/loadingcourses: initializing loadingcourses of the cgsfeedback block');
            const instanceid = document.querySelector('.cgsfeedback-loading-courses').getAttribute('data-instanceid')
            var section = document.getElementById(`inst${instanceid}`);

            if (section == null) {
                Log.debug('block_cgsfeedback/control: section not found!');
                return;
            }

            // Add listener to the hide/show icon
            if (document.querySelector('.cgsfeedback-instructions-container .fa-eye') != null) {
                document.querySelector('.cgsfeedback-instructions-container .fa-eye').addEventListener('click', hideInstructions);
            }

            const loadCourseNames = () => {
                let user = document.querySelector('.cgsfeedback-loading-courses').getAttribute('data-user');
                let instanceid = document.querySelector('[data-block=cgsfeedback]').getAttribute('id');

                Ajax.call([{

                    methodname: 'block_cgsfeedback_get_courses',
                    args: {
                        user: user
                    },
                    done: function (response) {
                        let context = JSON.parse(response.ctx);
                        context.instanceid = instanceid;
                        // Remove the class that centres the spinner
                        document.getElementById('cgsfeedback-loading-container').classList.remove('cgsfeedback-loading-courses-first');
                        if (context.courses == null) {
                            context.text = "Assignments not found"

                            Templates.renderForPromise('block_cgsfeedback/no_content', context)
                                .then(({ html, js }) => {
                                    Templates.replaceNodeContents('#cgsfeedback-loading-container', html, js);
                                })
                                .catch((error) => displayException(error));
                        } else {

                            Templates.renderForPromise('block_cgsfeedback/content', context)
                                .then(({ html, js }) => {
                                    Templates.replaceNodeContents('#cgsfeedback-loading-container', html, js);
                                })
                                .catch((error) => displayException(error));
                        }


                    },
                    fail: function (reason) {
                        console.log(reason);
                    }
                }
                ])

            }


            function hideInstructions(e) {

                const classList = Array.from(e.target.classList);
                if (classList.includes('fa-eye')) {
                    e.target.classList.remove('fa-eye');
                    e.target.classList.add('fa-eye-slash');
                    e.target.setAttribute('title', 'Show instructions');
                    document.querySelector('.cgsfeedback-instructions-container span').classList.add('cgsfeedback-hide-instructions');
                } else {
                    e.target.classList.add('fa-eye');
                    e.target.classList.remove('fa-eye-slash');
                    e.target.setAttribute('title', 'Hide instructions');
                    document.querySelector('.cgsfeedback-instructions-container span').classList.remove('cgsfeedback-hide-instructions');
                }
            }

            // Load courses
            loadCourseNames();


        }

        return {
            init: init
        };
    });