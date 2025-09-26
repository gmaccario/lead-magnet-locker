<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>

<div class="lead-magnet-locker wrap">
    <h1>Lead Magnet Settings</h1>

    <form method="post" action="">
        <?php wp_nonce_field('lead_magnet_settings', 'settings_nonce'); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Date & Time Trigger</th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_date_trigger" value="1" <?php checked($enable_date_trigger, '1'); ?>>
                        Enable date/time restrictions for forms
                    </label>
                    <p class="description">When enabled, forms will only be visible between the specified dates and times.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Start Date</th>
                <td>
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="regular-text">
                    <p class="description">Forms will be visible starting from this date.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">End Date</th>
                <td>
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="regular-text">
                    <p class="description">Forms will be hidden after this date.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Start Time</th>
                <td>
                    <input type="time" name="start_time" value="<?php echo esc_attr($start_time); ?>">
                    <p class="description">Daily start time for form visibility.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">End Time</th>
                <td>
                    <input type="time" name="end_time" value="<?php echo esc_attr($end_time); ?>">
                    <p class="description">Daily end time for form visibility.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Timezone</th>
                <td>
                    <select name="timezone">
                        <?php
                        $timezones = timezone_identifiers_list();
                        foreach ($timezones as $tz) {
                            echo '<option value="' . esc_attr($tz) . '"' . selected($timezone, $tz, false) . '>' . esc_html($tz) . '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">Timezone for date/time calculations.</p>
                </td>
            </tr>
        </table>

        <div class="card">
            <h3>Current Status</h3>
            <?php if ($this->is_form_visible()): ?>
                <p style="color: green;"><strong>✓ Forms are currently VISIBLE</strong></p>
            <?php else: ?>
                <p style="color: red;"><strong>✗ Forms are currently HIDDEN</strong></p>
            <?php endif; ?>

            <p><strong>Current Server Time:</strong> <?php echo esc_attr(current_time('Y-m-d H:i:s')); ?>
                <?php echo (get_option('timezone_string', 'UTC'))
                        ? '(' . esc_attr(get_option('timezone_string', 'UTC')) . ')'
                        : '';
                ?>
            </p>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
