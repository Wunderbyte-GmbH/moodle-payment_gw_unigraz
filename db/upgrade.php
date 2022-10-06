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
 * Upgrade script for paygw_unigraz.
 *
 * @package    paygw_unigraz
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool always true
 */
function xmldb_paygw_unigraz_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    // Automatically generated Moodle v3.11.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2022080800) {

        // Define field paymentbrand to be added to paygw_unigraz.
        $table = new xmldb_table('paygw_unigraz');
        $field = new xmldb_field('paymentbrand', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'unigraz_orderid');

        // Conditionally launch add field paymentbrand.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // unigraz savepoint reached.
        upgrade_plugin_savepoint(true, 2022080800, 'paygw', 'unigraz');
    }

    if ($oldversion < 2022080800) {

        // Define field pboriginal to be added to paygw_unigraz.
        $table = new xmldb_table('paygw_unigraz');
        $field = new xmldb_field('pboriginal', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'paymentbrand');

        // Conditionally launch add field pboriginal.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // unigraz savepoint reached.
        upgrade_plugin_savepoint(true, 2022080800, 'paygw', 'unigraz');
    }
    return true;
}
