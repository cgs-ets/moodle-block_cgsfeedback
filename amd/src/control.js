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
define(['core/url', 'core/log'],
    function (URL, Log) {
    'use strict';

    /**
     * Initializes the block controls.
     */
    function init(instanceid) {
        Log.debug('block_cgsfeedback/control: initializing controls of the cgsfeedback block');

        var section = document.getElementById(`inst${instanceid}`)

        if (section == null) {
            Log.debug('block_cgsfeedback/control: section not found!');
            return;
        }

        // Change the images URLS to be picked up the parentview plugin
        encodeURL();
        //
        const expandIcon = document.querySelectorAll('.cgsfeedback-expand-icon');
        expandIcon.forEach(icon => icon.addEventListener('click', expandTable));

        const collapseIcon = document.querySelectorAll('.cgsfeedback-collapse-icon');
        collapseIcon.forEach(icon => icon.addEventListener('click', collapseTable));

        // Show table
        function expandTable(e) {
            console.log("expandTable");
            console.log(e.target.getAttribute('data-courseid'));
            courseid = e.target.getAttribute('data-courseid');
            const table = document.getElementById(`cgsfeedback-activities-container-${courseid}`);
            console.log(table);
            table.removeAttribute('hidden'); // Show table
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

        function encodeURL() {
            const images = document.querySelectorAll("section#inst32  img");

            images.forEach(image => {
                const urlEncoded = encodeURIComponent(image.getAttribute('src'));
                console.log(urlEncoded);
                const userid = document.querySelector('.cgsfeedback-course-container').getAttribute('data-userid');
                const newURL = URL.relativeUrl('/local/parentview/get.php', {
                    addr: urlEncoded,
                    user: userid
                });

                image.setAttribute('src', newURL);

            })

        }


    }

    return {
        init: init
    };
});