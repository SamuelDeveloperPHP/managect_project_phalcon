(function () {
  var search = document.getElementById('overview-search');
  var status = document.getElementById('overview-status');
  var table = document.getElementById('overview-tasks');
  var empty = document.getElementById('overview-empty');

  if (!search || !status || !table) return;

  function filter() {
    var term = search.value.trim().toLowerCase();
    var selected = status.value;
    var visible = 0;

    Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
      var matches = (!term || row.textContent.toLowerCase().indexOf(term) !== -1) &&
        (!selected || row.getAttribute('data-status') === selected);
      row.hidden = !matches;
      if (matches) visible++;
    });

    if (empty) empty.hidden = visible !== 0;
  }

  search.addEventListener('input', filter);
  status.addEventListener('change', filter);
}());
