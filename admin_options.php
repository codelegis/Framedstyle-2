<?php
    global $wpdb;
    $woo = $wpdb->get_results( "SELECT * FROM `fware_woo` LIMIT 1;", ARRAY_A )[0];
    $shippo_api_key = $wpdb->get_var( "SELECT `data` FROM `fware_config_x` WHERE `key` = 'shippo_api_key' LIMIT 1");
?>

<div class="container">
    <h1 class="option_title">FramedWare Options</h1>
</div>

<!--
<div class="container">
    <h3>WooCommerce API Connect</h3>
    <!- -<span class="description">Use this API key to connect to WooCommerce</span>- ->
    <?php if(empty($woo['woo_consumer_key']) || empty($woo['woo_consumer_secret'])) {?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="framedware-consumer-key">Consumer key</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Consumer key</span></legend>
                        <input class="input-text regular-input required" type="text" name="framedware-consumer-key" id="framedware-consumer-key" style="" value="" placeholder="" />
                    </fieldset>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="framedware-consumer-secret">Consumer secret</label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Consumer secret</span></legend>
                        <input class="input-text regular-input required" type="text" name="framedware-consumer-secret" id="framedware-consumer-secret" style="" value="" placeholder="" />
                    </fieldset>
                </td>
            </tr>
        </table>
        <button class="button button-primary" id="framedware-woocommerce-connect" type="button">Save</button>
    <?php } else { ?>
        <p>You are now connected to WooCommerce.</p>
        <button class="button button-primary" id="framedware-woocommerce-disconnect" type="button">Disconnect</button>
    <?php }?>
</div>
-->

<!--
<div class="container">
    <h3>shippo</h3>
    <table class="form-table">
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="framedware-shippo-api-key">Shippo API key</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Shippo API key</span></legend>
                    <input class="input-text regular-input required" type="text" name="framedware-shippo-api-key" id="framedware-shippo-api-key" style="" value="<?php echo $shippo_api_key; ?>" placeholder="" />
                </fieldset>
            </td>
        </tr>
    </table>
    <button class="button button-primary" id="framedware-shippo-save" type="button">Save</button>
</div>
-->

<div class="container">
    <h3>Reports</h3>
    <a href="<?php echo FRAMEDWARE_SITE_URL . '/wp-admin/admin.php?page=framedware_report'; ?>">
        <button class="button">Reports</button>
    </a>
</div>

<div class="container">
    <div class="framedware-admin-container">
        <h3>Options</h3>
        <form method="post" id="form_config">
            <div id="framedware_result"></div>
            <input type="checkbox" name="ui_3d" id="ui_3d" value="1">
            <label class="checkbox-label" for="ui_3d">Show 3D</label>
            <br>
            <input type="checkbox" name="skip_crop" id="skip_crop" value="1">
            <label class="checkbox-label" for="skip_crop">Skip Ratios Screen</label>
            <!-- Save user input values  -->
            <button type="button" class="cgd_save_user_input framedware_config_save_ii">Save</button>
        </form>
    </div>
</div>

<div class="container">
    <h3>Instructions</h3>
    <p class="description-label">Loaded from <?php echo PLUGINPATH . 'instructions.php'; ?></p>
    <pre class="install-instructions">
    <?php
        include (PLUGINPATH . 'instructions.php');
    ?>
    </pre>
</div>