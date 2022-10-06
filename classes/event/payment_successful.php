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
 * The payment_successful event.
 *
 * @package mod_booking
 * @copyright 2014 David Bogner, http://www.edulabs.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace paygw_unigraz\event;

/**
 * The payment_successful event class.
 *
 * @property-read array $other { Extra information about event. }
 * @since Moodle 3.11
 * @copyright 2022 Georg Maißer <georg.maisser@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_successful extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    public static function get_name() {
        return get_string('payment_successful', 'paygw_unigraz');
    }

    public function get_description() {
        return "The user with the id {$this->userid} has successfully paid for " . $this->other['orderid'];
    }

    public function get_url() {
        return new \moodle_url('/payment/gateway/unigraz/checkout.php');
    }
}
