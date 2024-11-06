<?php
namespace XAutoPoster\Admin;

class Settings {
    public function registerSettings() {
        register_setting(
            'xautoposter_options',
            'xautoposter_options',
            [$this, 'validateOptions']
        );

        // Twitter API Settings Section
        add_settings_section(
            'xautoposter_twitter_settings',
            __('Twitter API Settings', 'xautoposter'),
            [$this, 'renderSettingsHeader'],
            'xautoposter-api-settings'
        );

        // API Key
        add_settings_field(
            'api_key',
            __('API Key', 'xautoposter'),
            [$this, 'renderTextField'],
            'xautoposter-api-settings',
            'xautoposter_twitter_settings',
            ['name' => 'api_key']
        );

        // API Secret
        add_settings_field(
            'api_secret',
            __('API Secret', 'xautoposter'),
            [$this, 'renderTextField'],
            'xautoposter-api-settings',
            'xautoposter_twitter_settings',
            ['name' => 'api_secret']
        );

        // Access Token
        add_settings_field(
            'access_token',
            __('Access Token', 'xautoposter'),
            [$this, 'renderTextField'],
            'xautoposter-api-settings',
            'xautoposter_twitter_settings',
            ['name' => 'access_token']
        );

        // Access Token Secret
        add_settings_field(
            'access_token_secret',
            __('Access Token Secret', 'xautoposter'),
            [$this, 'renderTextField'],
            'xautoposter-api-settings',
            'xautoposter_twitter_settings',
            ['name' => 'access_token_secret']
        );

        // Auto Share Settings Section
        add_settings_section(
            'xautoposter_auto_share_settings',
            __('Automatic Share Settings', 'xautoposter'),
            [$this, 'renderAutoShareHeader'],
            'xautoposter-auto-share'
        );

        // Share Interval
        add_settings_field(
            'interval',
            __('Share Interval', 'xautoposter'),
            [$this, 'renderIntervalField'],
            'xautoposter-auto-share',
            'xautoposter_auto_share_settings'
        );

        // Categories
        add_settings_field(
            'categories',
            __('Categories to Share', 'xautoposter'),
            [$this, 'renderCategoriesField'],
            'xautoposter-auto-share',
            'xautoposter_auto_share_settings'
        );

        // Auto Share Enable/Disable
        add_settings_field(
            'auto_share',
            __('Enable Auto Share', 'xautoposter'),
            [$this, 'renderCheckboxField'],
            'xautoposter-auto-share',
            'xautoposter_auto_share_settings',
            ['name' => 'auto_share']
        );

        // Post Template
        add_settings_field(
            'post_template',
            __('Post Template', 'xautoposter'),
            [$this, 'renderTemplateField'],
            'xautoposter-auto-share',
            'xautoposter_auto_share_settings'
        );
    }

    public function renderSettingsHeader() {
        $api_verified = get_option('xautoposter_api_verified', false);
        $api_error = get_option('xautoposter_api_error', '');
        
        if ($api_verified) {
            echo '<div class="notice notice-success inline"><p>' . 
                 esc_html__('Twitter API connection is working correctly. Settings are now locked for security.', 'xautoposter') . 
                 '</p></div>';
            
            echo '<div class="api-lock-controls">';
            echo '<button type="button" id="unlock-api-settings" class="button">' .
                 esc_html__('Modify API Settings', 'xautoposter') .
                 '</button>';
            echo '</div>';
        } elseif ($api_error) {
            echo '<div class="notice notice-error inline"><p>' . 
                 esc_html($api_error) . 
                 '</p></div>';
        } else {
            echo '<div class="notice notice-info inline"><p>' . 
                 esc_html__('Please enter your Twitter API credentials and save to verify the connection.', 'xautoposter') . 
                 '</p></div>';
        }
    }

    public function renderAutoShareHeader() {
        echo '<p>' . esc_html__('Configure automatic sharing settings below.', 'xautoposter') . '</p>';
    }

    public function renderTextField($args) {
        $options = get_option('xautoposter_options', []);
        $name = $args['name'];
        $value = isset($options[$name]) ? $options[$name] : '';
        $api_verified = get_option('xautoposter_api_verified', false);
        
        printf(
            '<input type="text" id="%1$s" name="xautoposter_options[%1$s]" value="%2$s" class="regular-text" %3$s>',
            esc_attr($name),
            esc_attr($value),
            $api_verified ? 'disabled="disabled"' : ''
        );
    }

