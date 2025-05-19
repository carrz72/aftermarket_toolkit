/**
 * Forum response formatting and functionality
 */
document.addEventListener('DOMContentLoaded', function() {
  // Format all forum responses for proper content fit
  document.querySelectorAll('.forum-response').forEach(response => {
    // Get the response content element
    const content = response.querySelector('.response-content');
    
    // Ensure all response boxes have proper sizing based on content
    if (content) {
      // Get the actual content width
      const textContent = content.querySelector('.response-body');
      if (textContent && textContent.offsetWidth > 0) {
        // Set minimum width to accommodate the text
        response.style.minWidth = Math.min(
          Math.max(200, textContent.scrollWidth + 40), // At least 200px, but expand for content
          response.parentElement.offsetWidth * 0.9 // Maximum of 90% of parent width
        ) + 'px';
      }
    }
  });
});