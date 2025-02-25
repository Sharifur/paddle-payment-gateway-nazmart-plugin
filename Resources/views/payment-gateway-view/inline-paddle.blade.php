{{-- omit order button instead show this paddle pay now button or trigger this button  --}}

<a href="#!" id="paddle_trigger_button" class="paddle_button d-none"
   data-product="51090"
   data-title="test title"
   data-customer="test name"
   data-allow-quantity="0"
   data-passthrough="{{json_encode(["order_id" => 123])}}"
   data-email="email@example.com"
>Buy Now!</a>
<div class="btn-wrapper mt-4">
    <button class="boxed-btn btn-saas btn-block paddle-order-button cmn-btn cmn-btn-bg-1" type="button">Order Package</button>
</div>
<script src="https://cdn.paddle.com/paddle/paddle.js"></script>
<script type="text/javascript">
    //if user select paddle then hide send a request to the server with all order info so that i can send users to paddle server for overlay checkout
    document.addEventListener("DOMContentLoaded", (event) => {
    @if(request()->is("landlord/wallet-history*"))
    document.querySelector('.payment-gateway-wrapper li[data-gateway="paddle"]').style.display = "none";
    @endif

    @if(request()->is("user-home*"))
        let formContainer = document.querySelector("#user_add_subscription_form");
    @else
        let formContainer = document.querySelector("form.contact-page-form.custom--form.order-form");
    @endif
    let formSubmitButton = formContainer.querySelector('.form-group button')
    var selectedPaymentGateway = formContainer.querySelector('input[name="selected_payment_gateway"]')?.value;
    let paymentGatewayRenderLi = document.querySelectorAll(".payment-gateway-wrapper ul li");

    @if(request()->is("user-home*"))
            if( selectedPaymentGateway === "paddle"){
                formSubmitButton.style.display = "none";
            }
    @endif

    let newPaddleSubmitButton = formContainer.querySelector(".paddle-order-button");
    newPaddleSubmitButton.addEventListener("click",function(event){
        event.preventDefault();
        let formContainer = document.querySelector("form.contact-page-form.order-form");
        var paddleButton = document.getElementById("paddle_trigger_button");
        var planType = document.querySelector('select[name="subdomain"]').value;
        var subdomainvalue = planType;
        var customDomain = document.querySelector('input[name="custom_subdomain"]')?.value;
        if( planType == "custom_domain__dd"){
            subdomainvalue = customDomain;
        }


        @if(request()->is("user-home*"))
            let themeSlug = document.querySelector('select[name="theme_slug"]').value;
            let package_id = document.querySelector('select[name="package"]').value;
        @else
            let themeSlug = document.querySelector('input[name="theme_slug"]').value;
            let package_id = document.querySelector('input[name="package_id"]').value;
        @endif
        if(themeSlug == ''){
            alert('{{__('Select a theme')}}');
            return;
        }
        if(package_id == ''){
            alert('{{__('Select a package')}}');
            return;
        }
        //todo submit ajax requwest with all the below information then get json response from the paddle payment gateway controller
        $.ajax({
            url : "{{route('landlord.frontend.order.payment.form')}}",
            type : "post",
            data: {
                _token: "{{csrf_token()}}",
                payment_gateway: 'paddle',
                package_id: package_id,
                name: document.querySelector('input[name="name"]').value,
                email: document.querySelector('input[name="email"]').value,
                subdomain: subdomainvalue,
                custom_subdomain: planType == "custom_domain__dd" ? subdomainvalue :customDomain,
                theme_slug: themeSlug,
                selected_payment_gateway: 'paddle',
            },
            success: function(data){
                // console.log(data);
                {{--data-product="51090"--}}
                    {{--data-title="test title"--}}
                    {{--data-customer="test name"--}}
                    {{--data-passthrough="{{json_encode(["order_id" => 123])}}"--}}
                    {{--data-email="email@example.com"--}}
                if(data.success === "danger"){
                    return;
                }
                paddleButton.setAttribute("data-product",data.product_id);
                paddleButton.setAttribute("data-title",data.title);
                paddleButton.setAttribute("data-customer",data.customer_name);
                paddleButton.setAttribute("data-email",data.customer_email);
                paddleButton.setAttribute("data-success",data.return_url);
                paddleButton.setAttribute("data-passthrough",data.passthrough);
                paddleButton.dispatchEvent(new MouseEvent("click"));
                //todo triggle paddle button with data
            },
            error: function (errors){
                // console.log(errors.responseJSON.message);
                alert(errors.responseJSON.message);
            }
        })
        /*

        payment_gateway: paytabs
package_id: 7
name: Glenna Park
email: qodohyqaco@mailinator.com
subdomain: custom_domain__dd
custom_subdomain:
selected_payment_gateway: paytabs
trasaction_id:

        */

    });
    for (let i=0; i <paymentGatewayRenderLi.length; i++){
        paymentGatewayRenderLi[i].addEventListener("click",function(event){
            event.preventDefault();
            selectedPaymentGateway = paymentGatewayRenderLi[i].getAttribute("data-gateway");
            let subscriptionModal = document.querySelector("#user_add_subscription");
            if( selectedPaymentGateway === "paddle"){
                @if(!request()->is("user-home*"))
                    formSubmitButton.style.display = "none";
                @endif

                if(subscriptionModal !== null){
                    subscriptionModal.querySelector('.modal-footer').style.display = 'none';
                }
            }else{
                @if(!request()->is("user-home*"))
                    formSubmitButton.style.display = "block";
                @endif

                if(subscriptionModal !== null){
                    subscriptionModal.querySelector('.modal-footer').style.display = 'block';
                }
            }
        });
    }

    });


    // ,function (e){
    //     alert("working")
    //     e.preventDefault();
    //     let paddleButton = document.querySelector(".paddle_button");
    //     paddleButton.dispatchEvent(new MouseEvent("click"));
    // })
    @if( get_static_option('paddle_test_mode') === 'on')
    Paddle.Environment.set('sandbox'); //remove this line for live payment
    @endif
    Paddle.Setup({ vendor: {{get_static_option("paddle_vendor_id")}} });
</script>
