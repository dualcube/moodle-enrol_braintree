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
 * Course wise edit form.
 *
 * Adds new instance of enrol_braintree to specified course
 * or edits current instance.
 *
 * @package    enrol_braintree
 * @copyright  2016 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Adrita Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
/**
 * Sets up moodle edit form class methods.
 * @copyright  2016 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Adrita Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_braintree_edit_form extends moodleform {
    /**
     * Sets up moodle form.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_braintree'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_braintree'), $options);
        $mform->setDefault('status', $plugin->get_config('status'));
        $mform->addElement('text', 'cost', get_string('cost', 'enrol_braintree'), array('size' => 4));
        $mform->setType('cost', PARAM_RAW); // Use unformat_float to get real value.
        $mform->setDefault('cost', format_float($plugin->get_config('cost'), 2, true));
        $braintreecurrencies = $plugin->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_braintree'), $braintreecurrencies);
        $mform->setDefault('currency', $plugin->get_config('currency'));

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_braintree'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('roleid'));
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_braintree'),
            array('optional' => true, 'defaultunit' => 86400));
        $mform->setDefault('enrolperiod', $plugin->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_braintree');
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_braintree'),
            array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_braintree');
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_braintree'),
            array('optional' => true));
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_braintree');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'),
                get_string('instanceeditselfwarningtext', 'core_enrol'));
        }

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }
    /**
     * Sets up moodle form validation.
     * @param stdClass $data
     * @param stdClass $files
     * @return $error error list
     */
    public function validation($data, $files) {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);

        list($instance, $plugin, $context) = $this->_customdata;

        if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_braintree');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_braintree');
        }

        return $errors;
    }
}
