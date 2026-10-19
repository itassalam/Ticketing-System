(function () {
    var table = document.querySelector('[data-category-table]');
    if (!table) return;

    var body = table.tBodies[0];
    var rows = Array.from(body.querySelectorAll('tr[data-category-row]'));
    var filterForm = document.querySelector('[data-category-filter]');
    var search = document.querySelector('[data-category-search]');
    var statusSelect = document.querySelector('[data-category-status]');
    var pageSizeSelect = document.querySelector('[data-category-page-size]');
    var summary = document.querySelector('[data-category-summary]');
    var pager = document.querySelector('[data-category-pager]');
    var resetButton = document.querySelector('[data-category-reset]');
    var emptyState = body.querySelector('tr[data-empty-category]');
    var page = 1;
    var status = statusSelect ? statusSelect.value : '';
    var query = search.value.trim().toLocaleLowerCase();
    var pageSize = Number(pageSizeSelect.value);
    var emptyResult = document.createElement('tr');
    emptyResult.innerHTML = '<td colspan="' + table.tHead.rows[0].cells.length + '" style="text-align:center;color:#64748b;padding:24px;">Tidak ada kategori yang cocok.</td>';

    function render() {
        var filtered = rows.filter(function (row) {
            var matchesSearch = !query || row.textContent.toLocaleLowerCase().includes(query);
            var matchesStatus = !status || row.dataset.status === status;
            return matchesSearch && matchesStatus;
        });
        var effectivePageSize = pageSize === 0 ? filtered.length || 1 : pageSize;
        var pageCount = Math.max(1, Math.ceil(filtered.length / effectivePageSize));
        page = Math.min(page, pageCount);
        var firstIndex = (page - 1) * effectivePageSize;
        var visible = filtered.slice(firstIndex, firstIndex + effectivePageSize);

        rows.forEach(function (row) {
            row.style.display = visible.indexOf(row) === -1 ? 'none' : '';
        });
        if (emptyState) emptyState.hidden = rows.length > 0;
        emptyResult.hidden = filtered.length > 0 || rows.length === 0;
        if (!emptyResult.parentNode) body.appendChild(emptyResult);
        summary.innerHTML = 'Menampilkan <strong>' + (filtered.length ? (firstIndex + 1) + '–' + (firstIndex + visible.length) : '0') + '</strong> dari <strong>' + filtered.length + '</strong> kategori';
        renderPager(pageCount);
        resetButton.hidden = !query && !status && pageSize === 10;
    }

    function renderPager(pageCount) {
        pager.replaceChildren();
        if (pageCount <= 1) return;

        addPageButton('‹', Math.max(1, page - 1), page === 1, 'Halaman sebelumnya');
        var firstPage = Math.max(1, Math.min(page - 2, pageCount - 4));
        var lastPage = Math.min(pageCount, firstPage + 4);
        for (var number = firstPage; number <= lastPage; number++) {
            addPageButton(String(number), number, false, 'Halaman ' + number, number === page);
        }
        addPageButton('›', Math.min(pageCount, page + 1), page === pageCount, 'Halaman berikutnya');
    }

    function addPageButton(label, targetPage, disabled, description, active) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.setAttribute('aria-label', description);
        if (active) {
            button.classList.add('active');
            button.setAttribute('aria-current', 'page');
        }
        button.addEventListener('click', function () {
            page = targetPage;
            render();
        });
        pager.appendChild(button);
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        query = search.value.trim().toLocaleLowerCase();
        status = statusSelect ? statusSelect.value : '';
        pageSize = pageSizeSelect.value === 'all' ? 0 : Number(pageSizeSelect.value);
        page = 1;
        render();
    });
    resetButton.addEventListener('click', function () {
        search.value = '';
        if (statusSelect) statusSelect.value = '';
        pageSizeSelect.value = '10';
        query = '';
        status = '';
        pageSize = 10;
        page = 1;
        render();
    });
    render();
})();
