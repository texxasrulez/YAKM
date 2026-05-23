(function(){
  function populate(select, data){
    select.innerHTML = '';
    (data.recipients || []).forEach(function(r, idx){
      var opt = document.createElement('option');
      opt.value = r.id;
      opt.textContent = r.label;
      opt.dataset.email = r.email;
      opt.id = String(idx+1);
      select.appendChild(opt);
    });
  }
  function init(){
    var selects = document.querySelectorAll('select[name="recipient_id"]');
    if (selects.length === 0) return;
    fetch('kontact/api/recipients.php', {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(data){ selects.forEach(function(sel){ populate(sel, data); }); })
      .catch(function(e){ console && console.warn && console.warn('Kontact recipients load failed', e); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
