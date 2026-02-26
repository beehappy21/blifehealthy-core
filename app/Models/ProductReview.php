<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    protected $table = 'product_reviews';

    // ✅ ให้รับได้ทุกคอลัมน์ (กันปัญหา fillable ตัด merchant_id)
    protected $guarded = [];
}