    public function renderIntervalField() {
        $options = get_option('xautoposter_options', []);
        $current = isset($options['interval']) ? $options['interval'] : '30min';
        
        $intervals = [
            '5min' => __('Every 5 minutes', 'xautoposter'),
            '15min' => __('Every 15 minutes', 'xautoposter'),
            '30min' => __('Every 30 minutes', 'xautoposter'),
            '60min' => __('Every hour', 'xautoposter')
        ];
        
        echo '<select name="xautoposter_options[interval]" id="interval">';
        foreach ($intervals as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
        
        echo '<p class="description">' . 
             esc_html__('Select how often posts should be automatically shared.', 'xautoposter') . 
             '</p>';
    }

    public function renderCategoriesField() {
        $options = get_option('xautoposter_options', []);
        $selected = isset($options['categories']) ? (array)$options['categories'] : [];
        
        $categories = get_categories(['hide_empty' => false]);
        
        echo '<div class="category-checkboxes">';
        foreach ($categories as $category) {
            printf(
                '<label><input type="checkbox" name="xautoposter_options[categories][]" value="%s"%s> %s</label><br>',
                esc_attr($category->term_id),
                in_array($category->term_id, $selected) ? ' checked' : '',
                esc_html($category->name)
            );
        }
        echo '</div>';
        
        echo '<p class="description">' . 
             esc_html__('Select which categories should be automatically shared. Leave empty to share all categories.', 'xautoposter') . 
             '</p>';
    }

    public function renderCheckboxField($args) {
        $options = get_option('xautoposter_options', []);
        $name = $args['name'];
        $checked = isset($options[$name]) ? $options[$name] : '0';
        
        printf(
            '<input type="checkbox" id="%1$s" name="xautoposter_options[%1$s]" value="1"%2$s>',
            esc_attr($name),
            checked('1', $checked, false)
        );
    }

    public function renderTemplateField() {
        $options = get_option('xautoposter_options', []);
        $template = isset($options['post_template']) ? 
            $options['post_template'] : '%title% %link% %hashtags%';
        
        printf(
            '<textarea id="post_template" name="xautoposter_options[post_template]" rows="3" class="large-text">%s</textarea>',
            esc_textarea($template)
        );
        
        echo '<p class="description">' . 
             esc_html__('Available variables: %title%, %link%, %hashtags%', 'xautoposter') . 
             '</p>';
    }

    public function validateOptions($input) {
        $output = [];
        
        // API ayarları değiştiğinde doğrulama yap
        $current_options = get_option('xautoposter_options', []);
        $api_fields = ['api_key', 'api_secret', 'access_token', 'access_token_secret'];
        $api_changed = false;
        
        foreach ($api_fields as $field) {
            if (isset($input[$field])) {
                $output[$field] = sanitize_text_field($input[$field]);
                if (!isset($current_options[$field]) || $current_options[$field] !== $output[$field]) {
                    $api_changed = true;
                }
            }
        }
        
        // API ayarları değiştiyse veya ilk kez girildiyse doğrula
        if ($api_changed) {
            try {
                $twitter = new \XAutoPoster\Services\TwitterService(
                    $output['api_key'],
                    $output['api_secret'],
                    $output['access_token'],
                    $output['access_token_secret']
                );
                
                if ($twitter->verifyCredentials()) {
                    update_option('xautoposter_api_verified', true);
                    delete_option('xautoposter_api_error');
                    
                    add_settings_error(
                        'xautoposter_options',
                        'api_verified',
                        __('Twitter API connection verified successfully. Settings are now locked.', 'xautoposter'),
                        'success'
                    );
                }
            } catch (\Exception $e) {
                update_option('xautoposter_api_verified', false);
                update_option('xautoposter_api_error', $e->getMessage());
                
                add_settings_error(
                    'xautoposter_options',
                    'api_error',
                    sprintf(
                        __('Twitter API verification failed: %s', 'xautoposter'),
                        $e->getMessage()
                    ),
                    'error'
                );
            }
        }
        
        // Interval validation
        if (isset($input['interval'])) {
            $valid_intervals = ['5min', '15min', '30min', '60min'];
            $output['interval'] = in_array($input['interval'], $valid_intervals) ? 
                $input['interval'] : '30min';
            
            // Reschedule cron if interval changed
            if (!isset($current_options['interval']) || 
                $current_options['interval'] !== $output['interval']) {
                wp_clear_scheduled_hook('xautoposter_cron_hook');
                wp_schedule_event(time(), $output['interval'], 'xautoposter_cron_hook');
            }
        }
        
        // Categories validation
        if (isset($input['categories'])) {
            $output['categories'] = array_map('intval', (array)$input['categories']);
        }
        
        // Auto share checkbox
        $output['auto_share'] = isset($input['auto_share']) ? '1' : '0';
        
        // Template validation
        if (isset($input['post_template'])) {
            $output['post_template'] = wp_kses_post($input['post_template']);
        }
        
        return $output;
    }
}