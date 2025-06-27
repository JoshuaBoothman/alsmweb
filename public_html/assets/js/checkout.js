// This script will handle the Stripe payment process on the checkout page.

document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Stripe.js with your publishable key.
    // **ACTION REQUIRED**: Replace the placeholder text below with your real Publishable Key.
    const stripe = Stripe('pk_test_51ReOfr03IGtarUhKQHSztXVhZQqDzvT6NXzQVo1YvJBzh7dphj6fQi1pO7G5l5HAKSb9TUx2uVNoSMbVTq9WwBF400kypQNQrw');

    const elements = stripe.elements();

    // 2. Create and mount the Card Element.
    const cardElement = elements.create('card', {
        style: {
            base: {
                iconColor: '#6c757d',
                color: '#333',
                fontWeight: '500',
                fontFamily: 'Roboto, Open Sans, Segoe UI, sans-serif',
                fontSize: '16px',
                fontSmoothing: 'antialiased',
                ':-webkit-autofill': {
                    color: '#fce883',
                },
                '::placeholder': {
                    color: '#888',
                },
            },
            invalid: {
                iconColor: '#dc3545',
                color: '#dc3545',
            },
        },
    });
    cardElement.mount('#card-element');

    // 3. Handle real-time validation errors from the Card Element.
    const cardErrors = document.getElementById('card-errors');
    cardElement.on('change', function (event) {
        if (event.error) {
            cardErrors.textContent = event.error.message;
        } else {
            cardErrors.textContent = '';
        }
    });

    // 4. Handle form submission.
    const form = document.getElementById('payment-form');
    const submitButton = document.getElementById('submit-button');
    const originalButtonText = submitButton.textContent;

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        setLoading(true);

        // Save shipping address to session
        const formData = new FormData(form);
        const addressData = {
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            address: formData.get('address'),
        };
        try {
            const saveAddrResponse = await fetch('api/save_address_to_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(addressData),
            });
            if (!saveAddrResponse.ok) throw new Error('Could not save shipping address.');
        } catch (e) {
            cardErrors.textContent = e.message;
            setLoading(false);
            return;
        }
        
        // Fetch client secret from our server
        const paymentIntentResponse = await fetch('api/create_payment_intent.php', { method: 'POST' });
        const { clientSecret, error: backendError } = await paymentIntentResponse.json();
        if (backendError) {
            cardErrors.textContent = backendError;
            setLoading(false);
            return;
        }

        // Confirm the card payment with Stripe
        const { error: stripeError, paymentIntent } = await stripe.confirmCardPayment(
            clientSecret, {
                payment_method: { card: cardElement }
            }
        );

        if (stripeError) {
            cardErrors.textContent = stripeError.message;
            setLoading(false);
            return;
        }
        
        if (paymentIntent.status === 'succeeded') {
            window.location.href = 'payment_success.php';
        } else {
            cardErrors.textContent = "Payment not successful. Status: " + paymentIntent.status;
        }

        setLoading(false);
    });

    function setLoading(isLoading) {
        submitButton.disabled = isLoading;
        if (isLoading) {
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...`;
        } else {
            submitButton.innerHTML = originalButtonText;
        }
    }
});
