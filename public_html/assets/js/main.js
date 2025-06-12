// A good practice: wait for the HTML to fully load before running scripts
document.addEventListener('DOMContentLoaded', function() {

    // 1. Select the HTML elements we need by their ID
    const updateButton = document.getElementById('update-news-btn');
    const newsContent = document.getElementById('news-content');

    // 2. Add an event listener that waits for a 'click' on our button
    // First, check if the button actually exists on the current page to prevent errors
    if (updateButton) {
        updateButton.addEventListener('click', function() {
            // 3. When the button is clicked, this function runs
            newsContent.textContent = "The button was clicked! The news has been updated.";
            newsContent.style.color = 'red';
        });
    }

});

// --- Contact Form Validation ---
const contactForm = document.getElementById('contactForm');

// Check if the form exists on the current page
if (contactForm) {
    contactForm.addEventListener('submit', function(event) {
        // This 'event' object is the submission event itself
        const nameInput = document.getElementById('contactName');
        const emailInput = document.getElementById('contactEmail');
        const messageInput = document.getElementById('contactMessage');
        
        let isValid = true;
        let errorMessages = [];

        // Rule 1: Check if name is empty
        if (nameInput.value.trim() === '') {
            isValid = false;
            errorMessages.push('Name is required.');
        }

        // Rule 2: Check for a valid email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            isValid = false;
            errorMessages.push('Please enter a valid email address.');
        }

        // Rule 3: Check if message is empty
        if (messageInput.value.trim() === '') {
            isValid = false;
            errorMessages.push('Message is required.');
        }

        // If any validation rule failed, stop the form submission
        if (!isValid) {
            // This is the most important line - it STOPS the form from submitting
            event.preventDefault(); 
            
            // Show all error messages in a single alert
            alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
        }
    });
}