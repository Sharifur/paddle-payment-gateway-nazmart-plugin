<?php

namespace Modules\PaddlePaymentGateway\Http\Controllers;

use App\Helpers\ModuleMetaData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\PaddlePaymentGateway\Http\Helpers\JsonDataModifier;

class PaddlePaymentGatewayAdminPanelController extends Controller
{
    public function settings(){
        $all_module_meta_data = (new ModuleMetaData("PaddlePaymentGateway"))->getExternalPaymentGateway();
        $paddle = array_filter($all_module_meta_data,function ( $item ){
            if ($item->name === "Paddle"){
                return $item;
            }
        });
        $paddle_status = current($paddle)->status;
        return view('paddlepaymentgateway::admin.settings',compact('paddle_status'));
    }

    public function settingsUpdate(Request $request){

        $request->validate([
            "paddle_vendor_id" => "required|string",
            "paddle_vendor_auth_code" => "required|string",
            "paddle_yearly_subscription_product_id" => "required|string",
            "paddle_monthly_subscription_product_id" => "required|string",
            "paddle_onetime_subscription_product_id" => "required|string",
            "paddle_status" => "nullable|string",
            "paddle_public_key" => "required|string",
        ]);

        update_static_option("paddle_vendor_id",$request->paddle_vendor_id);
        update_static_option("paddle_vendor_auth_code",$request->paddle_vendor_auth_code);
        update_static_option("paddle_yearly_subscription_product_id",$request->paddle_yearly_subscription_product_id);
        update_static_option("paddle_monthly_subscription_product_id",$request->paddle_monthly_subscription_product_id);
        update_static_option("paddle_onetime_subscription_product_id",$request->paddle_onetime_subscription_product_id);

        //if ($request->has("paddle_status")){
            $jsonModifier = json_decode(file_get_contents("core/Modules/PaddlePaymentGateway/module.json"));
            $jsonModifier->nazmartMetaData->paymentGateway->status = $request?->paddle_status === 'on';
            file_put_contents("core/Modules/PaddlePaymentGateway/module.json",json_encode($jsonModifier));
        //}

        if ($request->has("paddle_public_key")){
            file_put_contents("assets/uploads/public.txt",$request->paddle_public_key);
        }


        return back()->with(['msg' => __('Settings updated'),'msg' => 'success']);
    }
}
