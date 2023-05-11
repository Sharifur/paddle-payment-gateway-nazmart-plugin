@extends(route_prefix()."admin.admin-master")
@section('title') {{__('Paddle Payment Gateway Settings')}}@endsection
@section("content")
    <div class="col-12 stretch-card">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">{{__('Paddle Payment Gateway Settings')}}</h4>
                <x-error-msg/>
                <x-flash-msg/>
                <form class="forms-sample" method="post" action="{{route('paddle.'.route_prefix().'admin.settings')}}">
                    @csrf
                    <x-fields.input type="text" value="{{get_static_option('paddle_vendor_id')}}" name="paddle_vendor_id" label="{{__('Vendor ID')}}"/>
                    <x-fields.input type="text" value="{{get_static_option('paddle_vendor_auth_code')}}" name="paddle_vendor_auth_code" label="{{__('Vendor Auth Code')}}"/>
                    <x-fields.input type="text" value="{{get_static_option('paddle_yearly_subscription_product_id')}}" name="paddle_yearly_subscription_product_id" label="{{__('Yearkt Subscription Product Id')}}"/>
                    <x-fields.input type="text" value="{{get_static_option('paddle_monthly_subscription_product_id')}}" name="paddle_monthly_subscription_product_id" label="{{__('Monthly Subscription Product Id')}}"/>
                    <x-fields.input type="text" value="{{get_static_option('paddle_onetime_subscription_product_id')}}" name="paddle_onetime_subscription_product_id" label="{{__('(Life Time Subscription) One Time Payment Product Id')}}"/>
                    <div class="form-group">
                        <label>{{__("Public Key")}}</label>
                        <textarea  name="paddle_public_key" class="form-control"  rows="10" >{{file_get_contents("assets/uploads/public.txt")}}</textarea>
                    </div>

                    <x-fields.switcher label="{{__('Paddle Payment Gateway Enable/Disable')}}" name="paddle_status" value="{{$paddle_status}}"/>
                    <button type="submit" class="btn btn-gradient-primary mt-5 me-2">{{__('Save Changes')}}</button>
                </form>
            </div>
        </div>
    </div>
@endsection
