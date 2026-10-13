<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_btcpay;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verify that only fully settled invoices grant access.
 *
 * @package paygw_btcpay
 * @copyright 2026 Libreria de Satoshi
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\paygw_btcpay\webhook\handler::class)]
final class handler_test extends \advanced_testcase {
    /** @var \stdClass Transaction under test. */
    private $txn;
    /** @var \context_course Course context. */
    private $context;

    protected function setUp(): void {
        global $DB, $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->libdir . '/filelib.php');
        set_config('enabled', 1, 'paygw_btcpay');
        $generator = $this->getDataGenerator();
        $account = $generator->get_plugin_generator('core_payment')->create_payment_account();
        \core_payment\helper::save_payment_gateway((object) [
            'accountid' => $account->get('id'), 'gateway' => 'btcpay', 'enabled' => 1,
            'config' => json_encode([
                'btcpay_base_url' => 'https://btcpay.example.test', 'btcpay_store_id' => 'store',
                'btcpay_api_key' => 'test-key', 'btcpay_fulfill_status' => 'Settled',
            ]),
        ]);
        $course = $generator->create_course();
        $user = $generator->create_user();
        $this->context = \context_course::instance($course->id);
        $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $itemid = enrol_get_plugin('fee')->add_instance($course, [
            'customint1' => $account->get('id'), 'cost' => 10, 'currency' => 'EUR', 'roleid' => $role->id,
        ]);
        $paymentid = \core_payment\helper::save_payment(
            $account->get('id'), 'enrol_fee', 'fee', $itemid, $user->id, 10, 'EUR', 'btcpay'
        );
        $this->txn = (object) [
            'paymentid' => $paymentid, 'component' => 'enrol_fee', 'paymentarea' => 'fee',
            'itemid' => $itemid, 'userid' => $user->id, 'amount' => '10', 'currency' => 'EUR',
            'btcpay_invoice_id' => 'test-invoice', 'btcpay_checkout_url' => 'https://btcpay.example.test/i/test-invoice',
            'btcpay_status' => 'New', 'delivered' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $this->txn->id = $DB->insert_record('paygw_btcpay_txn', $this->txn);
    }

    /** A confirmed partial payment must not enrol the student. */
    public function test_payment_settled_does_not_enrol(): void {
        webhook\handler::process_webhook(['type' => 'InvoicePaymentSettled', 'invoiceId' => 'test-invoice'], 'test-invoice');
        $this->assert_access(false);
    }

    /** Ignored events must not deliver using an old stored status. */
    public function test_unknown_event_does_not_use_stale_status(): void {
        global $DB;
        $DB->set_field('paygw_btcpay_txn', 'btcpay_status', 'Settled', ['id' => $this->txn->id]);
        webhook\handler::process_webhook(['type' => 'InvoiceReceivedPayment'], 'test-invoice');
        $this->assert_access(false);
    }

    /**
     * Verify settlement against the invoice API.
     *
     * @dataProvider invoice_states
     * @param array $invoice Invoice API response
     * @param bool $deliver Expected access
     */
    #[DataProvider('invoice_states')]
    public function test_settlement_requires_verified_invoice(array $invoice, bool $deliver): void {
        \curl::mock_response(json_encode($invoice));
        webhook\handler::process_webhook(['type' => 'InvoiceSettled', 'invoiceId' => 'test-invoice'], 'test-invoice');
        $this->assert_access($deliver);
    }

    /** Invoice API responses, including malformed and mismatched responses. */
    public static function invoice_states(): array {
        $invoice = ['id' => 'test-invoice', 'status' => 'Settled', 'additionalStatus' => 'None'];
        return [
            'full payment' => [$invoice, true],
            'overpayment' => [array_replace($invoice, ['additionalStatus' => 'PaidOver']), true],
            'partial payment' => [array_replace($invoice, ['status' => 'New', 'additionalStatus' => 'PaidPartial']), false],
            'manually settled partial' => [array_replace($invoice, ['additionalStatus' => 'PaidPartial']), false],
            'late payment' => [array_replace($invoice, ['additionalStatus' => 'PaidLate']), false],
            'unconfirmed' => [array_replace($invoice, ['status' => 'Processing']), false],
            'expired' => [array_replace($invoice, ['status' => 'Expired']), false],
            'unknown additional status' => [array_replace($invoice, ['additionalStatus' => 'Other']), false],
            'missing additional status' => [['id' => 'test-invoice', 'status' => 'Settled'], false],
        ];
    }

    /** An API decoding failure must leave delivery retryable. */
    public function test_verification_failure_does_not_enrol_and_can_retry(): void {
        \curl::mock_response('not-json');
        try {
            webhook\handler::process_webhook(['type' => 'InvoiceSettled'], 'test-invoice');
            $this->fail('Invoice verification failure must propagate for webhook redelivery.');
        } catch (\moodle_exception $e) {
            $this->assert_access(false);
        }
        \curl::mock_response(json_encode(['id' => 'test-invoice', 'status' => 'Settled', 'additionalStatus' => 'None']));
        webhook\handler::process_webhook(['type' => 'InvoiceSettled'], 'test-invoice');
        $this->assert_access(true);
    }

    /** Arbitrary payload status must not override a payment-level event. */
    public function test_payment_event_cannot_override_status(): void {
        webhook\handler::process_webhook([
            'type' => 'InvoicePaymentSettled', 'status' => 'Settled', 'data' => ['status' => 'Settled'],
        ], 'test-invoice');
        $this->assert_access(false);
    }

    /** Existing early-delivery configuration must not bypass settlement verification. */
    public function test_legacy_processing_configuration_does_not_enrol(): void {
        global $DB;
        $gateway = $DB->get_record('payment_gateways', ['gateway' => 'btcpay'], '*', MUST_EXIST);
        $config = json_decode($gateway->config, true);
        $config['btcpay_fulfill_status'] = 'Processing';
        $gateway->config = json_encode($config);
        $DB->update_record('payment_gateways', $gateway);
        webhook\handler::process_webhook(['type' => 'InvoiceProcessing'], 'test-invoice');
        $this->assert_access(false);
    }

    /** A response belonging to another invoice must never grant access. */
    public function test_mismatched_invoice_is_rejected(): void {
        \curl::mock_response(json_encode(['id' => 'other-invoice', 'status' => 'Settled', 'additionalStatus' => 'None']));
        try {
            webhook\handler::process_webhook(['type' => 'InvoiceSettled'], 'test-invoice');
            $this->fail('The fetched invoice must match the saved invoice ID.');
        } catch (\moodle_exception $e) {
            $this->assert_access(false);
        }
    }

    /** Repeated valid delivery must keep one enrolment. */
    public function test_duplicate_settlement_is_idempotent(): void {
        global $DB;
        \curl::mock_response(json_encode(['id' => 'test-invoice', 'status' => 'Settled', 'additionalStatus' => 'None']));
        webhook\handler::process_webhook(['type' => 'InvoiceSettled'], 'test-invoice');
        webhook\handler::process_webhook(['type' => 'InvoiceSettled'], 'test-invoice');
        $this->assert_access(true);
        $this->assertEquals(1, $DB->count_records('user_enrolments', ['userid' => $this->txn->userid]));
    }

    /** Check the actual course entitlement and delivery record. */
    private function assert_access(bool $expected): void {
        global $DB;
        $this->assertSame($expected, is_enrolled($this->context, $this->txn->userid));
        $this->assertEquals((int) $expected, $DB->get_field('paygw_btcpay_txn', 'delivered', ['id' => $this->txn->id]));
    }
}
