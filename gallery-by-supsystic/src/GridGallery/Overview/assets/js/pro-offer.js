/* Free vs PRO comparison and PRO pricing on the Overview page (the same behaviour as on the supsystic.com plugin page). */
(function (init) {
  // The script is printed in the page head, before the markup
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(function () {
  var offer = document.querySelector('.sgg-pro-offer');
  if (!offer) {
    return;
  }

  /* Comparison filter: hides a group heading once all of its rows are out */
  (function () {
    var table = offer.querySelector('.ggMtxTable');
    if (!table) {
      return;
    }
    var filters = offer.querySelectorAll('.ggMtxFilter');
    var empty = table.querySelector('.ggMtxEmpty');
    var nodes = Array.prototype.slice.call(table.children);

    function apply(want) {
      var group = null;
      var groupVisible = 0;
      var total = 0;

      function closeGroup() {
        if (group) {
          group.hidden = groupVisible === 0;
        }
      }

      nodes.forEach(function (node) {
        if (node.hasAttribute('data-gg-group')) {
          closeGroup();
          group = node;
          groupVisible = 0;
          return;
        }
        var tier = node.getAttribute('data-gg-tier');
        if (!tier) {
          return;
        }
        var show = want === 'all' || tier === want;
        node.hidden = !show;
        if (show) {
          groupVisible++;
          total++;
        }
      });
      closeGroup();

      if (empty) {
        empty.hidden = total !== 0;
      }
    }

    Array.prototype.forEach.call(filters, function (btn) {
      btn.addEventListener('click', function () {
        Array.prototype.forEach.call(filters, function (b) {
          b.classList.toggle('ggMtxFilterActive', b === btn);
        });
        apply(btn.getAttribute('data-gg-tier-filter'));
      });
    });
  })();

  /* Free / Pro rings: fill the arc and count the number up the first time the cards come into view */
  (function () {
    var band = offer.querySelector('.ggMtxTop');
    var plans = offer.querySelectorAll('.ggMtxPlan');
    if (!band || !plans.length) {
      return;
    }
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function reveal(plan) {
      plan.classList.add('ggIsIn');
      var num = plan.querySelector('.ggMtxRingNum');
      if (!num) {
        return;
      }
      var target = parseInt(num.getAttribute('data-count'), 10);
      if (!target) {
        return;
      }
      if (reduced) {
        num.textContent = target;
        return;
      }
      var DURATION = 1400;
      var started = null;
      num.textContent = '0';

      function tick(now) {
        if (started === null) {
          started = now;
        }
        var p = (now - started) / DURATION;
        if (p > 1) {
          p = 1;
        }
        num.textContent = Math.round((1 - Math.pow(1 - p, 5)) * target);
        if (p < 1) {
          window.requestAnimationFrame(tick);
        }
      }
      window.requestAnimationFrame(tick);
    }

    band.classList.add('ggMtxAnimReady');
    if (!('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(plans, reveal);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          reveal(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });
    Array.prototype.forEach.call(plans, function (plan) {
      io.observe(plan);
    });
  })();

  /* Pricing: licence tabs */
  (function () {
    var root = offer.querySelector('.supPricing');
    if (!root) {
      return;
    }
    var tabs = root.querySelectorAll('.sptTab');
    var panels = root.querySelectorAll('.sptPanel');
    Array.prototype.forEach.call(tabs, function (tab) {
      tab.addEventListener('click', function () {
        var licence = tab.getAttribute('data-licence');
        Array.prototype.forEach.call(tabs, function (t) {
          var isActive = t === tab;
          t.classList.toggle('is-active', isActive);
          t.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        Array.prototype.forEach.call(panels, function (p) {
          p.hidden = p.getAttribute('data-licence') !== licence;
        });
      });
    });
  })();
});
