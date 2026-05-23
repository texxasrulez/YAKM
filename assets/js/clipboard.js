(function(){
  function copyText(text){
    if(navigator.clipboard && navigator.clipboard.writeText){
      return navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
      return Promise.resolve();
    }
  }
  function init(){
    document.querySelectorAll('[data-copy-target]').forEach(btn => {
      btn.addEventListener('click', async function(){
        const sel = btn.getAttribute('data-copy-target');
        const node = document.querySelector(sel);
        if(!node) return;
        const raw = node.textContent;
        try {
          await copyText(raw);
          const prev = btn.textContent;
          btn.textContent = (window.tr ? window.tr('copied','Copied!') : 'Copied!');
          setTimeout(()=>{ btn.textContent = prev; }, 1200);
        } catch(e){
          console.error(e);
        }
      });
    });
  }
  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
