// Feedback System for My Orders Page
document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const feedbackBtns = document.querySelectorAll('.feedback-btn');
    const feedbackModal = document.getElementById('feedback-modal');
    const closeModal = document.getElementById('close-modal');
    const cancelFeedback = document.getElementById('cancel-feedback');
    const submitFeedback = document.getElementById('submit-feedback');
    const feedbackText = document.getElementById('feedback-text');
    const modalOrderId = document.getElementById('modal-order-id');
    const stars = document.querySelectorAll('.star');
    
    let currentRating = 0;
    let currentOrderId = '';

    // Open feedback modal when feedback button is clicked
    feedbackBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            currentOrderId = this.getAttribute('data-order-id');
            modalOrderId.textContent = currentOrderId;
            
            // Check if this order already has feedback
            const orderCard = this.closest('.history-card');
            const existingFeedback = orderCard.querySelector('.order-feedback');
            
            if (existingFeedback) {
                // Editing existing feedback
                const feedbackContent = existingFeedback.querySelector('.feedback-content p').textContent;
                feedbackText.value = feedbackContent.replace(/"/g, '').trim();
                currentRating = 5; // Default to 5 stars for existing feedback
                updateStars(5);
            } else {
                // New feedback
                feedbackText.value = '';
                currentRating = 0;
                updateStars(0);
            }
            
            feedbackModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close feedback modal
    function closeFeedbackModal() {
        feedbackModal.classList.remove('active');
        document.body.style.overflow = '';
        currentRating = 0;
        updateStars(0);
    }

    // Close modal events
    closeModal.addEventListener('click', closeFeedbackModal);
    cancelFeedback.addEventListener('click', closeFeedbackModal);

    // Close modal when clicking outside
    feedbackModal.addEventListener('click', function(e) {
        if (e.target === feedbackModal) {
            closeFeedbackModal();
        }
    });

    // Star rating functionality
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            currentRating = rating;
            updateStars(rating);
        });

        // Hover effects
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            updateStars(rating, true);
        });

        star.addEventListener('mouseleave', function() {
            updateStars(currentRating);
        });
    });

    // Update stars appearance
    function updateStars(rating, isHover = false) {
        stars.forEach(star => {
            const starRating = parseInt(star.getAttribute('data-rating'));
            if (starRating <= rating) {
                star.classList.add('active');
                if (!isHover) {
                    star.style.color = '#e67e22';
                }
            } else {
                star.classList.remove('active');
                if (!isHover) {
                    star.style.color = '#555555';
                }
            }
        });
    }

    // Submit feedback
    submitFeedback.addEventListener('click', function() {
        const feedback = feedbackText.value.trim();
        
        if (!feedback) {
            showNotification('Please enter your feedback before submitting.', 'error');
            return;
        }
        
        if (currentRating === 0) {
            showNotification('Please provide a rating.', 'error');
            return;
        }
        
        // Find the order card and update/add feedback
        const orderCard = document.querySelector(`.feedback-btn[data-order-id="${currentOrderId}"]`).closest('.history-card');
        let feedbackSection = orderCard.querySelector('.order-feedback');
        
        if (!feedbackSection) {
            // Create new feedback section
            feedbackSection = document.createElement('div');
            feedbackSection.className = 'order-feedback';
            feedbackSection.innerHTML = `
                <div class="feedback-header">
                    <span class="feedback-id">Feedback #${Math.floor(Math.random() * 1000)}</span>
                    <span class="feedback-date">${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                </div>
                <div class="feedback-content">
                    <p>"${feedback}"</p>
                </div>
            `;
            
            // Insert after the history card footer
            const cardFooter = orderCard.querySelector('.history-card-footer');
            cardFooter.parentNode.insertBefore(feedbackSection, cardFooter.nextSibling);
            
            // Update button text
            const feedbackBtn = orderCard.querySelector('.feedback-btn');
            feedbackBtn.innerHTML = '<i class="fas fa-edit"></i> EDIT FEEDBACK';
        } else {
            // Update existing feedback
            feedbackSection.querySelector('.feedback-content p').textContent = `"${feedback}"`;
            feedbackSection.querySelector('.feedback-date').textContent = 
                `${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`;
        }
        
        // Show success message
        showNotification('Feedback submitted successfully!', 'success');
        
        // Close modal
        closeFeedbackModal();
        
        // In a real application, you would send this data to your server
        console.log('Feedback submitted:', {
            orderId: currentOrderId,
            feedback: feedback,
            rating: currentRating,
            timestamp: new Date().toISOString()
        });
    });

    // Notification function
    function showNotification(message, type) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.feedback-notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `feedback-notification ${type === 'error' ? 'error' : ''}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;
        
        document.body.appendChild(notification);
        
        // Add close button functionality
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', function() {
            notification.remove();
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Keyboard accessibility
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && feedbackModal.classList.contains('active')) {
            closeFeedbackModal();
        }
    });

    // Enable/disable submit button based on input
    feedbackText.addEventListener('input', function() {
        const hasFeedback = this.value.trim().length > 0;
        submitFeedback.disabled = !hasFeedback || currentRating === 0;
    });

    // Initial state
    submitFeedback.disabled = true;
});

 function redirectToOrderSummary() {
            window.location.href = '/My Order/order-summary.html';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const statusSteps = document.querySelectorAll('.status-step');
            
            statusSteps.forEach((step, index) => {
                setTimeout(() => {
                    step.style.opacity = '1';
                    step.style.transform = 'translateY(0)';
                }, index * 300);
            });
        });