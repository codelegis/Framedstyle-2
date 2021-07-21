<?php add_thickbox(); ?>
<div id="modal-payout" style="display:none;">
    <h1>PayPal Payout</h1>
    <form id="form-payout">
        <table id="payout-list" class="wp-list-table widefat striped posts">
            <thead>
                <th>#</th>
                <th>Store ID</th>
                <th>Store Name</th>
                <th>Email</th>
                <th>Amount</th>
            </thead>
            <tbody>
                <!-- -->
            </tbody>
        </table>
        <div class="paypal_creadentials">
            <div class="paypal_creadentials_title">PayPal API credentials</div>
            <label>Client ID:</label>
            <input type="text" autocomplete="off" name="client_id" id="client_id">
            <label>Secret:</label>
            <input type="text" autocomplete="off" name="secret" id="secret">
        </div>
        <div class="fff">
            <div class="box box-title">
                <!-- -->
            </div>
            <div class="box box-controls">
                <button type="button" class="button" onclick="tb_remove();">Cancel</button>
                <button type="button" class="button button-primary" id="paypal-payout-send">Send Payout</button>
            </div>
        </div>
    </form>
    <div id="form-response">
        <div id="form-response-html">
            <!-- -->
        </div>
        <div class="fff">
            <div class="box">
                <button type="button" class="button" id="back">Back</button>
                <button type="button" class="button button-primary" onclick="tb_remove();">Close</button>
            </div>
        </div>
    </div>
</div>

<h1>Reports</h1>

<div id="report_controls">
    <div id="quick_select">
        <select id="pick_month">
            <option></option>
            <?php
                for ($i = 1; $i <= 12; $i++) {
                    echo '<option value="' . sprintf('%02d', $i) . '" data-days="' . date('t', mktime(0, 0, 0, $i, 10)) . '">' . date('F', mktime(0, 0, 0, $i, 10)) . '</option>';
                }
            ?>
        </select>
        <select id="pick_range">
            <option></option>
            <option value="01_07">1 - 7</option>
            <option value="08_14">8 - 14</option>
            <option value="15_21">15 - 21</option>
            <option value="22_x">22 - 31</option>
        </select>
        <button class="button" id="pick">Pick</button>
    </div>
    <label for="date_after" class="label">Date from:</label><br />
    <input type="text" autocomplete="off" name="date_after" id="date_after" value="<?php echo (isset($_GET['after']) ? $_GET['after'] : ''); ?>"><br />
    <label for="date_after" class="label">Date to:</label><br />
    <input type="text" autocomplete="off" name="date_before" id="date_before" value="<?php echo (isset($_GET['before']) ? $_GET['before'] : ''); ?>">
    <button class="button" id="search">Search</button>
</div>

<div class="framedware-report-container">
    <div class="fff">
        <div class="box box-title">
            <h2>Summary report</h2>
        </div>
        <div class="box box-controls">
            <button class="button" id="export_summary">Export .CSV</button>
        </div>
    </div>
    <table id="framedware-report-summary" class="wp-list-table widefat fixed striped posts">
        <thead>
            <th>#</th>
            <th></th>
            <th>Store ID</th>
            <th>Number of orders</th>
            <th>Subtotal Sum</th>
            <th>Shipping total Sum</th>
            <th>Tax total Sum</th>
            <th>Order total Sum</th>
        </thead>
        <tbody>
            <?php
                $i = 1;
                $output = '';
                foreach ($nini as $place => $item) {
                    $store = getPickupLocationDetails($place);
                    $output .=
                        '<tr>' .
                        '<td>' . $i . '.</td>' .
                        '<td><input type="checkbox" id="place_' . $place . '" name="place_' . $place . '" value="1" data-id="' . $place . '" data-amount="' . number_format($item['total_sum'], 2, '.', '') . '" data-name="' . (isset($store['name']) ? htmlentities($store['name']) : '') . '" data-email="' . (isset($store['email']) ? $store['email'] : '') . '"></td>' .
                        '<td><a href="/wp-admin/post.php?post=' . $place . '&action=edit" target="_blank">' . $place . '</a></td>' .
                        '<td>' . $item['number_of_orders'] . '</td>' .
                        '<td>' . $item['currency_symbol'] . number_format($item['subtotal_sum'], 2, '.', '') . ' ' . $item['currency'] . '</td>' .
                        '<td>' . $item['currency_symbol'] . number_format($item['shipping_total_sum'], 2, '.', '') . ' ' . $item['currency'] . '</td>' .
                        '<td>' . $item['currency_symbol'] . number_format($item['total_tax_sum'], 2, '.', '') . ' ' . $item['currency'] . '</td>' .
                        '<td>' . $item['currency_symbol'] . number_format($item['total_sum'], 2, '.', '') . ' ' . $item['currency'] . '</td>' .
                        '</tr>';
                    $i++;
                }
                echo $output;
            ?>
        </tbody>
    </table>
    <div class="fff">
        <div class="box box-title">
            <!-- -->
        </div>
        <div class="box box-controls">
            <button type="button" id="lala" class="button button-primary">Payout</button>
        </div>
    </div>
