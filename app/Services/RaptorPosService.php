<?php

namespace App\Services;

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RaptorPosService
{
    private string $apiUrl;
    private string $jwtKey;
    private string $codeOutlet;
    private string $cdept;
    private string $groups;
    private string $nationality;
    private string $valuetaxser;
    private string $location;

    public function __construct()
    {
        $this->apiUrl      = config('raptor.api_url', 'http://test-api.probussystem.net:5000/bills');
        $this->jwtKey      = config('raptor.jwt_key', 'raptor');
        $this->codeOutlet  = config('raptor.code_outlet', 'RST');
        $this->cdept       = config('raptor.cdept', 'FB');
        $this->groups      = config('raptor.groups', 'FB');
        $this->nationality = config('raptor.nationality', 'LOCAL');
        $this->valuetaxser = config('raptor.valuetaxser', 'Y');
        $this->location    = config('raptor.location', '01');
    }

    /**
     * Push a paid (or voided) transaction to Raptor POS API.
     */
    public function pushTransaction(Transaction $transaction, bool $isVoid = false): bool
    {
        if (! config('raptor.enabled', false)) {
            return true;
        }

        try {
            $payload = $this->buildPayload($transaction, $isVoid);
            $token   = $this->makeJwt($payload);

            $ch = curl_init($this->apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::error('RaptorPOS cURL error: ' . $error, ['transaction_id' => $transaction->id]);
                return false;
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                Log::warning('RaptorPOS HTTP ' . $httpCode, [
                    'transaction_id' => $transaction->id,
                    'response'       => $response,
                ]);
                return false;
            }

            Log::info('RaptorPOS pushed transaction', [
                'transaction_id' => $transaction->id,
                'invoice_no'     => $transaction->invoice_no,
                'http_code'      => $httpCode,
            ]);

            return true;

        } catch (\Throwable $e) {
            Log::error('RaptorPOS push failed: ' . $e->getMessage(), [
                'transaction_id' => $transaction->id,
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);
            return false;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function buildPayload(Transaction $transaction, bool $isVoid): array
    {
        $transaction->load(['sell_lines.product']);

        $shift     = $this->resolveShift();
        $paidBill  = $this->buildPaidBill($transaction->invoice_no);

        $details = [];
        foreach ($transaction->sell_lines as $line) {
            $productName = optional($line->product)->name ?? 'Item';
            $unitPrice   = (float) ($line->unit_price_before_discount ?? $line->unit_price);
            $qty         = (float) $line->quantity;
            $itemTax     = (float) ($line->item_tax ?? 0);
            $lineTotal   = round($unitPrice * $qty, 2);
            $lineTax     = round($itemTax * $qty, 2);

            $details[] = [
                'product_name' => $productName,
                'product_code' => optional($line->product)->sku ?? '',
                'quantity'     => $qty,
                'unit_price'   => $unitPrice,
                'amount'       => $lineTotal,
                'tax_amount'   => $lineTax,
                'cdept'        => $this->cdept,
                'groups'       => $this->groups,
            ];
        }

        return [
            'orderNumber'  => $transaction->invoice_no,
            'paidbill'     => $paidBill,
            'code_outlet'  => $this->codeOutlet,
            'shift'        => $shift,
            'pax'          => (int) ($transaction->pax ?? 1),
            'nationality'  => $this->nationality,
            'valuetaxser'  => $this->valuetaxser,
            'location'     => $this->location,
            'g_net'        => (float) $transaction->total_before_tax,
            'g_tax'        => (float) $transaction->tax_amount,
            'g_gross'      => (float) $transaction->final_total,
            'is_void'      => $isVoid ? 1 : 0,
            'detail'       => $details,
        ];
    }

    /**
     * Build "paidbill" in format A25XXXXXXXXX — outlet prefix + YYMMDDNNNNN.
     * Uses invoice_no as the sequential part (strips non-numeric suffix).
     */
    private function buildPaidBill(string $invoiceNo): string
    {
        $prefix = strtoupper(substr($this->codeOutlet, 0, 1));
        $yy     = date('y');
        // Extract numeric portion of invoice_no for the counter
        $numeric = preg_replace('/\D/', '', $invoiceNo);
        $seq     = str_pad(substr($numeric, -7), 7, '0', STR_PAD_LEFT);

        return $prefix . $yy . $seq;
    }

    private function resolveShift(): string
    {
        $hour = (int) date('H');
        if ($hour >= 6 && $hour < 14) return '1';
        if ($hour >= 14 && $hour < 22) return '2';
        return '3';
    }

    /**
     * Produce a HS256 JWT without any external library.
     */
    private function makeJwt(array $payload): string
    {
        $header    = $this->b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body      = $this->b64url(json_encode($payload));
        $signature = $this->b64url(hash_hmac('sha256', "$header.$body", $this->jwtKey, true));

        return "$header.$body.$signature";
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
