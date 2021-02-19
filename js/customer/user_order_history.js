const shopifyCustomerHistoryWidget = new function() {
  const xobj = new XMLHttpRequest();
  const shopifyCustomerHistoryWidgets = document.querySelectorAll('.shopifyCustomerHistoryWidget');

  const loadJSON = function (endpoint, context, callback) {
    xobj.abort();
    xobj.overrideMimeType("application/json");
    xobj.open('GET', endpoint, true); // Replace 'my_data' with the path to your file
    xobj.onreadystatechange = function () {
      if (xobj.readyState == 4 && xobj.status == "200") {
        // Required use of an anonymous callback as .open will NOT return a value but simply returns undefined in asynchronous mode
        var data = JSON.parse(xobj.responseText);
        callback.call(context, data);
      }
    };
    xobj.send(null);
  }

  function appendLeadingZeroes(n){
    if(n <= 9){
      return "0" + n;
    }
    return n
  }
  Number.prototype.formatMoney = function(c, d, t){
    var n = this, 
      c = isNaN(c = Math.abs(c)) ? 2 : c, 
      d = d == undefined ? "." : d, 
      t = t == undefined ? "," : t, 
      s = n < 0 ? "-" : "", 
      i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))), 
      j = (j = i.length) > 3 ? j % 3 : 0;
    return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
  };

  Array.prototype.forEach.call(shopifyCustomerHistoryWidgets, (el, index) => new Vue({
    el,
    data: {
      records: [],
      hasNextPage: null,
      hasPrevPage: null,
      cursor: 0,
      direction: 'after',
      perPage: (el.dataset.perPage != "null") ? el.dataset.perPage : 10,
      email: el.dataset.email,
      endpoint: el.dataset.endpoint
    },
    computed: {
      url: function() {
        return this.endpoint + "?email=" + this.email + "&page=" + this.cursor + "&direction=" + this.direction + "&per-page=" + this.perPage;
      }
    },
    methods: {
      getNextPage: function() {
        this.direction = 'after';
        this.getData();
      },
      getPrevPage: function() {
        this.direction = 'before';
        this.getData();
      },
      getData: function() {
        const self = this;
        loadJSON(this.url, this, function(data) {
          self.cursor = data.lastCursor;
          if (data.pageInfo != null) {
            self.hasNextPage = data.pageInfo.hasNextPage;
            self.hasPrevPage = data.pageInfo.hasPreviousPage;
          }
          self.records = data.orders;
        });
      },
      formatMoney: function(value) {
        var n = Number(value);
        return n.formatMoney(2,'.',',');
      },
      formatStatus: function(value) {
        value = value.replace('_', ' ');
        value = value.toLowerCase();
        const words = value.split(" ");
        for (let i = 0; i < words.length; i++) {
          words[i] = words[i][0].toUpperCase() + words[i].substr(1);
        }

        return words.join(" ");
      },
      formatDate: function(value) {
        const d = new Date(value);
        return appendLeadingZeroes(d.getMonth()) + "-" + appendLeadingZeroes(d.getDay()) + "-" + d.getFullYear();
      }
    },
    created: function() {
      this.getData();
    }
  }));
}();
