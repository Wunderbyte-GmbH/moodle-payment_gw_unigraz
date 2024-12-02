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
 * This class contains a list of webservice functions related to the unigraz payment gateway.
 *
 * @package    paygw_unigraz
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_unigraz\external;

use context_system;
use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;
use core_payment\helper as payment_helper;
use paygw_unigraz\event\delivery_error;
use paygw_unigraz\event\payment_completed;
use paygw_unigraz\event\payment_error;
use paygw_unigraz\event\payment_successful;
use paygw_unigraz\unigraz_helper;
use local_shopping_cart\interfaces\interface_transaction_complete;
use paygw_unigraz\interfaces\interface_transaction_complete as ug_interface_transaction_complete;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

if (!interface_exists(interface_transaction_complete::class)) {
    class_alias(ug_interface_transaction_complete::class, interface_transaction_complete::class);
}

/**
 * Transaction complete class.
 */
class transaction_complete extends external_api implements interface_transaction_complete {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'tid' => new external_value(PARAM_TEXT, 'unique transaction id', VALUE_DEFAULT, ''),
            'token' => new external_value(PARAM_RAW, 'Purchase token', VALUE_DEFAULT, ''),
            'customer' => new external_value(PARAM_RAW, 'Customer Id', VALUE_DEFAULT, ''),
            'ischeckstatus' => new external_value(PARAM_BOOL, 'If initial purchase or cron execution', VALUE_DEFAULT, false),
            'resourcepath' => new external_value(
                PARAM_TEXT,
                'The order id coming back from the payment provider',
                VALUE_DEFAULT,
                ''
            ),
            'userid' => new external_value(PARAM_INT, 'user id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea payment area
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param string $token
     * @param string $customer
     * @param bool $ischeckstatus
     * @param string $resourcepath
     * @param int $userid
     * @return array
     */
    public static function execute(
        string $component,
        string $paymentarea,
        int $itemid,
        string $tid = '',
        string $token = '0',
        string $customer = '0',
        bool $ischeckstatus = false,
        string $resourcepath = '',
        int $userid = 0
    ): array {

        global $USER, $DB, $CFG, $DB;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'tid' => $tid,
            'token' => $token,
            'customer' => $customer,
            'ischeckstatus' => $ischeckstatus,
            'resourcepath' => $resourcepath,
            'userid' => $userid,
        ]);

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'unigraz');
        $sandbox = $config->environment == 'sandbox';

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('unigraz');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $successurl = helper::get_success_url($component, $paymentarea, $itemid)->__toString();
        $serverurl = $CFG->wwwroot;

        $ughelper = new unigraz_helper($config->environment, $config->secret);
        $orderdetails = $ughelper->check_status((int)$tid);

        $success = false;
        $message = '';

        if ($orderdetails && isset($orderdetails->object->status)) {
            $returnstatus = $orderdetails->object->status;
            $url = $serverurl;
            $status = '';
            // SANDBOX OR PROD.
            if ($sandbox == true) {
                if ($returnstatus == 31) {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_unigraz');
                } else {
                    // Not Approved.
                    $status = false;
                }
            } else {
                if ($returnstatus == 31) {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_unigraz');
                } else {
                    // Not Approved.
                    $status = false;
                }
            }

            if ($status == 'success') {
                $url = $successurl;
                $success = true;

                // Check if order is existing.

                $checkorder = $DB->get_record('paygw_unigraz_openorders', ['tid' => $tid, 'itemid' => $itemid,
                'userid' => intval($userid)]);

                $existingdata = $DB->get_record('paygw_unigraz', ['unigraz_orderid' => $tid]);

                if (!empty($existingdata)) {
                    return [
                        'url' => $url ?? '',
                        'success' => true,
                        'message' => 'doublechecking payment',
                    ];
                }

                if (empty($checkorder)) {
                    // Purchase already stored.
                    $success = false;
                    $message = get_string('internalerror', 'paygw_unigraz');
                } else {
                    try {
                        $paymentid = payment_helper::save_payment(
                            $payable->get_account_id(),
                            $component,
                            $paymentarea,
                            $itemid,
                            (int) $userid,
                            $amount,
                            $currency,
                            'unigraz'
                        );

                        $record = new \stdClass();
                        $record->paymentid = $paymentid;
                        $record->unigraz_orderid = $tid;

                        $record->paymentbrand = 'unknown';
                        $record->pboriginal = 'unknown';

                        $DB->insert_record('paygw_unigraz', $record);

                        // Set status in open_orders to complete.
                        if (
                            $existingrecord = $DB->get_record(
                                'paygw_unigraz_openorders',
                                ['tid' => $tid]
                            )
                        ) {
                            $existingrecord->status = 3;
                            $DB->update_record('paygw_unigraz_openorders', $existingrecord);

                            // We trigger the payment_completed event.
                            $context = context_system::instance();
                            $event = payment_completed::create([
                                'context' => $context,
                                'userid' => $userid,
                                'other' => [
                                    'orderid' => $tid,
                                ],
                            ]);
                            $event->trigger();
                        }

                        // We trigger the payment_successful event.
                        $context = context_system::instance();
                        $event = payment_successful::create(['context' => $context, 'other' => [
                            'message' => $message,
                            'orderid' => $tid,
                        ]]);
                        $event->trigger();

                        // The order is delivered.
                        // If the delivery was not successful, we trigger an event.
                        if (!payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $userid)) {
                            $context = context_system::instance();
                            $event = delivery_error::create(['context' => $context, 'other' => [
                                'message' => $message,
                                'orderid' => $tid,
                            ]]);
                            $event->trigger();
                        }

                        // Delete transaction after its been delivered.
                    } catch (\Exception $e) {
                        debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                        $success = false;
                        $message = get_string('internalerror', 'paygw_unigraz');
                    }
                }
            } else {
                $success = false;
                $message = get_string('payment_error', 'paygw_unigraz') . " " . var_export($orderdetails);
            }
        } else {
            // Could not capture authorization!
            $success = false;
            $message = get_string('cannotfetchorderdatails', 'paygw_unigraz') . " " . var_export($orderdetails);

            // We need to transform the success url to a "no success url".
            $url = str_replace('success=1', 'success=0', $successurl);
        }

        // If there is no success, we trigger this event.
        if (!$success) {
            // We trigger the payment_successful event.
            $context = context_system::instance();
            $event = payment_error::create(['context' => $context, 'other' => [
                'message' => $message,
                'orderid' => $tid,
                'itemid' => $itemid,
                'component' => $component,
                'paymentarea' => $paymentarea]]);
            $event->trigger();
        }

        return [
            'url' => $url ?? '',
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Redirect URL.'),
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW, 'Message (usually the error message).'),
        ]);
    }
}
