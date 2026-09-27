/**
 * AJAX Cart Handler for Cascade E-Commerce
 */
window.CascadeCart = (function() {
  'use strict';

  // Add product to cart asynchronously
  async function addToCart(productId, quantity = 1, options = {}) {
    const btn = options.buttonElement;
    if (btn) Cascade.buttonLoading(btn, true, 'Ajout...');

    try {
      const response = await fetch('api/cart.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ action: 'add', product_id: productId, quantity: quantity })
      });
      
      const data = await response.json();
      
      if (data.success) {
        Cascade.toast(data.message || 'Produit ajouté au panier !', 'success');
        updateCartBadge(data.cart_count);
        refreshCartDrawer();
        if (options.openDrawer !== false) {
          Cascade.drawer.open('cart-drawer');
        }
      } else {
        Cascade.toast(data.error || 'Impossible d\'ajouter au panier', 'error');
      }
    } catch (err) {
      Cascade.toast('Erreur lors de l\'ajout au panier', 'error');
    } finally {
      if (btn) Cascade.buttonLoading(btn, false);
    }
  }

  // Update item quantity in cart
  async function updateQuantity(productId, quantity) {
    try {
      const response = await fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', product_id: productId, quantity: quantity })
      });
      const data = await response.json();
      if (data.success) {
        updateCartBadge(data.cart_count);
        refreshCartDrawer();
      }
    } catch (err) {
      Cascade.toast('Erreur de mise à jour', 'error');
    }
  }

  // Remove item from cart
  async function removeItem(productId) {
    try {
      const response = await fetch('api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', product_id: productId })
      });
      const data = await response.json();
      if (data.success) {
        Cascade.toast('Article retiré du panier', 'info');
        updateCartBadge(data.cart_count);
        refreshCartDrawer();
      }
    } catch (err) {
      Cascade.toast('Erreur de suppression', 'error');
    }
  }

  // Helper to update header cart count badge
  function updateCartBadge(count) {
    const badges = document.querySelectorAll('.js-cart-count');
    badges.forEach(b => {
      b.textContent = count;
      b.style.display = count > 0 ? 'inline-flex' : 'none';
    });
  }

  // Refresh Cart Drawer content
  async function refreshCartDrawer() {
    const drawerBody = document.querySelector('#cart-drawer .drawer-body');
    if (!drawerBody) return;

    try {
      const response = await fetch('api/cart.php?action=get');
      const data = await response.json();

      if (!data.items || data.items.length === 0) {
        drawerBody.innerHTML = `
          <div style="text-align: center; padding: 40px 20px; color: var(--color-text-muted);">
            <i class="fas fa-shopping-bag" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
            <p>Votre panier est vide</p>
            <a href="catalog.php" class="btn btn-primary" style="margin-top: 16px;">Découvrir les produits</a>
          </div>
        `;
        const totalEl = document.querySelector('#cart-drawer .cart-total');
        if (totalEl) totalEl.textContent = '0.00 $';
        return;
      }

      drawerBody.innerHTML = data.items.map(item => `
        <div style="display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--color-border);">
          <img src="${item.image || 'assets/images/default.png'}" style="width: 60px; height: 60px; object-fit: cover; border-radius: var(--radius-sm);" />
          <div style="flex: 1;">
            <h4 style="font-size: 14px; margin-bottom: 4px;">${item.name}</h4>
            <div style="color: var(--color-primary); font-weight: 600; font-size: 14px;">${item.price} $</div>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 6px;">
              <button onclick="CascadeCart.updateQuantity(${item.id}, ${item.quantity - 1})" class="btn btn-secondary btn-sm" style="padding: 2px 8px;">-</button>
              <span style="font-size: 14px;">${item.quantity}</span>
              <button onclick="CascadeCart.updateQuantity(${item.id}, ${item.quantity + 1})" class="btn btn-secondary btn-sm" style="padding: 2px 8px;">+</button>
            </div>
          </div>
          <button onclick="CascadeCart.removeItem(${item.id})" style="background: none; border: none; color: var(--color-error); cursor: pointer; align-self: flex-start;">&times;</button>
        </div>
      `).join('');

      const totalEl = document.querySelector('#cart-drawer .cart-total');
      if (totalEl) totalEl.textContent = `${data.total} $`;

    } catch (err) {
      console.error('Failed to refresh cart:', err);
    }
  }

  return {
    add: addToCart,
    updateQuantity: updateQuantity,
    removeItem: removeItem,
    refresh: refreshCartDrawer
  };
})();
