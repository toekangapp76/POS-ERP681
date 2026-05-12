$(document).ready(function() {
    //If location is set then show tables.
    getLocationTables($('input#location_id').val());

    $('select#select_location_id').change(function() {
        var location_id = $(this).val();
        getLocationTables(location_id);
    });

    // ── Floor Plan Overlay on POS Load ─────────────────
    if ($('#table_select_overlay').length) {
        var $overlay = $('#table_select_overlay');
        var $obody   = $('#table_overlay_body');
        var floorUrl = $overlay.data('floor-url') || '/modules/tables-floor-plan';
        var locationId = $('input#location_id').val() || null;

        // Hanya tampil jika belum ada meja terpilih (transaksi baru)
        var preselectedTable = $('span#restaurant_module_span').data('transaction_id');
        if (!preselectedTable) {
            $overlay.show();
            // Load floor plan
            $.get(floorUrl, { location_id: locationId }, function(data) {
                var html = '';
                var hasTable = false;
                $.each(data, function(section, tables) {
                    hasTable = true;
                    html += '<div class="fp-section" style="margin-bottom:20px;">';
                    html += '<div class="fp-section-title" style="font-weight:bold;font-size:13px;background:#4a4a8a;color:#fff;padding:6px 12px;border-radius:4px 4px 0 0;margin-bottom:10px;">' + section + '</div>';
                    html += '<div class="fp-tables" style="display:flex;flex-wrap:wrap;gap:12px;padding:4px 2px;">';
                    $.each(tables, function(i, t) {
                        var cls = 'fp-table fp-' + t.shape + ' fp-' + t.status;
                        var badge = t.transaction_id ? '<span class="fp-badge">1</span>' : '';
                        var cap   = t.transaction_id ? (t.invoice_no || 'Order aktif') : t.capacity + ' kursi';
                        html += '<div class="' + cls + '" data-id="' + t.id + '" data-name="' + t.name + '" data-status="' + t.status + '" data-trxid="' + (t.transaction_id || '') + '" style="cursor:pointer;">';
                        html += badge + '<div class="fp-name">' + t.name + '</div>';
                        html += '<div class="fp-cap">' + cap + '</div></div>';
                    });
                    html += '</div></div>';
                });
                if (!hasTable) {
                    html = '<p class="text-center text-muted" style="padding:40px;">Belum ada meja terdaftar. <a href="/modules/tables/create">Tambah meja</a></p>';
                }
                $obody.html(html);

                // Klik meja
                $obody.find('.fp-table').on('click', function() {
                    var id    = $(this).data('id');
                    var name  = $(this).data('name');
                    var trxId = $(this).data('trxid');
                    // Meja terisi → buka order yang sudah ada
                    if (trxId) {
                        window.location.href = '/pos/payment/' + trxId;
                        return;
                    }
                    // Meja kosong → tanya jumlah tamu
                    showOverlayPaxPrompt($obody, id, name, function(pax) {
                        $('#pax_count').val(pax);
                        if ($('#pax_display_text').length) {
                            $('#pax_display_text').text(pax);
                            pax > 0 ? $('#pax_display').show() : $('#pax_display').hide();
                        }
                        if ($('#res_table_id').length) {
                            $('#res_table_id').val(id).trigger('change');
                        } else {
                            $('span#restaurant_module_span').data('pending_table', id);
                        }
                        $overlay.fadeOut(200);
                    });
                });
            }).fail(function() {
                $obody.html('<p class="text-danger text-center" style="padding:40px;">Gagal memuat floor plan.</p>');
            });
        }

        // Tanpa Meja
        $('#btn_skip_table').on('click', function() {
            $overlay.fadeOut(200);
        });

        // Atur Layout — tutup overlay lalu buka floor plan canvas modal
        $('#btn_atur_layout_overlay').on('click', function() {
            $overlay.fadeOut(200, function() {
                var tries = 0;
                var check = setInterval(function() {
                    if ($('#btn_floor_plan').length) {
                        clearInterval(check);
                        $('#btn_floor_plan').trigger('click');
                    } else if (++tries > 40) {
                        clearInterval(check);
                    }
                }, 100);
            });
        });
    }

    // Set pending table setelah restaurant_module_span selesai diload
    var _origGetLocationTables = window.getLocationTables;
    // (hook via MutationObserver)
    var $span = $('span#restaurant_module_span');
    if ($span.length) {
        var observer = new MutationObserver(function() {
            var pending = $span.data('pending_table');
            if (pending && $('#res_table_id').length) {
                $('#res_table_id').val(pending).trigger('change');
                $span.removeData('pending_table');
            }
        });
        observer.observe($span[0], { childList: true, subtree: true });
    }

    // ── PAX PROMPT untuk overlay pilih meja ────────────────
    function showOverlayPaxPrompt($container, tableId, tableName, onConfirm) {
        $('#overlay_pax_prompt').remove();
        var html = '<div id="overlay_pax_prompt" style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.45);z-index:100;display:flex;align-items:center;justify-content:center;border-radius:0 0 10px 10px;">'
            + '<div style="background:#fff;border-radius:12px;padding:24px 28px;min-width:260px;box-shadow:0 8px 28px rgba(0,0,0,.3);text-align:center;">'
            + '<h4 style="margin:0 0 4px;color:#2d3a8c;"><i class="fa fa-table"></i> ' + tableName + '</h4>'
            + '<p style="color:#666;font-size:13px;margin-bottom:16px;">Masukkan jumlah tamu</p>'
            + '<div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:20px;">'
            + '<button id="ovl_pax_minus" type="button" style="width:38px;height:38px;border-radius:50%;border:2px solid #2d3a8c;background:#fff;font-size:20px;line-height:1;cursor:pointer;color:#2d3a8c;">−</button>'
            + '<input type="number" id="ovl_pax_input" value="2" min="1" max="99" style="width:70px;text-align:center;font-size:26px;font-weight:bold;border:2px solid #ddd;border-radius:8px;padding:4px 6px;">'
            + '<button id="ovl_pax_plus" type="button" style="width:38px;height:38px;border-radius:50%;border:2px solid #2d3a8c;background:#fff;font-size:20px;line-height:1;cursor:pointer;color:#2d3a8c;">+</button>'
            + '</div>'
            + '<button id="ovl_pax_confirm" type="button" style="background:#2d3a8c;color:#fff;border:none;padding:9px 28px;border-radius:6px;font-size:14px;font-weight:bold;cursor:pointer;margin-right:8px;"><i class="fa fa-check"></i> Pilih Meja</button>'
            + '<button id="ovl_pax_cancel" type="button" style="background:#eee;color:#555;border:none;padding:9px 16px;border-radius:6px;font-size:14px;cursor:pointer;">Batal</button>'
            + '</div>'
            + '</div>';

        $container.css('position', 'relative').append(html);
        $('#ovl_pax_input').focus().select();

        $(document).on('click.ovlPax', '#ovl_pax_minus', function() {
            var v = parseInt($('#ovl_pax_input').val()) || 1;
            if (v > 1) $('#ovl_pax_input').val(v - 1);
        });
        $(document).on('click.ovlPax', '#ovl_pax_plus', function() {
            var v = parseInt($('#ovl_pax_input').val()) || 1;
            if (v < 99) $('#ovl_pax_input').val(v + 1);
        });
        $(document).on('click.ovlPax', '#ovl_pax_confirm', function() {
            var pax = parseInt($('#ovl_pax_input').val()) || 0;
            $('#overlay_pax_prompt').remove();
            $(document).off('click.ovlPax keyup.ovlPax');
            onConfirm(pax);
        });
        $(document).on('click.ovlPax', '#ovl_pax_cancel', function() {
            $('#overlay_pax_prompt').remove();
            $(document).off('click.ovlPax keyup.ovlPax');
        });
        $(document).on('keyup.ovlPax', '#ovl_pax_input', function(e) {
            if (e.keyCode === 13) $('#ovl_pax_confirm').trigger('click');
            if (e.keyCode === 27) $('#ovl_pax_cancel').trigger('click');
        });
    }

    $(document).on('click', 'button.add_modifier', function() {
        var checkbox = $(this)
            .closest('div.modal-content')
            .find('input:checked');
        selected = [];
        checkbox.each(function() {
            selected.push($(this).val());
        });
        var index = $(this)
            .closest('div.modal-content')
            .find('input.index')
            .val();

        var quantity = __read_number($(this).closest('tr').find('input.pos_quantity'));
        add_selected_modifiers(selected, index, quantity);
    });
    $(document).on('click', '#refresh_orders', function() {
        refresh_orders();
    });

    //Auto refresh orders
    if ($('#refresh_orders').length > 0) {
        var refresh_interval = parseInt($('#__orders_refresh_interval').val()) * 1000;

        setInterval(function(){ 
            refresh_orders();
        }, refresh_interval);
    }
});

