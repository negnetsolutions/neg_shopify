const cartController = function (el) {
  var _ = this;
  this.el = el;

  if (this.el === null) {
    return;
  }

  this.cartWrapper = this.el.querySelector('.cart_wrapper');
  this.checkoutBtn = this.cartWrapper.querySelector('.checkout-btn');

  this.updateItemCount = function updateItemCount(e) {
    const target = e.target;

    if (target.nodeName === 'INPUT') {
      let parentNode = target.closest('li');

      // Update the cart value.
      shopping_cart.updateItem(parentNode.getAttribute('data-variant-id'), target.value);

      e.preventDefault();
      e.stopPropagation();
    }
  };

  this.removeItem = function removeItem(e) {
    const target = e.target;

    let button = target.closest('.item-remove');
    if (button) {
      let parentNode = target.closest('li');

      // Update the cart value.
      shopping_cart.updateItem(parentNode.getAttribute('data-variant-id'), 0);

      e.preventDefault();
      e.stopPropagation();
    }
  };

  this.checkout = function checkout(e) {
    _.checkoutBtn.value = 'Please Wait';
    _.checkoutBtn.disabled = true;
    shopping_cart.checkout(function(data) {
      if (data.status !== 'OK') {
        _.checkoutBtn.value = 'Checkout';
        _.checkoutBtn.disabled = false;
      }
    });
    e.preventDefault();
    e.stopPropagation();
  };

  this.renderCart = function renderCart(cart) {
    if (cart.length === 0) {
      return;
    }

    let fragment = document.createDocumentFragment();
    const itemsWrapper = _.cartWrapper.querySelector('.items');
    const subtotalWrapper = _.cartWrapper.querySelector('.subtotal');
    const checkoutWrapper = _.cartWrapper.querySelector('.checkout-form');
    const formWrapper = _.cartWrapper.querySelector('form');
    const emptyWrapper = formWrapper.querySelector('.cart-item-list.text');

    if (cart.items.length === 0) {

      const el = createElementFromHTML("<div class='cart-item-list text'><h2>Your cart is currently empty!</h2><p>No worries though, plenty to choose from right over <a href='" + drupalSettings.cart.emptyRedirect + "'>here</a>.</p></div");
      fragment.appendChild(el);

      subtotalWrapper.style.display = 'none';
      checkoutWrapper.style.display = 'none';
      itemsWrapper.style.display = 'none';
      if (!emptyWrapper) {
        formWrapper.appendChild(fragment);
      }
    }
    else {
      let subtotal = _.cartWrapper.querySelector('.subtotal > .value');
      let itemList = _.cartWrapper.querySelector('.item-list');

      subtotalWrapper.removeAttribute("style");
      checkoutWrapper.removeAttribute("style");
      itemsWrapper.removeAttribute("style");
      if (emptyWrapper) {
        emptyWrapper.remove();
      }

      _.checkoutBtn.addEventListener('click', _.checkout);
      itemList.addEventListener('change', _.updateItemCount);
      itemList.addEventListener('click', _.removeItem);

      // Set subtotal.
      var formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
      });

      const price = formatter.format(cart.total / 100);

      // Build the cart items view.
      for (let i = 0; i < cart.items.length; i++) {
        let el = createElementFromHTML(cart.items[i].view);
        let number = el.querySelector('.item-qty input');
        number.value = cart.items[i].quantity;
        el.setAttribute('data-variant-id', cart.items[i].variant_id);
        fragment.appendChild(el);
      }

      window.requestAnimationFrame(function() {
        subtotal.innerHTML = price;
        itemList.innerHTML = '';
        itemList.appendChild(fragment);
      });
    }

  };

  this.registerForUpdates = function registerForUpdates() {
    shopping_cart.registerObserver(this.renderCart);
    this.renderCart(shopping_cart.cart);
  };

};

(function processCarts() {
  const carts = document.querySelectorAll('.shopify_cart');
  for (let i = 0; i < carts.length; i++) {
    carts[i].cartController = new cartController(carts[i]);
    if (carts[i].classList.contains('cart-page')) {
      carts[i].cartController.registerForUpdates();
    }
  }
}());
