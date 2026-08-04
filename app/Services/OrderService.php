<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\StoreProduct;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private InvoiceParserService $parser,
        private CloudinaryService $cloudinary
    ) {}

    public function parseInvoice(string $rawText, ?UploadedFile $image = null): array
    {
        $parsedData = $this->parser->parse($rawText);

        $imageUrl = null;
        if ($image) {
            $imageUrl = $this->cloudinary->upload($image, 'invoices');
        }

        return [
            'invoice_image_url' => $imageUrl,
            'ocr_raw_text' => $rawText,
            'parsed_data' => $parsedData,
        ];
    }

    public function create(User $user, Store $store, array $data): Order
    {
        $order = Order::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'invoice_number' => $data['invoice_number'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'supplier_name' => $data['supplier_name'] ?? null,
            'subtotal' => $data['subtotal'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'total' => $data['total'] ?? 0,
            'currency' => $data['currency'] ?? 'COP',
            'invoice_image_url' => $data['invoice_image_url'] ?? null,
            'ocr_raw_text' => $data['ocr_raw_text'] ?? null,
            'ocr_confidence' => $data['ocr_confidence'] ?? null,
            'status' => 'pending',
            'type' => $data['type'] ?? 'proveedor',
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $item['total_price'] ?? 0,
                    'matched_product_id' => $item['matched_product_id'] ?? null,
                ]);
            }
        }

        $this->matchItemsToProducts($order, $store);

        $order->load(['items.matchedProduct', 'payments']);

        return $order;
    }

    public function getUserOrders(User $user, ?int $storeId = null)
    {
        $query = Order::where('user_id', $user->id)->with(['items', 'payments']);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->latest()->paginate(15);
    }

    public function getOrder(User $user, int $orderId): ?Order
    {
        return Order::where('user_id', $user->id)
            ->with(['items.matchedProduct', 'payments.user'])
            ->find($orderId);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update([
            'invoice_number' => $data['invoice_number'] ?? $order->invoice_number,
            'invoice_date' => $data['invoice_date'] ?? $order->invoice_date,
            'supplier_name' => $data['supplier_name'] ?? $order->supplier_name,
            'subtotal' => $data['subtotal'] ?? $order->subtotal,
            'tax' => $data['tax'] ?? $order->tax,
            'total' => $data['total'] ?? $order->total,
            'currency' => $data['currency'] ?? $order->currency,
            'type' => $data['type'] ?? $order->type,
            'notes' => $data['notes'] ?? $order->notes,
        ]);

        if (! empty($data['items']) && is_array($data['items'])) {
            $order->items()->delete();

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $item['total_price'] ?? 0,
                    'matched_product_id' => $item['matched_product_id'] ?? null,
                ]);
            }
        }

        $this->matchItemsToProducts($order, $order->store);

        $order->load(['items.matchedProduct', 'payments']);

        return $order;
    }

    public function delete(Order $order): bool
    {
        if ($order->invoice_image_url && str_starts_with($order->invoice_image_url, 'http')) {
            $this->cloudinary->delete($order->invoice_image_url);
        }

        return $order->delete();
    }

    public function verify(Order $order): Order
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'verified']);

            if ($order->type === 'proveedor') {
                foreach ($order->items as $item) {
                    if ($item->matched_product_id) {
                        $item->matchedProduct()->increment('stock_quantity', $item->quantity);
                    }
                }
            }
        });

        return $order->fresh(['items.matchedProduct']);
    }

    public function getPendingItems(Store $store): array
    {
        $items = OrderItem::whereNull('matched_product_id')
            ->whereHas('order', function ($q) use ($store) {
                $q->where('store_id', $store->id)->where('type', 'proveedor');
            })
            ->with(['order:id,invoice_date,created_at'])
            ->orderByDesc('created_at')
            ->get();

        $groups = [];

        foreach ($items as $item) {
            $key = $this->normalizeName($item->product_name);

            if ($key === '') {
                continue;
            }

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'product_name' => $item->product_name,
                    'total_quantity' => 0,
                    'order_count' => 0,
                    'latest_unit_price' => null,
                    'suggested_sale_price' => null,
                ];
            }

            $groups[$key]['total_quantity'] += $item->quantity;
            $groups[$key]['order_count']++;

            if ($groups[$key]['latest_unit_price'] === null) {
                $groups[$key]['latest_unit_price'] = (float) $item->unit_price;
                $groups[$key]['suggested_sale_price'] = round((float) $item->unit_price * 1.4, 2);
            }
        }

        return array_values($groups);
    }

    public function linkPendingItems(Store $store, string $productName, int $productId): int
    {
        $product = StoreProduct::where('id', $productId)->where('store_id', $store->id)->first();

        if (! $product) {
            throw new \InvalidArgumentException('Product not found in store');
        }

        $targetName = $this->normalizeName($productName);

        return DB::transaction(function () use ($store, $productId, $product, $targetName): int {
            $items = OrderItem::whereNull('matched_product_id')
                ->whereHas('order', function ($q) use ($store) {
                    $q->where('store_id', $store->id)->where('type', 'proveedor');
                })
                ->with('order:id,status')
                ->get()
                ->filter(function (OrderItem $item) use ($targetName) {
                    return $this->normalizeName($item->product_name) === $targetName;
                });

            foreach ($items as $item) {
                $item->update(['matched_product_id' => $productId]);

                if ($item->order->status === 'verified') {
                    $product->increment('stock_quantity', $item->quantity);
                }
            }

            return $items->count();
        });
    }

    private function matchItemsToProducts(Order $order, Store $store): void
    {
        if ($order->type !== 'proveedor') {
            return;
        }

        $products = StoreProduct::where('store_id', $store->id)->get(['id', 'name']);

        $index = [];
        foreach ($products as $product) {
            $tokens = $this->nameTokens($product->name);
            if ($tokens !== []) {
                $index[$product->id] = $tokens;
            }
        }

        foreach ($order->items as $item) {
            if ($item->matched_product_id) {
                continue;
            }

            $itemTokens = $this->nameTokens($item->product_name);

            if ($itemTokens === []) {
                continue;
            }

            $bestId = null;
            $bestScore = 0.0;

            foreach ($index as $productId => $tokens) {
                $score = $this->nameSimilarity($itemTokens, $tokens);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestId = $productId;
                }
            }

            if ($bestScore >= 0.75 && $bestId !== null) {
                $item->update(['matched_product_id' => $bestId]);
            }
        }
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = strtr($name, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $name = preg_replace('/[^a-z0-9 ]/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    private function nameTokens(string $name): array
    {
        $normalized = $this->normalizeName($name);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_unique(array_filter(explode(' ', $normalized))));
    }

    private function nameSimilarity(array $tokensA, array $tokensB): float
    {
        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($tokensA, $tokensB));

        return (2 * $intersection) / (count($tokensA) + count($tokensB));
    }
}
