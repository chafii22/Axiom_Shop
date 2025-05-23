/**
 * Updates the cart count in the navigation bar
 * @param {number} count - The new cart count
 */
function updateCartCountBadge(count) {
    const badge = document.getElementById('cart-count-badge');
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}