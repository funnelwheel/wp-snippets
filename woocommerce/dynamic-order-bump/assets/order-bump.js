document.addEventListener("DOMContentLoaded", () => {
    const orderBumpContainer = document.getElementById("order-bump-products");
    let excludedProducts = []; // Global state for excluded products

    /**
     * Display a loading spinner in the order bump section.
     */
    const showLoadingSpinner = () => {
        orderBumpContainer.innerHTML = '<p>Loading...</p>';
    };

    /**
     * Display an error message in the order bump section.
     */
    const showError = (message = "Failed to load products. Please try again.") => {
        orderBumpContainer.innerHTML = `<p style="color: red;">${message}</p>`;
    };

    /**
     * Fetch order bump products dynamically.
     */
    const fetchOrderBumpProducts = () => {
        showLoadingSpinner();

        fetch(`${orderBumpConfig.ajaxUrl}?action=get_order_bump_products`, {
            method: "GET",
            headers: { "Content-Type": "application/json" },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    const filteredProducts = data.data.filter(
                        (product) => !excludedProducts.includes(product.id.toString())
                    );
                    renderOrderBumpProducts(filteredProducts);
                } else {
                    showError("No products available for the order bump.");
                }
            })
            .catch(() => {
                console.error("Error fetching order bump products.");
                showError();
            });
    };

    /**
     * Render order bump products dynamically in the DOM.
     */
    const renderOrderBumpProducts = (products) => {
        if (!products.length) {
            orderBumpContainer.innerHTML = "<p>No products available for the order bump.</p>";
            return;
        }

        let productsHtml = "";
        products.forEach((product) => {
            productsHtml += `
                <div class="order-bump-product">
                    <img src="${product.image}" alt="${product.name}" />
                    <div class="order-bump-info">
                        <p>${product.name}</p>
                        <p>${product.price}</p>
                        <button type="button" class="add-to-cart" data-product-id="${product.id}">Add this</button>
                    </div>
                </div>`;
        });
        orderBumpContainer.innerHTML = productsHtml;

        attachAddToCartEvent();
    };

    /**
     * Attach event listeners to "Add to Cart" buttons.
     */
    const attachAddToCartEvent = () => {
        const addButtons = document.querySelectorAll(".add-to-cart");
        addButtons.forEach((button) => {
            button.addEventListener("click", (event) => {
                event.preventDefault();
                const productId = button.getAttribute("data-product-id");
                addToCart(productId, button);
            });
        });
    };

    /**
     * Add a product to the cart.
     * @param {string} productId - The ID of the product to add.
     * @param {HTMLElement} button - The button that triggered the action.
     */
    const addToCart = (productId, button) => {
        jQuery.ajax({
            url: orderBumpConfig.ajaxUrl,
            type: "POST",
            data: {
                action: "add_product_to_cart",
                product_id: productId,
                quantity: 1,
            },
            success: (response) => {
                if (response.success) {
                    // Add product to the excluded list (state)
                    addExcludedProduct(productId);

                    // Trigger WooCommerce checkout update
                    jQuery("body").trigger("update_checkout");

                    // Remove the clicked product from the list
                    const productElement = button.closest(".order-bump-product");
                    if (productElement) {
                        productElement.remove();
                    }

                    // Display a success message
                    displayCustomMessage("Product successfully added to the cart!");
                } else {
                    alert(response.data.message);
                }
            },
            error: () => {
                alert("An error occurred while adding the product. Please try again.");
            },
        });
    };

    /**
     * Display a custom message above the checkout order review section.
     * @param {string} message - The message to display.
     */
    const displayCustomMessage = (message) => {
        const customMessage = `
            <div class="custom-checkout-message" style="padding: 10px; background: #e0ffe0; border: 1px solid #00a000; margin-top: 10px;">
                <p>${message}</p>
            </div>`;
        jQuery("#order_review").prepend(customMessage);

        setTimeout(() => {
            jQuery(".custom-checkout-message").fadeOut(300, function () {
                jQuery(this).remove();
            });
        }, 5000); // Remove message after 5 seconds
    };

    /**
     * Add a product to the excluded products list (state).
     * @param {string} productId - The product ID to exclude.
     */
    const addExcludedProduct = (productId) => {
        if (!excludedProducts.includes(productId)) {
            excludedProducts.push(productId);
        }
    };

    // Fetch and render order bump products on page load
    fetchOrderBumpProducts();

    // Refresh order bump products whenever the cart is updated
    jQuery("body").on("updated_cart_totals updated_checkout", fetchOrderBumpProducts);
});
