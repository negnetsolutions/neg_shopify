var autoPager = function (endpoint, el, perPage, first_page) {
  var _ = this;
  this.next_page = first_page;
  this.working = false;
  this.el = el;
  this.perPage = perPage;
  this.total_pages = false;
  this.endpoint = endpoint;
  this.lastObserved;
  this.lastFetchCount = 0;
  this.isLoading = false;
  this.xobj = new XMLHttpRequest();
  this.debug = false;

  this.log = function(message) {
    if (_.debug) {
      console.debug(message);
    }
  }
  this.calculateTotalPages = function (total) {
    _.total_pages = Math.floor(parseInt(total, 10) / parseInt(_.perPage, 10));
  }

  this.clearItems = function () {
    _.el.innerHTML = "";
  }

  this.createElementFromHTML = function (htmlString) {
    var div = document.createElement('div');
    div.innerHTML = htmlString.trim();

    // Change this to div.childNodes to support multiple top-level nodes
    return div.firstChild; 
  }

  this.setFinishedLoading = function () {
    // Remove loading.
    var loading = _.el.querySelector('.col.loading');
    if (loading !== null) {
      _.el.removeChild(loading);
    }
    _.isLoading = false;
  }

  this.setLoading = function () {
    if (!_.isLoading) {
      var el = _.createElementFromHTML("<div class='col loading' data-page'" + _.next_page + "'><span class='text'>Loading...</span><div class='progress'></div></div>");
      _.el.appendChild(el);
      _.isLoading = true;
    }
  }

  // Check if there is already data pre-loaded.
  if (first_page !== 0) {
    var count = _.el.dataset.initialcount;
    _.lastFetchCount = _.el.children.length;
    _.calculateTotalPages(count);
  }
  else {
    // Remove innerHTML.
    _.clearItems();
    _.setLoading();
  }

  this.observer = new IntersectionObserver(function (entries) {
    var firstEntry = entries[0];
    if (firstEntry.isIntersecting) {
      _.unsetObserver();
      _.fetch();
    }
  });

  this.checkIfDone = function () {
    if( _.total_pages !== false && _.next_page > _.total_pages ){
      return true;
    }
    return false;
  }

  this.fetch = function() {
    if (_.working == true) {
      return false;
    }
    if (_.checkIfDone()){
      _.complete();
      return;
    }

    _.setLoading();
    var endpoint = _.endpoint() + "&page=" + _.next_page;

    _.working = true;
    this.loadJSON(endpoint, function(data){
      _.calculateTotalPages(data.count);
      _.log("Fetched page " + (_.next_page)  + " of " + _.total_pages + " " + data.count + " items.");

      _.lastFetchCount = data.items.length;

      var fragment = document.createDocumentFragment();
      for (var i = 0; i < data.items.length; i++) {
        var item = data.items[i];
        var el = _.createElementFromHTML(item);
        fragment.appendChild(el);
      }

      window.requestAnimationFrame(function() {
        _.setFinishedLoading();
        _.el.appendChild(fragment);
        _.next_page++;
        _.working = false;
        _.setObserver();
      });

    });

  }

  this.loadJSON = function (endpoint, callback) {

    _.log(endpoint);

    _.xobj.overrideMimeType("application/json");
    _.xobj.open('GET', endpoint, true); // Replace 'my_data' with the path to your file
    _.xobj.onreadystatechange = function () {
      if (_.xobj.readyState == 4 && _.xobj.status == "200") {
        // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
        var data = JSON.parse(_.xobj.responseText);
        callback.call(_, data);
      }
    };
    _.xobj.send(null);
  }

  this.complete = function () {
    _.unsetObserver();
  }

  this.setObserver = function () {

    if (_.el.children.length === 0) {
      return;
    }

    var midpoint = Math.floor(_.lastFetchCount * .67);
    if (midpoint === 0) {
      midpoint = 1;
    }
    var el = _.el.children[_.el.children.length - midpoint];

    var name = el.querySelector('.name');
    if (name !== null) {
      el = el.querySelector('.name');
    }

    _.lastObserved = el;
    _.observer.observe(el);
  }

  this.unsetObserver = function () {
    _.observer.unobserve(_.lastObserved);
    _.xobj.abort();
    _.working = false;
  }

  _.setObserver();

};
