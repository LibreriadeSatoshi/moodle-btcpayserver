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
 * Webhook event processor for BTCPay Server.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_btcpay\webhook;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes verified webhook events and delivers orders when appropriate.
 */
class handler {

    /**
     * Process an authenticated event. Exceptions must cause webhook redelivery.
     *
     * @param array $eventdata Parsed JSON webhook payload
     * @param string $invoiceid Invoice ID from payload
     */
    public static function process_webhook(array $eventdata, string $invoiceid): void {
        global $DB;

        $type = $eventdata['type'] ?? null;
        $statuses = [
            'InvoiceCreated' => 'New',
            'InvoiceProcessing' => 'Processing',
            'InvoiceExpired' => 'Expired',
            'InvoiceInvalid' => 'Invalid',
        ];
        $reviewevents = [
            'InvoiceReceivedPayment',
            'InvoicePaymentSettled',
            'InvoicePaidAfterExpiration',
            'InvoiceExpiredPaidPartial',
        ];
        // Payment-level events inspect exceptions only; they cannot authorize delivery.
        if (!is_string($type) ||
                ($type !== 'InvoiceSettled' && !isset($statuses[$type]) && !in_array($type, $reviewevents, true))) {
            return;
        }

        $factory = \core\lock\lock_config::get_lock_factory('paygw_btcpay');
        $lock = $factory->get_lock('invoice:' . $invoiceid, 10);
        if (!$lock) {
            throw new \moodle_exception('error_processing_failed', 'paygw_btcpay');
        }
        try {
            // Read after acquiring the lock so concurrent callbacks cannot both deliver.
            $txn = $DB->get_record('paygw_btcpay_txn', ['btcpay_invoice_id' => $invoiceid]);
            if (!$txn || (int) $txn->delivered === 1) {
                return;
            }
            if (isset($statuses[$type])) {
                // Only a freshly verified settlement can resolve a review decision.
                if ($txn->btcpay_status === 'Review') {
                    return;
                }
                $txn->btcpay_status = $statuses[$type];
                $txn->timemodified = time();
                $DB->update_record('paygw_btcpay_txn', $txn);
                return;
            }

            $config = \core_payment\helper::get_gateway_configuration(
                $txn->component, $txn->paymentarea, $txn->itemid, 'btcpay'
            );
            $client = new \paygw_btcpay\client(
                $config['btcpay_base_url'], $config['btcpay_api_key'], $config['btcpay_store_id']
            );
            $invoice = $client->get_invoice($invoiceid);
            $additionalstatus = $invoice['additionalStatus'] ?? null;
            $fullysettled = ($invoice['status'] ?? null) === 'Settled' &&
                in_array($additionalstatus, ['None', 'PaidOver'], true);
            if (in_array($additionalstatus, ['PaidPartial', 'PaidLate'], true) ||
                    ($type === 'InvoiceSettled' && !$fullysettled)) {
                $txn->btcpay_status = 'Review';
                $txn->timemodified = time();
                $DB->update_record('paygw_btcpay_txn', $txn);
                error_log('paygw_btcpay: transaction ' . (int) $txn->id . ' requires manual review; delivery withheld.');
                return;
            }
            // Even an eligible API response cannot turn a payment event into invoice settlement.
            if ($type !== 'InvoiceSettled') {
                return;
            }

            $transaction = $DB->start_delegated_transaction();
            try {
                if (!\core_payment\helper::deliver_order(
                    $txn->component, $txn->paymentarea, (int) $txn->itemid, (int) $txn->paymentid, (int) $txn->userid
                )) {
                    throw new \moodle_exception('error_processing_failed', 'paygw_btcpay');
                }
                $txn->btcpay_status = 'Settled';
                $txn->delivered = 1;
                $txn->timemodified = time();
                $DB->update_record('paygw_btcpay_txn', $txn);
                $transaction->allow_commit();
            } catch (\Throwable $e) {
                $transaction->rollback($e);
            }
        } finally {
            $lock->release();
        }
    }
}
