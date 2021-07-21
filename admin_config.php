<?php
    //var_dump(json_decode($data, true)); // test
    //var_dump($shipping_classes); // test
?>
<h1 class="frameware_headline">FramedWare Configuration</h1>
<div class="framedware-admin-container">
    <div class="config_get_data">
        <form method="post" id="form_config">

            <div id="framedware_result"></div>

            <!-- Restore default values for all input fields -->
            <button type="button" class="cgd_restore cgd_restore_all framedware_config_restore">Restore Default values</button>

            <!-- Save user input values  -->
            <button type="button" class="cgd_save_user_input framedware_config_save">Save</button>

            <div class="cgd_section cgd_section_a">
                <label for="default_min_print_res">Minimum Print Resolution (dots per <?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                <input type="number" name="default_min_print_res" id="default_min_print_res">
                <label for="minimum_print_length">Minimum Print Length (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                <input type="number" name="minimum_print_length" id="minimum_print_length">
                <label for="frame_weight_factor">Frame Weight Factor</label>
                <input type="number" name="frame_weight_factor" id="frame_weight_factor" step=".01">
                <label for="frame_size_padding">Frame Size Padding (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                <input type="number" name="frame_size_padding" id="frame_size_padding">
                <label for="wall_image_width">Wall Picture Width (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                <input type="number" name="wall_image_width" id="wall_image_width">
                <label for="mat_size">Mat Size (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                <input type="number" name="mat_size" id="mat_size" step=".1">
            </div> <!-- cgd_section_a -->

            <div class="cgd_section cgd_section_b">
                <span class="cgd_section_inner_title">Gallery Walls</span>
                <div class="cgd_section_inner cgd_section_custom">

                    <div id="container_wall">
                        <?php
                            $gw = ['1x3', '2x4', '3x3', '4x3', 'stairway'];
                            foreach ($gw as $w) {
                        ?>
                        <div class="cgd_ratio_wrapper">
                            <span class="cgd_section_inner_title"><?php echo ucfirst($w); ?></span>
                            <div class="cgd_section_column_wrapper">
                                <div class="cgd_section_column cgd_column_gw">
                                    <label for="wall_price_<?php echo $w; ?>">Price (<?php echo FRAMEDWARE_CURRENCY; ?>)</label>
                                    <input type="number" name="wall_price_<?php echo $w; ?>" id="wall_price_<?php echo $w; ?>" step="0.01">
                                </div> <!-- cgd_section_column -->
                                <div class="cgd_section_column cgd_column_gw">
                                    <label for="wall_weight_<?php echo $w; ?>">Weight (<?php echo FRAMEDWARE_UNIT_WEIGHT; ?>)</label>
                                    <input type="number" name="wall_weight_<?php echo $w; ?>" id="wall_weight_<?php echo $w; ?>" step="0.01">
                                </div> <!-- cgd_section_column -->
                                <div class="cgd_section_column cgd_column_gw">
                                    <label for="wall_length_<?php echo $w; ?>">Length (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                                    <input type="number" name="wall_length_<?php echo $w; ?>" id="wall_length_<?php echo $w; ?>" step="0.01">
                                </div> <!-- cgd_section_column -->
                                <div class="cgd_section_column cgd_column_gw">
                                    <label for="wall_width_<?php echo $w; ?>">Width (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                                    <input type="number" name="wall_width_<?php echo $w; ?>" id="wall_width_<?php echo $w; ?>" step="0.01">
                                </div> <!-- cgd_section_column -->
                                <div class="cgd_section_column cgd_column_gw">
                                    <label for="wall_height_<?php echo $w; ?>">Height (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                                    <input type="number" name="wall_height_<?php echo $w; ?>" id="wall_height_<?php echo $w; ?>" step="0.01">
                                </div> <!-- cgd_section_column -->
                                <div class="cgd_section_column cgd_column_gw">
                                    <label class="shipping-select" for="wall_shipping_class_<?php echo $w; ?>">Shipping Class</label>
                                    <select name="wall_shipping_class_<?php echo $w; ?>" id="wall_shipping_class_<?php echo $w; ?>" step="0.01">
                                        <option value=""></option>
                                        <?php
                                        if (is_array($shipping_classes) && ! empty($shipping_classes)) {
                                            foreach ($shipping_classes as $shipping_class) {
                                                echo '<option value="' . $shipping_class->term_id . '">' . $shipping_class->name . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div> <!-- cgd_section_column -->
                            </div>
                        </div>
                        <?php
                            }
                        ?>

                    </div>
                </div> <!-- Custom Section End -->
            </div>

            <div class="cgd_section cgd_section_b">
                    <span class="cgd_section_title">Ratios for precropping frames</span>
                        <div class="checkbox_container">
                            <input type="checkbox" name="ui_custom_custom" id="ui_custom_custom" value="1">
                            <label for="ui_custom_custom">Custom Aspect Ratio</label>
                            <br>
                            <input type="checkbox" name="ui_express_1_1" id="ui_express_1_1" value="1">
                            <label for="ui_express_1_1">1:1 Aspect Ratio</label>
                            <br>
                            <input type="checkbox" name="ui_express_3_2" id="ui_express_3_2" value="1">
                            <label for="ui_express_3_2">3:2 Aspect Ratio</label>
                            <br>
                            <input type="checkbox" name="ui_express_4_3" id="ui_express_4_3" value="1">
                            <label for="ui_express_4_3">4:3 Aspect Ratio</label>
                            <br>
                            <input type="checkbox" name="ui_express_16_9" id="ui_express_16_9" value="1">
                            <label for="ui_express_16_9">16:9 Aspect Ratio</label>
                            <br>
                    </div><!--End of checkbox_container-->

                <script id="config-template-paper" type="text/x-config-template-paper">
                    <div class="cgd_ratio_wrapper paper paper_{ratio}" id="paper_{key}">
                        <div class="cgd_section_column_wrapper">
                            <div class="cgd_section_column">
                                <label for="paper[{ratio}][{key}][long_side]">Size : Long Side (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                                <input type="number" name="paper[{ratio}][{key}][long_side]" id="paper[{ratio}][{key}][long_side]">
                                <label for="paper[{ratio}][{key}][price]">Price (<?php echo FRAMEDWARE_CURRENCY; ?>)</label>
                                <input type="number" name="paper[{ratio}][{key}][price]" id="paper[{ratio}][{key}][price]">
                            </div> <!-- cgd_section_column -->
                            <div class="cgd_section_column">
                                <label for="paper[{ratio}][{key}][short_side]">Size : Short Side (<?php echo FRAMEDWARE_UNIT_DIMENSION; ?>)</label>
                                <input type="number" name="paper[{ratio}][{key}][short_side]" id="paper[{ratio}][{key}][short_side]">
                                <label for="paper[{ratio}][{key}][invisible_glass_price]">Add on for Invisible Glass</label>
                                <input type="number" name="paper[{ratio}][{key}][invisible_glass_price]" id="paper[{ratio}][{key}][invisible_glass_price]">
                            </div> <!-- cgd_section_column -->
                            <div class="cgd_section_column">
                                <label for="paper[{ratio}][{key}][shipping_class]">Shipping Class</label>
                                <select name="paper[{ratio}][{key}][shipping_class]" id="paper[{ratio}][{key}][shipping_class]">
                                    <option value=""></option>
                                    <?php
                                        if (is_array($shipping_classes) && ! empty($shipping_classes)) {
                                            foreach ($shipping_classes as $shipping_class) {
                                                echo '<option value="' . $shipping_class->term_id . '">' . $shipping_class->name . '</option>';
                                            }
                                        }
                                    ?>
                                </select>
                                <label class="adobe_stock_select" for="paper[{ratio}][{key}][adobe_stock_retail]">Adobe Stock Retail</label>
                                <input type="number" name="paper[{ratio}][{key}][adobe_stock_retail]" id="paper[{ratio}][{key}][adobe_stock_retail]">
                            </div> <!-- cgd_section_column -->
                            <div class="cgd_section_column cgd_column_third">
                                <div class="cgd_section_column_inner">
                                    <input type="radio" name="paper[{ratio}][{key}][active]" id="paper[{ratio}][{key}][active]_active" value="1" checked>
                                    <label for="paper[{ratio}][{key}][active]_active">Active</label>
                                    <br>
                                    <input type="radio" name="paper[{ratio}][{key}][active]" id="paper[{ratio}][{key}][active]_inactive" value="0">
                                    <label for="paper[{ratio}][{key}][active]_inactive">Inactive</label>
                                </div>
                                <div class="cgd_section_column_inner">
                                    <button type="button" class="cgd_remove paper_remove">Remove Size</button>
                                </div>
                            </div> <!-- cgd_section_column -->
                        </div>
                    </div>
                </script>

                <!-- Custom Section -->
                <div class="cgd_section_inner cgd_section_custom">
                    <span class="cgd_section_inner_title">Custom Ratio</span>
                    <div id="container_custom_custom">
                        <!-- /// -->
                    </div>
                    <div class="cgd_add_size">
                        <button type="button" class="cgd_add_size_button paper_add" data-ratio="custom_custom">Add Custom Size</button>
                    </div>
                </div> <!-- Custom Section End -->

                <div class="cgd_hidden_advanced">
                    <!-- 1:1 Section -->
                    <div class="cgd_section_inner cgd_section_1_1">
                        <span class="cgd_section_inner_title">1:1 Ratio</span>
                        <div id="container_express_1_1">
                            <!-- /// -->
                        </div>
                        <div class="cgd_add_size">
                            <button type="button" class="cgd_add_size_button paper_add" data-ratio="express_1_1">Add 1:1 Size</button>
                        </div>
                    </div> <!-- 1:1 Section End -->

                    <!-- 3:2 Section -->
                    <div class="cgd_section_inner cgd_section_3_2">
                        <span class="cgd_section_inner_title">3:2 Ratio</span>
                        <div id="container_express_3_2">
                            <!-- /// -->
                        </div>
                        <div class="cgd_add_size">
                            <button type="button" class="cgd_add_size_button paper_add" data-ratio="express_3_2">Add 3:2 Size</button>
                        </div>
                    </div> <!-- 3:2 Section End -->

                    <!-- 4:3 Section -->
                    <div class="cgd_section_inner cgd_section_4_3">
                        <span class="cgd_section_inner_title">4:3 Ratio</span>
                        <div id="container_express_4_3">
                            <!-- /// -->
                        </div>
                        <div class="cgd_add_size">
                            <button type="button" class="cgd_add_size_button paper_add" data-ratio="express_4_3">Add 4:3 Size</button>
                        </div>
                    </div> <!-- 4:3 Section End -->

                    <!-- 16:9 Section -->
                    <div class="cgd_section_inner cgd_section_16_9">
                        <span class="cgd_section_inner_title">16:9 Ratio</span>
                        <div id="container_express_16_9">
                            <!-- /// -->
                        </div>
                        <div class="cgd_add_size">
                            <button type="button" class="cgd_add_size_button paper_add" data-ratio="express_16_9">Add 16:9 Size</button>
                        </div>
                    </div> <!-- 16:9 Section End -->

                </div> <!-- cgd_hidden_advanced -->
            </div> <!-- cgd_section_b -->

            <div class="cgd_section cgd_section_d">
                <label for="lowres_title">Resolution too low Title</label><br>
                <input type="text" name="lowres_title" id="lowres_title"><br>

                <label for="lowres_mesaage">Resolution too low Message</label><br>
                <input type="text" name="lowres_message" id="lowres_message"><br>

            </div> <!-- cgd_section_b -->

            <label for="cgd_advanced_button_input" class="cgd_advanced_button">Advanced</label>



            <!-- Save user input values  -->
            <button type="button" class="cgd_save_user_input framedware_config_save">Save</button>

        </form>
    </div> <!-- config_get_data -->
</div>
