(function () {
  var DOMAINS = [
    "gmail.com","yahoo.com","yahoo.com.my","outlook.com","hotmail.com",
    "hotmail.my","icloud.com","me.com","mac.com","live.com",
    "protonmail.com","proton.me","fastmail.com","zoho.com","gmx.com",
    "mail.com","qq.com","163.com","yandex.com","tm.net.my",
    "streamyx.com","naver.com","googlemail.com","msn.com","aol.com"
  ];

  // Returns 'empty' | 'typing' | 'valid' | 'invalid'
  // 'typing'  = partial domain that is still a valid prefix → no error yet
  // 'valid'   = domain exactly matches the allowlist
  // 'invalid' = domain typed is NOT a prefix of any allowed domain (e.g. gmail.com123)
  function getState(val) {
    val = (val || '').trim();
    if (!val) return 'empty';
    var at = val.lastIndexOf('@');
    if (at === -1) return 'typing';
    var local  = val.slice(0, at);
    var domain = val.slice(at + 1).toLowerCase();
    if (!local)  return 'invalid';
    if (!domain) return 'typing';
    if (DOMAINS.indexOf(domain) !== -1) return 'valid';
    if (DOMAINS.some(function(d) { return d.indexOf(domain) === 0; })) return 'typing';
    return 'invalid';
  }

  function applyState(input, errEl, noValidate) {
    if (noValidate) return;
    var state = getState(input.value);
    input.classList.remove('ac-field-valid', 'ac-field-invalid');
    if (errEl) errEl.textContent = '';

    if (state === 'valid') {
      input.classList.add('ac-field-valid');
      input.dataset.acValid = 'true';
    } else if (state === 'invalid') {
      input.classList.add('ac-field-invalid');
      input.dataset.acValid = 'false';
      if (errEl) errEl.textContent = 'Please use a valid email domain (e.g. @gmail.com, @outlook.com).';
    } else {
      delete input.dataset.acValid;
    }
  }

  function esc(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function init(input) {
    var noValidate = input.hasAttribute('data-no-domain-validate');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'position:relative;display:block';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var ul = document.createElement('ul');
    ul.className = 'email-ac-dropdown';
    wrap.appendChild(ul);

    // Locate the nearest .field-error sibling of the wrapper, or create one
    var errEl = null;
    if (!noValidate) {
      var par  = wrap.parentNode;
      var kids = par ? Array.from(par.children) : [];
      for (var k = 0; k < kids.length; k++) {
        if (kids[k] !== wrap && kids[k].classList.contains('field-error')) {
          errEl = kids[k]; break;
        }
      }
      if (!errEl) {
        errEl = document.createElement('span');
        errEl.className = 'field-error email-ac-err';
        wrap.parentNode.insertBefore(errEl, wrap.nextSibling);
      }
    }

    var active = -1;

    function show(val) {
      var at = val.indexOf('@');
      if (at === -1) { hide(); return; }
      var local = val.slice(0, at + 1);
      var typed = val.slice(at + 1).toLowerCase();
      var hits  = DOMAINS.filter(function(d) { return d.indexOf(typed) === 0; });
      if (!hits.length || (hits.length === 1 && typed === hits[0])) { hide(); return; }
      ul.innerHTML = '';
      active = -1;
      hits.forEach(function(domain) {
        var li = document.createElement('li');
        li.className = 'email-ac-item';
        li.innerHTML = esc(local) + '<strong>' + esc(typed) + '</strong>' + esc(domain.slice(typed.length));
        li.dataset.value = local + domain;
        li.addEventListener('mousedown', function(ev) {
          ev.preventDefault();
          input.value = this.dataset.value;
          hide();
          applyState(input, errEl, noValidate);
        });
        ul.appendChild(li);
      });
      ul.style.display = 'block';
    }

    function hide() { ul.style.display = 'none'; ul.innerHTML = ''; active = -1; }

    function setActive(idx) {
      var items = ul.querySelectorAll('.email-ac-item');
      items.forEach(function(el, i) { el.classList.toggle('email-ac-active', i === idx); });
      active = idx;
    }

    input.addEventListener('input', function() {
      show(this.value);
      applyState(input, errEl, noValidate);
    });
    input.addEventListener('focus', function() { show(this.value); });
    input.addEventListener('blur',  function() { applyState(input, errEl, noValidate); });

    input.addEventListener('keydown', function(ev) {
      var items = ul.querySelectorAll('.email-ac-item');
      if (!items.length || ul.style.display === 'none') return;
      if (ev.key === 'ArrowDown') {
        ev.preventDefault(); setActive((active + 1) % items.length);
      } else if (ev.key === 'ArrowUp') {
        ev.preventDefault(); setActive((active - 1 + items.length) % items.length);
      } else if ((ev.key === 'Enter' || ev.key === 'Tab') && active >= 0) {
        ev.preventDefault();
        input.value = items[active].dataset.value;
        hide();
        applyState(input, errEl, noValidate);
      } else if (ev.key === 'Escape') {
        hide();
      }
    });

    document.addEventListener('click', function(ev) {
      if (!wrap.contains(ev.target)) hide();
    });
  }

  document.querySelectorAll('input[type="email"]').forEach(init);
})();
