<?php
add_action('show_user_profile', 'aadb2c_user_fields');
add_action('edit_user_profile', 'aadb2c_user_fields');
function aadb2c_user_fields($user)
{
?>
    <h3>Azure B2C Fields</h3>

    <table class="form-table">
        <?php
        $fields["aadb2c_object_id"] = array(
            "label" => "Object Id",
            "meta"     => "aadb2c_object_id",
        );
        $fields = apply_filters('aadb2c_user_fields_filter', $fields);

        foreach ($fields as $key => $value) {
        ?>
            <tr>
                <th><label for="<?php echo $key ?>"><?php echo $value["label"] ?></label></th>
                <td><input type="text" name="<?php echo $key ?>" value="<?php echo esc_attr(get_the_author_meta($value["meta"], $user->ID)); ?>" class="regular-text" /></td>
            </tr>
        <?php
        }
        ?>
    </table>
<?php
}

add_action('personal_options_update', 'aadb2c_update_user_fields');
add_action('edit_user_profile_update', 'aadb2c_update_user_fields');
function aadb2c_update_user_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $fields["aadb2c_object_id"] = array(
        "label" => "Object Id",
        "meta"     => "aadb2c_object_id",
    );
    $fields = apply_filters('aadb2c_update_user_fields_filter', $fields);

    foreach ($fields as $key => $value) {
        update_user_meta($user_id, $value["meta"], sanitize_text_field($_POST[$key]));
    }
}
