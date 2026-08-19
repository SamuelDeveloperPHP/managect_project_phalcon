document.addEventListener('DOMContentLoaded', function () {
  if (!window.Swal) {
    return;
  }

  document.querySelectorAll('.flash .errorMessage, .flash .successMessage, .flash .noticeMessage, .flash .warningMessage').forEach(function (message) {
    var text = (message.textContent || '').trim();
    if (!text) {
      return;
    }

    var icon = 'info';
    var title = 'Informação';
    if (message.classList.contains('errorMessage')) {
      icon = 'error';
      title = 'Atenção';
    } else if (message.classList.contains('successMessage')) {
      icon = 'success';
      title = 'Sucesso';
    } else if (message.classList.contains('warningMessage')) {
      icon = 'warning';
      title = 'Atenção';
    }

    Swal.fire(title, text, icon);
  });
});
