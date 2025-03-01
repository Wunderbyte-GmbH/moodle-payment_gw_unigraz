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
 * Class paygw_unigraz_generator for generation of dummy data
 *
 * @package paygw_unigraz
 * @category test
 * @copyright 2024 Andrii Semenets
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class paygw_unigraz_generator extends testing_module_generator {
    /**
     *
     * @var int keep track of how many booking options have been created.
     */
    protected $paymentgateway = 0;

    /**
     * To be called from data reset code only, do not use in tests.
     *
     * @return void
     */
    public function reset() {
        $this->paymentgateway = 0;

        parent::reset();
    }

    /**
     * Function to create a dummy PayOne payment gateway configuration.
     *
     * @param array|stdClass $record
     * @return stdClass the payment gateway object
     */
    public function create_configuration($record = null) {
        global $DB;

        $record = (array) $record;

        if (!isset($record['accountid'])) {
            throw new coding_exception('accountid must be present in phpunit_util::create_option() $record');
        }

        if (!isset($record['gateway'])) {
            throw new coding_exception('gateway must be present in phpunit_util::create_option() $record');
        }

        if (!isset($record['enabled'])) {
            throw new coding_exception('enabled must be present in phpunit_util::create_option() $record');
        }

        $this->paymentgateway++;

        $record = (object) $record;
        $record->timecreated = time();
        $record->timemodified = time();

        $config = new stdClass();
        $config->environment = 'sandbox';
        // Load the credentials from Github.
        $config->brandname = getenv('BRANDNAME');
        $config->clientid = getenv('CLIENTID');
        $config->secret = getenv('PAYONE_SECRET');

        $record->config = json_encode($config);

        $record->id = $DB->insert_record('payment_gateways', $record);

        return $record;
    }

    /**
     * Function, to get userid
     * @param string $username
     * @return int
     */
    private function get_user(string $username) {
        global $DB;

        if (!$id = $DB->get_field('user', 'id', ['username' => $username])) {
            throw new Exception('The specified user with username "' . $username . '" does not exist');
        }
        return $id;
    }
}
