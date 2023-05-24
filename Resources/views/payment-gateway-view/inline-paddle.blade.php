{{-- omit order button instead show this paddle pay now button or trigger this button  --}}

<a href="#!" class="paddle_button"
   data-product="51090"
   data-title="test title"
   data-customer="test name"
   data-passthrough="{{json_encode(["order_id" => 123])}}"
   data-price="50"
   data-email="email@example.com"
>Buy Now!</a>
<div class="form-group btn-wrapper mt-4">
    <button class="boxed-btn btn-saas btn-block paddle-order-button cmn-btn cmn-btn-bg-1" type="submit">Order Package</button>
    <p>[generate order information using ajax call so that  can send payment request through paddle overlay checkout]</p>
</div>
<script src="https://cdn.paddle.com/paddle/paddle.js"></script>
<script type="text/javascript">
    //if user select paddle then hide send a request to the server with all order info so that i can send users to paddle server for overlay checkout
    window.addEventListener("DOMContentLoaded", (event) => {
        let formSubmitButton = document.querySelector("form.contact-page-form.order-form button[type='submit']")
        // document).querySelector("form.contact-page-form.order-form button[type='submit']");
        formSubmitButton.addEventListener("click",function(event){
            event.preventDefault();
            let formContainer = document.querySelector("form.contact-page-form.order-form");
            formContainer.addEventListener("submit",function (event){
                event.preventDefault();

            })
            alert("i am triggered")
            console.log(event)
        });
    });


    // ,function (e){
    //     alert("working")
    //     e.preventDefault();
    //     let paddleButton = document.querySelector(".paddle_button");
    //     paddleButton.dispatchEvent(new MouseEvent("click"));
    // })
    Paddle.Environment.set('sandbox'); //remove this line for live payment
    Paddle.Setup({ vendor: 12073 });
</script>