function getLocationTables(location_id) {
    var transaction_id = $('span#restaurant_module_span').data('transaction_id');

    if (location_id != '') {
        $.ajax({
            method: 'GET',
            url: '/modules/data/get-pos-details',
            data: { location_id: location_id, transaction_id: transaction_id },
            dataType: 'html',
            success: function(result) {
                $('span#restaurant_module_span').html(result);
                //REPAIR MODULE:set technician from repair module
                if ($("#repair_technician").length) {
                    $("select#res_waiter_id").val($("#repair_technician").val()).change();
                }
            },
        });
    }
}

function add_selected_modifiers(selected, index, quantity = 1) {
    if (selected.length > 0) {
        $.ajax({
            method: 'GET',
            url: $('button.add_modifier').data('url'),
            data: { selected: selected, index: index, quantity: quantity },
            dataType: 'html',
            success: function(result) {
                if (result != '') {
                    $('table#pos_table tbody')
                        .find('tr')
                        .each(function() {
                            if ($(this).data('row_index') == index) {
                                $(this)
                                    .find('td:first .selected_modifiers')
                                    .html(result);
                                return false;
                            }
                        });

                    //Update total price.
                    pos_total_row();
                }
            },
        });
    } else {
        $('table#pos_table tbody')
            .find('tr')
            .each(function() {
                if ($(this).data('row_index') == index) {
                    $(this)
                        .find('td:first .selected_modifiers')
                        .html('');
                    return false;
                }
            });

        //Update total price.
        pos_total_row();
    }
}

function refresh_orders() {
    $('.overlay').removeClass('hide');
    var orders_for = $('input#orders_for').val();
    var service_staff_id = '';
    if ($('select#service_staff_id').val()) {
        service_staff_id = $('select#service_staff_id').val();
    }
    $.ajax({
        method: 'POST',
        url: '/modules/refresh-orders-list',
        data: { orders_for: orders_for, service_staff_id: service_staff_id },
        dataType: 'html',
        success: function(data) {
            $('#orders_div').html(data);
            $('.overlay').addClass('hide');
        },
    });

    $.ajax({
        method: 'POST',
        url: '/modules/refresh-line-orders-list',
        data: { orders_for: orders_for, service_staff_id: service_staff_id },
        dataType: 'html',
        success: function(data) {
            $('#line_orders_div').html(data);
            $('.overlay').addClass('hide');
        },
    });
}
