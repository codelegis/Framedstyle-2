<?php
    global $wpdb;
    $list = $wpdb->get_results($wpdb->prepare("SELECT * FROM `fware_pricelist`;"));
?>
<h1>Art | Price List</h1>
<div class="framedware-admin-container-full">
    <div>
        <a href="<?php echo menu_page_url('framedware_pricelist_input', false); ?>" class="clean">
            <button type="button" class="cgd_save_user_input">New</button>
        </a>
    </div>
    <table class="table table-striped" id="art_list">
        <thead>
        <tr>
            <th scope="col">Name</th>
            <th scope="col">Actions</th>
        </tr>
        </thead>
        <?php
            if ( ! empty($list)) {
                foreach ($list as $item) {
                    echo '
                    <tbody>
                    <tr>
                        <td>' . $item->name . '</td>
                        <td>
                            <a href="' . menu_page_url('framedware_pricelist_input', false) . '&id=' . $item->id . '">Edit</a> |  
                            <a href="javascript:;" onclick="pricelist_delete(' . $item->id . ')">Delete</a>
                        </td>
                    </tr>
                    </tbody>';
                }
            }
        ?>
    </table>
</div>
