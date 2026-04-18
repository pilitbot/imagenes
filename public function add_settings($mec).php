 "/**
	 * Add Single Builder Settings into Single Event Page Settings Section
	 *
	 * @since     1.0.0
	 */
	public function add_settings($mec)
	{
		$settings = $mec->settings;
		$builders = get_posts([
			'post_type' => 'mec_esb',
			'posts_per_page' => -1
		]);
?>
		<div class="mec-form-row" id="mec_settings_single_event_single_default_builder_wrap" style="display:none;">
			<?php
			if (!$builders) {
				echo __('Please Create New Design for Single Event Page', 'mec-single-builder');
				echo ' <a href="' . admin_url('post-new.php?post_type=mec_esb') . '" class="taxonomy-add-new">' . __('Create new', 'mec-single-builder') . '</a>';
			}
			?>
			<label class="mec-col-3" for="mec_settings_single_event_single_default_builder"><?php _e('Default Builder for Single Event', 'mec-single-builder'); ?></label>
			<div class="mec-col-9">
				<select id="mec_settings_single_event_single_default_builder" name="mec[settings][single_single_default_builder]">
					<?php foreach ($builders as $builder) : ?>
						<option value="<?php echo $builder->ID ?>" <?php echo (isset($settings['single_single_default_builder']) and $settings['single_single_default_builder'] == $builder->ID) ? 'selected="selected"' : ''; ?>><?php echo esc_html($builder->post_title) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="mec-form-row" id="mec_settings_single_event_single_modal_default_builder_wrap" style="display:none;">
			<?php
			if (!$builders) {
				echo __('Please Create New Design for Single Event Page', 'mec-single-builder');
				echo ' <a href="' . admin_url('post-new.php?post_type=mec_esb') . '" class="taxonomy-add-new">' . __('Create new', 'mec-single-builder') . '</a>';
			}
			?>
			<label class="mec-col-3" for="mec_settings_single_event_single_modal_default_builder"><?php _e('Default Builder for Modal View', 'mec-single-builder'); ?></label>
			<div class="mec-col-9">
				<select id="mec_settings_single_event_single_modal_default_builder" name="mec[settings][single_modal_default_builder]">
					<?php foreach ($builders as $builder) : ?>
						<option value="<?php echo $builder->ID ?>" <?php echo (isset($settings['single_modal_default_builder']) and $settings['single_modal_default_builder'] == $builder->ID) ? 'selected="selected"' : ''; ?>><?php echo $builder->post_title ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="mec-form-row" id="mec_settings_custom_event_for_set_settings_wrap">
			<label class="mec-col-3" for="mec_settings_custom_event_for_set_settings"><?php _e('Custom Event For Set Settings', 'mec-single-builder'); ?></label>
			<div class="mec-col-9">
				<select id="mec_settings_custom_event_for_set_settings" name="mec[settings][custom_event_for_set_settings]">
					<?php
						$events = \MEC\Events\EventsQuery::getInstance()->get_events([
							'posts_per_page' => -1,
						]);
					   	$v_selected = (int)\MEC\Settings\Settings::getInstance()->get_settings('custom_event_for_set_settings');
						foreach($events as $event){

							$event_id = $event->ID;
							$event_title = $event->post_title;
							$selected = $event_id == $v_selected ? 'selected="selected"' : '';
							echo '<option value="'.$event_id.'" '.$selected.'>'.$event_title.'</option>';
						}
					?>
				</select>
				<span class="mec-tooltip">
					<div class="box left">
						<h5 class="title"><?php _e('Default Single Event Template on Elementor', 'mec'); ?></h5>
						<div class="content"><p><?php esc_attr_e("Choose your event for single builder addon.", 'mec-single-builder'); ?><a href="#" target="_blank"><?php _e('Read More', 'mec-single-builder'); ?></a></p></div>
					</div>
					<i title="" class="dashicons-before dashicons-editor-help"></i>
				</span>
			</div>
		</div>

		<script>
			jQuery(document).ready(function() {
				if (jQuery('#mec_settings_single_event_single_style').val() == 'builder') {
					jQuery('#mec_settings_single_event_single_default_builder_wrap').css('display', 'block');
					jQuery('#mec_settings_single_event_single_modal_default_builder_wrap').css('display', 'block');
					jQuery('#mec_settings_custom_event_for_set_settings_wrap').css('display', 'block');

				}

				jQuery('#mec_settings_single_event_single_style').on('change', function() {
					if (jQuery(this).val() == 'builder') {
						jQuery('#mec_settings_single_event_single_default_builder_wrap').css('display', 'block');
						jQuery('#mec_settings_single_event_single_modal_default_builder_wrap').css('display', 'block');
						jQuery('#mec_settings_custom_event_for_set_settings_wrap').css('display', 'block');
					} else {
						jQuery('#mec_settings_single_event_single_default_builder_wrap').css('display', 'none');
						jQuery('#mec_settings_single_event_single_modal_default_builder_wrap').css('display', 'none');
						jQuery('#mec_settings_custom_event_for_set_settings_wrap').css('display', 'none');
					}
				})
			})
		</script>

	<?php
	}"