<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    // POST /api/products/{id}/reviews
    // 1 user ต่อ 1 product: ถ้ามีแล้ว = update
    public function createOrUpdate(Request $request, $id)
    {
        $user = $request->user();

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title'  => ['nullable', 'string', 'max:200'],
            'body'   => ['nullable', 'string', 'max:2000'],
        ]);

        // หา merchant_id จากสินค้า (กัน NOT NULL merchant_id)
        $product = DB::table('products')
            ->where('id', (int)$id)
            ->select(['id', 'merchant_id'])
            ->first();

        if (!$product) {
            return response()->json(['ok' => false, 'message' => 'Product not found'], 404);
        }

        $review = ProductReview::updateOrCreate(
            ['product_id' => (int)$id, 'user_id' => (int)$user->id],
            [
                'merchant_id' => (int)$product->merchant_id,
                'rating'      => (int)$data['rating'],
                'title'       => $data['title'] ?? null,
                'body'        => $data['body'] ?? null,
                'updated_at'  => now(),
            ]
        );

        return response()->json(['ok' => true, 'review' => $review]);
    }

    // GET /api/products/{id}/reviews (public)
    public function list(Request $request, $id)
    {
        $reviews = DB::table('product_reviews')
            ->join('users', 'users.id', '=', 'product_reviews.user_id')
            ->where('product_reviews.product_id', (int)$id)
            ->orderByDesc('product_reviews.id')
            ->select([
                'product_reviews.id',
                'product_reviews.product_id',
                'product_reviews.user_id',
                'users.name as user_name',
                'product_reviews.rating',
                'product_reviews.title',
                'product_reviews.body',

                // ✅ ใช้ชื่อคอลัมน์จริงใน DB: reply
                'product_reviews.reply',
                'product_reviews.replied_at',

                'product_reviews.created_at',
            ])
            ->get();

        return response()->json(['ok' => true, 'items' => $reviews]);
    }

    // POST /api/merchant/reviews/{id}/reply
    // ร้านค้าตอบรีวิว: ต้องเป็นเจ้าของสินค้านั้น (merchant owner)
    public function reply(Request $request, $id)
    {
        $user = $request->user();

        $data = $request->validate([
            // รับชื่อ field จาก client เป็น reply_body ได้เหมือนเดิม
            'reply_body' => ['required', 'string', 'max:2000'],
        ]);

        // หา review + product + ตรวจว่า owner_user_id ของ merchant = user ปัจจุบัน
        $row = DB::table('product_reviews')
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->join('merchants', 'merchants.id', '=', 'products.merchant_id')
            ->where('product_reviews.id', (int)$id)
            ->select([
                'product_reviews.id as review_id',
                'merchants.owner_user_id as owner_user_id',
            ])
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Review not found'], 404);
        }

        if ((int)$row->owner_user_id !== (int)$user->id) {
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        DB::table('product_reviews')
            ->where('id', (int)$id)
            ->update([
                // ✅ ใช้ชื่อคอลัมน์จริงใน DB: reply
                'reply' => $data['reply_body'],

                // ✅ ชื่อคอลัมน์ใน DB ของคุณคือ replied_by_user_id
                'replied_by_user_id' => (int)$user->id,

                'replied_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }
}