// This script will handle the Stripe payment process on the checkout page.

document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Stripe.js with your publishable key.
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

        // Get the CSRF token from the form's data attribute
        const csrfToken = form.dataset.csrfToken;

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
                // **MODIFIED**: Send the CSRF token along with the address data
                body: JSON.stringify({ ...addressData, csrf_token: csrfToken }),
            });
            if (!saveAddrResponse.ok) {
                 const errorResult = await saveAddrResponse.json();
                 throw new Error(errorResult.error || 'Could not save shipping address.');
            }
        } catch (e) {
            cardErrors.textContent = e.message;
            setLoading(false);
            return;
        }
        
        // Fetch client secret from our server, now sending the CSRF token
        const paymentIntentResponse = await fetch('api/create_payment_intent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ csrf_token: csrfToken }),
        });
        
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
