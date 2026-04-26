<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Orderitems extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
    ];


    public function order()
    {
        return $this->belongsTo(Orders::class);
    }


    public function product()
    {
        return $this->belongsTo(Products::class);
    }
}
