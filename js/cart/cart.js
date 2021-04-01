const shopping_cart = new function (){
  var _ = this;
  this.xobj = new XMLHttpRequest();
  this.cart = [];
  this.endpoint = drupalSettings.cart.endpoint;
  this.cartObservers = [];
  this.debug = false;
  this.cacheTTL = 60000; // 60 second cache.

  this.getCache = function getCache(name) {
    if (window.localStorage) {
      const created = localStorage.getItem('created_' + name);
      if (Number(created) + _.cacheTTL > Number(Date.now())) {
        return localStorage.getItem(name);
      }
    }

    return null;
  };

  this.setCache = function setCache(name, value) {
    if (window.localStorage) {
      _.log('Caching Cart Data');
      localStorage.setItem(name, value);
      localStorage.setItem('created_' + name, Date.now());
    }
  };

  this.registerObserver = function registerObserver(callback) {
    _.cartObservers.push(callback);

    if (typeof _.cart !== 'undefined' && typeof _.cart.items !== 'undefined') {
      callback(_.cart);
    }
  }

  this.log = function log(message) {
    if (_.debug === true) {
      console.debug(message);
    }
  };

  this.request = function loadJSON(params, callback) {

    _.log(params);
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
        // Process data.
        _.handleData(data, callback);

        if (typeof data.cart !== 'undefined') {
          // Clear any redirects from the cache.
          delete data.redirectToCart;
          delete data.redirect;

          // Save data for cache.
          _.setCache('cart', JSON.stringify(data));
        }
      }
    };
    _.xobj.send(null);
  };

  this.handleData = function handleData(data, callback) {
    _.cart = data.cart;

    if (typeof callback !== 'undefined') {
      callback.call(_, data);
    }

    if (typeof data.cart !== 'undefined' && typeof data.cart.items !== 'undefined') {
      // Notify observers.
      for (let i = 0; i < _.cartObservers.length; i++) {
        _.cartObservers[i](data.cart);
      }
    }


    if (typeof data.redirectToCart !== 'undefined') {
      if (typeof cartBlock !== 'undefined') {
        cartBlock.open();
      }
      else {
        window.location = drupalSettings.cart.cartPage;
      }
      return false;
    }

    if (typeof data.redirect !== 'undefined') {
      window.location = data.redirect;
      return false;
    }

    if (data.cart.hasOwnProperty("checkoutStarted") && data.cart.checkoutStarted === true) {
      _.stopCheckout();
    }

    return true;
  };

  this.checkDataConsitency = function checkDataConsitency(d) {
    try {
      const data = JSON.parse(d);
      if (typeof data.status === 'undefined' || data.status != 'OK') {
        return false;
      }
    }
    catch (e) {
      console.debug("json parse error");
      return false;
    }
    return true;

  };

  this.loadCart = function loadCart() {
    const data = _.getCache('cart');

    if (data !== null && _.checkDataConsitency(data)) {
      setTimeout(function() {
        _.log('Using Cached Cart Data');
        _.handleData(JSON.parse(data));
      }, 30);
      return;
    }

    _.request(
      {
        request: 'render'
      }
    );

  };

  this.findItemByVariantId = function findItemByVariantId(variant_id) {
    for (let i = 0; i < _.cart.items.length; i++) {
      let item = _.cart.items[i];
      if (item.variantId == variant_id) {
        return item;
      }
    }

    return null;
  };

  this.updateItem = function updateItem(variant_id, qty) {
    // Attempt to queue analytics event.
    if (typeof events === 'object') {
      let item = _.findItemByVariantId(variant_id);
      let qtyDiff = qty - item.quantity;
      if (qtyDiff === 0) {
        return;
      }

      let event = (qtyDiff > 0) ? 'addToCart' : 'removeFromCart';
      const details = {
        'sku': item.sku,
        'qty': Math.abs(qtyDiff)
      };

      events.triggerEvent(event, details);
    }

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
    // Attempt to queue analytics event.
    if (typeof events === 'object') {

      let items = [];
      for (let i = 0; i < _.cart.items.length; i++) {
        let item = _.cart.items[i];
        items.push({
          'sku': item.sku,
          'qty': item.quantity
        });
      }

      const details = {
        'items': items
      };

      events.triggerEvent('checkout', details);
    }

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
        btn.value = 'Add to Cart';
        btn.disabled = false;
      }
    );

    return false;
  };

  _.loadCart();
}();
