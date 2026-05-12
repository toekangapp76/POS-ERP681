<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\Restaurant\ResTable;
use App\Transaction;
use App\TransactionSellLine;
use App\Variation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelfOrderController extends Controller
{
    /**
     * Show self-order menu page for a table.
     */
    public function index(string $token)
    {
        $table = ResTable::where('qr_token', $token)->firstOrFail();

        $business_id  = $table->business_id;
        $location_id  = $table->location_id;

        $categories = $this->getMenuCategories($business_id, $location_id);

        // Check for existing open order on this table
        $existing_transaction = Transaction::where('business_id', $business_id)
            ->where('res_table_id', $table->id)
            ->where('type', 'sell')
            ->whereIn('status', ['draft', 'ordered'])
            ->where('payment_status', '!=', 'paid')
            ->latest()
            ->first();

        return view('self_order.index', compact(
            'table', 'categories', 'existing_transaction', 'token'
        ));
    }

    /**
     * Return menu products as JSON (for AJAX reload).
     */
    public function menu(string $token, Request $request)
    {
        $table = ResTable::where('qr_token', $token)->firstOrFail();
        $business_id = $table->business_id;
        $location_id = $table->location_id;
        $categories  = $this->getMenuCategories($business_id, $location_id);

        return response()->json($categories);
    }

    /**
     * Place or append an order.
     */
    public function placeOrder(string $token, Request $request)
    {
        $request->validate([
            'items'        => 'required|array|min:1',
            'items.*.variation_id' => 'required|integer',
            'items.*.quantity'     => 'required|numeric|min:0.5',
            'items.*.note'         => 'nullable|string|max:255',
            'pax'          => 'nullable|integer|min:1',
        ]);

        $table = ResTable::where('qr_token', $token)->firstOrFail();
        $business_id = $table->business_id;
        $location_id = $table->location_id;

        DB::beginTransaction();
        try {
            // Get or create draft transaction for this table
            $transaction = Transaction::where('business_id', $business_id)
                ->where('res_table_id', $table->id)
                ->where('type', 'sell')
                ->whereIn('status', ['draft', 'ordered'])
                ->where('payment_status', '!=', 'paid')
                ->latest()
                ->first();

            if (! $transaction) {
                $transaction = Transaction::create([
                    'business_id'    => $business_id,
                    'location_id'    => $location_id,
                    'res_table_id'   => $table->id,
                    'type'           => 'sell',
                    'status'         => 'draft',
                    'payment_status' => 'due',
                    'created_by'     => $this->getSystemUserId($business_id),
                    'contact_id'     => $this->getDefaultContactId($business_id),
                    'transaction_date' => now(),
                    'invoice_no'     => $this->generateInvoiceNo($business_id, $location_id),
                    'pax'            => $request->input('pax', 1),
                    'sub_total'      => 0,
                    'total_before_tax' => 0,
                    'tax_amount'     => 0,
                    'final_total'    => 0,
                    'is_self_order'  => 1,
                ]);
            } else {
                // Update pax only if provided and transaction is new-ish
                if ($request->filled('pax')) {
                    $transaction->pax = $request->input('pax');
                    $transaction->save();
                }
            }

            $totalAdded = 0;
            foreach ($request->input('items') as $item) {
                $variation = Variation::with('product')->find($item['variation_id']);
                if (! $variation) continue;

                $qty       = (float) $item['quantity'];
                $price     = (float) $variation->sell_price_inc_tax;
                $priceExcl = (float) $variation->default_sell_price;
                $itemTax   = round($price - $priceExcl, 4);
                $lineTotal = round($price * $qty, 4);

                // Find existing line for same variation to merge
                $existingLine = TransactionSellLine::where('transaction_id', $transaction->id)
                    ->where('variation_id', $variation->id)
                    ->where('sell_line_note', $item['note'] ?? '')
                    ->first();

                if ($existingLine) {
                    $existingLine->quantity += $qty;
                    $existingLine->save();
                } else {
                    TransactionSellLine::create([
                        'transaction_id'  => $transaction->id,
                        'product_id'      => $variation->product_id,
                        'variation_id'    => $variation->id,
                        'quantity'        => $qty,
                        'unit_price'      => $priceExcl,
                        'unit_price_inc_tax' => $price,
                        'item_tax'        => $itemTax,
                        'tax_id'          => $variation->product->tax_id ?? null,
                        'sell_line_note'       => $item['note'] ?? '',
                        'line_discount_type'   => 'fixed',
                        'line_discount_amount' => 0,
                        'unit_price_before_discount' => $priceExcl,
                        'lot_no_line_id'  => null,
                    ]);
                }

                $totalAdded += $lineTotal;
            }

            // Recalculate totals
            $this->recalculateTransaction($transaction);

            DB::commit();

            return response()->json([
                'success'        => true,
                'transaction_id' => $transaction->id,
                'message'        => 'Pesanan berhasil dikirim!',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('SelfOrder placeOrder error: ' . $e->getMessage(), [
                'token' => $token,
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Gagal mengirim pesanan.'], 500);
        }
    }

    /**
     * Get current order status for this table.
     */
    public function orderStatus(string $token)
    {
        $table = ResTable::where('qr_token', $token)->firstOrFail();

        $transaction = Transaction::with('sell_lines.product', 'sell_lines.variations')
            ->where('business_id', $table->business_id)
            ->where('res_table_id', $table->id)
            ->where('type', 'sell')
            ->whereIn('status', ['draft', 'ordered'])
            ->where('payment_status', '!=', 'paid')
            ->latest()
            ->first();

        if (! $transaction) {
            return response()->json(['has_order' => false]);
        }

        $lines = $transaction->sell_lines->map(fn($l) => [
            'name'      => optional($l->product)->name,
            'quantity'  => $l->quantity,
            'price'     => $l->unit_price_inc_tax,
            'note'      => $l->sell_line_note,
            'subtotal'  => round($l->unit_price_inc_tax * $l->quantity, 2),
        ]);

        return response()->json([
            'has_order'      => true,
            'transaction_id' => $transaction->id,
            'invoice_no'     => $transaction->invoice_no,
            'final_total'    => $transaction->final_total,
            'items'          => $lines,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function getMenuCategories(int $business_id, int $location_id): \Illuminate\Support\Collection
    {
        $products = Product::with(['variations', 'category'])
            ->where('business_id', $business_id)
            ->where('status', 'active')
            ->where('enable_stock', '!=', 2)
            ->get();

        return $products
            ->groupBy(fn($p) => optional($p->category)->name ?? 'Lainnya')
            ->map(fn($items, $catName) => [
                'category' => $catName,
                'items'    => $items->map(fn($p) => [
                    'id'           => $p->id,
                    'name'         => $p->name,
                    'image_url'    => $p->image_url,
                    'description'  => $p->product_description ?? '',
                    'variations'   => $p->variations->map(fn($v) => [
                        'id'    => $v->id,
                        'name'  => $v->name,
                        'price' => (float) $v->sell_price_inc_tax,
                    ])->values(),
                ])->values(),
            ])->values();
    }

    private function recalculateTransaction(Transaction $transaction): void
    {
        $lines = TransactionSellLine::where('transaction_id', $transaction->id)->get();

        $subtotal = $lines->sum(fn($l) => $l->unit_price_inc_tax * $l->quantity);
        $taxTotal = $lines->sum(fn($l) => $l->item_tax * $l->quantity);
        $netTotal = $subtotal - $taxTotal;

        $transaction->sub_total        = round($subtotal, 4);
        $transaction->total_before_tax = round($netTotal, 4);
        $transaction->tax_amount       = round($taxTotal, 4);
        $transaction->final_total      = round($subtotal, 4);
        $transaction->save();
    }

    private function getSystemUserId(int $business_id): int
    {
        return DB::table('users')
            ->where('business_id', $business_id)
            ->orderBy('id')
            ->value('id') ?? 1;
    }

    private function getDefaultContactId(int $business_id): int
    {
        return DB::table('contacts')
            ->where('business_id', $business_id)
            ->where('type', 'customer')
            ->orderBy('id')
            ->value('id') ?? 1;
    }

    private function generateInvoiceNo(int $business_id, int $location_id): string
    {
        $prefix = 'SO-';
        $last   = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('location_id', $location_id)
            ->where('type', 'sell')
            ->where('invoice_no', 'like', $prefix . '%')
            ->max('invoice_no');

        $num = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($num, 6, '0', STR_PAD_LEFT);
    }
}
