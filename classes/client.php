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
 * BTCPay Server Greenfield API HTTP client.
 *
 * @package    paygw_btcpay
 * @copyright  2025 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_btcpay;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP client for BTCPay Server Greenfield API v1.
 */
class client {

    /** @var string Base URL (HTTPS) */
    protected $baseurl;

    /** @var string API key */
    protected $apikey;

    /** @var string Store ID */
    protected $storeid;

    /** @var int HTTP timeout in seconds */
    protected $timeout = 15;

    /**
     * Constructor.
     *
     * @param string $baseurl BTCPay Server base URL (must be HTTPS)
     * @param string $apikey API key
     * @param string $storeid Store ID
     * @throws \InvalidArgumentException if base URL is invalid
     */
    public function __construct(string $baseurl, string $apikey, string $storeid) {
        $baseurl = rtrim($baseurl, '/');
        if (strpos($baseurl, 'https://') !== 0) {
            throw new \InvalidArgumentException('BTCPay base URL must use HTTPS');
        }
        $this->baseurl = $baseurl;
        $this->apikey = $apikey;
        $this->storeid = $storeid;
    }

    /**
     * Create an invoice via the Greenfield API.
     *
     * @param float $amount Amount in currency units
     * @param string $currency ISO 4217 currency code
     * @param array $metadata Optional metadata (e.g. ['orderId' => 'moodle:123'])
     * @param string|null $redirecturl URL to redirect user after payment
     * @param int $expirationminutes Invoice validity in minutes
     * @return array Invoice object with id, checkoutLink, status
     * @throws \moodle_exception on API or network error
     */
    public function create_invoice(float $amount, string $currency, array $metadata = [],
            ?string $redirecturl = null, int $expirationminutes = 60): array {
        $url = $this->baseurl . '/api/v1/stores/' . $this->storeid . '/invoices';

        $body = [
            'amount' => (string) $amount,
            'currency' => $currency,
            'metadata' => $metadata,
        ];
        $body['checkout'] = [
            'expirationMinutes' => $expirationminutes,
        ];
        if ($redirecturl !== null && $redirecturl !== '') {
            $body['checkout']['redirectURL'] = $redirecturl;
        }

        $response = $this->request('POST', $url, $body);
        if (isset($response['id'], $response['checkoutLink'])) {
            return $response;
        }
        throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null,
            'Invalid API response: missing id or checkoutLink');
    }

    /**
     * Verify webhook signature (HMAC-SHA256, constant-time comparison).
     *
     * @param string $rawbody Raw request body (before JSON parsing)
     * @param string $signatureheader Value of BTCPAY-SIG header (format: sha256=&lt;hex&gt;)
     * @param string $webhooksecret Webhook secret
     * @return bool True if signature is valid
     */
    public static function verify_webhook_signature(string $rawbody, string $signatureheader, string $webhooksecret): bool {
        if ($signatureheader === '' || $webhooksecret === '') {
            return false;
        }
        if (strpos($signatureheader, 'sha256=') !== 0) {
            return false;
        }
        $extracted = substr($signatureheader, 7);
        if (!is_string($extracted) || strlen($extracted) !== 64 || !ctype_xdigit($extracted)) {
            return false;
        }
        $computed = hash_hmac('sha256', $rawbody, $webhooksecret, false);
        return hash_equals($computed, $extracted);
    }

    /**
     * Perform HTTP request.
     *
     * @param string $method GET or POST
     * @param string $url Full URL
     * @param array|null $body JSON body for POST
     * @return array Decoded JSON response
     * @throws \moodle_exception on error
     */
    protected function request(string $method, string $url, ?array $body): array {
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => $this->timeout]);
        $headers = [
            'Authorization: token ' . $this->apikey,
            'Content-Type: application/json',
        ];

        if ($method === 'POST' && $body !== null) {
            $raw = json_encode($body);
            if ($raw === false) {
                throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null, 'JSON encode error');
            }
            $response = $curl->post($url, $raw, ['CURLOPT_HTTPHEADER' => $headers]);
        } else {
            $response = $curl->get($url, [], ['CURLOPT_HTTPHEADER' => $headers]);
        }

        $info = $curl->get_info();
        $errno = $curl->get_errno();
        if ($errno) {
            $err = $curl->get_error();
            throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null, $err ?: 'CURL error');
        }

        $code = (int) ($info['http_code'] ?? 0);
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null,
                'Invalid JSON response');
        }

        if ($code >= 400) {
            $message = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : $response;
            throw new \moodle_exception('error_invoice_creation_failed', 'paygw_btcpay', '', null,
                'HTTP ' . $code . ': ' . (is_string($message) ? $message : json_encode($message)));
        }

        return is_array($decoded) ? $decoded : [];
    }
}
