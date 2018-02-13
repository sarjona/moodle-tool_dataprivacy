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
 * The contact form to the site's Data Protection Officer
 *
 * @copyright 2018 onwards Jun Pataleta
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package tool_dataprivacy
 */

use tool_dataprivacy\api;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * The contact form to the site's Data Protection Officer
 *
 * @copyright 2018 onwards Jun Pataleta
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package tool_dataprivacy
 */
class tool_dataprivacy_data_request_form extends moodleform {

    /**
     * Form definition.
     *
     * @throws HTML_QuickForm_Error
     * @throws coding_exception
     * @throws dml_exception
     */
    public function definition() {
        global $DB, $USER;
        $mform =& $this->_form;

        // Get users whom you are being a guardian to if your role has the capability to make data requests for children.
        $allusernames = get_all_user_name_fields(true, 'u');
        $children = $DB->get_records_sql("SELECT u.id, $allusernames
                                            FROM {role_assignments} ra, {context} c, {user} u
                                           WHERE ra.userid = ?
                                                 AND ra.contextid = c.id
                                                 AND c.instanceid = u.id
                                                 AND c.contextlevel = " . CONTEXT_USER, [$USER->id]);
        if ($children) {
            $persons = [$USER->id => fullname($USER)];
            foreach ($children as $child) {
                $childcontext = context_user::instance($child->id);
                if (has_capability('tool/dataprivacy:makedatarequestsforchildren', $childcontext)) {
                    $persons[$child->id] = fullname($child);
                }
            }
            $mform->addElement('select', 'userid', get_string('requestfor', 'tool_dataprivacy'), $persons);
        } else {
            // Requesting for self.
            $mform->addElement('hidden', 'userid', $USER->id);
        }
        $mform->setType('userid', PARAM_INT);

        // Subject access request type.
        $options = [
            api::DATAREQUEST_TYPE_EXPORT => get_string('requesttypeexport', 'tool_dataprivacy'),
            api::DATAREQUEST_TYPE_DELETE => get_string('requesttypedelete', 'tool_dataprivacy')
        ];
        $mform->addElement('select', 'type', get_string('requesttype', 'tool_dataprivacy'), $options);
        $mform->setType('type', PARAM_INT);
        $mform->addHelpButton('type', 'requesttype', 'tool_dataprivacy');

        // Request comments text area.
        $textareaoptions = ['cols' => 60, 'rows' => 10];
        $mform->addElement('textarea', 'comments', get_string('requestcomments', 'tool_dataprivacy'), $textareaoptions);
        $mform->setType('type', PARAM_ALPHANUM);
        $mform->addHelpButton('comments', 'requestcomments', 'tool_dataprivacy');

        // Action buttons.
        $this->add_action_buttons();

    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function validation($data, $files) {
        $errors = [];

        $validrequesttypes = [
            api::DATAREQUEST_TYPE_EXPORT,
            api::DATAREQUEST_TYPE_DELETE
        ];
        if (!in_array($data['type'], $validrequesttypes)) {
            $errors['type'] = get_string('errorinvalidrequesttype', 'tool_dataprivacy');
        }

        if (api::has_ongoing_request($data['userid'], $data['type'])) {
            $errors['type'] = get_string('errorrequestalreadyexists', 'tool_dataprivacy');
        }

        return $errors;
    }
}