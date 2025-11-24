jQuery(document).ready(function($){
    // populate category filter (optional)
    $.get(clmsData.restUrl + '?categories_for_select=1', function(){ /* optionally implement category endpoint */ });

    var table = $('#clmsProductsTable').DataTable({
        serverSide: false, // set false because our REST returns paginated blocks, but DataTables serverSide expects a different contract; using client-side but with AJAX paging below
        processing: true,
        ajax: function(data, callback, settings) {
            // DataTables sends 'start' and 'length' etc in 'data'
            var params = {
                draw: data.draw,
                start: data.start,
                length: data.length,
                search: { value: $('#clms-search').val() || data.search.value },
                columns: data.columns,
                order: data.order,
                category: $('#clms-category-filter').val(),
                stock: $('#clms-stock-filter').val()
            };

            $.ajax({
                url: clmsData.restUrl,
                method: 'GET',
                data: params,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', clmsData.nonce);
                },
                success: function(res) {
                    callback(res);
                },
                error: function(xhr) {
                    callback({ draw: data.draw, recordsTotal:0, recordsFiltered:0, data:[] });
                }
            });
        },
        pageLength: clmsData.perPage,
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'sku' },
            { data: 'price_html' },
            { data: 'stock' },
            { data: 'categories' }
        ],
    });

    // search button
    $('#clms-search-btn').on('click', function() {
        table.ajax.reload();
    });

    // filters
    $('#clms-stock-filter, #clms-category-filter').on('change', function() {
        table.ajax.reload();
    });
});
