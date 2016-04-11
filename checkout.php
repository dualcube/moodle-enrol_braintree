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
 * Listens for Instant Payment Notification from Braintree
 *
 * This script waits for Payment notification from Braintree,
 * then double checks that data by sending it back to Braintree.
 * If Braintree verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_braintree
 * @copyright  2016 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Adrita Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!

require_once('braintree/lib/Braintree.php');
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
// Braintree does not like when we return error messages here.
// the custom handler just logs exceptions and stops.
// set_exception_handler('enrol_braintree_charhge_exception_handler').;
// Keep out casual intruders.
if (empty($_POST) or !empty($_GET)) {
    print_error("Sorry, you can not use the script that way.");
}

$data = new stdClass();

foreach ($_POST as $key => $value) {
    $data->$key = $value;
}
$custom = explode('-', $data->custom);
$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();
// Get the user and course records.

if (! $user = $DB->get_record("user", array("id" => $data->userid))) {
    message_braintree_error_to_admin("Not a valid user id", $data);
    redirect($CFG->wwwroot);
}

if (! $course = $DB->get_record("course", array("id" => $data->courseid))) {
    message_braintree_error_to_admin("Not a valid course id", $data);
    redirect($CFG->wwwroot);
}

if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
    message_braintree_error_to_admin("Not a valid context id", $data);
    redirect($CFG->wwwroot);
}

$PAGE->set_context($context);

if (! $plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
    message_braintree_error_to_admin("Not a valid instance id", $data);
    redirect($CFG->wwwroot);
}

 // If currency is incorrectly set then someone maybe trying to cheat the system.

if ($data->courseid != $plugininstance->courseid) {
    message_braintree_error_to_admin("Course Id does not match to the course settings, received: ".$data->courseid, $data);
    redirect($CFG->wwwroot);
}

$plugin = enrol_get_plugin('braintree');

// Check that amount paid is the correct amount.
if ( (float) $plugininstance->cost <= 0 ) {
    $cost = (float) $plugin->get_config('cost');
} else {
    $cost = (float) $plugininstance->cost;
}

$destination = "$CFG->wwwroot/course/view.php?id=$course->id";
// Use the same rounding of floats as on the enrol form.
$cost = format_float($cost, 2, false);

Braintree_Configuration::environment($plugin->get_config('environment'));
Braintree_Configuration::merchantId($plugin->get_config('merchantId'));
Braintree_Configuration::publicKey($plugin->get_config('publickey'));
Braintree_Configuration::privateKey($plugin->get_config('privatekey'));

$nonce = $_POST ['payment_method_nonce'];

