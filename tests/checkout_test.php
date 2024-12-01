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
 * Testing checkout in payment gateway paygw_unigraz
 *
 * @package    paygw_unigraz
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_unigraz;

use local_shopping_cart\local\entities\cartitem;
use local_shopping_cart\shopping_cart;
use local_shopping_cart\shopping_cart_history;
use local_shopping_cart\local\cartstore;
use local_shopping_cart\output\shoppingcart_history_list;
use local_shopping_cart\local\pricemodifier\modifiers\checkout;
use paygw_unigraz\external\get_config_for_js;
use stdClass;

/**
 * Testing checkout in payment gateway paygw_unigraz
 *
 * @package    paygw_unigraz
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class checkout_test extends \advanced_testcase {

    /** @var \core_payment\account account */
    private $account;

    /**
     * Setup function.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('country', 'AT');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $this->account = $generator->create_payment_account(['name' => 'UniGraz1']);

        $record = new stdClass();
        $record->accountid = $this->account->get('id');
        $record->gateway = 'unigraz';
        $record->enabled = 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $config = new stdClass();
        $config->environment = 'sandbox';
        // Load the credentials from Github.
        $config->brandname = getenv('BRANDNAME');
        $config->clientid = getenv('CLIENTID');
        $config->secret = getenv('PAYONE_SECRET');

        $record->config = json_encode($config);

        $accountgateway1 = \core_payment\helper::save_payment_gateway($record);
    }
    /**
     * Test rule on paygw_unigraz checkput process.
     *
     * @covers \paygw_unigraz\gateway
     * @covers \local_shopping_cart\payment\service_provider::get_payable()
     * @throws \coding_exception
     */
    public function test_checkout(): void {
        global $DB;

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setAdminUser();
        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('UniGraz1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');

        // Create local_shopping_cart's item.
        $now = time();
        $canceluntil = strtotime('+14 days', $now);
        $serviceperiodestart = $now;
        $serviceperiodeend = strtotime('+100 days', $now);
        // This cartitem data is not really used (except for itemid), because data is fetched from service_provider.
        // See \local_shopping_cart\shopping_cart\service_provider for real values.
        $itemobj = new cartitem(
            1,
            '1',
            10.00,
            get_config('local_shopping_cart', 'globalcurrency') ?? 'EUR',
            'local_shopping_cart',
            'main',
            '',
            '',
            $canceluntil,
            $serviceperiodestart,
            $serviceperiodeend,
            'A'
        );
        $item1 = $itemobj->as_array();
        $this->setUser($student1->id);
        // Clean shopping cart.
        shopping_cart::delete_all_items_from_cart($student1->id);
        // Put an item into the cart.
        shopping_cart::buy_for_user($student1->id);
        $cartstore = cartstore::instance($student1->id);
        $data1 = shopping_cart::add_item_to_cart('local_shopping_cart', 'main', $item1['itemid'], $student1->id);
        $data2 = $cartstore->get_data();
        $data3 = checkout::prepare_checkout($data2);
        // Get payable info.
        $payable = \local_shopping_cart\payment\service_provider::get_payable('main', $data3['identifier']);
        // Validate payable.
        $this->assertEquals($this->account->get('id'), $payable->get_account_id());
        $this->assertEquals(10, $payable->get_amount());
        $this->assertEquals('EUR', $payable->get_currency());
        // Validate JS params for gateway modal dialog.
        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data3['identifier']);
        $this->assertEquals(10, $res['cost']);
        $this->assertEquals('EUR', $res['currency']);
        $this->assertEquals('sandbox', $res['environment']);
        $this->assertStringContainsString('https://payment.preprod.payone.com/hostedcheckout/PaymentMethods/', $res['rooturl']);
    }

    /**
     * Test for enrol fee.
     *
     * @covers \paygw_unigraz\gateway
     * @covers \enrol_fee\payment\service_provider::get_payable()
     */
    public function test_get_payable(): void {
        global $DB;
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $feeplugin = enrol_get_plugin('fee');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $data = [
            'courseid' => $course->id,
            'customint1' => $this->account->get('id'),
            'cost' => 250,
            'currency' => 'USD',
            'roleid' => $studentrole->id,
        ];
        $id = $feeplugin->add_instance($course, $data);
        // Validate payable.
        $payable = \enrol_fee\payment\service_provider::get_payable('fee', $id);
        $this->assertEquals($this->account->get('id'), $payable->get_account_id());
        $this->assertEquals(250, $payable->get_amount());
        $this->assertEquals('USD', $payable->get_currency());
    }
}
