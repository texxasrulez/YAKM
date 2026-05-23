(function(){
  function inject(form){
    if (!form || form.dataset.kontactHp) return;
    var hp = document.createElement('input');
    hp.type = 'text';
    hp.name = 'hp';
    hp.value = '';
    hp.style.position='absolute';
    hp.style.left='-10000px';
    hp.autocomplete='off';
    var kt = document.createElement('input');
    kt.type = 'hidden';
    kt.name = 'kt';
    kt.value = String(Math.floor(Date.now()/1000));
    form.appendChild(hp);
    form.appendChild(kt);
    form.dataset.kontactHp = '1';
  }
  function init(){
    var forms = document.querySelectorAll('form[action*="kontact/send_mail.php"]');
    for (var i=0;i<forms.length;i++) inject(forms[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
