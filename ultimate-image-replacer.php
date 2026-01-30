<?php
/**
 * Plugin Name: Ultimate Image & ID Replacer
 * Plugin URI:  https://github.com/sflwa/ultimate-image-replacer/
 * Description: Recursively replaces multiple source image URLs and Attachment IDs with a target replacement. Safe for Serialized PHP and JSON data with escaped slashes.
 * Version:     2.1
 * Author:      SFLWA
 * Author URI:  https://sflwa.net/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ultimate-image-replacer
 * Requires PHP: 7.4
 * Tested up to: 6.9
 */

if (!defined('ABSPATH')) exit;

// 1. Register the Tools Menu
add_action('admin_menu', function() {
    add_management_page(
        __('Image Replacer', 'ultimate-image-replacer'),
        __('Image Replacer', 'ultimate-image-replacer'),
        'manage_options',
        'ultimate-image-replacer',
        'uir_render_admin_page'
    );
});

// 2. Load WordPress Media Library Scripts
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'tools_page_ultimate-image-replacer') return;
    wp_enqueue_media();
});

// 3. Admin UI Logic
function uir_render_admin_page() {
    if (isset($_POST['uir_process']) && check_admin_referer('uir_action', 'uir_nonce')) {
        uir_process_replacement();
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Ultimate Image & ID Replacer', 'ultimate-image-replacer'); ?></h1>
        <p><?php _e('Safely swap old media assets for a new one across all content and meta (JSON & Serialized compatible).', 'ultimate-image-replacer'); ?></p>
        
        <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 800px;">
            <?php wp_nonce_field('uir_action', 'uir_nonce'); ?>
            
            <div style="margin-bottom: 30px;">
                <h3>1. <?php _e('Select Source Images (Old)', 'ultimate-image-replacer'); ?></h3>
                <p class="description"><?php _e('Select one or more images you want to replace everywhere.', 'ultimate-image-replacer'); ?></p>
                <button type="button" id="uir_select_sources" class="button"><?php _e('Select Sources', 'ultimate-image-replacer'); ?></button>
                <div id="uir_source_preview" style="margin-top:15px; display: flex; gap: 10px; flex-wrap: wrap;"></div>
                <input type="hidden" name="source_ids" id="source_ids">
            </div>

            <hr>

            <div style="margin-bottom: 30px;">
                <h3>2. <?php _e('Select Target Image (New)', 'ultimate-image-replacer'); ?></h3>
                <p class="description"><?php _e('Select the single image that will take the place of the sources.', 'ultimate-image-replacer'); ?></p>
                <button type="button" id="uir_select_target" class="button"><?php _e('Select Target', 'ultimate-image-replacer'); ?></button>
                <div id="uir_target_preview" style="margin-top:15px;"></div>
                <input type="hidden" name="target_id" id="target_id">
            </div>

            <div style="padding: 15px; background: #fcf3f3; border-left: 4px solid #d63638; margin-bottom: 20px;">
                <p style="margin:0;"><strong><?php _e('Critical:', 'ultimate-image-replacer'); ?></strong> <?php _e('Always back up your database before running this tool. This will modify wp_posts and wp_postmeta directly.', 'ultimate-image-replacer'); ?></p>
            </div>
            
            <input type="submit" name="uir_process" class="button button-primary button-large" value="<?php _e('Execute Replacement', 'ultimate-image-replacer'); ?>">
        </form>
    </div>

    <script>
    jQuery(document).ready(function($){
        var srcFrame, tgtFrame;
        $('#uir_select_sources').on('click', function(e){
            e.preventDefault();
            if (srcFrame) { srcFrame.open(); return; }
            srcFrame = wp.media({ title: 'Select Old Media', button: { text: 'Select' }, multiple: true });
            srcFrame.on('select', function() {
                var selection = srcFrame.state().get('selection');
                var ids = [];
                $('#uir_source_preview').html('');
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    ids.push(attachment.id);
                    $('#uir_source_preview').append('<img src="'+attachment.url+'" style="width:60px;height:60px;object-fit:cover;border:1px solid #ddd;">');
                });
                $('#source_ids').val(ids.join(','));
            });
            srcFrame.open();
        });

        $('#uir_select_target').on('click', function(e){
            e.preventDefault();
            if (tgtFrame) { tgtFrame.open(); return; }
            tgtFrame = wp.media({ title: 'Select New Media', button: { text: 'Select' }, multiple: false });
            tgtFrame.on('select', function() {
                var attachment = tgtFrame.state().get('selection').first().toJSON();
                $('#target_id').val(attachment.id);
                $('#uir_target_preview').html('<img src="'+attachment.url+'" style="width:120px;border:2px solid #2271b1;padding:2px;">');
            });
            tgtFrame.open();
        });
    });
    </script>
    <?php
}

// 4. Processing Core
function uir_process_replacement() {
    global $wpdb;
    $source_ids = array_filter(explode(',', $_POST['source_ids']));
    $target_id = intval($_POST['target_id']);

    if (empty($source_ids) || !$target_id) {
        echo '<div class="error"><p>'.__('Error: Please select both source and target images.', 'ultimate-image-replacer').'</p></div>';
        return;
    }

    $target_url = wp_get_attachment_url($target_id);

    foreach ($source_ids as $s_id) {
        $s_url = wp_get_attachment_url($s_id);
        $s_escaped = str_replace('/', '\/', $s_url);
        
        // Find rows that likely contain the data to avoid scanning the entire DB line by line
        $tables = [
            $wpdb->posts => 'post_content',
            $wpdb->postmeta => 'meta_value'
        ];

        foreach ($tables as $table => $column) {
            $results = $wpdb->get_results("SELECT * FROM $table WHERE $column LIKE '%" . $wpdb->esc_like($s_url) . "%' OR $column LIKE '%" . $wpdb->esc_like($s_escaped) . "%' OR $column LIKE '%id\":$s_id%'");

            foreach ($results as $row) {
                $pk = ($table == $wpdb->posts) ? 'ID' : 'meta_id';
                $old_data = $row->$column;
                $new_data = uir_recursive_worker($s_id, $target_id, $s_url, $target_url, $old_data);

                if ($old_data !== $new_data) {
                    $wpdb->update($table, [$column => $new_data], [$pk => $row->$pk]);
                }
            }
        }
    }
    echo '<div class="updated"><p>'.__('Success: Database updated. Please clear your site cache.', 'ultimate-image-replacer').'</p></div>';
}

function uir_recursive_worker($old_id, $new_id, $old_url, $new_url, $data) {
    if (empty($data)) return $data;

    if (is_serialized($data)) {
        $unserialized = unserialize($data);
        return serialize(uir_recursive_worker($old_id, $new_id, $old_url, $new_url, $unserialized));
    }

    if (is_string($data) && (strpos($data, '{') === 0 || strpos($data, '[') === 0)) {
        $json = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $replaced_json = uir_recursive_worker($old_id, $new_id, $old_url, $new_url, $json);
            return json_encode($replaced_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = uir_recursive_worker($old_id, $new_id, $old_url, $new_url, $value);
        }
        return $data;
    }

    if (is_string($data) || is_numeric($data)) {
        if ($data == $old_id) return (string)$new_id;
        
        $old_esc = str_replace('/', '\/', $old_url);
        $new_esc = str_replace('/', '\/', $new_url);
        
        $data = str_replace($old_url, $new_url, $data);
        $data = str_replace($old_esc, $new_esc, $data);
    }

    return $data;
}