</div>

<br>
<br>

<div class="framedware-report-container">
    <div class="fff">
        <div class="box box-title">
            <h2>All orders</h2>
        </div>
        <div class="box box-controls">
            <button class="button" id="export_all">Export .CSV</button>
        </div>
    </div>
    <table id="framedware-report-all" class="wp-list-table widefat fixed striped posts">
        <thead>
            <th>#</th>
            <th>Store ID</th>
            <th>Order number</th>
            <th>Order date</th>
            <th>Subtotal</th>
            <th>Shipping total</th>
            <th>Tax total</th>
            <th>Order total</th>
        </thead>
        <tbody>
            <?php
                $i = 1;
                $output = '';
                foreach ($orders as $e) {
                    $place = 'unknown';
                    if (isset($e['shipping_lines']['0']['meta_data']) && ! empty(isset($e['shipping_lines']['0']['meta_data']))) {
                        foreach ($e['shipping_lines']['0']['meta_data'] as $item) {
                            if (isset($item['key']) && $item['key'] == '_pickup_location_id') {
                                $place = '<a href="/wp-admin/post.php?post=' . $item['value'] . '&action=edit" target="_blank">' . $item['value'] . '</a>';
                            }
                        }
                    }
                    $date_created = new DateTime($e['date_created']);
                    $subtotal = 0;
                    if (is_array($e['line_items'])) {
                        foreach ($e['line_items'] as $item) {
                            $subtotal += $item['subtotal'];
                        }
                    }
                    $output .=
                        '<tr>' .
                        '<td>' . $i . '.</td>' .
                        '<td>' . $place . '</td>' .
                        '<td><a href="' . FRAMEDWARE_SITE_URL . '/wp-admin/post.php?post=' . $e['number'] . '&action=edit">' . ' #' . $e['number'] . '</a></td>' .
                        '<td>' . $date_created->format('M d, Y') . '</td>' .
                        '<td>' . $e['currency_symbol'] . number_format($subtotal, 2, '.', '') . ' ' . $e['currency'] . '</td>' .
                        '<td>' . $e['currency_symbol'] . $e['shipping_total'] . ' ' . $e['currency'] . '</td>' .
                        '<td>' . $e['currency_symbol'] . $e['total_tax'] . ' ' . $e['currency'] . '</td>' .
                        '<td>' . $e['currency_symbol'] . $e['total'] . ' ' . $e['currency'] . '</td>' .
                        '</tr>';
                    $i++;
                }
                echo $output;
            ?>
        </tbody>
    </table>
</div>

