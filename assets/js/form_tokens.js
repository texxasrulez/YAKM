(function () {
  function ready(fn){ if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    var form = document.querySelector('form#kontact-form, form[action$="kontact/send_mail.php"]');
    if (!form) return;

    // Ensure hidden fields exist
    function ensure(name){
      var el = form.querySelector('input[name="'+name+'"]');
      if (!el) { el = document.createElement('input'); el.type='hidden'; el.name=name; form.appendChild(el); }
      return el;
    }
    var hp    = ensure('hp');            // honeypot
    var kt    = ensure('kt');            // submit timestamp
    var ksig  = ensure('ksig');          // HMAC for kt (server checks if present)
    var csrf  = ensure('csrf_token');
    var csrf2 = ensure('csrf');    // CSRF token

    var tokensReady = false;
    function fetchTokens(cb){
      var xhr = new XMLHttpRequest();
      xhr.open('GET', '/kontact/api/form_token.php', true);
      xhr.onreadystatechange = function(){
        if (xhr.readyState === 4){
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              var data = JSON.parse(xhr.responseText);
              kt.value   = (data.kt || '') + '';
              ksig.value = (data.ksig || '') + '';
              csrf.value = (data.csrf_token || '') + '';
              csrf2.value = csrf.value;
              tokensReady = (csrf.value !== '');
            } catch(e){ /* ignore parse error */ }
          }
          if (typeof cb === 'function') cb(tokensReady);
        }
      };
      xhr.send();
    }

    // Fetch immediately on load
    fetchTokens();

    // Optional: enforce client min wait before enabling submit button
    var minWait = parseInt(form.getAttribute('data-min-wait-seconds') || '0', 10);
    if (!isNaN(minWait) && minWait > 0) {
      var btn = form.querySelector('button[type=\"submit\"], input[type=\"submit\"]');
      if (btn) {
        btn.disabled = true;
        setTimeout(function(){ btn.disabled = false; }, Math.max(0, minWait) * 1000);
      }
    }

    // Expose a hook other scripts (recaptcha) can call to ensure tokens
    form.__ensureKontactTokens = function(next){
      if (tokensReady) { return next && next(true); }
      fetchTokens(function(){ next && next(tokensReady); });
    };
  });
})();