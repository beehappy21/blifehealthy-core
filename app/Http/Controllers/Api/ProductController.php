<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * helper: get approved merchant by owner user
     */
    private function myApprovedMerchantOrFail($user)
    {
        $m = DB::table('merchants')->where('owner_user_id', $user->id)->first();
        if (!$m) return [null, response()->json(['ok' => false, 'message' => 'merchant not found'], 404)];
        if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'message' => 'merchant not approved'], 403)];
        return [$m, null];
    }

    /**
     * POST /api/merchant/products
     * Create product (merchant only)
     */
    public function create(Request $request)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'detail' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,active,inactive'],
        ]);

        $productId = DB::table('products')->insertGetId([
            'merchant_id' => $merchant->id,
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'detail' => $data['detail'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'product_id' => $productId]);
    }

    /**
     * PATCH /api/merchant/products/{id}
     * Update product (merchant only)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'detail' => ['nullable', 'string'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'category_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,active,inactive'],
        ]);

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        DB::table('products')->where('id', (int)$id)->update([
            'name' => $data['name'] ?? $product->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
            'detail' => array_key_exists('detail', $data) ? $data['detail'] : ($product->detail ?? null),
            'video_url' => array_key_exists('video_url', $data) ? $data['video_url'] : ($product->video_url ?? null),
            'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $product->category_id,
            'status' => $data['status'] ?? $product->status,
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/merchant/products
     * List my products (merchant only)
     */
    public function listMine(Request $request)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $rows = DB::table('products')
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->select(['id','merchant_id','category_id','name','description','status','created_at','updated_at'])
            ->get();

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    /**
     * POST /api/merchant/products/{id}/variants
     * Add variant (merchant only)
     */
    public function addVariant(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:80'],
            'options' => ['required', 'array'], // {color,size,pack_qty}
            'price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer'],
            'weight_g' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        // sku unique check (product_variants.sku เป็น unique)
        $skuExists = DB::table('product_variants')->where('sku', $data['sku'])->exists();
        if ($skuExists) {
            return response()->json(['ok' => false, 'message' => 'SKU already exists'], 422);
        }

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => (int)$id,
            'sku' => $data['sku'],
            'option_json' => json_encode($data['options'], JSON_UNESCAPED_UNICODE),
            'price' => $data['price'],
            'weight_g' => $data['weight_g'] ?? 0,
            'stock_qty' => $data['stock_qty'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'variant_id' => $variantId]);
    }

    /**
     * PATCH /api/merchant/variants/{id}/stock
     * Update stock (and optionally price) for a variant (merchant only)
     */
    public function updateStock(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $variant = DB::table('product_variants')->where('id', (int)$id)->first();
        if (!$variant) return response()->json(['ok' => false, 'message' => 'variant not found'], 404);

        $product = DB::table('products')->where('id', (int)$variant->product_id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        $data = $request->validate([
            'stock_qty' => ['required', 'integer'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $update = [
            'stock_qty' => $data['stock_qty'],
            'updated_at' => now(),
        ];
        if (array_key_exists('price', $data)) $update['price'] = $data['price'];
        if (array_key_exists('status', $data)) $update['status'] = $data['status'];

        DB::table('product_variants')->where('id', (int)$id)->update($update);

        return response()->json(['ok' => true]);
    }

    /**
     * GET /api/shop/{merchantId}/products
     * Public list products of a merchant shop
     */
    public function listShop(Request $request, $merchantId)
    {
        $rows = DB::table('products')
            ->join('merchants', 'merchants.id', '=', 'products.merchant_id')
            ->where('products.merchant_id', (int)$merchantId)
            ->where('merchants.status', 'approved')
            ->where('products.status', 'active')
            ->orderByDesc('products.id')
            ->select([
                'products.id',
                'products.merchant_id',
                'products.category_id',
                'products.name',
                'products.description',
                'products.video_url',
                'products.status',
                'products.created_at',
            ])
            ->get();

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    /**
     * GET /api/products/{id}
     * Public product detail (include variants + images)
     */
    public function detail(Request $request, $id)
    {
        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'not found'], 404);

        $merchant = DB::table('merchants')->where('id', (int)$product->merchant_id)->first();
        if (!$merchant || $merchant->status !== 'approved') {
            return response()->json(['ok' => false, 'message' => 'shop not available'], 404);
        }

        // variants
        $variants = DB::table('product_variants')
            ->where('product_id', (int)$id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(function ($v) {
                $v->options = json_decode($v->option_json ?? '{}', true);
                unset($v->option_json);
                return $v;
            });

        // images
        $images = DB::table('product_images')
            ->where('product_id', (int)$id)
            ->orderBy('sort')
            ->get();

        return response()->json([
            'ok' => true,
            'product' => $product,
            'variants' => $variants,
            'images' => $images,
        ]);
    }
}