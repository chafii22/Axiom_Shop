class CartService {
    static async addToCart(productId, quantity = 1) {
        const result = await this._makeRequest('add', { product_id: productId, quantity });
        if (result.status === 'success' && result.data?.cart_count !== undefined) {
            // Update the cart badge in the navigation
            updateCartCountBadge(result.data.cart_count);
        }
        return result;
    }
    
    static async removeFromCart(productId) {
        const result = await this._makeRequest('remove', { product_id: productId });
        if (result.status === 'success' && result.data?.cart_count !== undefined) {
            updateCartCountBadge(result.data.cart_count);
        }
        return result;
    }
    
    static async updateQuantity(productId, change) {
        const result = await this._makeRequest('update', { product_id: productId, change });
        if (result.status === 'success' && result.data?.cart_count !== undefined) {
            updateCartCountBadge(result.data.cart_count);
        }
        return result;
    }
    
    static async clearCart() {
        const result = await this._makeRequest('clear');
        if (result.status === 'success') {
            updateCartCountBadge(0);
        }
        return result;
    }
    
    static async getCart() {
        return this._makeRequest('get');
    }
    
    static async _makeRequest(action, params = {}) {
        const formData = new URLSearchParams();
        formData.append('action', action);
        
        for (const key in params) {
            formData.append(key, params[key]);
        }
        
        const response = await fetch('api/cart_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        return await response.json();
    }
}


class Shop {
    constructor() {
        this.productContainer = document.querySelector('.product-grid');
        this.categoryIcons = document.querySelectorAll('.category-icon-item');
   
        this.modal = document.getElementById('product-modal');
        this.sidebarToggle = document.getElementById("sidebar-toggle");
        this.sidebar = document.querySelector('.sticky-sidebar');
        
        this.initEventListeners();
        this.initSidebar();
    }

    initSidebar() {
        // Check if sidebar was collapsed in previous session
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            this.sidebar.classList.add('collapsed');
        }
        
        // Add event listener for sidebar toggle
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.sidebar.classList.toggle('collapsed');
                
                // Store preference in localStorage
                localStorage.setItem('sidebarCollapsed', this.sidebar.classList.contains('collapsed'));
            });
        }
    }

    
    loadImages() {
        return new Promise(resolve => {
            const images = document.querySelectorAll('.product-image');
            let loadedImages = 0;
            const totalImages = images.length;

            if (totalImages === 0) {
                resolve();
                return;
            }

            images.forEach(img => {
                if (img.complete) {
                    loadedImages++;
                    if (loadedImages === totalImages) {
                        resolve();
                    }
                } else {
                    img.addEventListener('load', () => {
                        loadedImages++;
                        if (loadedImages === totalImages) {
                            resolve();
                        }
                    });

                    img.addEventListener('error', () => {
                        loadedImages++;
                        if (loadedImages === totalImages) {
                            resolve();
                        }
                    });
                }
            });
        });
    }
    
    initEventListeners() {
        
        
        // Category filtering
        if (this.categoryIcons.length > 0 && this.productContainer) {
            this.categoryIcons.forEach(icon => {
                icon.addEventListener('click', (e) => this.handleCategoryFilter(e, icon));
            });
        }
        
        
        // Initial attachment of product event listeners
        this.attachProductEventListeners();
        
        // Product click for modal
        document.addEventListener('click', (event) => {
            // Handle add to cart button clicks
            if (event.target.classList.contains('add-to-cart-btn') || 
                event.target.closest('.add-to-cart-btn')) {
                const button = event.target.classList.contains('add-to-cart-btn') ? 
                    event.target : event.target.closest('.add-to-cart-btn');
                this.addToCart(button.dataset.productId, button);
                event.stopPropagation();
            }
            
            // Handle wishlist button clicks
            if (event.target.classList.contains('wishlist-btn') || 
                event.target.closest('.wishlist-btn')) {
                const button = event.target.classList.contains('wishlist-btn') ? 
                    event.target : event.target.closest('.wishlist-btn');
                this.toggleWishlist(button);
                event.stopPropagation();
            }
            
            // Handle product image clicks
            if (event.target.classList.contains('product-image')) {
                const productItem = event.target.closest('.product-item');
                this.openProductModal(productItem);
            }
        });
        
        // Modal event listeners
        if (this.modal) {
            // Close button
            const closeBtn = this.modal.querySelector('.close-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeModal());
            }
            
            // Close when clicking outside
            window.addEventListener('click', (event) => {
                if (event.target === this.modal) {
                    this.closeModal();
                }
            });
            
            // Star rating in modal
            const stars = this.modal.querySelectorAll('.stars i');
            stars.forEach(star => {
                star.addEventListener('click', () => this.rateProduct(star));
                
                // Hover effects
                star.addEventListener('mouseover', () => this.highlightStars(star));
                star.addEventListener('mouseout', () => this.resetStars());
            });
            
            // Add to cart from modal
            const modalAddBtn = this.modal.querySelector('.modal-add-to-cart');
            if (modalAddBtn) {
                modalAddBtn.addEventListener('click', () => {
                    const productId = this.modal.getAttribute('data-product-id');
                    this.addToCart(productId, modalAddBtn);
                });
            }
        }
    }
    
    
    
    handleCategoryFilter(e, icon) {
        e.preventDefault();
        
        // Remove active class from all icons
        this.categoryIcons.forEach(item => item.classList.remove('active'));
        
        // Add active class to clicked icon
        icon.classList.add('active');
        
        const selectedCategory = icon.getAttribute('data-category');
        
        // Show loading indicator
        this.productContainer.innerHTML = '<div class="loading">Loading products...</div>';
        
        // Update URL without refreshing page
        this.updateURL(selectedCategory);
        
        // Reset to page 1 when changing categories
        let url = 'api/get_products.php';
        if (selectedCategory) {
            url += '?category_id=' + selectedCategory;
        }

        console.log('Fetching products from URL:', url);
        
        // Fetch filtered products
        fetch(url)
            .then(response => {
                console.log('category filter response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                console.log('Category data received, length:', data.length);
                // Replace the product list with new data
                this.productContainer.innerHTML = data;
                
                

                // Re-attach event listeners to the new product items
                this.attachProductEventListeners();
            })
            .catch(error => {
                console.error('Error fetching products:', error);
                this.productContainer.innerHTML = '<p class="error">Error loading products. Please try again later.</p>';
            });
    }
    


    attachProductEventListeners() {
        // Product item event listeners
        const productItems = this.productContainer.querySelectorAll('.product-item');
        
        productItems.forEach(item => {
            // Add to cart button
            const addToCartBtn = item.querySelector('.add-to-cart-btn');
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.addToCart(addToCartBtn.dataset.productId, addToCartBtn);
                });
            }
            
            // Wishlist button
            const wishlistBtn = item.querySelector('.wishlist-btn');
            if (wishlistBtn) {
                wishlistBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleWishlist(wishlistBtn);
                });
            }
            
            // Product image
            const productImage = item.querySelector('.product-image');
            if (productImage) {
                productImage.addEventListener('click', () => {
                    this.openProductModal(item);
                });
            }
        });
    }
    

    
    updateURL(categoryId) {
        const url = new URL(window.location);
        
        if (categoryId) {
            url.searchParams.set('category_id', categoryId);
        } else {
            url.searchParams.delete('category_id');
        }
        
        window.history.pushState({}, '', url);
    }
    
   
    addToCart(productId, buttonElement) {
        CartService.addToCart(productId, 1)
        .then(data => {
            if (data.status === 'success') {
                this.showNotification(data.message, 'success');
                this.updateCartCounter(data.data.cart_count);
                
                // Add animation to button
                buttonElement.classList.add('added');
                setTimeout(() => {
                    buttonElement.classList.remove('added');
                }, 1000);
            } else {
                this.showNotification(data.message || 'Unknown error', 'error');
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            this.showNotification('Error adding to cart', 'error');
        });
    }
    
    toggleWishlist(button) {
        const productId = button.getAttribute('data-product-id');
        
        fetch('api/wishlist_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle&product_id=' + productId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.in_wishlist) {
                    button.classList.add('in-wishlist');
                    this.showNotification('Added to wishlist', 'success');
                } else {
                    button.classList.remove('in-wishlist');
                    this.showNotification('Removed from wishlist', 'success');
                }
            } else {
                this.showNotification(data.message || 'Error updating wishlist', 'error');
            }
        })
        .catch(error => {
            console.error('Error updating wishlist:', error);
            this.showNotification('Error updating wishlist', 'error');
        });
    }
    
    openProductModal(productItem) {
        const productId = productItem.getAttribute('data-product-id');
        const productName = productItem.querySelector('h3').textContent;
        const productPrice = productItem.querySelector('.price').textContent.replace('$', '');
        
        // Get data from hidden div
        const productData = productItem.querySelector('.product-data');
        const adminName = productData.querySelector('[data-type="admin"]').textContent;
        const estimateTime = productData.querySelector('[data-type="estimate"]').textContent;
        const imageSrc = productData.querySelector('[data-type="image"]').textContent;
        
        // Set modal data
        this.modal.setAttribute('data-product-id', productId);
        this.modal.querySelector('#modal-name').textContent = productName;
        this.modal.querySelector('#modal-price').textContent = productPrice;
        this.modal.querySelector('#modal-admin').textContent = adminName;
        this.modal.querySelector('#modal-estimate').textContent = estimateTime;
        this.modal.querySelector('#modal-image').src = imageSrc;
        
        // Get rating data from API
        this.fetchProductRating(productId);
        
        // Show the modal
        this.modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
    }
    
    closeModal() {
        this.modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Re-enable scrolling
    }
    
    fetchProductRating(productId) {
        fetch(`api/get_product_rating.php?product_id=${productId}`)
            .then(response => response.json())
            .then(data => {
                const ratingAvg = this.modal.querySelector('.rating-avg');
                const ratingCount = this.modal.querySelector('.rating-count');
                const stars = this.modal.querySelectorAll('.stars i');
                
                ratingAvg.textContent = data.average.toFixed(1);
                ratingCount.textContent = `(${data.count} ratings)`;
                
                // Update stars visual
                stars.forEach((star, index) => {
                    if (index < Math.floor(data.average)) {
                        star.className = 'fas fa-star';
                    } else if (index < data.average) {
                        star.className = 'fas fa-star-half-alt';
                    } else {
                        star.className = 'far fa-star';
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching product rating:', error);
            });
    }
    
    rateProduct(star) {
        const rating = parseInt(star.getAttribute('data-rating'));
        const productId = this.modal.getAttribute('data-product-id');
        
        fetch('api/rate_product.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&rating=${rating}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showNotification('Thank you for rating!', 'success');
                this.fetchProductRating(productId); // Refresh rating display
            } else {
                this.showNotification(data.message || 'Error submitting rating', 'error');
            }
        })
        .catch(error => {
            console.error('Error rating product:', error);
            this.showNotification('Error submitting rating', 'error');
        });
    }
    
    highlightStars(selectedStar) {
        const rating = parseInt(selectedStar.getAttribute('data-rating'));
        const stars = this.modal.querySelectorAll('.stars i');
        
        stars.forEach((star, index) => {
            if (index < rating) {
                star.className = 'fas fa-star';
            } else {
                star.className = 'far fa-star';
            }
        });
    }
    
    resetStars() {
        // Re-fetch current rating to reset stars correctly
        const productId = this.modal.getAttribute('data-product-id');
        this.fetchProductRating(productId);
    }
    
    updateCartCounter(count) {
        const cartCounter = document.querySelector('.cart-counter');
        if (cartCounter) {
            cartCounter.textContent = count;
            cartCounter.classList.add('bounce');
            setTimeout(() => {
                cartCounter.classList.remove('bounce');
            }, 1000);
        }
    }
    
    showNotification(message, type) {
        // Remove any existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        // Add to body
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.remove();
            }, 400); // Match with animation duration
        }, 3000);
    }
}




document.addEventListener("DOMContentLoaded", function() {
    const shop = new Shop();
});

