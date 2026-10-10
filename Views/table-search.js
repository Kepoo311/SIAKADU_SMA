(() => {
    const searches = document.querySelectorAll('[data-table-search]');

    searches.forEach((search) => {
        const tableId = search.dataset.tableSearch;
        const table = document.getElementById(tableId);

        if (!(table instanceof HTMLTableElement)) {
            console.error(`Table search target "${tableId}" was not found.`);
            return;
        }

        const rows = Array.from(table.querySelectorAll('tbody tr'))
            .filter((row) => !row.hasAttribute('data-empty-state'));
        const filter = Array.from(document.querySelectorAll('[data-table-filter]'))
            .find((element) => element.dataset.tableFilter === tableId);
        const results = Array.from(document.querySelectorAll('[data-table-results]'))
            .find((element) => element.dataset.tableResults === tableId);
        const empty = Array.from(document.querySelectorAll('[data-table-empty]'))
            .find((element) => element.dataset.tableEmpty === tableId);

        const update = () => {
            const query = search.value.trim().toLocaleLowerCase();
            const selectedFilter = filter?.value ?? '';
            let visibleCount = 0;

            rows.forEach((row) => {
                const searchableText = (row.dataset.search ?? row.textContent)
                    .toLocaleLowerCase();
                const matchesQuery = query === '' || searchableText.includes(query);
                const matchesFilter = selectedFilter === '' ||
                    row.dataset.statusFilter === selectedFilter;
                const visible = matchesQuery && matchesFilter;

                row.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (results) {
                results.textContent = `Menampilkan ${visibleCount} dari ${rows.length} data.`;
                results.hidden = rows.length === 0;
            }
            if (empty) {
                empty.hidden = rows.length === 0 || visibleCount > 0;
            }
        };

        search.addEventListener('input', update);
        filter?.addEventListener('change', update);
        update();
    });
})();
