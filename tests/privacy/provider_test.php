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

use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * BTCPay transaction data participates in payment privacy requests.
 *
 * @package paygw_btcpay
 * @copyright 2026 Libreria de Satoshi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** Create a payment with a corresponding invoice record.
     * @param int $userid User ID.
     * @param string $invoiceid Invoice ID.
     * @return \stdClass Payment record.
     */
    private function create_transaction(int $userid, string $invoiceid): \stdClass {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account();
        $paymentid = $generator->create_payment([
            'accountid' => $account->get('id'), 'userid' => $userid,
            'amount' => 20, 'currency' => 'USD', 'gateway' => 'btcpay',
        ]);
        $payment = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);
        $DB->insert_record('paygw_btcpay_txn', (object) [
            'paymentid' => $paymentid, 'userid' => $userid,
            'component' => $payment->component, 'paymentarea' => $payment->paymentarea, 'itemid' => $payment->itemid,
            'amount' => '20', 'currency' => 'USD', 'btcpay_invoice_id' => $invoiceid,
            'btcpay_checkout_url' => 'https://pay.example/i/' . $invoiceid,
            'btcpay_status' => 'Settled', 'delivered' => 1,
            'timecreated' => 1700000000, 'timemodified' => 1700000100,
        ]);
        return $payment;
    }

    /** Core payment exports include the user's invoice without another user's data. */
    public function test_export_payment_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $payment = $this->create_transaction($user->id, 'invoice-one');
        $other = $this->getDataGenerator()->create_user();
        $this->create_transaction($other->id, 'invoice-two');
        $context = \context_system::instance();
        \core_payment\privacy\provider::export_payment_data_for_user_in_context(
            $context, [], $user->id, $payment->component, $payment->paymentarea, $payment->itemid
        );
        $data = writer::with_context($context)->get_data([
            get_string('payments', 'payment'), 'payment-' . $payment->id, get_string('gatewayname', 'paygw_btcpay'),
        ]);
        $this->assertCount(1, $data->invoices);
        $data = $data->invoices[0];
        $this->assertSame('invoice-one', $data->btcpay_invoice_id);
        $this->assertSame('Settled', $data->btcpay_status);
        $this->assertSame('https://pay.example/i/invoice-one', $data->btcpay_checkout_url);
        $this->assertSame('USD', $data->currency);
        $this->assertEquals(1, $data->delivered);
    }

    /** Retried payments export every invoice associated with the payment. */
    public function test_export_multiple_invoices_for_one_payment(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $payment = $this->create_transaction($user->id, 'invoice-one');
        $invoice = $DB->get_record('paygw_btcpay_txn', ['paymentid' => $payment->id], '*', MUST_EXIST);
        unset($invoice->id);
        $invoice->btcpay_invoice_id = 'invoice-retry';
        $DB->insert_record('paygw_btcpay_txn', $invoice);
        $context = \context_system::instance();
        provider::export_payment_data($context, ['payment'], $payment);
        $data = writer::with_context($context)->get_data(['payment', get_string('gatewayname', 'paygw_btcpay')]);
        $this->assertSame(['invoice-one', 'invoice-retry'], array_column($data->invoices, 'btcpay_invoice_id'));
    }

    /** Deleting payments removes only their associated invoice records. */
    public function test_delete_payment_data(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $payment = $this->create_transaction($user->id, 'invoice-one');
        $other = $this->getDataGenerator()->create_user();
        $retained = $this->create_transaction($other->id, 'invoice-two');
        \core_payment\privacy\provider::delete_data_for_payment_sql(
            'SELECT id FROM {payments} WHERE userid = :userid', ['userid' => $user->id]
        );
        $this->assertFalse($DB->record_exists('paygw_btcpay_txn', ['paymentid' => $payment->id]));
        $this->assertTrue($DB->record_exists('paygw_btcpay_txn', ['paymentid' => $retained->id]));
    }

    /** Payments without an invoice should not produce empty gateway exports. */
    public function test_export_without_invoice(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        provider::export_payment_data($context, ['payment'], (object) ['id' => 0]);
        $this->assertFalse(writer::with_context($context)->has_any_data());
    }
}
