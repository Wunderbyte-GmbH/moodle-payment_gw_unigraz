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
 * Behat data generator for paygw_unigraz.
 *
 * @package   paygw_unigraz
 * @category  test
 * @copyright 2024 Andrii Semenets
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_paygw_unigraz_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'configuration' => [
                'datagenerator' => 'configuration',
                'required' => ['account', 'gateway', 'enabled'],
                'switchids' => ['account' => 'accountid'],
            ],
        ];
    }

    /**
     * Get the payment account ID using an activity idnumber.
     *
     * @param string $accountname
     * @return int The payment account id
     */
    protected function get_account_id(string $accountname): int {
        global $DB;

        if (!$id = $DB->get_field('payment_accounts', 'id', ['name' => $accountname])) {
            throw new Exception('The specified payment account with name "' . $accountname . '" does not exist');
        }
        return $id;
    }
}
