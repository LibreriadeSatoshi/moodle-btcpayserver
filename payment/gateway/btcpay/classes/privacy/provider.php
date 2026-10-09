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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace paygw_btcpay\privacy;

use core_payment\privacy\paygw_provider;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Invoice data is exported and deleted through the core payment subsystem.
 *
 * @package paygw_btcpay
 * @copyright 2026 Libreria de Satoshi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\subsystem_provider,
    paygw_provider {

    /**
     * Describe invoice data stored locally and sent to BTCPay Server.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('paygw_btcpay_txn', [
            'userid' => 'privacy:metadata:userid',
            'paymentid' => 'privacy:metadata:paymentid',
            'component' => 'privacy:metadata:component',
            'paymentarea' => 'privacy:metadata:paymentarea',
            'itemid' => 'privacy:metadata:itemid',
            'amount' => 'privacy:metadata:amount',
            'currency' => 'privacy:metadata:currency',
            'btcpay_invoice_id' => 'privacy:metadata:btcpay_invoice_id',
            'btcpay_checkout_url' => 'privacy:metadata:btcpay_checkout_url',
            'btcpay_status' => 'privacy:metadata:btcpay_status',
            'delivered' => 'privacy:metadata:delivered',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:paygw_btcpay_txn');
        $collection->add_external_location_link('btcpay', [
            'paymentid' => 'privacy:metadata:paymentid',
            'amount' => 'privacy:metadata:amount',
            'currency' => 'privacy:metadata:currency',
        ], 'privacy:metadata:btcpay');
        return $collection;
    }

    /**
     * Export all invoices associated with the requested payment.
     *
     * @param \context $context Payment context.
     * @param array $subcontext Payment export path.
     * @param \stdClass $payment Payment record.
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment) {
        global $DB;
        $records = $DB->get_records('paygw_btcpay_txn', ['paymentid' => $payment->id], 'id');
        if (!$records) {
            return;
        }
        foreach ($records as $record) {
            unset($record->id);
            $record->userid = transform::user($record->userid);
            $record->timecreated = transform::datetime($record->timecreated);
            $record->timemodified = transform::datetime($record->timemodified);
        }
        $subcontext[] = get_string('gatewayname', 'paygw_btcpay');
        writer::with_context($context)->export_data($subcontext, (object) ['invoices' => array_values($records)]);
    }

    /**
     * Delete invoice data for the selected payments.
     *
     * @param string $paymentsql Query selecting payment IDs.
     * @param array $paymentparams Query parameters.
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams) {
        global $DB;
        $DB->delete_records_select('paygw_btcpay_txn', "paymentid IN ({$paymentsql})", $paymentparams);
    }
}
