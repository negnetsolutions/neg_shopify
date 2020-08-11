
var Collections = function (el, opts) {
  var _ = this;
  this.autopager = false;
  this.el = el;

  this.fetchOptions = {
    'perpage': el.dataset.perpage,
    'id': el.dataset.id,
    'type': el.dataset.type,
    'sort': el.dataset.sort,
  };

  this.opts = (typeof(opts) !== "undefined") ? opts : {};
  this.url = el.dataset.endpoint;
  this.itemsEl = el.querySelector('.items');
  this.firstPage = (_.itemsEl.children.length > 0) ? 1 : 0;
  this.sortEl = el.querySelector('#sort_order');

  if (this.sortEl !== null) {
    this.sortEl.addEventListener('change', function (e) {
      const value = e.target.options[e.target.selectedIndex].value;
      _.fetchOptions["sort"] = value;
      _.fetch();
    });
  }

  this.fetch = function () {
    if (_.autopager !== false) {
      _.autopager.unsetObserver();
      _.firstPage = 0;
    }

    _.autopager = new autoPager(_.getUrl, _.itemsEl, _.el.dataset.perpage, _.firstPage);
  }

  this.getUrl = function() {

    var str = "";
    for (var key in _.fetchOptions) {
      str += "&";
      str += key + "=" + encodeURIComponent(_.fetchOptions[key]);
    }

    if (typeof(_.opts.fetchOptionsCallback) !== "undefined") {
      var opts = _.opts.fetchOptionsCallback.call(_.opts.fetchOptionsCallbackContext);
      for (var key in opts) {
        str += "&";
        str += key + "=" + encodeURIComponent(opts[key]);
      }
    }

    return _.url + "?" + str.substring(1);
  }

  _.fetch();

};

var collections = document.querySelectorAll(".shopify_collection.autopager");
Array.prototype.forEach.call(collections, function(el) { new Collections(el) });
