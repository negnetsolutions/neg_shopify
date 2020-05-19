const cartPage = new function () {
  var _ = this;
  this.el = document.querySelector('#cart');

  if (this.el === null) {
    return;
  }

  this.cartWrapper = this.el.querySelector('.cart_wrapper');
  this.checkoutBtn = this.cartWrapper.querySelector('.checkout-btn');

  this.createElementFromHTML = function (htmlString) {
    var div = document.createElement('div');
    div.innerHTML = htmlString.trim();

    // Change this to div.childNodes to support multiple top-level nodes
    return div.firstChild; 
  }

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
    shopping_cart.checkout();
    e.preventDefault();
    e.stopPropagation();
  };

  this.renderCart = function renderCart(cart) {

    if (cart.length === 0) {
      return;
    }

    if (cart.items.length === 0) {
      _.cartWrapper.innerHTML = "<div class='cart-item-list text'><h2>Your cart is currently empty!</h2><p>No worries though, plenty to choose from right over <a href='" + drupalSettings.cart.emptyRedirect + "'>here</a>.</p></div";
    }
    else {
      let subtotal = _.cartWrapper.querySelector('.subtotal > .value');
      let itemList = _.cartWrapper.querySelector('.item-list');

      _.checkoutBtn.addEventListener('click', _.checkout);
      itemList.addEventListener('change', _.updateItemCount);
      itemList.addEventListener('click', _.removeItem);

      // Set subtotal.
      const price = "$" + (cart.total / 100).toFixed(2);

      // Build the cart items view.
      let fragment = document.createDocumentFragment();
      for (let i = 0; i < cart.items.length; i++) {
        let el = _.createElementFromHTML(cart.items[i].view);
        let number = el.querySelector('.item-qty input');
        number.value = cart.items[i].quantity;
        el.setAttribute('data-variant-id', cart.items[i].variantId);
        fragment.appendChild(el);
      }

      window.requestAnimationFrame(function() {
        subtotal.innerHTML = price;
        itemList.innerHTML = '';
        itemList.appendChild(fragment);
      });
    }

  };

  shopping_cart.registerObserver(this.renderCart);
  this.renderCart(shopping_cart.cart);

}();
