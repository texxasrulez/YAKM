// Lightweight table sorter for all admin tables.
(function(){
  function getCellText(td){
    if(!td) return "";
    return (td.getAttribute('data-value') ?? td.textContent ?? "").trim();
  }
  function inferType(val){
    if(val === "" || val === null) return "string";
    if(!isNaN(val) && /^-?\d+(\.\d+)?$/.test(val)) return "number";
    let d = Date.parse(val);
    if(!isNaN(d)) return "date";
    return "string";
  }
  function cmp(a, b, type){
    if(type === "number") return (parseFloat(a)||0) - (parseFloat(b)||0);
    if(type === "date") return (Date.parse(a)||0) - (Date.parse(b)||0);
    return a.localeCompare(b, undefined, {numeric:true, sensitivity:'base'});
  }
  function makeSortable(table){
    if(table.__sortable_bound) return;
    table.__sortable_bound = true;
    const thead = table.tHead;
    if(!thead || !thead.rows.length) return;
    const headers = thead.rows[0].cells;
    Array.from(headers).forEach((th, colIdx)=>{
      th.style.cursor = 'pointer';
      th.setAttribute('title', 'Sort');
      th.addEventListener('click', function(){
        const tbody = table.tBodies[0];
        if(!tbody) return;
        const rows = Array.from(tbody.rows);
        const sample = rows.find(r => r.cells[colIdx]) || null;
        const val = sample ? getCellText(sample.cells[colIdx]) : "";
        const type = th.dataset.sortType || inferType(val);
        const currentDir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
        Array.from(headers).forEach(h=>{ h.dataset.sortDir=""; h.classList.remove('sorted-asc','sorted-desc'); });
        th.dataset.sortDir = currentDir;
        th.classList.add(currentDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
        rows.sort((r1,r2)=>{
          const a = getCellText(r1.cells[colIdx]);
          const b = getCellText(r2.cells[colIdx]);
          const c = cmp(a,b,type);
          return currentDir === 'asc' ? c : -c;
        });
        const frag = document.createDocumentFragment();
        rows.forEach(r => frag.appendChild(r));
        tbody.appendChild(frag);
      });
    });
  }
  function init(){ document.querySelectorAll('table').forEach(makeSortable); }
  if(document.readyState === 'loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