if ($customer = $DB->get_record('braintree_customer', array('iduser' => $USER->id))) {
    $customerid = $customer->idcustomer;
    $resultp = Braintree_PaymentMethod::create([
    'customerId' => $customerid,
    'paymentMethodNonce' => $nonce,
    'options' => [
    'failOnDuplicatePaymentMethod' => true,
    'makeDefault' => true
    ]
    ]);
} else {
    $result = Braintree_Customer::create([
    'firstName' => $USER->firstname,
    'lastName' => $USER->lastname,
    'email' => $USER->email,
    'paymentMethodNonce' => $nonce
    ]);
    if (!$result->success) {
        foreach ($result->errors->deepAll() as $error) {
            redirect($destination);
        }
    }
    $customerid = $result->customer->id;
    $record = new stdClass();
    $record->iduser = $USER->id;
    $record->idcustomer = $customerid;
    $record->create_date = time();
    $DB->insert_record('braintree_customer', $record);
}
    $result2 = Braintree_Transaction::sale([
    'customerId' => $customerid,
    'amount' => $cost,
    'options' => [ 'submitForSettlement' => true ]
    ]);

    if ($result2->transaction) {
        $data->merchant_id = $result2->transaction->merchantAccountId;
        $data->txn_id = isset($result2->transaction->id) ? $result2->transaction->id : '';
        $data->amount = $result2->transaction->amount;
        $data->currency = $result2->transaction->currencyIsoCode;
        $data->tax = $result2->transaction->taxAmount;
        $data->payment_type = $result2->transaction->type;
        $data->memo = $result2->transaction->orderId;
        $data->payment_status = $result2->transaction->status;
        $data->pending_reason = $result2->transaction->gatewayRejectionReason;
        $data->reason_code = $result2->transaction->processorResponseCode;
    }
    if ($result2->success) {
        // Send the file, this line will be reached if no error was thrown above.
        // ALL CLEAR !

        $DB->insert_record("enrol_braintree", $data);

        if ($plugininstance->enrolperiod) {
            $timestart = time();
            $timeend   = $timestart + $plugininstance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend   = 0;
        }

        // Enrol user.
        $plugin->enrol_user($plugininstance, $USER->id, $plugininstance->roleid, $timestart, $timeend);

        // Pass $view=true to filter hidden caps if the user cannot see them.
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                             '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        $mailstudents = $plugin->get_config('mailstudents');
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');
        $shortname = format_string($course->shortname, true, array('context' => $context));

        $a = new stdClass();
        if (!empty($mailstudents)) {
            $a = new stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $context));
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_braintree';
            $eventdata->name              = 'braintree_enrolment';
            $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);

        }

        if (!empty($mailteachers) && !empty($teacher)) {
            $a->course = format_string($course->fullname, true, array('context' => $context));
            $a->user = fullname($user);

            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_braintree';
            $eventdata->name              = 'braintree_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }

        if (!empty($mailadmins)) {
            $a->course = format_string($course->fullname, true, array('context' => $context));
            $a->user = fullname($user);
            $admins = get_admins();
            foreach ($admins as $admin) {
                $eventdata = new stdClass();
                $eventdata->modulename        = 'moodle';
                $eventdata->component         = 'enrol_braintree';
                $eventdata->name              = 'braintree_enrolment';
                $eventdata->userfrom          = $user;
                $eventdata->userto            = $admin;
                $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
                $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml   = '';
                $eventdata->smallmessage      = '';
                message_send($eventdata);
            }
        }
        if (!empty($SESSION->wantsurl)) {
            $destination = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
        } else {
            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
        }

        $fullname = format_string($course->fullname, true, array('context' => $context));

        if (is_enrolled($context, null, '', true)) { // TODO: use real braintree check.
            redirect($destination, get_string('paymentthanks', '', $fullname));

        } else {   // Somehow they aren't enrolled yet!
            $PAGE->set_url($destination);
            echo $OUTPUT->header();
            $a = new stdClass();
            $a->teacher = get_string('defaultcourseteacher');
            $a->fullname = $fullname;
            notice(get_string('paymentsorry', '', $a), $destination);
        }
    } else if ($result2->transaction) {
        $DB->insert_record("enrol_braintree", $data);
        if (!empty($SESSION->wantsurl)) {
            $destination = $SESSION->wantsurl;
                  unset($SESSION->wantsurl);
        } else {
            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
        }

        $fullname = format_string($course->fullname, true, array('context' => $context));

        if (is_enrolled($context, null, '', true)) { // TODO: use real braintree check.
               redirect($destination, get_string('paymentthanks', '', $fullname));

        } else {   // Somehow they aren't enrolled yet!
            $PAGE->set_url($destination);
                echo $OUTPUT->header();
            $a = new stdClass();
            $a->teacher = get_string('defaultcourseteacher');
            $a->fullname = $fullname;
            notice(get_string('paymentsorry', '', $a), $destination);
        }
    } else {
        echo("Validation errors: \n");
        echo($result2->errors->deepAll());
    }


    // --- HELPER FUNCTIONS --------------------------------------------------------------------------------------!

    /**
     * Send payment error message to the admin.
     *
     * @param string $subject
     * @param stdClass $data
     */
    function message_braintree_error_to_admin($subject, $data) {
            echo $subject;
        $admin = get_admin();
        $site = get_site();

        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

        foreach ($data as $key => $value) {
            $message .= s($key) ." => ". s($value)."\n";
        }

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_braintree';
        $eventdata->name              = 'braintree_enrolment';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $admin;
        $eventdata->subject           = "BRAINTREE ERROR: ".$subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }
