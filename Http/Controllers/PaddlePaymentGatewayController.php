<?php

namespace Modules\PaddlePaymentGateway\Http\Controllers;

use App\Events\TenantRegisterEvent;
use App\Helpers\Payment\DatabaseUpdateAndMailSend\LandlordPricePlanAndTenantCreate;
use App\Mail\BasicMail;
use App\Mail\PlaceOrder;
use App\Mail\TenantCredentialMail;
use App\Models\PaymentLogs;
use App\Models\PricePlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\PaddlePaymentGateway\Entities\PaddleProduct;
use Modules\PaddlePaymentGateway\Entities\PaddleSubscriptionHistory;
use Xgenious\Paymentgateway\Base\PaymentGatewayHelpers;
use Xgenious\Paymentgateway\Facades\XgPaymentGateway;

class PaddlePaymentGatewayController extends Controller
{
    public function chargeCustomer($args)
    {
        //detect it is coming from which method for which kind of payment
        //dd($args);
        //detect it for landlord or tenant website
        if (in_array($args["payment_type"],["price_plan"]) && $args["payment_for"] === "landlord"){
            //get product id
            $paddleProduct = PaddleProduct::where(["price_plan_id" => $args["payment_details"]["package_id"]])->first();
            //dd($paddleProduct);
//            dd($paddleProduct);
            $returnData = [
                "type" => "success",
                "vendor_id"=> get_static_option("paddle_vendor_id"),
                "product_id" => $paddleProduct?->product_id,
                "title"=> $args["payment_details"]["package_name"],
                "customer_name"=> $args["payment_details"]["name"],
                "customer_email"=> $args["payment_details"]["email"],
                "return_url"=> $args["success_url"],
                "passthrough" => json_encode(["order_id" => XgPaymentGateway::wrapped_id($args["payment_details"]['id']),"payment_type" => $args["payment_type"],'is_subscription' => 0]), //order id and order type, 'is_subscription' => 0 -> means one time payment
            ];
            return response()->json($returnData);
            //return $this->chargeCustomerForLandlordPricePlanPurchase($args);
        }
        return response()->json(['msg' => __("paddle payment gateway is not available for this purpose"),'type' => 'danger']);
//        // all tenant payment process will from here....
//        if (in_array($args["payment_type"],["shop_checkout"]) && $args["payment_for"] === "tenant"){
//            return $this->chargeCustomerForLandlordPricePlanPurchase($args);
//        }
        abort(501,__("paddle payment gateway is not available for this purpose"));
    }

    private function chargeCustomerForLandlordPricePlanPurchase($args)
    {
        return $this->prepareForPaddleSubscriptionPayment($args);
//        return $this->prepareForPaddleOneTimePayment($args);
    }

    private function prepareForPaddleOneTimePayment($args){
        //url https://sandbox-vendors.paddle.com/api/2.0/product/generate_pay_link;
        //dd($args,$args["payment_details"]);
        //build price in USD, then others
        $payment_params = [
            "vendor_id"=> get_static_option("paddle_vendor_id"),
            "vendor_auth_code"=> get_static_option("paddle_vendor_auth_code"),
            "product_id" => get_static_option("paddle_onetime_subscription_product_id"),
            "title"=> $args["payment_details"]["package_name"],
//           "webhook_url"=> route("paddlepaymentgateway.landlord.price.plan.ipn"), //not work on localhost. also it is not required when you provide product id
            "prices"=> [sprintf("%s:%s",get_static_option('site_global_currency',"USD"),$args['total'])], //set price per currency
//           "custom_message"=> "this is test message", // a message shown in checkout page below title
            //"image_url"=> "https://xgenious.com/wp-content/uploads/2022/07/Group-1171274812.png",
            "return_url"=> $args["success_url"]."?checkout={checkout_hash}", //success url with checkout hash, can use it as get ipn as well, but for paddle it prepared to use webhook to track payment
            "quantity_variable"=> 0, //not allow to user to change quantity at checkout page
            "expires"=> Carbon::today()->addDays(2)->format("Y-m-d"), //this url will be expired after 2days
            "customer_name"=> $args["payment_details"]["name"],
            "customer_email"=> $args["payment_details"]["email"],
            "passthrough" => json_encode(["order_id" => XgPaymentGateway::wrapped_id($args["payment_details"]['id']),"payment_type" => $args["payment_type"],'is_subscription' => 0]), //order id and order type, 'is_subscription' => 0 -> means one time payment

//           "recurring_prices"=> "",
//           "trial_days"=> "",
//           "coupon_code"=> "",
//           "discountable"=> "",


//           "affiliates"=> "",
//           "recurring_affiliate_limit"=> "",
//           "customer_country"=> "",
//           "customer_postcode"=> "",
//           "passthrough"=> "",
//           "vat_number"=> "",
//           "vat_company_name"=> "",
//           "vat_street"=> "",
//           "vat_city"=> "",
//           "vat_state"=> "",
//           "vat_country"=> "",
//           "vat_postcode"=> ""
        ];

        $req = Http::post($this->getBaseUrl(
                prefix: "vendors",
                version: "2.0",
                sandbox: true
            )."product/generate_pay_link",$payment_params);
        $checkout_url = $req->object();
        if (property_exists($checkout_url,"success") && $checkout_url->success){
            return redirect()->away($checkout_url?->response?->url);
        }else {
            abort(501,$req->object()?->error?->message);
        }
    }

    private function getBaseUrl($prefix = "vendor",$version="2.0",$sandbox=true){
        //sandbox-
        $sandbox = get_static_option('paddle_test_mode') === 'on';
        $sandbox_prefix = $sandbox ? "sandbox-" : "";
        //todo: check test mode enable or not, return base url based on the mode
        return "https://".$sandbox_prefix.$prefix.".paddle.com/api/".$version."/";
    }

    private function getCredentials(){
        //todo: return vendor api keys credentials
        return [
            "vendor_id" => "12073",
            "vendor_auth_code" => "48e030a524ee0ee15288148f7f63627d476561a8e19c6b5cc2"
        ];
    }


    /**
     * @method landlordPricePlanIpn
     * param $request
     *
     *  this is ipn/callback/webhook method for the payment gateway i am implementing, it will received information form the payment gatewya after successful payment by the user
     *
     * */
    public function landlordPricePlanIpn(Request $request){

        /*
        [2023-05-06 23:15:43] production.INFO: array (
              'event_time' => '2023-05-06 17:15:43',
              'marketing_consent' => '0',
              'p_country' => 'BD',
              'p_coupon' => NULL,
              'p_coupon_savings' => '0.00',
              'p_currency' => 'USD',
              'p_custom_data' => NULL,
              'p_customer_email' => 'qodohyqaco@mailinator.com',
              'p_customer_name' => '11',
              'p_earnings' => '{"12073":"28.0000"}',
              'p_order_id' => '619588', //keep this in database as transactioid like this eg: paddle_order_id_619588
              'p_paddle_fee' => '2.00',
              'p_price' => '30.00',
              'p_product_id' => NULL,
              'p_quantity' => '1',
              'p_sale_gross' => '30.00',
              'p_tax_amount' => '0.00',
              'p_used_price_override' => '1',
              'passthrough' => '{"order_id":"15238846060","payment_type":"price_plan"}',
              'quantity' => '1',
              'p_signature' => 'NbG6NxJ9DtQr9H1rsMr8dJ/RL05HqcuZbnIOFzPV78n+JZzUhyPwSXrpe2NR1EJjhdsoIQtesIomrstkEnVkKMPrL7TveXc9LI9S+Yv4GqysoMF5fwLPATcID9dm+i8P3NkV2zwtuV+ErajI/2Rw0XhAyYTf3fsDAR5g8ukNm+WnJLi2z+57oZp4OiOlPygs/95grcfsoVUSXQf0CEzTwBn/mLRhQj0Ol0lYQQ29xPaHcWELEBpAp+95aY+boX0RRup9k3hkCXtHYMazED1qahDeciNAZQLUwJyc59SNo7xF5lcur2Cuvgtq1SHHtztEgR6s4dd76BwLUFoW+oSTPSUbJFn+uCndW7UwO00xN1xwY1RAJGHkpSoypCfdj4KJ8P8qBF1vqaNGUWp4EOyl3RTYpVgv4aqwFvKEfbx1t4pwecInl0AmdxWYDkNvLKPM0a2T8hUeOt4n4fBfrnF2TPwCFGPAJ6DCxvA25FfED1+ht4bma4+RHbyAGi1AFfcCFuQThaiTkpWJy/JBkrxUsYsZns+hk4lGrfyk33nf9oJQKEcZvRazDi7C1SfR22Ai+oMi8xshmey3f5aWqnSEASqL9AK1+bnRssv7zNgK4XUHY1rDNaQkEEe2ywfX4QLycfNvmrGZa64GxDXejXxi8Fs5T3gVPPWUbBogVytc9zc=',
         )
        */
        // \Log::info($request->all());

        $passthrough = json_decode($request->passthrough,true);
        // Log::info($passthrough);
        $is_subscription = $passthrough['is_subscription'] ?? 0;
        $transaction_id_column = 'order_id';
        $transaction_id = "paddle_order_id_".$request[$transaction_id_column] ?? '';

        $public_key_string = file_get_contents("assets/uploads/public.txt");

        $public_key = openssl_get_publickey($public_key_string);
        $signature = base64_decode($request->p_signature);
        $fields = $request->all();
        unset($fields['p_signature']);
        ksort($fields);
        foreach($fields as $k => $v) {
            if(!in_array(gettype($v), array('object', 'array'))) {
                $fields[$k] = "$v";
            }
        }
        $data = serialize($fields);
        $verification = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

        if($verification == 1) {
            // \Log::info('Yay! Signature is valid!');
            echo 'Yay! Signature is valid!';

            PaddleSubscriptionHistory::updateOrCreate([
                'order_id' => PaymentGatewayHelpers::unwrapped_id($passthrough["order_id"]),
                'subscription_id' => \request()->subscription_id,
            ],[
                'order_id' => PaymentGatewayHelpers::unwrapped_id($passthrough["order_id"]),
                'subscription_id' => \request()->subscription_id,
                'user_id' => "00",
                "checkout_id" =>  \request()->checkout_id,
                "subscription_payment_id" =>  \request()->subscription_payment_id,
                "subscription_plan_id" =>  \request()->subscription_plan_id,
                "paddle_user_id" =>  \request()->paddle_user_id,
                "status" => 1
            ]);

            //todo add more filter to check that it is old payment or renew payment. if it is renew payment then do not cancel the request, check it by paddle subscription id, and plan name of each order, also check the tenant id
            $current_order_details = PaymentLogs::find(PaymentGatewayHelpers::unwrapped_id($passthrough["order_id"]));
            $all_subscription = PaddleSubscriptionHistory::where('order_id',PaymentGatewayHelpers::unwrapped_id($passthrough["order_id"]))->orderBy('id','desc')->get();//->skip(1);

            foreach($all_subscription as $sub){
                //todo send api request to cancel all the subscription
                //todo update status in paddle subscription history table...

                //todo check if it is same tenant_id Payment and tenant subscription id is same or not

                if ($current_order_details->tenant_id === $sub?->order_details?->tenant_id && $sub->subscription_id != \request()->subscription_id){
                    //todo check subscription id is same or not
                    $req = Http::post($this->getBaseUrl(
                            prefix: "vendors",
                            version: "2.0",
                            sandbox: true
                        )."subscription/users_cancel",[
                        "vendor_id"=> get_static_option("paddle_vendor_id"),
                        "vendor_auth_code"=> get_static_option("paddle_vendor_auth_code"),
                        "subscription_id"=> $sub->subscription_id
                    ]);
                    $checkout_url = $req->object();
                    $sub->status = 0;
                    $sub->save();
                }
                //todo check if it is success then change status to 0 of this entity
            }
            //todo: check if this user has an existing subscription, cancell all of them through paddle subscription cancel apis.
            //todo: show list of active subscription in paddle settings page

            $payment_data = [
                "status" => "complete",
                "transaction_id" => $transaction_id,
                "order_id" => PaymentGatewayHelpers::unwrapped_id($passthrough["order_id"])
            ];
            if ($is_subscription == 0){
                $this->runPostPaymentProcessForLandlordPricePlanSuccessPayment($payment_data);
            }

            if ($request->instalments > 1){
                $this->runPostPaymentProcessForLandlordPricePlanSuccessPaymentForRecurringPayment($payment_data);
                //todo make method to run post payment success for subscription payment
                //todo mark the payment complete.. add a payment history... increase expired date
            }else{
                $this->runPostPaymentProcessForLandlordPricePlanSuccessPayment($payment_data);
            }


        }

        // Log::info("paddle payment verified failed...");



    }


