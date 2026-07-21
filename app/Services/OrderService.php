<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

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
            'notes' => $data['notes'] ?? null,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
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

        $order->load('items');

        return $order;
    }

    public function getUserOrders(User $user, ?int $storeId = null)
    {
        $query = Order::where('user_id', $user->id)->with('items');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->latest()->paginate(15);
    }

    public function getOrder(User $user, int $orderId): ?Order
    {
        return Order::where('user_id', $user->id)
            ->with('items')
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
            'notes' => $data['notes'] ?? $order->notes,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
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

        $order->load('items');

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
        $order->update(['status' => 'verified']);
        return $order;
    }
}
