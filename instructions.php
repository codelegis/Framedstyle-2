<?php
  echo '
# Admin instructions
    <a href="https://www.dropbox.com/sh/r09rr3pmf9y3i1t/AAD3vrjVwEiwoeScCovLHf5Na?dl=0" target="_blank">Admin instructions</a>


# Configuration
    1. Activate plugin. Product category "Uploads" is automatically created, if it does not already exist.
    2. Connect WooCommerce in Plugin Options.
    3. Create <a href="http://frameshops.test/wp-admin/admin.php?page=wc-settings&tab=advanced&section=keys" target="_blank">WooCommerce API keys</a>, if they do not exist already. Make sure to copy your new keys now as the secret key will be hidden once you leave that page. 
    4. Create <a href="/wp-admin/admin.php?page=wc-settings&tab=advanced&section=webhooks" target="_blank">WooCommerce webhook</a> with following required data:        
        * Status: Active
        * Topic: Order created        
        * Delivery URL: ' . FRAMEDWARE_SITE_URL . '/framedware/woo-webhook-order-complete
        * API version: v2
    5. Add the shortcodes bellow, to the page(s) of your choice.
    6. Create server cron job (daily) to run ' . FRAMEDWARE_SITE_URL . '/framedware/cron
        

# SHORTCODE parameters
    SINGLE FILE
    [framedeware_single instance="<span title="alphanumeric, underscore" style="background-color: rgba(212,201,190,0.4);">unique_value</span>"] 
    [framedeware_adobe_stock instance="<span title="alphanumeric, underscore" style="background-color: rgba(212,201,190,0.4);">unique_value</span>"]   
    
    GALLERY WALLS
    [framedeware_gallery_wall_1x3]
    [framedeware_gallery_wall_2x4]
    [framedeware_gallery_wall_3x3]
    [framedeware_gallery_wall_4x3]
    [framedeware_gallery_wall_stairway]
    
    FRAME PRO
    [framedeware_framepro]


# MANUALLY
    * To manually run cron job script click <a href="' . FRAMEDWARE_SITE_URL . '/framedware/cron" target="_blank">here</a>.
    * To manually create pickup location order folders click <a href="' . FRAMEDWARE_SITE_URL . '/framedware/location/prep" target="_blank">here</a>.
    * To manually delete upload files, FTP to: /public_html/uploadhandler/uploads/
    * To change frontend configuration (prices, filestack), FTP to: /public_html/wp-content/plugins/framedware/config.js
    * To change backend configuration (cron job, PayPal), FTP to: /public_html/wp-content/plugins/framedware/config.php
    
    
# FRAME PARTS LEGEND
    See <a href="' . FRAMEDWARE_PLUGIN_URL . '/assets/img/admin/legend.jpg" target="_blank">image</a>. 
';