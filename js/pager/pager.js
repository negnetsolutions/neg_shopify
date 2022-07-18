var Pager = function (el, opts) {
  var _ = this;
  this.autopager = false;
  this.el = el;
  this.pager = this.el.querySelector('.neg_shopify_pager');
  this.xobj = new XMLHttpRequest();
  this.xobj.overrideMimeType("application/json");
  this.currentPage = 0;
  this.total = el.dataset.total;
  this.totalPages = Math.floor(this.total / el.dataset.perpage);

  if (!this.pager) {
    return;
  }

  this.pagerItems = this.pager.querySelector('ul');

  this.fetchOptions = {
    'perpage': el.dataset.perpage,
    'id': el.dataset.id,
    'type': el.dataset.type,
    'sort': el.dataset.sort,
  };

  this.opts = (typeof(opts) !== "undefined") ? opts : {};
  this.url = el.dataset.endpoint;
  this.itemsEl = el.querySelector('.items');
  this.itemsContainer = this.itemsEl.querySelector('.items_container');

  if (!this.itemsContainer) {
    this.itemsContainer = this.itemsEl;
  }

  this.firstPage = (_.itemsContainer.children.length > 0) ? 1 : 0;
  this.sortEl = el.querySelector('#sort_order');

  if (this.sortEl !== null) {
    this.sortEl.addEventListener('change', function (e) {
      const value = e.target.options[e.target.selectedIndex].value;
      _.fetchOptions["sort"] = value;
      _.fetch(0);
    });
  }

  this.fetch = function (page) {
    _.currentPage = parseInt(page);
    const endpoint = _.getUrl() + "&page=" + page;

    _.buildPager();

    history.pushState(null, null, _.buildUrl(page));

    _.itemsContainer.classList.add("dim");

    _.xobj.open('GET', endpoint, true); // Replace 'my_data' with the path to your file
    _.xobj.onreadystatechange = function () {
      if (_.xobj.readyState == 4 && _.xobj.status == "200") {
        var data = JSON.parse(_.xobj.responseText);

        var fragment = document.createDocumentFragment();
        for (var i = 0; i < data.items.length; i++) {
          var item = data.items[i];
          var el = createElementFromHTML(item);

          fragment.appendChild(el);
        }

        // Run any scripts included.
        let scripts = fragment.querySelectorAll('script');
        for (let n = 0; n < scripts.length; n++) {
          eval(scripts[n].innerHTML)
        }

        const currentLink = _.pager.querySelector('.is-active');
        if (currentLink) {
          currentLink.classList.remove('Loader');
        }

        window.requestAnimationFrame(function() {
          _.itemsContainer.innerHTML = '';
          _.itemsContainer.appendChild(fragment);
          _.itemsContainer.classList.remove("dim");
          _.el.scrollIntoView({ block: 'start',  behavior: 'smooth' });
        });
      }
    };
    _.xobj.send(null);
  }

  this.buildUrl = function(page) {
    let path = window.location.pathname;
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('page', page);

    if (page == 0) {
      urlParams.delete('page');
    }

    const params = urlParams.toString();

    return path + ((params.length > 0) ? '?' : '') + params;
  };

  this.buildPager = function() {

    const fragment = document.createDocumentFragment();
    const totalPagesShown = parseInt(9);
    let disabled = '';

    if (parseInt(_.currentPage) > 0) {
      let el = createElementFromHTML("<li class='first'><a href='" + _.buildUrl(0) + "' class='pager__item pager__item--first' data-page='0'><span class='title'>‹‹ First</span></a></li>");
      fragment.appendChild(el);
    }

    // Previous Page.
    disabled = (parseInt(_.currentPage) > 0) ? '' : ' disabled';
    el = createElementFromHTML("<li class='prev"+disabled+"'><a href='" + _.buildUrl(parseInt(_.currentPage) - 1) + "' class='pager__item pager__item--prev' rel='prev' data-page='" + (parseInt(_.currentPage) - 1) + "'><span class='title'>Previous</span></a></li>");
    fragment.appendChild(el);

    let middle = Math.ceil(totalPagesShown / 2);
    let current = parseInt(_.currentPage);
    let first = current - middle;
    let last = current + totalPagesShown - middle;
    let max = parseInt(_.totalPages);

    let i = first;
    if (last > max) {
      i = i + (max - last);
      last = max;
    }

    if (i < 0) {
      last = last + (1 - i);
      i = 0;
    }

    if (i != max && i > 0) {
      el = createElementFromHTML("<li class='ellipsis'>…</li>");
      fragment.appendChild(el);
    }

    for (; i <= last && i <= max; i++) {
      let el = createElementFromHTML("<li class='page'><a href='" + _.buildUrl(i) + "' class='pager__item" + ((i == parseInt(_.currentPage)) ? ' is-active Loader' : '') + "' data-page='" + i +"'><span class='title'>" + (i + 1) + "</span></a></li>");
      fragment.appendChild(el);
    }

    if (last < parseInt(_.totalPages)) {
      el = createElementFromHTML("<li class='ellipsis'>…</li>");
      fragment.appendChild(el);
    }

    // Next Page.
    disabled = ((current + 1) <= parseInt(_.totalPages)) ? '' : ' disabled';
    el = createElementFromHTML("<li class='next"+disabled+"'><a href='" + _.buildUrl(parseInt(_.currentPage) + 1) + "' class='pager__item pager__item--next' rel='next' data-page='" + (parseInt(_.currentPage) + 1) + "'><span class='title'>Next</span></a></li>");
    fragment.appendChild(el);

    if (parseInt(_.currentPage) != (parseInt(_.totalPages))) {
      let el = createElementFromHTML("<li class='last'><a href='" + _.buildUrl(parseInt(_.totalPages)) + "' class='pager__item pager__item--last' data-page='" + (parseInt(_.totalPages)) + "'><span class='title'>Last ››</span></a></li>");
      fragment.appendChild(el);
    }

    // Info.
    el = createElementFromHTML("<li class='info'><span>" + (current + 1) + " of " + (parseInt(_.totalPages) + 1) + " Pages</span></li>")
    fragment.appendChild(el);

    _.pagerItems.innerHTML = '';
    _.pagerItems.appendChild(fragment);
  };

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

  window.addEventListener('popstate', function(event) {
    const urlParams = new URLSearchParams(window.location.search);
    let page = urlParams.get('page');
    if (!page) {
      page = 0;
    }
      _.fetch(page);
  });

  _.pager.addEventListener('click', function (e) {
    const link = e.target.closest('a');

    if (link) {
      e.preventDefault();
      e.stopPropagation();
      _.fetch(link.dataset.page);
    }
  });

  if (_.firstPage == 0) {
    _.fetch(0);
  }

};

window.shopify_pagers = [];

window.shopify_pager_manager = new function() {
  const _ = this;
  this.pagers = [];

  Array.prototype.forEach.call(document.querySelectorAll(".pager"), function(el) {
    let p = new Pager(el);
    window.shopify_pagers.push(p);
    _.pagers.push(p);
  });

  this.getPagerByEl = function getPagerByEl(el) {

    console.debug(_.pagers);
    for (let i = 0; i < _.pagers.length; i++) {
      if (_.pagers[i].el === el) {
        return _.pagers[i];
      }
    }

    return null;
  };

}();
