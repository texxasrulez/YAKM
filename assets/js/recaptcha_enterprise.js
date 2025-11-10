// reCAPTCHA Enterprise helper — hardened
(function(){
  var MAX_WAIT_MS = 2500; // total wait before we give up
  var RETRY_MS    = 150;

  function debug(){ if (window.KONTACT_DEBUG) try { console.debug.apply(console, arguments); } catch(e){} }

  function getSiteKey(){
    // 1) script src with ?render=SITE_KEY
    var nodes = document.querySelectorAll('script[src*="recaptcha/enterprise.js"]');
    for (var i=0;i<nodes.length;i++){
      var src = nodes[i].getAttribute('src')||'';
      var m = src.match(/[?&]render=([^&]+)/);
      if (m && m[1]) return decodeURIComponent(m[1]);
    }
    // 2) <div class="g-recaptcha" data-sitekey="...">
    var d = document.querySelector('.g-recaptcha[data-sitekey]');
    if (d && d.getAttribute('data-sitekey')) return d.getAttribute('data-sitekey');
    // 3) meta
    var meta = document.querySelector('meta[name="recaptcha-sitekey"]');
    if (meta && meta.content) return meta.content;
    // 4) data attribute on form
    var f = document.getElementById('kontact-form');
    if (f && f.getAttribute('data-recaptcha-sitekey')) return f.getAttribute('data-recaptcha-sitekey');
    // 5) global
    if (window.RECAPTCHA_SITE_KEY) return String(window.RECAPTCHA_SITE_KEY);
    return '';
  }

  function ensureHidden(form, name){
    var el = form.querySelector('input[name="'+name+'"]');
    if (!el) {
      el = document.createElement('input');
      el.type = 'hidden';
      el.name = name;
      form.appendChild(el);
    }
    return el;
  }

  function badgeError(form, msg){
    // Surface a small message near submit button to avoid silent failures
    var id = 'rce-msg';
    var node = document.getElementById(id);
    if (!node){
      node = document.createElement('div');
      node.id = id;
      node.style.cssText = 'margin-top:6px;font-size:12px;color:#b91c1c;';
      var btn = form.querySelector('button[type="submit"],input[type="submit"]');
      (btn && btn.parentNode ? btn.parentNode : form).appendChild(node);
    }
    node.textContent = msg;
  }

  function executeWithRetry(siteKey, form, cb){
    var start = Date.now();
    (function tick(){
      if (!window.grecaptcha || !grecaptcha.enterprise || typeof grecaptcha.enterprise.execute !== 'function'){
        if (Date.now() - start > MAX_WAIT_MS) { cb(new Error('loader-timeout')); return; }
        return setTimeout(tick, RETRY_MS);
      }
      grecaptcha.enterprise.ready(function(){
        grecaptcha.enterprise.execute(siteKey, {action:'submit'}).then(function(token){
          ensureHidden(form, 'recaptcha_token').value = token;
          ensureHidden(form, 'rce_token').value = token;
          ensureHidden(form, 'g-recaptcha-response').value = token;
          ensureHidden(form, 'recaptcha_action').value = 'submit';
          cb(null, token);
        }).catch(function(err){
          if (Date.now() - start > MAX_WAIT_MS) { cb(err||new Error('execute-failed')); return; }
          setTimeout(tick, RETRY_MS);
        });
      });
    })();
  }

  function ensurePreSubmit(form, cb){
    // Let form_tokens.js populate CSRF/HMAC first if present
    var ensureFn = form.__ensureKontactTokens;
    if (typeof ensureFn === 'function') ensureFn(function(){ cb(); });
    else cb();
  }

  function intercept(e){
    var form = e.target;
    if (!form || form.id !== 'kontact-form') return;
    // Prevent double handling
    if (form.__rce_submitting) return;
    e.preventDefault();

    var siteKey = getSiteKey();
    debug('rce: siteKey=', siteKey);

    ensurePreSubmit(form, function(){
      if (!siteKey){
        // No site key found: submit as-is; server may allow alternate checks or will log clear error
        debug('rce: no site key found, submitting without token');
        form.__rce_submitting = true;
        try { form.submit(); } finally { form.__rce_submitting = false; }
        return;
      }
      executeWithRetry(siteKey, form, function(err, token){
        if (err || !token){
          debug('rce: token error:', err && (err.message||err));
          badgeError(form, 'reCAPTCHA token could not be obtained. Please reload and try again.');
          // Fail-closed: do not submit without token when siteKey is configured
          return;
        }
        debug('rce: got token');
        form.__rce_submitting = true;
        try { form.submit(); } finally { form.__rce_submitting = false; }
      });
    });
  }

  // Capture submit
  document.addEventListener('submit', intercept, true);
})();