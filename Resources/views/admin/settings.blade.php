@extends(route_prefix()."admin.admin-master")
@section('title') {{__('Paddle Payment Gateway Settings')}}@endsection
@section("style")
    <style>
        .margin-top-30 {
            margin-top: 30px;
        }
    </style>
@endsection
@section("content")
    <div class="row">
        <div class="col-12">
            <x-error-msg/>
            <x-flash-msg/>
        </div>
        <div class="col-6 stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">{{__('Set Paddle product id for price plan')}}</h4>

                    <a href="#" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#paddle_product_add">Add New Paddle Product</a>
                    <table class="table table-striped margin-top-30">
                        <thead>
                            <th>ID</th>
                            <th>Package Name</th>
                            <th>Type</th>
                            <th>Paddle Id</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                        @foreach($all_paddle_products as $pod)
                            <tr>
                                <td>{{$pod->id}}</td>
                                <td>{{$pod?->price_plan?->title}}</td>
<<<<<<< HEAD
                                <td>{{ \App\Enums\PricePlanTypEnums::getText($pod?->price_plan?->type ?? 7)}}</td>
=======
                                <td>{{ \App\Enums\PricePlanTypEnums::getText($pod?->price_plan?->type ?? 9)}}</td>
>>>>>>> 7ebd50f74fc5e585e217faf62c6dd0861cce6237
                                <td>{{$pod->product_id}}</td>
                                <td>
                                    <a href="#" data-settings="{{json_encode(["id" => $pod->id,"price_plan" => $pod->price_plan_id,"product_id" => $pod->product_id])}}" class="btn btn-info btn-sm paddle_product_edit" data-bs-toggle="modal" data-bs-target="#paddle_product_edit"><i class="mdi mdi-pencil"></i></a>
                                    <a href="#" data-id="{{$pod->id}}" class="btn btn-danger btn-sm paddle_product_delete"><i class="mdi mdi-trash-can"></i></a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-6 stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-4">{{__('Paddle Payment Gateway Settings')}}</h4>

                    <form class="forms-sample" method="post" action="{{route("paddle.landlord.admin.settings")}}">
                        @csrf
                        <x-fields.input type="text" value="{{get_static_option('paddle_vendor_id')}}" name="paddle_vendor_id" label="{{__('Vendor ID')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('paddle_vendor_auth_code')}}" name="paddle_vendor_auth_code" label="{{__('Vendor Auth Code')}}"/>
                        {{--
                        <x-fields.input type="text" value="{{get_static_option('paddle_yearly_subscription_product_id')}}" name="paddle_yearly_subscription_product_id" label="{{__('Yearkt Subscription Product Id')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('paddle_monthly_subscription_product_id')}}" name="paddle_monthly_subscription_product_id" label="{{__('Monthly Subscription Product Id')}}"/>
                        <x-fields.input type="text" value="{{get_static_option('paddle_onetime_subscription_product_id')}}" name="paddle_onetime_subscription_product_id" label="{{__('(Life Time Subscription) One Time Payment Product Id')}}"/>
                       --}}
                        <div class="form-group">
                            <label>{{__("Public Key")}}</label>
                            <textarea  name="paddle_public_key" class="form-control"  rows="10" >{{file_get_contents("assets/uploads/public.txt")}}</textarea>
                        </div>

                        <x-fields.switcher label="{{__('Paddle Payment Gateway Enable/Disable')}}" name="paddle_status" value="{{$paddle_status}}"/>
                        <x-fields.switcher label="{{__('Paddle Test Mode Enable/Disable')}}" name="paddle_test_mode" value="{{$paddle_test_mode}}"/>
                        <button type="submit" class="btn btn-gradient-primary mt-5 me-2">{{__('Save Changes')}}</button>
                    </form>
                </div>
            </div>
        </div>
        
        
        <div class="col-12 stretch-card mt-3">
            <div class="card ">
                <div class="card-body">
                    <h4 class="card-title mb-4">{{__('Paddle Subscription History')}}</h4>

                    <table class="table table-striped margin-top-30">
                        <thead>
                            <th>ID</th>
                            <th>Order ID</th>
                            <th>Subscription ID</th>
                            <th>Checkout ID</th>
                        </thead>
                        <tbody>
                        @foreach($all_subscriptions as $sub)
                            <tr>
                                <td>{{$sub->id}}</td>
                                <td>{{$sub->order_id}}</td>
                                <td>{{$sub->subscription_id}}</td>
                                <td>{{$sub->checkout_id}}</td>
                                <td> 
                                    <span class="d-inline-block p-1 alert-{{$sub->status === 0 ? 'danger' : 'success'}}">{{$sub->status === 0 ? 'cancel' : 'active' }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="pagination-wrapper">
                        {!! $all_subscriptions->links() !!}
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    <div class="modal fade" id="paddle_product_add" aria-hidden="true">
        <div class="modal-dialog ">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{__('Add new Paddle Product')}}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>×</span></button>
                </div>

                <form action="{{route("paddle.landlord.admin.settings.product.insert")}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="price_plan_id">{{__('Select Price Plan')}}</label>
                            <select name="price_plan_id" class="form-control">
                                    <option value="">{{__("--Select Plan--")}}</option>
                                @foreach($all_price_plans as $plan)
                                    <option value="{{$plan->id}}">{{$plan->title}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="order_status">{{__('Paddle Product/Subscription ID')}}</label>
                            <input name="product_id" class="form-control" placeholder="{{__("product id")}}"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__('Close')}}</button>
                        <button type="submit" class="btn btn-primary">{{__('Change Status')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="paddle_product_edit" aria-hidden="true">
        <div class="modal-dialog ">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{__('Edit Paddle Product')}}</h5>
                    <button type="button" class="close" data-bs-dismiss="modal"><span>×</span></button>
                </div>

                <form action="{{route("paddle.landlord.admin.settings.product.update")}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <input type="hidden" name="id" >
                        <div class="form-group">
                            <label for="order_status">{{__('Select Price Plan')}}</label>
                            <select name="price_plan_id" class="form-control">
                                <option value="">{{__("--Select Plan--")}}</option>
                                @foreach($all_price_plans as $plan)
                                <option value="{{$plan->id}}">{{$plan->title}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="product_id">{{__('Paddle Product/Subscription ID')}}</label>
                            <input name="product_id" class="form-control"/>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__('Close')}}</button>
                        <button type="submit" class="btn btn-primary">{{__('Save Changes')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section("scripts")
    <script>
        (function ($){
            "use strict";

            $(document).on("click",".paddle_product_edit",function (e){
                e.preventDefault();
                let allSettings = $(this).data("settings");
                let modalContainer = $("#paddle_product_edit")
;                modalContainer.find("input[name='id']").val(allSettings.id)
;                modalContainer.find("input[name='product_id']").val(allSettings.product_id)
;                modalContainer.find("select[name='price_plan_id'] option[value='"+allSettings.price_plan+"']").attr("selected",true)

            });
            $(document).on("click",".paddle_product_delete",function (e){
                e.preventDefault();
                let el = $(this);
                Swal.fire({
                    title: '{{__("Are you sure?")}}',
                    text: '{{__("You would not be able to revert this item!")}}',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: "{{__('Yes, Delete it!')}}",
                    cancelButtonText: "{{__('Cancel')}}",

                }).then((result) => {
                    if (result.isConfirmed) {
                        //ajakx call
                        el.parent().parent().hide();
                        $.ajax({
                            url: "{{route("paddle.landlord.admin.settings.product.delete")}}",
                            type: "post",
                            data:{
                                _token : "{{csrf_token()}}",
                                    id: el.data("id")
                            },
                            success : function (data){
                                el.parent().parent().remove();
                            }

                        })
                    }
                });

            })


        })(jQuery);
    </script>
@endsection
