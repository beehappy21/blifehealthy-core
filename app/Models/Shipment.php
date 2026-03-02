<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'tracking_no',
        'fee',
        'status',
        'label_url',
        'payload_json',
    ];
}
