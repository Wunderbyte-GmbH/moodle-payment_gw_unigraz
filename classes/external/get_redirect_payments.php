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
use paygw_unigraz\unigraz_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');


class get_redirect_payments extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'tid' => new external_value(PARAM_TEXT, 'unique transaction id'),
            'cartid' => new external_value(PARAM_INT, 'unique transaction id'),
            'providerid' => new external_value(PARAM_INT, 'provider id'),
        ]);
    }

    public static function execute($component, $paymentarea, $itemid, $tid, $cartid, $providerid) {
        GLOBAL $CFG;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'tid' => $tid,
            'cartid' => $cartid,
            'providerid' => $providerid
        ]);
        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'unigraz');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('unigraz');
        $environment = $config['environment'];
        $price = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $root = $CFG->wwwroot;
        $secret = $config['secret'];
        $entityid = $config['clientid'];
        $ughelper = new unigraz_helper($environment, $entityid, $secret);

        $redirecturl = $root . "/payment/gateway/unigraz/checkout.php?customer=" .
        $config['clientid'] . "&itemid=" . $itemid . "&component=" . $component .
        "&paymentarea=" . $paymentarea . "&tid=" . $tid . "&ischeckstatus=true" . "&cartid=" . $cartid;

        $url = $ughelper->checkout_cart($cartid, $providerid, $redirecturl);;

        return [
            'url' => $url,
        ];

    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns() {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Redirect URL.')
        ]);
    }
}
