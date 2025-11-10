let currentFeedbackId = null;

// Mobile menu toggle
document.getElementById('burger-menu').addEventListener('click', function() {
    document.getElementById('main-nav').classList.toggle('active');
});

// Show delete confirmation modal
function confirmDeletion(id) {
    currentFeedbackId = id;
    document.getElementById('deleteId').textContent = id;
    document.getElementById('deleteModal').style.display = 'flex';
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Delete feedback
function deleteFeedback() {
    // In a real application, this would be an API call
    console.log('Deleting feedback with ID: ' + currentFeedbackId);
    
    // Show success message
    const alert = document.querySelector('.alert.success');
    alert.style.display = 'block';
    
    // Hide the alert after 3 seconds
    setTimeout(() => {
        alert.style.display = 'none';
    }, 3000);
    
    // Close modal
    closeModal('deleteModal');
    
    // In a real app, you would remove the feedback card from the DOM
    // or refresh the page after deletion
}

// Show reply modal
function showReplyModal(feedbackText) {
    document.getElementById('originalFeedback').textContent = feedbackText;
    document.getElementById('replyModal').style.display = 'flex';
}

// Send reply
function sendReply() {
    const replyMessage = document.getElementById('replyMessage').value;
    
    if (replyMessage.trim() === '') {
        alert('Please enter a reply message');
        return;
    }
    
    // In a real application, this would send the reply via API
    console.log('Sending reply: ' + replyMessage);
    
    // Show success message (you could create a specific success message for replies)
    const alert = document.querySelector('.alert.success');
    alert.textContent = 'Reply sent successfully!';
    alert.style.display = 'block';
    
    // Hide the alert after 3 seconds
    setTimeout(() => {
        alert.style.display = 'none';
        alert.textContent = 'Feedback deleted successfully.';
    }, 3000);
    
    // Close modal
    closeModal('replyModal');
}

// Add event listeners to reply buttons
document.addEventListener('DOMContentLoaded', function() {
    const replyButtons = document.querySelectorAll('.reply-btn');
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const feedbackCard = this.closest('.feedback-card');
            const feedbackText = feedbackCard.querySelector('.feedback-message p').textContent;
            showReplyModal(feedbackText);
        });
    });
});