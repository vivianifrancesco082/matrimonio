(function() {
  var params = new URLSearchParams(window.location.search);
  var famiglia = params.get('famiglia');
  if (famiglia) {
    var modal = document.getElementById('welcomeModal');
    document.getElementById('welcomeTitle').textContent = famiglia;
    //document.getElementById('rsvpTitle').textContent = famiglia;
    modal.style.display = 'flex';
    document.getElementById('welcomeOk').addEventListener('click', function() {
      modal.style.opacity = '0';
      modal.style.transition = 'opacity 0.3s';
      setTimeout(function() { modal.style.display = 'none'; }, 300);
    });
  }
})();