    /**
     * @method runPostPaymentProcessForLandlordPricePlanSuccessPayment
     * @param array $payment_data
     * this method will run process for after a successful payment for landlord price plan payment.
     * */
    private function runPostPaymentProcessForLandlordPricePlanSuccessPayment($payment_data)
    {
        //todo: have to check is it initial payment or subscription payment

        /*

        [2023-05-09 18:43:27] production.INFO: array (
  'alert_id' => '4782024',
  'alert_name' => 'subscription_payment_succeeded',
  'balance_currency' => 'USD',
  'balance_earnings' => '568.55',
  'balance_fee' => '30.45',
  'balance_gross' => '599',
  'balance_tax' => '0',
  'checkout_id' => '1440384-chref20430e801e-9710772e3e',
  'country' => 'BD',
  'coupon' => NULL,
  'currency' => 'USD',
  'custom_data' => NULL,
  'customer_name' => '21',
  'earnings' => '568.55',
  'email' => 'dvrobin4@gmail.com',
  'event_time' => '2023-05-09 12:43:26',
  'fee' => '30.45',
  'initial_payment' => '0',
  'instalments' => '3',
  'marketing_consent' => '0',
  'next_bill_date' => '2023-05-10',
  'next_payment_amount' => '599',
  'order_id' => '621267-5045403',
  'passthrough' => '{"order_id":"917001277384","payment_type":"price_plan"}',
  'payment_method' => 'card',
  'payment_tax' => '0',
  'plan_name' => 'Advance Plan',
  'quantity' => '1',
  'receipt_url' => 'http://sandbox-my.paddle.com/receipt/621267-5045403/1440384-chref20430e801e-9710772e3e',
  'sale_gross' => '599',
  'status' => 'active',
  'subscription_id' => '475697',
  'subscription_payment_id' => '5045403',
  'subscription_plan_id' => '50938',
  'unit_price' => '599.00',
  'user_id' => '485125',
  'p_signature' => 'l2tdMAStpZl15qDQTQef90b0ANYZqW923nZ9Uku0c8lYtkCfRzs22cR/obvn+Vbk0i195H3EmgP/TegahEyq9pFjFxl+L5uVbFXJwVL1YjK3Mp8WMPhSk1dFV4SrB8bV565esRHN3DfmjyjHNrGK6MkqNM26E/OZIw5EBmwX6/Mt+Y3d3lbvOkVA/V77U8Biy26LB4U5Ar0LlijEVmm6UydPbs+QJtTP2ggzC+xeYf4kvUX4Z8sFuxkEUBy1qV7s3XtHEOTdVu/zyJeWW3HCXZEOnscwgzXAN0Eh0bukQhElbebIGwjeaYKc3UPNWfADW2TfQuwYtniCi/MHdOqskKAcDHPy+39KbCQRpQBV03e3eozH7DN51cePWkEuNI7yggn/GgLvEivnHBRTxUy7oJvQyGfSV4z3+RIOWVEENLcO419a0L9RcgAxEuQVzAQdD4jA243HHu4busBHx2Ts+qRF+tkaV8Cl088ZPog33NEzEFR03x/aPDTAEXGw/u2V1iiXa0qNFQPZNBxjqPJQsDotfFfvahXKjQUKcln0VJSLDXcfvctt2IrvoU39rErlSD25OFkAMbi2XZli1xgniiSoURP7PBTAJ4Kx0KMFqObbsEUczDHwVJJAayzON8xeFeB/k2q81CeAp+cMs8RiEsnZngeHQhAQVGk8YZu3n8M=',
)
         * */




        if (isset($payment_data['status']) && $payment_data['status'] === 'complete') {
            //todo detect it is first payment of subscription payment
            $this->runPostPaymentProcessForLandlordPricePlanSuccessPaymentForfirstPayment($payment_data);
            $order_id = wrap_random_number($payment_data['order_id']);
            return redirect()->route("landlord.frontend.order.payment.success", $order_id);
        }

        return $this->landlordPricePlanPostPaymentCancelPage();
    }

