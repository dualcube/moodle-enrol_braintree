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
 * Stript enrolments plugin settings and presets.
 *
 * @package    enrol_braintree
 * @copyright  2016 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Adrita Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

         // --- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_braintree_settings', '', get_string('pluginname_desc', 'enrol_braintree')));
      $options = array(
        'sandbox' => 'Test Mode',
          'production' => 'live Mode'
      );
      $settings->add(new admin_setting_configselect('enrol_braintree/environment', get_string('environment',
        'enrol_braintree'), get_string('environment_desc', 'enrol_braintree'), 'sandbox', $options));
      $settings->add(new admin_setting_configtext('enrol_braintree/merchantId', get_string('merchantId',
        'enrol_braintree'), get_string('merchantId_desc', 'enrol_braintree'), '', PARAM_TEXT));
      $settings->add(new admin_setting_configtext('enrol_braintree/publickey', get_string('publickey',
        'enrol_braintree'), get_string('publickey_desc', 'enrol_braintree'), '', PARAM_TEXT));
      $settings->add(new admin_setting_configtext('enrol_braintree/privatekey', get_string('privatekey',
        'enrol_braintree'), get_string('privatekey_desc', 'enrol_braintree'), '', PARAM_TEXT));

      $settings->add(new admin_setting_configcheckbox('enrol_braintree/mailstudents', get_string('mailstudents',
        'enrol_braintree'), '', 0));

      $settings->add(new admin_setting_configcheckbox('enrol_braintree/mailteachers', get_string('mailteachers',
        'enrol_braintree'), '', 0));

      $settings->add(new admin_setting_configcheckbox('enrol_braintree/mailadmins', get_string('mailadmins',
        'enrol_braintree'), '', 0));

      // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
      // it describes what should happen when users are not supposed to be enrolled any more.
      $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
      );
      $settings->add(new admin_setting_configselect('enrol_braintree/expiredaction', get_string('expiredaction',
        'enrol_braintree'), get_string('expiredaction_help', 'enrol_braintree'), ENROL_EXT_REMOVED_SUSPENDNOROLES, $options));

      // ---Enrol instance defaults----------------------------------------------------------------------------
      $settings->add(new admin_setting_heading('enrol_braintree_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

      $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
      $settings->add(new admin_setting_configselect('enrol_braintree/status',
        get_string('status', 'enrol_braintree'), get_string('status_desc', 'enrol_braintree'), ENROL_INSTANCE_DISABLED, $options));

      $settings->add(new admin_setting_configtext('enrol_braintree/cost', get_string('cost', 'enrol_braintree'),
        '', 0, PARAM_FLOAT, 4));

      $braintreecurrencies = enrol_get_plugin('braintree')->get_currencies();
      $settings->add(new admin_setting_configselect('enrol_braintree/currency', get_string('currency', 'enrol_braintree'),
        '', 'USD', $braintreecurrencies));

      if (!during_initial_install()) {
          $options = get_default_enrol_roles(context_system::instance());
          $student = get_archetype_roles('student');
          $student = reset($student);
          $settings->add(new admin_setting_configselect('enrol_braintree/roleid',
          get_string('defaultrole', 'enrol_braintree'), get_string('defaultrole_desc', 'enrol_braintree'), $student->id, $options));
      }

        $settings->add(new admin_setting_configduration('enrol_braintree/enrolperiod',
        get_string('enrolperiod', 'enrol_braintree'), get_string('enrolperiod_desc', 'enrol_braintree'), 0));
}
