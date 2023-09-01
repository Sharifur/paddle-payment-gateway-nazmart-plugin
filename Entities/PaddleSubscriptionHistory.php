<?php

namespace Modules\PaddlePaymentGateway\Entities;

use App\Models\PaymentLogs;
use App\Models\PricePlan;
use Illuminate\Database\Eloquent\Model;

class PaddleSubscriptionHistory extends Model
{
    protected $table = 'paddle_subscription_history';
    protected $fillable = ['order_id','subscription_id','user_id',"checkout_id","subscription_payment_id","subscription_plan_id","paddle_user_id",'status','paddle_order_id'];

    protected static function newFactory()
    {
        return \Modules\NewsLetter\Database\factories\PaddleSubscriptionHistory::new();
    }
    protected $casts = [
        'status' => 'integer'
    ];

    public function order_details(){
        return $this->belongsTo(PaymentLogs::class,"order_id");
    }


}