    /**
     * @method landlordPricePlanPostPaymentUpdateDatabase
     * @param id $order_id, string  $transaction_id
     *
     * update database for the payment success record
     * */

    private function landlordPricePlanPostPaymentUpdateDatabase($order_id, $transaction_id)
    {
        PaymentLogs::where('id', $order_id)->update([
            'transaction_id' => $transaction_id,
            'status' => 'complete',
            'payment_status' => 'complete',
            'updated_at' => Carbon::now()
        ]);
    }

    /**
     * @method landlordPricePlanPostPaymentSendOrderMail
     * @param id $order_id
     * send mail to admin and user regarding the payment
     * */
    private function landlordPricePlanPostPaymentSendOrderMail($order_id)
    {
        $package_details = PaymentLogs::where('id', $order_id)->first();
        $all_fields = [];
        unset($all_fields['package']);
        $all_attachment = [];
        $order_mail = get_static_option('order_page_form_mail') ? get_static_option('order_page_form_mail') : get_static_option('site_global_email');

        try {
            Mail::to($order_mail)->send(new PlaceOrder($all_fields, $all_attachment, $package_details, "admin", 'regular'));
            Mail::to($package_details->email)->send(new PlaceOrder($all_fields, $all_attachment, $package_details, 'user', 'regular'));

        } catch (\Exception $e) {
            //return redirect()->back()->with(['type' => 'danger', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * @method landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail
     * @param int $order_id
     * create tenant, create database, migrate database table, seed database dummy data, with a default admin account
     * */
    private function landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail($order_id)
    {
        $log = PaymentLogs::findOrFail($order_id);
        if (empty($log))
        {
            abort(462,__('Does not exist, Tenant does not exists'));
        }

        $user = User::where('id', $log->user_id)->first();
        $tenant = Tenant::find($log->tenant_id);

        if (!empty($log) && $log->payment_status == 'complete' && is_null($tenant)) {
            event(new TenantRegisterEvent($user, $log->tenant_id, get_static_option('default_theme')));
            try {
                $raw_pass = get_static_option_central('tenant_admin_default_password') ??'12345678';
                $credential_password = $raw_pass;
                $credential_email = $user->email;
                $credential_username = get_static_option_central('tenant_admin_default_username') ?? 'super_admin';

                Mail::to($credential_email)->send(new TenantCredentialMail($credential_username, $credential_password));

            } catch (\Exception $e) {

            }

        } else if (!empty($log) && $log->payment_status == 'complete' && !is_null($tenant) && $log->is_renew == 0) {
            try {
                $raw_pass = get_static_option_central('tenant_admin_default_password') ?? '12345678';
                $credential_password = $raw_pass;
                $credential_email = $user->email;
                $credential_username = get_static_option_central('tenant_admin_default_username') ?? 'super_admin';

                Mail::to($credential_email)->send(new TenantCredentialMail($credential_username, $credential_password));

            } catch (\Exception $exception) {
                $message = $exception->getMessage();
                if(str_contains($message,'Access denied')){
                    abort(463,__('Database created failed, Make sure your database user has permission to create database'));
                }
            }
        }

        return true;
    }
    /**
     * @method landlordPricePlanPostPaymentUpdateTenant
     * @param array $payment_data
     *
     * */
    private function landlordPricePlanPostPaymentUpdateTenant(array $payment_data)
    {
        try{
            $payment_log = PaymentLogs::where('id', $payment_data['order_id'])->first();
            $tenant = Tenant::find($payment_log->tenant_id);

            //todo: check if old created date is today or not, if it is today, then did not update expired date....

            $updateData = [
                'renew_status' => $renew_status = is_null($tenant->renew_status) ? 0 : $tenant->renew_status+1,
                'is_renew' => $renew_status == 0 ? 0 : 1,
                'start_date' => $payment_log->start_date,

            ];
            if (!$tenant->created_at->isToday()){
                $updateData[ 'expire_date'] = get_plan_left_days($payment_log->package_id, $tenant->expire_date);
            }

            \DB::table('tenants')->where('id', $tenant->id)->update($updateData);


        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            if(str_contains($message,'Access denied')){
                abort(462,__('Database created failed, Make sure your database user has permission to create database'));
            }
        }
    }

    /**
     * @method landlordPricePlanPostPaymentCancelPage
     * @return static cancel page for landlord price plan order
     * */

    private function landlordPricePlanPostPaymentCancelPage()
    {
        return redirect()->route('landlord.frontend.order.payment.cancel.static');
    }

    private function prepareForPaddleSubscriptionPayment($args)
    {
        $package_details = PricePlan::find($args['payment_details']['package_id']);
        $product_id = get_static_option("paddle_onetime_subscription_product_id");
        if($package_details->type == 0){
            $product_id = get_static_option("paddle_monthly_subscription_product_id");
            //monthly
        }elseif ($package_details->type == 1){
            $product_id = get_static_option("paddle_yearly_subscription_product_id");
            //yearly
        }else {
            $this->prepareForPaddleOneTimePayment($args);
        }
        // dd($package_details->type);

        //url https://sandbox-vendors.paddle.com/api/2.0/product/generate_pay_link;
        //dd($args,$args["payment_details"]);
        //build price in USD, then others
        $payment_params = [
            "vendor_id"=> get_static_option("paddle_vendor_id"),
            "vendor_auth_code"=> get_static_option("paddle_vendor_auth_code"),
            "product_id"=> $product_id,
            "title"=> $args["payment_details"]["package_name"],
            //"webhook_url"=> route("paddlepaymentgateway.landlord.price.plan.ipn"), //not work on localhost. also it is not required when you provide product id
            "prices"=> [sprintf("%s:%s",get_static_option('site_global_currency',"USD"),$args['total'])], //set price per currency
//           "custom_message"=> "this is test message", // a message shown in checkout page below title
            //"image_url"=> "https://xgenious.com/wp-content/uploads/2022/07/Group-1171274812.png",
            "return_url"=> $args["success_url"]."?checkout={checkout_hash}", //success url with checkout hash, can use it as get ipn as well, but for paddle it prepared to use webhook to track payment
            "quantity_variable"=> 0, //not allow to user to change quantity at checkout page
            "expires"=> Carbon::today()->addDays(2)->format("Y-m-d"), //this url will be expired after 2days
            "customer_email"=> $args["payment_details"]["email"],
            "passthrough" => json_encode(["order_id" => XgPaymentGateway::wrapped_id($args["payment_details"]['id']),"payment_type" => $args["payment_type"],'is_subscription' => 1]), //order id and order type

            "recurring_prices"=> [sprintf("%s:%s",get_static_option('site_global_currency',"USD"),$args['total'])],
//           "trial_days"=> "",
//           "coupon_code"=> "",
//           "discountable"=> "",


//           "affiliates"=> "",
//           "recurring_affiliate_limit"=> "",
//           "customer_country"=> "",
//           "customer_postcode"=> "",
//           "passthrough"=> "",
//           "vat_number"=> "",
//           "vat_company_name"=> "",
//           "vat_street"=> "",
//           "vat_city"=> "",
//           "vat_state"=> "",
//           "vat_country"=> "",
//           "vat_postcode"=> ""
        ];

        $req = Http::post($this->getBaseUrl(
                prefix: "vendors",
                version: "2.0",
                sandbox: true
            )."product/generate_pay_link",$payment_params);
        $checkout_url = $req->object();
        if (property_exists($checkout_url,"success") && $checkout_url->success){
            return redirect()->away($checkout_url?->response?->url);
        }else {
            abort(501,$req->object()?->error?->message);
        }
    }

    private function runPostPaymentProcessForLandlordPricePlanSuccessPaymentForfirstPayment(array $payment_data)
    {
        Log::info($payment_data);
        try {
            $this->landlordPricePlanPostPaymentUpdateDatabase($payment_data['order_id'], $payment_data['transaction_id']);
            $this->landlordPricePlanPostPaymentSendOrderMail($payment_data['order_id']);
            $this->landlordPricePlanPostPaymentTenantCreateEventWithCredentialMail($payment_data['order_id']);
            $this->landlordPricePlanPostPaymentUpdateTenant($payment_data);

        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            if(str_contains($message,'Access denied')){
                if(request()->ajax()){
                    abort(462,__('Database created failed, Make sure your database user has permission to create database'));
                }
            }

            $payment_details = PaymentLogs::where('id',$payment_data['order_id'])->first();
            if(empty($payment_details))
            {
                abort(500,__('Does not exist, Tenant does not exists'));
            }
            LandlordPricePlanAndTenantCreate::store_exception($payment_details->tenant_id,'Domain create',$exception->getMessage(), 0);

            //todo: send an email to admin that this user databse could not able to create automatically

            try {
                $message = sprintf(__('Database Creating failed for user id %1$s , please checkout admin panel and generate database for this user from admin panel manually'),
                    $payment_details->user_id);
                $subject = sprintf(__('Database Crating failed for user id %1$s'),$payment_details->user_id);
                Mail::to(get_static_option('site_global_email'))->send(new BasicMail($message,$subject));

            } catch (\Exception $e) {
                LandlordPricePlanAndTenantCreate::store_exception($payment_details->tenant_id,'domain failed email',$e->getMessage(), 0);
            }
        }
    }

    private function runPostPaymentProcessForLandlordPricePlanSuccessPaymentForRecurringPayment(array $payment_data)
    {
        if (isset($payment_data['status']) && $payment_data['status'] === 'complete') {
            //todo detect subscription payment
            //todo: update payment log for recurring payment
            //todo: update tenant info and expired date based on the payment made

            //todo fetch price plan details
            //todo fetch user info
            $last_payment_log =   PaymentLogs::where('id', $payment_data["order_id"])->first();
            if (is_null($last_payment_log)){
                Log::info("{$last_payment_log} package id not found for order {$payment_data["order_id"]}");
                abort(501,"package id not found");
            }
            $used_price_plan = PricePlan::find($last_payment_log->package_id);
            $tenant = Tenant::find($last_payment_log->tenant_id);


            $package_start_date = '';
            $package_expire_date = '';

            if (!empty($used_price_plan)) {
                if ($used_price_plan->type == 0) { //monthly
                    $package_start_date = Carbon::now()->format('d-m-Y h:i:s');
                    $package_expire_date = Carbon::now()->addMonth(1)->format('d-m-Y h:i:s');

                } elseif ($used_price_plan->type == 1) { //yearly
                    $package_start_date = Carbon::now()->format('d-m-Y h:i:s');
                    $package_expire_date = Carbon::now()->addYear(1)->format('d-m-Y h:i:s');
                } else { //lifetime
                    $package_start_date = Carbon::now()->format('d-m-Y h:i:s');
                    $package_expire_date = null;
                }
            }

            if ($package_expire_date != null) {
                $old_days_left = Carbon::now()->diff($last_payment_log->expire_date);
                $left_days = 0;

                if ($old_days_left->invert == 0) {
                    $left_days = $old_days_left->days;
                }

                $renew_left_days = 0;
                $renew_left_days = Carbon::parse($package_expire_date)->diffInDays();

                $sum_days = $left_days + $renew_left_days;
                $new_package_expire_date = Carbon::today()->addDays($sum_days)->format("d-m-Y h:i:s");
            } else {
                $new_package_expire_date = null;
            }

            PaymentLogs::findOrFail($last_payment_log->id)->update([
                'email' => $last_payment_log->email,
                'name' => $last_payment_log->name,
                'package_name' => $used_price_plan->title,
                'package_price' => $used_price_plan->price,
                'package_gateway' => 'wallet',
                'package_id' => $used_price_plan->id,
                'user_id' => $tenant->user_id ?? null,
                'tenant_id' => $tenant->tenant_id ?? null,
                'status' => 'complete',
                'payment_status' => 'complete',
                'renew_status' => is_null($last_payment_log->renew_status) ? 1 : $last_payment_log->renew_status + 1,
                'is_renew' => 1,
                'track' => Str::random(10) . Str::random(10),
                'updated_at' => Carbon::now(),
                'start_date' => $package_start_date,
                'expire_date' => $new_package_expire_date
            ]);

            //update tenant
            \DB::table('tenants')->where('id', $tenant->id)->update([
                'renew_status' => $renew_status = is_null($tenant->renew_status) ? 0 : $tenant->renew_status+1,
                'is_renew' => $renew_status == 0 ? 0 : 1,
                'start_date' => $package_start_date,
                'expire_date' => get_plan_left_days($last_payment_log->id, $tenant->expire_date)
            ]);


        }

        return $this->landlordPricePlanPostPaymentCancelPage();
    }
}
