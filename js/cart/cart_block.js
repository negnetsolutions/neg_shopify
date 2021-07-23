const cartBlock = new function cartBlock() {
  var _ = this;
  this.el = document.querySelector('#block-shopifyshoppingcart');
  this.isRegistered = false;

  if (this.el == null) {
    return;
  }

  this.cart = this.el.querySelector('.shopify_cart');
  this.overlay = this.el.querySelector('.mini-cart-overlay');
  this.closeButton = this.el.querySelector('.close-cart');
  this.cartLinks = document.querySelectorAll("a[href^='/cart']");

  this.close = function close() {
    _.el.classList.remove("open");
  };

  this.open = function open() {
    if (_.isRegistered === false) {
      _.cart.cartController.registerForUpdates();
    }
    _.el.classList.add("open");
  };

  this.overlay.addEventListener("click", function(event) {
    if (event.target === _.overlay) {
      _.close();
    }
  });

  this.closeButton.addEventListener("click", function (event) {
    _.close();
  });

  // Setup cartLinks interception.
  for (let i = 0; i < this.cartLinks.length; i++) {
    this.cartLinks[i].addEventListener("click", function(event) {
      event.preventDefault();
      event.stopPropagation();
      _.open();
    });
  }

}();
