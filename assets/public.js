$ = jQuery;
$(document.body).on("updated_checkout", function () {
  const stripe = Stripe(srpLocal.pKey);

  const elements = stripe.elements();

  const card = elements.create("card");
  card.mount("#card-element");

  $('#card-element').addClass('themed')

  const cardholderName = document.getElementById("cardholder-name");
  const form = $("form.woocommerce-checkout");
  const getPacks = document.getElementById("get_packages");

  //checkout_place_order

  getPacks.addEventListener("click", async (ev) => {
    ev.preventDefault();

    const email = document.getElementById('billing_email').value ? document.getElementById('billing_email').value : '';
    const name = cardholderName.value ;
    const address_line_1 = document.getElementById('billing_address_1').value ? document.getElementById('billing_address_1').value : '';
    const address_line_2 = document.getElementById('billing_address_2').value ? document.getElementById('billing_address_2').value : '';
    const address_country = document.getElementById('billing_country').value ? document.getElementById('billing_country').value : '';
    const address_state = document.getElementById('billing_email').value ? document.getElementById('billing_email').value : '';
    const address_zip = document.getElementById('billing_postcode').value ? document.getElementById('billing_postcode').value : '';
    const address_city = document.getElementById('billing_city').value ? document.getElementById('billing_city').value : '';
    const phone = document.getElementById('billing_phone').value ? document.getElementById('billing_phone').value : '';

    const billing = {
        name : name ,
        email: email , 
        address: {
            city: address_city, 
            country : address_country, 
            line1: address_line_1, 
            line2: address_line_2, 
            postal_code: address_zip, 
            state: address_state, 
        },
        phone : phone,
    };

    const { paymentMethod, error } = await stripe.createPaymentMethod(
      "card",
      card,
      { billing_details: billing }
    );

    if (error) {
      // Show error in payment form
    } else {
      // Send paymentMethod.id to your server (see Step 2)

      console.log(paymentMethod.id);

      const response = await fetch(srpLocal.get_packages, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ payment_method_id: paymentMethod.id, sKey: srpLocal.sKey, amount: srpLocal.amount }),
      });

      const json = await response.json();
      console.log(json);

      // Handle server response (see Step 3)
      handleInstallmentPlans(json);
    }
  });

  const selectPlanForm = document.getElementById("installment-plan-form");
  let availablePlans = [];

  const handleInstallmentPlans = async (response) => {
    if (response.error) {
      // Show error from server on payment form
    } else {
      // Store the payment intent ID.
      document.getElementById("payment-intent-id").value = response.intent_id;
      availablePlans = response.available_plans;

      // Show available installment options
      availablePlans.forEach((plan, idx) => {
        const newInput = document.getElementById("immediate-plan").cloneNode();
        newInput.setAttribute("value", idx);
        newInput.setAttribute("id", 'installmant_plan_' + idx);
        const label = document.createElement("label");
        label.setAttribute('for', 'installmant_plan_' + idx);
        label.appendChild(newInput);
        label.appendChild(
          document.createTextNode(`${plan.count} ${plan.interval}s`)
        );

        selectPlanForm.appendChild(label);
      });

      document.getElementById("details").hidden = true;
      document.getElementById("plans").hidden = false;
    }
  };


  $('form.woocommerce-checkout').on("checkout_place_order", async function (ev) {
    const selectedPlanIdx = selectPlanForm.installment_plan.value;
    const selectedPlan = availablePlans[selectedPlanIdx];
    const intentId = document.getElementById("payment-intent-id").value;
    const response = await fetch(srpLocal.confirm_payment, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        payment_intent_id: intentId,
        selected_plan: selectedPlan,
        sKey: srpLocal.sKey,
        redirect : srpLocal.redirect
      }),
    });

    const responseJson = await response.json();

    // Show success / error response.
    document.getElementById("plans").hidden = true;
    document.getElementById("result").hidden = false;

    var message;
    if (responseJson.status === "succeeded" && selectedPlan !== undefined) {
      message = `Success! You made a charge with this plan : ${selectedPlan.count} ${selectedPlan.interval}`;
      document.getElementById("srp_stat").value = 'success';
    } else if (responseJson.status === "succeeded") {
      message = "Success! You paid immediately!";
      document.getElementById("srp_stat").value = 'success';
    } else {
      message = "Uh oh! Something went wrong";
    }

    document.getElementById("status-message").innerText = message;
  });
});
