document.addEventListener('DOMContentLoaded', () => {
  const hash = location.hash.replace('#','');
  if (hash && document.getElementById(hash)) {
    document.getElementById(hash).hidden = false;
  }
});
