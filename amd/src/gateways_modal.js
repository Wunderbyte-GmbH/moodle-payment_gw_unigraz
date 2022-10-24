/* eslint-disable max-len */
/* eslint-disable no-unused-vars */
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
 * This module is responsible for unigraz content in the gateways modal.
 *
 * @module     paygw_unigraz/gateway_modal
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {exception as displayException} from 'core/notification';
import * as Repository from './repository';
import Templates from 'core/templates';
import Truncate from 'core/truncate';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {
    get_string as getString
} from 'core/str';

/**
 * Creates and shows a modal that contains a placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async () => {
    const modal = await ModalFactory.create({
        body: await Templates.render('paygw_unigraz/unigraz_button_placeholder', {})
    });
    modal.show();
    return modal;
};


/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Promise.all([
            showModalWithPlaceholder(),
            Repository.getConfigForJs(component, paymentArea, itemId),
        ])
        .then(([modal, unigrazConfig]) => {
            return Promise.all([
                modal,
                unigrazConfig,
            ]);
        })
        .then(([modal, unigrazConfig]) => {

            const providersjson = JSON.parse(unigrazConfig.providerobject);
            const optionsel = document.createElement('div');

            const context = {
                listofproviders: providersjson.object,
                cartid: unigrazConfig.cartid,
                component,
                paymentarea: paymentArea,
                itemid: itemId,
            };


            Templates.renderForPromise('paygw_unigraz/paymentoptions', context).then(({html, js}) => {
                    Templates.appendNodeContents(optionsel, html, js);
                    modal.setBody(optionsel);
                    return true;
                }).catch();

            return '';

        }).then(x => {
            const promise = new Promise(resolve => {
                window.addEventListener('onbeforeunload', (e) => {
                    promise.resolve();
                });
            });
            return promise;
        });
};