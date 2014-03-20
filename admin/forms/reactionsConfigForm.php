<?php
function reactionsConfigForm() {
	_gigya_formEl(
			array(
					'type'    => 'checkbox',
					'id'      => 'reaction_plugin',
					'label'   => __( 'Enable Reaction Plugin' ),
					'default' => 0
			)
	);
	$reaction_position_opts = array(
			"bottom" => __( "Bottom" ),
			"top"    => __( "Top" ),
			"both"   => __( "Both" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_position',
					'label'   => __( 'Position' ),
					'options' => $reaction_position_opts,
					'default' => $reaction_position_opts['bottom'],
			)
	);
	$reaction_counts_opts = array(
			"right" => __( "Right" ),
			"top"   => __( "Top" ),
			"none"  => __( "None" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_position',
					'label'   => __( 'Show Counts' ),
					'options' => $reaction_counts_opts,
					'default' => $reaction_counts_opts['right'],
			)
	);
	$reaction_layout_opts = array(
			"horizontal" => __( "Horizontal" ),
			"vertical"   => __( "Vertical" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_layout',
					'label'   => __( 'Layout' ),
					'options' => $reaction_layout_opts,
					'default' => $reaction_layout_opts['horizontal'],
			)
	);
	_gigya_formEl(
			array(
					'type'    => 'textarea',
					'id'      => 'reaction_buttons',
					'label'   => __( 'Reaction Buttons' ),
					'default' => _gigya_get_json( 'admin/forms/json/default_reaction' )
			)
	);
	_gigya_formEl(
			array(
					'type'  => 'text',
					'id'    => 'reaction_providers',
					'label' => __( 'Providers' ),
					'desc'  => __( 'Leave empty or type * for all providers or define specific providers, for example: facebook,twitter,google,linkedin' )
			)
	);
	$reaction_share_opts = array(
			"both"     => __( "both" ),
			"external" => __( "External" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_enable_share_activity',
					'options' => $reaction_share_opts,
					'default' => $reaction_share_opts['external'],
					'label'   => __( 'Enable Sharing to Activity Feed' ),
					'desc'    => 'When publishing feed items, by default the feed items are published to social networks only and will not appear on the site\'s Activity Feed plugin. To change this behavior, you must change the publish scope to "Both"'
			)
	);
	$reaction_count_opts = array(
			"number"     => __( "Number" ),
			"percentage" => __( "Percentage" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_count_type',
					'options' => $reaction_count_opts,
					'default' => $reaction_count_opts['number'],
					'label'   => __( 'Count Type' ),
			)
	);
	$reaction_privacy_opts = array(
			"private" => __( "Private" ),
			"public"  => __( "Public" ),
			"friends" => __( "Friends" )
	);
	_gigya_formEl(
			array(
					'type'    => 'select',
					'id'      => 'reaction_count_type',
					'options' => $reaction_privacy_opts,
					'default' => $reaction_privacy_opts['private'],
					'label'   => __( 'Privacy' ),
			)
	);
	_gigya_formEl(
			array(
					'type'  => 'textarea',
					'id'    => 'reactions_custom_code',
					'label' => __( "Additional Parameters (advanced)" ),
					'desc'  => __( 'Enter values in' ) . '<strong>key1=value1|key2=value2...keyX=valueX</strong>' . __( 'format' ) . '<br>' . __( 'See list of available:' ) . '<a href="http://developers.gigya.com/020_Client_API/010_Socialize/socialize.showReactionsBarUI" target="_blank">parameters</a>'
			)
	);
	_gigya_formEl(
			array(
					'type'    => 'checkbox',
					'id'      => 'reaction_multiple',
					'label'   => __( 'Allow multiple reactions' ),
					'default' => 1
			)
	);
}