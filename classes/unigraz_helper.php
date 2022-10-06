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
 * Contains helper class to work with unigraz REST API.
 *
 * @package    core_payment
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_unigraz;

use curl;
use core_payment\helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class unigraz_helper {

    /**
     * @var string environment
     */
    private $environment;

    /**
     * @var string Client ID
     */
    private $clientid;

    /**
     * @var string unigraz Secret
     */
    private $secret;

    /**
     * @var string base URL
     */
    private $baseurl;

    /**
     * helper constructor.
     *
     * @param string $clientid The client id.
     * @param string $secret unigraz secret.
     * @param bool $sandbox Whether we are working with the sandbox environment or not.
     */
    public function __construct( $environment, string $clientid, string $secret ) {

        $this->environment = $environment;
        $this->clientid = $clientid;
        $this->secret = $secret;

        if ($environment == 'sandbox') {
            $this->baseurl = 'https://stagebezahlung.uni-graz.at/v/1/shop/' . $secret;
        } else {
            $this->baseurl = 'https://bezahlung.uni-graz.at/v/1/shop/' . $secret;
        }
    }

    /**
     * Returns List of available prodivers for this gateway.
     *
     * @return string
     */
    public function get_provider() {
        $function = '/provider';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . $function);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $responsedata;
    }

    /**
     * Checks out a cart in order to process the payment.
     *
     * @param  int $cartid Cart Id
     * @param  int $providerid I.E Creditcard, Klarna etc.
     * @param  string $redirecturl The url to which the gateway redirects after payment
     * @return string The url that can be called for the redirect
     */
    public function checkout_cart($cartid, $providerid, $redirecturl) {
        $obj = (object) [
            "provider_id" => $providerid,
            "user_variable" => "localIdentifierCheckout",
            "user_email" => "shopnotify@uni-graz.at",
            "email" => "usernotify@uni-graz.at",
            "gender" => 1,
            "first_name" => "Max",
            "last_name" => "Mustermann",
            "address" => "Universitätsstraße 1",
            "zip" => "8010",
            "city" => "Graz",
            "country" => "AT",
            "ip" => "0.0.0.0/0",
            "user_url_success" => $redirecturl,
            "user_url_failure" => $redirecturl,
            "user_url_cancel" => $redirecturl,
            "user_url_pending" => $redirecturl,
            "user_url_timeout" => $redirecturl,
            "user_url_notify" => $redirecturl
        ];
        $data = json_encode($obj);
        $headers = array
        (
                'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $this->baseurl . '/cart' . '/' . $cartid . '/checkout' );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        $result = curl_exec($ch );
        curl_close( $ch );
        $obj = json_decode($result);
        return $obj->object->url_instant;
    }

    /**
     * Checks the Payment status for a given cartid
     *
     * @param  int $cartid
     * @return object|null Formatted API response.
     */
    public function check_status($cartid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseurl . '/cart' . '/'  . $cartid . '/checkout');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responsedata);
    }


    /**
     * Creates a checkout with the Provider given an array of items
     *
     * @param  array $items Array of items to be bought
     * @return string $result Unformatted API result
     */
    public function create_checkout($items) {

        $articles = [];
        foreach ($items as $item) {
            $sku = explode(' - ', $item->itemname);

            $singlearcticle = (object) [
                "sku" => $sku[0],
                "label" => $sku[1],
                "count" => 1,
                "price_net" => $item->price,
                "price_gross" => $item->price,
                'tax_mark' => 'A0',
                "vat_percent" => 0,
                "vat_amount" => 0,
                "spurious_exempt" => false,
                "performance_begin" => "2016-04-19",
                "performance_end" => "2016-04-19",
                "account" => "441000",
                "internal_order" => "AEP707000002",
                "user_variable" => "localIdentifierArticle"
            ];
            array_push($articles, $singlearcticle);
        }

        $obj = (object) [
            "user_variable" => "localIdentifierCart",
            "article" => $articles

        ];

        $data = json_encode($obj);

        $headers = array
        (
                'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $this->baseurl . '/cart');
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
        $result = curl_exec($ch );
        curl_close( $ch );
        return $result;

    }
}
