<?php

namespace Modules\PaddlePaymentGateway\Entities;

use App\Models\PricePlan;
use Illuminate\Database\Eloquent\Model;

class PaddleProduct extends Model
{
    protected $table = 'paddle_products';
    protected $fillable = ['price_plan_id','product_id'];

    protected static function newFactory()
    {
        return \Modules\NewsLetter\Database\factories\PaddlePaymentGateway::new();
    }

    public function price_plan(){
        return $this->belongsTo(PricePlan::class,"price_plan_id");
    }
}
