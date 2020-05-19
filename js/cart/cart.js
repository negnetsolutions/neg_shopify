const shopping_cart = new function (){
  var _ = this;
  this.xobj = new XMLHttpRequest();
  this.cart = [];
  this.endpoint = drupalSettings.cart.endpoint;
  this.cartObservers = [];

  this.registerObserver = function registerObserver(callback) {
    _.cartObservers.push(callback);
  }

  this.request = function loadJSON(params, callback) {

    console.debug(params);
    params.json = true;
    params.update = true;

    var queryString = Object.keys(params)
      .map(function (key) {
        return key + '=' + params[key];
      })
      .join('&');

    let endpoint = _.endpoint + '?' + queryString;

    _.xobj.abort();
    _.xobj.overrideMimeType('application/json');
    _.xobj.open('GET', endpoint, true);
    _.xobj.onreadystatechange = function () {
      if (_.xobj.readyState === 4 && _.xobj.status === 200) {
        var data = JSON.parse(_.xobj.responseText);
        _.cart = data.cart;

        if (typeof data.redirectToCart !== 'undefined') {
          window.location = drupalSettings.cart.cartPage;
          return;
        }

        if (typeof data.redirect !== 'undefined') {
          window.location = data.redirect;
          return;
        }

        if (data.cart.hasOwnProperty("checkoutStarted") && data.cart.checkoutStarted === true) {
          // Check to see if this is the cart page.
          if (window.location.pathname === drupalSettings.cart.cartPage) {
            console.debug("Checkout Stopped");
            _.stopCheckout();
          }
          else {
            console.debug("Checkout finished. Clearing cart...");
            _.resetCart();
            return;
          }
        }

        if (typeof callback !== 'undefined') {
          callback.call(_, data);
        }

        if (typeof data.cart.items !== 'undefined') {
          // Notify observers.
          for (let i = 0; i < _.cartObservers.length; i++) {
            _.cartObservers[i](data.cart);
          }
        }

      }
    };
    _.xobj.send(null);
  };

  this.loadCart = function loadCart() {
    _.request(
      {
        request: 'render'
      }
    );

  };

  this.updateItem = function updateItem(variant_id, qty) {
    _.request(
      {
        request: 'update',
        variantId: variant_id,
        qty: qty,
      });
  };

  this.resetCart = function resetCart() {
    _.request(
      {
        request: 'resetCart'
      });
  };

  this.stopCheckout = function stopCheckout() {
    _.request(
      {
        request: 'stopCheckout'
      });
  };

  this.checkout = function checkout() {
    _.request(
      {
        request: 'checkout'
      });
  };

  this.addToCart = function addToCart(e) {
    const form = document.querySelector('.shopify-add-to-cart-form');
    const variant_id = form.getAttribute('data-variant-id');
    const qty = form.querySelector('.form-item-quantity input').value
    const btn = form.querySelector('.form-submit');
    btn.value = 'Adding to Cart...';
    btn.disabled = true;

    _.request(
      {
        request: 'add',
        variantId: variant_id,
        qty: qty,
      }, function (data) {
        btn.value = 'Added to Cart!';
      }
    );

    return false;
  };

  _.loadCart();
}();
