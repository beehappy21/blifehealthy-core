<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private function myApprovedMerchantOrFail($user)
    {
        $m = DB::table('merchants')->where('owner_user_id', $user->id)->first();
        if (!$m) return [null, response()->json(['ok' => false, 'message' => 'merchant not found'], 404)];
        if ($m->status !== 'approved') return [null, response()->json(['ok' => false, 'message' => 'merchant not approved'], 403)];
        return [$m, null];
    }

    private function calcFee(float $price, string $feeType, float $feeValue): array
    {
        $feeAmount = 0.0;

        if ($feeType === 'percent') {
            $feeAmount = round($price * ($feeValue / 100), 2);
        } elseif ($feeType === 'fixed') {
            $feeAmount = round($feeValue, 2);
        }

        if ($feeAmount > $price) $feeAmount = $price;

        return [
            'platform_fee_amount' => $feeAmount,
            'merchant_net_amount' => round($price - $feeAmount, 2),
        ];
    }

    // POST /api/merchant/products
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

    // PATCH /api/merchant/products/{id}
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

    // GET /api/merchant/products
    public function listMine(Request $request)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $rows = DB::table('products')
            ->where('merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->select(['id','merchant_id','category_id','name','description','video_url','status','created_at','updated_at'])
            ->get();

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    // POST /api/merchant/products/{id}/variants
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
            'options' => ['required', 'array'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'integer'],
            'weight_g' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
            'platform_fee_type'  => ['nullable', 'in:percent,fixed'],
            'platform_fee_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!empty($data['platform_fee_value']) && empty($data['platform_fee_type'])) {
            return response()->json(['ok' => false, 'message' => 'platform_fee_type is required when platform_fee_value is provided'], 422);
        }

        $skuExists = DB::table('product_variants')->where('sku', $data['sku'])->exists();
        if ($skuExists) {
            return response()->json(['ok' => false, 'message' => 'SKU already exists'], 422);
        }

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => (int)$id,
            'sku' => $data['sku'],
            'option_json' => json_encode($data['options'], JSON_UNESCAPED_UNICODE),
            'price' => $data['price'],
            'platform_fee_type'  => $data['platform_fee_type'] ?? null,
            'platform_fee_value' => $data['platform_fee_value'] ?? null,
            'weight_g' => $data['weight_g'] ?? 0,
            'stock_qty' => $data['stock_qty'] ?? 0,
            'status' => $data['status'] ?? 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'variant_id' => $variantId]);
    }

    // PATCH /api/merchant/variants/{id}/stock
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
            'platform_fee_type'  => ['nullable', 'in:percent,fixed'],
            'platform_fee_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (array_key_exists('platform_fee_value', $data) && !array_key_exists('platform_fee_type', $data)) {
            return response()->json(['ok' => false, 'message' => 'platform_fee_type is required when platform_fee_value is provided'], 422);
        }

        $update = [
            'stock_qty' => $data['stock_qty'],
            'updated_at' => now(),
        ];
        if (array_key_exists('price', $data)) $update['price'] = $data['price'];
        if (array_key_exists('status', $data)) $update['status'] = $data['status'];
        if (array_key_exists('platform_fee_type', $data)) $update['platform_fee_type'] = $data['platform_fee_type'];
        if (array_key_exists('platform_fee_value', $data)) $update['platform_fee_value'] = $data['platform_fee_value'];

        DB::table('product_variants')->where('id', (int)$id)->update($update);

        return response()->json(['ok' => true]);
    }

    // GET /api/shop/{merchantId}/products  (with cover_url)
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
                'products.detail',
                'products.video_url',
                'products.status',
                'products.created_at',
            ])
            ->get();

        $productIds = $rows->pluck('id')->all();
        $covers = [];

        if (!empty($productIds)) {
            $imgRows = DB::table('product_images')
                ->whereIn('product_id', $productIds)
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['product_id', 'url']);

            foreach ($imgRows as $img) {
                if (!isset($covers[$img->product_id])) {
                    $covers[$img->product_id] = $img->url;
                }
            }
        }

        $rows = $rows->map(function ($p) use ($covers) {
            $p->cover_url = $covers[$p->id] ?? null;
            return $p;
        });

        return response()->json(['ok' => true, 'items' => $rows]);
    }

    // GET /api/products/{id}
    public function detail(Request $request, $id)
    {
        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'not found'], 404);

        $merchant = DB::table('merchants')->where('id', (int)$product->merchant_id)->first();
        if (!$merchant || $merchant->status !== 'approved') {
            return response()->json(['ok' => false, 'message' => 'shop not available'], 404);
        }

        $defaultType = $merchant->platform_fee_type ?? 'percent';
        $defaultValue = (float)($merchant->platform_fee_value ?? 0);

        $variants = DB::table('product_variants')
            ->where('product_id', (int)$id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(function ($v) use ($defaultType, $defaultValue) {
                $v->options = json_decode($v->option_json ?? '{}', true);
                unset($v->option_json);

                $feeType = $v->platform_fee_type ?? $defaultType;
                $feeValue = $v->platform_fee_value !== null ? (float)$v->platform_fee_value : $defaultValue;

                $price = (float)$v->price;
                $calc = $this->calcFee($price, $feeType, $feeValue);

                $v->platform_fee_type_used = $feeType;
                $v->platform_fee_value_used = $feeValue;
                $v->platform_fee_amount = $calc['platform_fee_amount'];
                $v->merchant_net_amount = $calc['merchant_net_amount'];

                return $v;
            });

        $images = DB::table('product_images')
            ->where('product_id', (int)$id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // images_general + images_by_variant
        $images_general = $images->filter(function ($img) {
            return empty($img->variant_id);
        })->values();

        $images_by_variant = [];
        foreach ($images as $img) {
            if (!empty($img->variant_id)) {
                $key = (string)$img->variant_id;
                if (!isset($images_by_variant[$key])) $images_by_variant[$key] = [];
                $images_by_variant[$key][] = $img;
            }
        }

        return response()->json([
            'ok' => true,
            'product' => $product,
            'variants' => $variants,
            'images' => $images,
            'images_general' => $images_general,
            'images_by_variant' => $images_by_variant,
        ]);
    }

    // POST /api/merchant/products/{id}/images
    public function addImage(Request $request, $id)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $product = DB::table('products')->where('id', (int)$id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        $data = $request->validate([
            'url' => ['required', 'string', 'max:1000'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        if (!empty($data['variant_id'])) {
            $v = DB::table('product_variants')
                ->where('id', (int)$data['variant_id'])
                ->where('product_id', (int)$id)
                ->first();
            if (!$v) return response()->json(['ok' => false, 'message' => 'variant not found for this product'], 422);
        }

        $imageId = DB::table('product_images')->insertGetId([
            'product_id' => (int)$id,
            'variant_id' => $data['variant_id'] ?? null,
            'url' => $data['url'],
            'sort' => $data['sort'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'image_id' => $imageId]);
    }

    // PATCH /api/merchant/images/{imageId}
    public function updateImage(Request $request, $imageId)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $data = $request->validate([
            'sort' => ['nullable', 'integer', 'min:0'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $img = DB::table('product_images')->where('id', (int)$imageId)->first();
        if (!$img) return response()->json(['ok' => false, 'message' => 'image not found'], 404);

        $product = DB::table('products')->where('id', (int)$img->product_id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        if (array_key_exists('variant_id', $data) && !empty($data['variant_id'])) {
            $v = DB::table('product_variants')
                ->where('id', (int)$data['variant_id'])
                ->where('product_id', (int)$img->product_id)
                ->first();
            if (!$v) return response()->json(['ok' => false, 'message' => 'variant not found for this product'], 422);
        }

        $update = ['updated_at' => now()];
        if (array_key_exists('sort', $data)) $update['sort'] = $data['sort'];
        if (array_key_exists('variant_id', $data)) $update['variant_id'] = $data['variant_id'] ?? null;

        DB::table('product_images')->where('id', (int)$imageId)->update($update);

        return response()->json(['ok' => true]);
    }

    // DELETE /api/merchant/images/{imageId}
    public function deleteImage(Request $request, $imageId)
    {
        $user = $request->user();
        [$merchant, $err] = $this->myApprovedMerchantOrFail($user);
        if ($err) return $err;

        $img = DB::table('product_images')->where('id', (int)$imageId)->first();
        if (!$img) return response()->json(['ok' => false, 'message' => 'image not found'], 404);

        $product = DB::table('products')->where('id', (int)$img->product_id)->first();
        if (!$product) return response()->json(['ok' => false, 'message' => 'product not found'], 404);
        if ((int)$product->merchant_id !== (int)$merchant->id) return response()->json(['ok' => false, 'message' => 'forbidden'], 403);

        DB::table('product_images')->where('id', (int)$imageId)->delete();

        return response()->json(['ok' => true]);
    }
}