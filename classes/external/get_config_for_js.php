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



use core_payment\helper;
use core_user;
use DateTime;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use paygw_unigraz\task\check_status;
use stdClass;
use paygw_unigraz\unigraz_helper;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class get_config_for_js extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }


    /**
     * Returns the config values required by the unigraz JavaScript SDK.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string[]
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        GLOBAL $CFG, $USER, $SESSION, $DB;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'unigraz');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('unigraz');

        $language = $USER->lang;
        $secret = $config['secret'];
        $root = $CFG->wwwroot;
        $environment = $config['environment'];

        // Get all items from shoppingcart.
        $items = shopping_cart_history::return_data_via_identifier($itemid);

        $ughelper = new unigraz_helper($environment, $secret);
        $provider = $ughelper->get_provider();
        $checkout = $ughelper->create_checkout($items);
        $checkoutobj = json_decode($checkout);
        $cartid = $checkoutobj->object->id;

        $record = new stdClass();
        $record->tid = $cartid;
        $record->itemid = $itemid;
        $record->userid = intval($USER->id);
        $record->status = 0;

        $DB->insert_record('paygw_unigraz_openorders', $record);

        // Create Task to check status after 30 minutes.
        $userid = $USER->id;
        $now = time();
        $nextruntime = strtotime('+30 min', $now);

        $taskdata = new stdClass();
        $taskdata->itemid = $itemid;
        $taskdata->customer = $config['clientid'];
        $taskdata->component = $component;
        $taskdata->paymentarea = $paymentarea;
        $taskdata->tid = $cartid;
        $taskdata->ischeckstatus = true;
        $taskdata->cartid = $cartid;

        $checkstatustask = new check_status();
        $checkstatustask->set_userid($userid);
        $checkstatustask->set_next_run_time($nextruntime);
        $checkstatustask->set_custom_data($taskdata);
        \core\task\manager::reschedule_or_queue_adhoc_task($checkstatustask);

        return [
            'clientid' => $config['clientid'],
            'brandname' => $config['brandname'],
            'cost' => helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge),
            'currency' => $payable->get_currency(),
            'rooturl' => $root,
            'environment' => $environment,
            'language' => $language,
            'providerobject' => $provider,
            'cartid' => $cartid
        ];
    }


    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'clientid' => new external_value(PARAM_TEXT, 'unigraz client ID'),
            'brandname' => new external_value(PARAM_TEXT, 'Brand name'),
            'cost' => new external_value(PARAM_FLOAT, 'Cost with gateway surcharge'),
            'currency' => new external_value(PARAM_TEXT, 'Currency'),
            'rooturl' => new external_value(PARAM_TEXT, 'Moodle Root URI'),
            'environment' => new external_value(PARAM_TEXT, 'Prod or Sandbox'),
            'language' => new external_value(PARAM_TEXT, 'language'),
            'providerobject' => new external_value(PARAM_TEXT, 'providers'),
            'cartid' => new external_value(PARAM_INT, 'unique transaction id'),
        ]);
    }
}
