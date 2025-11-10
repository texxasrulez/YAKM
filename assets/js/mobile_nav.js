// Simple, accessible mobile nav toggle used by both admin and public pages
(function(){
  function init(scope) {
    var btn = (scope || document).getElementById('menu-toggle');
    var nav = (scope || document).getElementById('site-nav') || (scope || document).getElementById('admin-nav');
    if(!btn || !nav) return;
    function setExpanded(expanded){
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      nav.setAttribute('data-open', expanded ? '1' : '0');
    }
    btn.addEventListener('click', function(){
      var expanded = btn.getAttribute('aria-expanded') === 'true';
      setExpanded(!expanded);
    });
    // Close on escape
    (scope || document).addEventListener('keydown', function(e){
      if(e.key === 'Escape'){
        setExpanded(false);
      }
    });
    // Ensure default collapsed on load for small screens
    setExpanded(false);
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', function(){ init(document); });
  } else {
    init(document);
  }
})();