<script>
    jQuery(document).ready(function(){
        jQuery('#pick').on('click', function() {
            let y = new Date().getFullYear();
            let m = jQuery('#pick_month').val();
            let r = jQuery('#pick_range').val();
            //console.log(y);
            //console.log(m);
            //console.log(r);
            if (m === '' && r === '') {
                alert('Select month / days range.');
                return;
            }
            if (m === '' && r !== '') { // days range selected only
                alert('Select month.');
                return;
            }
            if (m !== '' && r === '') { // month selected only
                d2 = jQuery('#pick_month').find(':selected').data('days');
                let date_1 = y + '-' + m + '-01';
                let date_2 = y + '-' + m + '-' + d2;
                jQuery('#date_after').val(date_1);
                jQuery('#date_before').val(date_2);
            }
            if (m !== '' && r !== '') { // both selected
                let a = r.split('_');
                //console.log(a);
                let d1 = a[0];
                let d2 = a[1];
                if (d2 == 'x') {
                    d2 = jQuery('#pick_month').find(':selected').data('days');
                }
                //console.log(d1);
                //console.log(d2);
                let date_1 = y + '-' + m + '-' + d1;
                let date_2 = y + '-' + m + '-' + d2;
                jQuery('#date_after').val(date_1);
                jQuery('#date_before').val(date_2);
            }
        });
        jQuery('#date_after, #date_before').datepicker({
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
        });
        jQuery('#search').on('click', function() {
            let url = '<?php echo FRAMEDWARE_SITE_URL . '/wp-admin/admin.php?page=framedware_report'; ?>';
            if (jQuery('#date_after').val().length > 0) {
                url += '&after=' + jQuery('#date_after').val();
            }
            if (jQuery('#date_before').val().length > 0) {
                url += '&before=' + jQuery('#date_before').val();
            }
            if (jQuery('#date_after').val().length == 0 && jQuery('#date_before').val().length == 0) {
                console.log('Criteria is empty.');
            } else {
                location.href = url;
            }
            console.log(url);
        });
        jQuery('#export_summary').on('click', function() {
            let url = '<?php echo FRAMEDWARE_SITE_URL . '/framedware/report/export/summary?'; ?>';
            if (jQuery('#date_after').val().length > 0) {
                url += '&after=' + jQuery('#date_after').val();
            }
            if (jQuery('#date_before').val().length > 0) {
                url += '&before=' + jQuery('#date_before').val();
            }
            if (jQuery('#date_after').val().length == 0 && jQuery('#date_before').val().length == 0) {
                alert('List is empty.');
            } else {
                location.href = url;
            }
            console.log(url);
        });
        jQuery('#export_all').on('click', function() {
            let url = '<?php echo FRAMEDWARE_SITE_URL . '/framedware/report/export/all?'; ?>';
            if (jQuery('#date_after').val().length > 0) {
                url += '&after=' + jQuery('#date_after').val();
            }
            if (jQuery('#date_before').val().length > 0) {
                url += '&before=' + jQuery('#date_before').val();
            }
            if (jQuery('#date_after').val().length == 0 && jQuery('#date_before').val().length == 0) {
                alert('List is empty.');
            } else {
                location.href = url;
            }
            console.log(url);
        });
        jQuery('#lala').on('click', function() {
            let html = '';
            let i = 1;
            let count = jQuery('#framedware-report-summary').find('input[type="checkbox"]:checked').length;
            if (count == 0) {
                alert('None selected.');
                return;
            }
            jQuery('#framedware-report-summary').find('input[type="checkbox"]:checked').each(function () {
                //console.log(jQuery(this));
                let id = jQuery(this).data('id');
                let id_link = '<a href="/wp-admin/post.php?post=' + id + '&action=edit" target="_blank">' + id + '</a>';
                let name = jQuery(this).data('name');
                let email = jQuery(this).data('email');
                let amount = jQuery(this).data('amount');
                html += '<tr><td>' + i + '.</td><td>' + id_link + '</td><td>' + name + '</td><td><input type="text" autocomplete="off" name="store_' + id + '[email]" id="store_' + id + '[email]" value="' + email + '" class=""></td><td><input type="text" autocomplete="off" name="store_' + id + '[amount]" id="store_' + id + '[amount]" value="' + amount + '" class=""></td></tr>';
                i++;
            });
            //console.log(html);
            jQuery('#modal-payout tbody').html(html);
            tb_show('', '#TB_inline?width=800&height=500&inlineId=modal-payout')
        });
        jQuery('#paypal-payout-send').on('click', function() {
            let data = jQuery('#form-payout').serialize();
            jQuery.ajax({
                url: framedwareWriteAjax.ajaxurl,
                type: 'POST',
                data: {
                    'data': data,
                    'action': 'paypal_payout_send'  // <-- WP action
                },
                dataType: 'json',
                success: function (response) {
                    console.log(response);
                    jQuery('#form-response-html').html(response.html);
                    jQuery('#form-payout').hide();
                    jQuery('#form-response').show();
                },
                error: function(xhr, status, error) {
                    //
                }
            });
        });
        jQuery('#back').on('click', function() {
            jQuery('#form-response-html').html('');
            jQuery('#form-payout').show();
            jQuery('#form-response').hide();
        });
    });
</script>