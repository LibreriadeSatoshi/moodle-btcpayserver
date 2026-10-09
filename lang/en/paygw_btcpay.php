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
 * Language strings for the BTCPay Server payment gateway.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'BTCPay Server';
$string['pluginname_desc'] = 'Accept Bitcoin and Lightning payments via BTCPay Server';

$string['gatewayname'] = 'BTCPay Server';
$string['gatewaydescription'] = 'Pay with Bitcoin or Lightning via BTCPay Server.';

$string['btcpay_base_url'] = 'BTCPay Server Base URL';
$string['btcpay_base_url_desc'] = 'The base URL of your BTCPay Server instance (must be HTTPS).';
$string['btcpay_base_url_help'] = 'The base URL of your BTCPay Server instance (must be HTTPS).';
$string['btcpay_store_id'] = 'Store ID';
$string['btcpay_store_id_desc'] = 'The store ID from your BTCPay Server.';
$string['btcpay_store_id_help'] = 'The store ID from your BTCPay Server.';
$string['btcpay_api_key'] = 'API Key';
$string['btcpay_api_key_desc'] = 'API key with permissions to create invoices.';
$string['btcpay_api_key_help'] = 'API key with permissions to create invoices.';
$string['btcpay_webhook_secret'] = 'Webhook Secret';
$string['btcpay_webhook_secret_desc'] = 'Secret configured in BTCPay Server webhook settings.';
$string['btcpay_webhook_secret_help'] = 'Secret configured in BTCPay Server webhook settings.';
$string['btcpay_fulfill_status'] = 'Fulfill Order Status';
$string['btcpay_fulfill_status_desc'] = 'Invoice status that triggers order fulfillment.';
$string['btcpay_fulfill_status_help'] = 'Invoice status that triggers order fulfillment.';
$string['btcpay_invoice_expiration_minutes'] = 'Invoice Expiration (minutes)';
$string['btcpay_invoice_expiration_minutes_desc'] = 'How long invoices remain valid (1-1440 minutes).';
$string['btcpay_invoice_expiration_minutes_help'] = 'How long invoices remain valid (1-1440 minutes).';

$string['fulfill_status_settled'] = 'Settled';
$string['fulfill_status_processing'] = 'Processing';

$string['error_invalid_config'] = 'Invalid gateway configuration.';
$string['error_invoice_creation_failed'] = 'Failed to create invoice. Please try again.';
$string['error_payment_not_found'] = 'Payment not found.';
$string['error_signature_invalid'] = 'Invalid webhook signature.';

$string['payment_received'] = 'Payment received! Access granted.';
$string['payment_processing'] = 'Payment processing...';
$string['payment_pending'] = 'Payment pending...';
$string['payment_expired'] = 'Payment expired. Please try again.';
$string['payment_received_processing'] = 'Payment received, processing...';
$string['payment_webhook_processing'] = 'Your payment has been received. Enrollment will be processed automatically via webhook. If you are not enrolled within a few minutes, please contact support.';

$string['returntocourse'] = 'Return to course';

$string['privacy:metadata:paygw_btcpay_txn'] = 'Invoice records linking Moodle payments to BTCPay Server.';
$string['privacy:metadata:btcpay'] = 'Payment references, amounts and currencies are sent to the configured BTCPay Server to create invoices.';
$string['privacy:metadata:userid'] = 'The user who made the payment.';
$string['privacy:metadata:paymentid'] = 'The Moodle payment reference.';
$string['privacy:metadata:component'] = 'The component receiving the payment.';
$string['privacy:metadata:paymentarea'] = 'The payment area within the receiving component.';
$string['privacy:metadata:itemid'] = 'The item purchased with the payment.';
$string['privacy:metadata:amount'] = 'The invoice amount.';
$string['privacy:metadata:currency'] = 'The invoice currency.';
$string['privacy:metadata:btcpay_invoice_id'] = 'The BTCPay Server invoice identifier.';
$string['privacy:metadata:btcpay_checkout_url'] = 'The URL of the invoice checkout page.';
$string['privacy:metadata:btcpay_status'] = 'The status of the invoice.';
$string['privacy:metadata:delivered'] = 'Whether the purchased item has been delivered.';
$string['privacy:metadata:timecreated'] = 'When the invoice record was created.';
$string['privacy:metadata:timemodified'] = 'When the invoice record was last updated.';
