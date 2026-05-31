<?php
class tdm_block_icon_simplified extends td_block {

	protected $additional_classes = array();
    static $style_selector = '';
	static $style_atts_prefix = '';
	static $style_atts_uid = '';
	static $module_template_part_index = '';
    static $aib_component_style = null;

    /**
     * Class constructor.
     *
     * @return void
     */
    function __construct() {
        /**
         * Disable loop block features. This block does not use a loop and it dosn't need to run a query.
         */
        parent::disable_loop_block_features();

        /* --
        -- Check to see if the element is being called into a tdb module template
        -- */
        if ( td_global::get_in_tdb_module_template() ) {
            global $tdb_module_template_params;

            /* -- Set the current module template part index, used for ensuring -- */
		    /* -- uniqueness between template parts of the same type -- */
            if( isset( $tdb_module_template_params['shortcodes'][get_class($this)] ) ) {
                $tdb_module_template_params['shortcodes'][get_class($this)]++;
            } else {
                $tdb_module_template_params['shortcodes'][get_class($this)] = 0;
            }

            self::$module_template_part_index = $tdb_module_template_params['shortcodes'][get_class($this)];

            // In composer, add an extra random string to ensure uniqueness
            if( tdc_state::is_live_editor_ajax() || tdc_state::is_live_editor_iframe() || is_admin() ) {
                $uniquid = uniqid();
                $newuniquid = '';
                while ( strlen( $newuniquid ) < 3 ) {
                    $newuniquid .= $uniquid[rand(0, 12)];
                }

                self::$module_template_part_index .= '_' . $newuniquid;
            }

            /* -- Add an additional class to the element -- */
            if( td_global::get_in_tdb_module_template() ) {
                $this->additional_classes[] = get_class($this) . '_' . self::$module_template_part_index;
            }

            /* -- Set the template part unique style vars -- */
            // Set the style atts prefix
            self::$style_atts_prefix = 'tdb_mts_';

            // Set the style atts uid
            self::$style_atts_uid = $tdb_module_template_params['template_class'] . '_' . get_class($this) . '_' . self::$module_template_part_index;
        } else {
	        // reset static properties
	        self::$style_selector = '';
	        self::$style_atts_prefix = '';
	        self::$style_atts_uid = '';
	        self::$module_template_part_index = '';
        }

        /* --
        -- Reset the Ai Builder specific static variables.
        -- */
        self::$aib_component_style = null;
    }

    /**
     * Compiles the shortcode's CSS rules.
     *
     * @return string
     */
    public function get_custom_css() {
		$style_atts_prefix = self::$style_atts_prefix;
		$style_atts_uid = self::$style_atts_uid;

        // Set the style selector
        $style_selector = '';

        $in_composer = td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax();
        $in_element = td_global::get_in_element();
		if( $in_element && $in_composer ) {
			$style_selector .= 'tdc-row-composer .';
		} else if( $in_element || $in_composer ) {
			$style_selector .= 'tdc-row .';
		}

        // Check to see if the element is being called into a tdb module template
        if( td_global::get_in_tdb_module_template() ) {
            global $tdb_module_template_params;

            $style_selector = $tdb_module_template_params['template_class'] . ' .' . $style_selector .  get_class($this) . '_' . self::$module_template_part_index;
        } else {
            $style_selector .= $this->block_uid;
        }

        // AI Builder component style CSS output.
        $aib_component_style_css_output = "";
        if ( !empty(self::$aib_component_style) ) {
            $aib_component_style_css_output =
                "/* @style_general_component_style_" . self::$aib_component_style->id . " */ 
                @style_general_component_style_" . self::$aib_component_style->id;
        }

        // Build the CSS.
        $raw_css =
            "<style>
                /* @style_general_tdm_block_icon_simplified */
                .tdm_block_icon_simplified {
					margin: 0;
					transform: translateZ(0);
				}
				.tdm_block_icon_simplified > .td-element-style {
					z-index: -1;
				}
				.tdm_block_icon_simplified .tdm-icon-wrap {
				    display: inline-flex;
				    align-items: center;
				    justify-content: center;
				    position: relative;
				    width: 1.6em;
				    height: 1.6em;
				    font-size: 50px;
				    line-height: 1;
				    color: #000;
				    border: 0 solid #000;
				    transition: color .2s ease-in-out, border-width .2s ease-in-out, border-color .2s ease-in-out, border-radius .2s ease-in-out, box-shadow .2s ease-in-out;
                    transform: translateZ(0);
				}
				.tdm_block_icon_simplified .tdm-icon-wrap:before {
				    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: -1;
                    opacity: 0;
                    transition: opacity .2s ease-in-out;
				}
                .tdm_block_icon_simplified .tdm-icon-wrap:hover:before {
                    opacity: 1;
                }
				.tdm_block_icon_simplified .tdm-icon-wrap i {
                    transition: background .2s ease-in-out, color .2s ease-in-out;
				}
				.tdm_block_icon_simplified svg {
				    display: block;
                    width: 1em;
                    height: auto;
                    line-height: 1;
                    fill: currentColor;
                    transition: fill .2s ease-in-out;
				}
				
				$aib_component_style_css_output
				
				/* @" . $style_atts_prefix . "display$style_atts_uid */
                .$style_selector {
                    display: @" . $style_atts_prefix . "display$style_atts_uid;
                }
				/* @" . $style_atts_prefix . "horizontal_align$style_atts_uid */
                .$style_selector {
                    text-align: @" . $style_atts_prefix . "horizontal_align$style_atts_uid;
                }
				/* @" . $style_atts_prefix . "size$style_atts_uid */
                .$style_selector .tdm-icon-wrap {
                    font-size: @" . $style_atts_prefix . "size$style_atts_uid;
                }
				/* @" . $style_atts_prefix . "spacing$style_atts_uid */
                .$style_selector .tdm-icon-wrap {
                    width: @" . $style_atts_prefix . "spacing$style_atts_uid;
                    height: @" . $style_atts_prefix . "spacing$style_atts_uid;
                }
				/* @" . $style_atts_prefix . "vert_align$style_atts_uid */
                .$style_selector .tdm-icon-wrap {
                    top: @" . $style_atts_prefix . "vert_align$style_atts_uid;
                }
                
                /* @" . $style_atts_prefix . "color_solid$style_atts_uid */
				.$style_selector .tdm-icon-wrap i {
					color: @" . $style_atts_prefix . "color_solid$style_atts_uid;
				    -webkit-text-fill-color: unset;
    				background: transparent;
				}
				.$style_selector .tdm-icon-wrap svg {
				    fill: @" . $style_atts_prefix . "color_solid$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "color_gradient$style_atts_uid */
				.$style_selector .tdm-icon-wrap i {
					@" . $style_atts_prefix . "color_gradient$style_atts_uid
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
				}
				html[class*='ie'] .$style_selector .tdm-icon-wrap i {
				    background: none;
					color: @" . $style_atts_prefix . "color_gradient_1$style_atts_uid;
				}
				.$style_selector .tdm-icon-wrap svg {
				    fill: @" . $style_atts_prefix . "color_gradient_1$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "color_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:hover i {
					color: @" . $style_atts_prefix . "color_h$style_atts_uid;
				}
				.$style_selector .tdm-icon-wrap:hover svg {
				    fill: @" . $style_atts_prefix . "color_h$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "color_gradient_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:hover i {
					-webkit-text-fill-color: unset;
					background: transparent;
					transition: none;
				}
				/* @" . $style_atts_prefix . "color_active$style_atts_uid */
				.$style_selector.td-scroll-in-view .tdm-icon-wrap i {
					color: @" . $style_atts_prefix . "color_active$style_atts_uid;
				}
				.$style_selector.td-scroll-in-view .tdm-icon-wrap svg {
				    fill: @" . $style_atts_prefix . "color_active$style_atts_uid;
				}
				
                /* @" . $style_atts_prefix . "bg_solid$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					background: @" . $style_atts_prefix . "bg_solid$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "bg_gradient$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					@" . $style_atts_prefix . "bg_gradient
				}
				/* @" . $style_atts_prefix . "bg_solid_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:before {
					background: @" . $style_atts_prefix . "bg_solid_h$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "bg_gradient_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:before {
					@" . $style_atts_prefix . "bg_gradient_h$style_atts_uid
				}
				/* @" . $style_atts_prefix . "bg_solid_active$style_atts_uid */
				.$style_selector.td-scroll-in-view .tdm-icon-wrap:before {
					background-color: @" . $style_atts_prefix . "bg_solid_active$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "bg_gradient_active$style_atts_uid */
				.$style_selector.td-scroll-in-view .tdm-icon-wrap:before {
					@" . $style_atts_prefix . "bg_gradient_active$style_atts_uid
				}
				.$style_selector.td-scroll-in-view .tdm-icon-wrap:before {
					opacity: 1;
				}
				
				/* @" . $style_atts_prefix . "border_size$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					border-width: @" . $style_atts_prefix . "border_size$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "border_style$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					border-style: @" . $style_atts_prefix . "border_style$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "border_color$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					border-color: @" . $style_atts_prefix . "border_color$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "border_color_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:hover {
					border-color: @" . $style_atts_prefix . "border_color_h$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "border_radius$style_atts_uid */
				.$style_selector .tdm-icon-wrap,
				.$style_selector .tdm-icon-wrap:before {
					border-radius: @" . $style_atts_prefix . "border_radius$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "border_radius_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:hover,
				.$style_selector .tdm-icon-wrap:hover:before {
					border-radius: @" . $style_atts_prefix . "border_radius_h$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "shadow$style_atts_uid */
				.$style_selector .tdm-icon-wrap {
					box-shadow: @" . $style_atts_prefix . "shadow$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "shadow_h$style_atts_uid */
				.$style_selector .tdm-icon-wrap:hover {
					box-shadow: @" . $style_atts_prefix . "shadow_h$style_atts_uid;
				}
				/* @" . $style_atts_prefix . "shadow_active$style_atts_uid */
				.$style_selector.td-scroll-in-view .tdm-icon-wrap {
					box-shadow: @" . $style_atts_prefix . "shadow_active$style_atts_uid;
				}
			</style>";

        // Compile the CSS and return it.
        $td_css_res_compiler = new td_css_res_compiler( $raw_css );
        $td_css_res_compiler->load_settings( __CLASS__ . '::cssMedia', $this->get_all_atts());

        return $td_css_res_compiler->compile_css();
    }

    /**
     * Sets up the various CSS rules which will later be compiled.
     *
     * @param td_res_context $res_ctx
     * @return void
     */
    static function cssMedia( $res_ctx ) {
		$style_atts_prefix = self::$style_atts_prefix;
		$style_atts_uid = self::$style_atts_uid;
        $scroll_to_class_active = !empty($res_ctx->get_shortcode_att('scroll_to_class')) && !td_global::get_in_tdb_module_template();

		/*
         * General.
         */
        $res_ctx->load_settings_raw('style_general_tdm_block_icon_simplified', 1);
        if ( !empty(self::$aib_component_style) ) {
            $res_ctx->load_settings_raw('style_general_component_style_' . self::$aib_component_style->id, self::$aib_component_style->css_output);
        }

        // Display.
        $res_ctx->load_settings_raw($style_atts_prefix . 'display' . $style_atts_uid, $res_ctx->get_shortcode_att('display'));

        // Horizontal align.
        $horizontal_align = $res_ctx->get_shortcode_att('content_align_horizontal');
        $horizontal_align_value = '';
        switch ( $horizontal_align ) {
            case 'content-horiz-left':
                $horizontal_align_value = 'left';
                break;
            case 'content-horiz-center':
                $horizontal_align_value = 'center';
                break;
            case 'content-horiz-right':
                $horizontal_align_value = 'right';
                break;
        }

        $res_ctx->load_settings_raw($style_atts_prefix . 'horizontal_align' . $style_atts_uid, $horizontal_align_value);

        // Icon size.
        $icon_size = $res_ctx->get_shortcode_att('size');
        $icon_size .= !empty($icon_size) ? 'px' : '';
        $res_ctx->load_settings_raw($style_atts_prefix . 'size' . $style_atts_uid, $icon_size);

        // Spacing around icon.
        $icon_spacing = $res_ctx->get_shortcode_att('spacing');
        if ( !$res_ctx->is('all') || $icon_spacing != '1.6' ) {
            $res_ctx->load_settings_raw($style_atts_prefix . 'spacing' . $style_atts_uid, $icon_spacing . 'em');
        }

        // Icon vertical align.
        $vert_align = $res_ctx->get_shortcode_att('vert_align');
        if ( !$res_ctx->is('all') || $vert_align != '0' ) {
            $res_ctx->load_settings_raw($style_atts_prefix . 'vert_align' . $style_atts_uid, $vert_align . 'px');
        }

        /*
         * Style.
         */
        // Color.
        $res_ctx->load_color_settings('color', $style_atts_prefix . 'color_solid' . $style_atts_uid, $style_atts_prefix . 'color_gradient' . $style_atts_uid, $style_atts_prefix . 'color_gradient_1' . $style_atts_uid);
        $hover_color = $res_ctx->get_shortcode_att( 'color_h' );
        $res_ctx->load_settings_raw($style_atts_prefix . 'color_h' . $style_atts_uid, $hover_color);
        if ( !empty($hover_color ) ) {
            $res_ctx->load_settings_raw( $style_atts_prefix . 'color_gradient_h' . $style_atts_uid, 1 );
        }
        if ( $scroll_to_class_active ) {
            $res_ctx->load_settings_raw( $style_atts_prefix . 'color_active' . $style_atts_uid, $hover_color);
            if ( !empty($hover_color) ) {
                $res_ctx->load_settings_raw( $style_atts_prefix . 'color_gradient_active' . $style_atts_uid, 1 );
            }
        }

        // Background color.
        $res_ctx->load_color_settings('bg', $style_atts_prefix . 'bg_solid' . $style_atts_uid, $style_atts_prefix . 'bg_gradient' . $style_atts_uid);
        $res_ctx->load_color_settings('bg_h', $style_atts_prefix . 'bg_solid_h' . $style_atts_uid, $style_atts_prefix . 'bg_gradient_h' . $style_atts_uid );
        if ( $scroll_to_class_active ) {
            $res_ctx->load_color_settings('bg_h', $style_atts_prefix . 'bg_solid_active' . $style_atts_uid, $style_atts_prefix . 'bg_gradient_active' . $style_atts_uid,);
        }

        // Border.
        $border_size = $res_ctx->get_shortcode_att('border_size');
        $border_size .= !empty($border_size) ? 'px' : '';
        $res_ctx->load_settings_raw($style_atts_prefix . 'border_size' . $style_atts_uid, $border_size);

        $border_style = $res_ctx->get_shortcode_att('border_style');
        $res_ctx->load_settings_raw($style_atts_prefix . 'border_style' . $style_atts_uid, $border_style);

        $res_ctx->load_settings_raw($style_atts_prefix . 'border_color' . $style_atts_uid, $res_ctx->get_shortcode_att('border_color'));
        $res_ctx->load_settings_raw($style_atts_prefix . 'border_color_h' . $style_atts_uid, $res_ctx->get_shortcode_att('border_color_h'));

        $border_radius = $res_ctx->get_shortcode_att('border_radius');
        $border_radius .= !empty($border_radius) ? 'px' : '';
        $res_ctx->load_settings_raw($style_atts_prefix . 'border_radius' . $style_atts_uid, $border_radius);

        $border_radius_h = $res_ctx->get_shortcode_att('border_radius_h');
        $border_radius_h .= !empty($border_radius_h) ? 'px' : '';
        $res_ctx->load_settings_raw($style_atts_prefix . 'border_radius_h' . $style_atts_uid, $border_radius_h);

        // Shadow.
        $res_ctx->load_shadow_settings( 0, 0, 2, 0, 'rgba(0, 0, 0, 0.1)', 'shadow', '', false, $style_atts_prefix, $style_atts_uid );
        $res_ctx->load_shadow_settings( 0, 0, 2, 0, 'rgba(0, 0, 0, 0.1)', 'shadow_h', '', false, $style_atts_prefix, $style_atts_uid );
        if ( $scroll_to_class_active ) {
            $res_ctx->load_settings_raw( $style_atts_prefix . 'shadow_active' . $style_atts_uid, 1 );
        }
    }

    /**
     * Renders the shortcode.
     *
     * @param array $atts
     * @param null  $content
     * @return string
     */
    function render( $atts, $content = null ) {
        parent::render( $atts );

        $in_editor = td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax();

        $buffy_icon = rawurldecode( base64_decode( strip_tags( (string) $this->get_att( 'svg' ) ) ) );
        $raw_tdicon = (string) $this->get_icon_att( 'tdicon' );

        $tdicon_data = '';
        if ( $in_editor && $raw_tdicon !== '' ) {
            $tdicon_data = ' data-td-svg-icon="' . esc_attr( $raw_tdicon ) . '"';
        }

        if ( $buffy_icon === '' && $raw_tdicon !== '' ) {
            $is_base64 = base64_encode( base64_decode( $raw_tdicon, true ) ) === $raw_tdicon;

            if ( $is_base64 ) {
                $svg = base64_decode( $raw_tdicon, true );
                $svg_safe = self::sanitize_inline_svg( $svg );
                if ( $svg_safe !== '' ) {
                    $buffy_icon = $svg_safe;
                }
            } else {
                // Validate icon class tokens similarly to header_categories
                $tokens = preg_split( '/\s+/', trim( $raw_tdicon ) );
                $allowed_prefixes = apply_filters(
                    'tdm_block_icon_simplified_icon_prefixes',
                    array(
                        'td-icon',
                        'tdc-font-',
                        'fa', 'fas', 'far', 'fal', 'fad', 'fab',
                        'mdi', 'ti', 'la', 'ri', 'bi', 'icon'
                    )
                );

                $safe_tokens = array();
                foreach ( $tokens as $t ) {
                    if ( $t === '' ) { continue; }
                    if ( ! preg_match( '/^[A-Za-z0-9\-_]+$/', $t ) ) { continue; }

                    $ok = false;
                    foreach ( $allowed_prefixes as $p ) {
                        if ( strpos( $t, $p ) === 0 ) { $ok = true; break; }
                    }
                    if ( $ok ) { $safe_tokens[] = $t; }
                }

                if ( ! empty( $safe_tokens ) ) {
                    $buffy_icon = '<i class="' . esc_attr( implode( ' ', $safe_tokens ) ) . '"></i>';
                }
            }
        }

        // If still no icon: front-end return empty; editor show placeholder
        if ( $buffy_icon === '' ) {
            if ( ! $in_editor ) {
                return '';
            }
            $buffy_icon = '<i class="tdc-font-fa tdc-font-fa-star-o"></i>';
        }

        /**
         * URL
         */
        $icon_wrap_tag   = 'div';
        $url_href_attr   = '';
        $url_target_attr = '';

        $url = (string) $this->get_att( 'url' );
        if ( $url !== '' ) {
            $url_safe = esc_url( $url );

            // if esc_url rejects it (e.g. javascript:), don't output href at all
            if ( $url_safe !== '' ) {
                $icon_wrap_tag = 'a';
                $url_href_attr = ' href="' . esc_attr( $url_safe ) . '"';

                if ( ! empty( $this->get_att( 'url_new_window' ) ) ) {
                    $url_target_attr = ' target="_blank" rel="noopener noreferrer"';
                }
            }
        }

        /**
         * VIDEO POPUP
         */
        $data_video_popup = '';
        if ( ! empty( $this->get_att( 'video_enable' ) ) ) {
            $video_url = (string) $this->get_att( 'video_url' );
            if ( $video_url !== '' ) {
                $video_url_safe = esc_url( $video_url );
                if ( $video_url_safe !== '' ) {
                    $data_video_popup = ' data-mfp-src="' . esc_attr( $video_url_safe ) . '"';
                }
            }
        }

        /**
         * SCROLL TO CLASS + OFFSET (front-end only)
         */
        $data_scroll_to_class = '';
        $data_scroll_offset   = '';

        if ( ! $in_editor ) {
            $scroll_to_class = (string) $this->get_att( 'scroll_to_class' );
            if ( $scroll_to_class !== '' ) {
                $data_scroll_to_class = ' data-scroll-to-class="' . esc_attr( $scroll_to_class ) . '"';
            }

            $scroll_offset = (string) $this->get_att( 'scroll_offset' );
            if ( $scroll_to_class !== '' && $scroll_offset !== '' ) {
                $scroll_offset_safe = is_numeric( $scroll_offset ) ? (string) intval( $scroll_offset ) : $scroll_offset;
                $data_scroll_offset = ' data-scroll-offset="' . esc_attr( $scroll_offset_safe ) . '"';
            }
        }

        // AI builder settings (existing behavior)
        $this->set_aib_settings( 'icon' );

        /**
         * OUTPUT
         */
        $buffy  = '';
        $buffy .= '<div class="' . esc_attr( $this->get_block_classes( $this->additional_classes ) ) . '" ' .
            $this->get_block_html_atts() . $tdicon_data . $data_video_popup . $data_scroll_to_class . $data_scroll_offset . '>';
        $buffy .= $this->get_block_css();
        $buffy .= '<' . tag_escape( $icon_wrap_tag ) . $url_href_attr . $url_target_attr . ' class="tdm-icon-wrap">';
        $buffy .= $buffy_icon; // already sanitized / escaped above
        $buffy .= '</' . tag_escape( $icon_wrap_tag ) . '>';
        $buffy .= '</div>';

        return $buffy;
    }

    /**
     * Sets AI Builder settings.
     *
     * @param $component_type
     *
     */
    private function set_aib_settings( $component_type ) {
        $in_composer = td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax();
        $td_aib_section = td_global::get_td_aib_section();

        if ( !empty($td_aib_section) ) {
            self::$aib_component_style = TagDiv\AiBuilder\Components::get_shortcode_style($this->get_att('aib_component_style'), $td_aib_section->style, $component_type);
            if ( !empty(self::$aib_component_style) ) {
                $this->additional_classes[] = 'td_aib_component_style_' . self::$aib_component_style->id;

                if ( !$in_composer ) {
                    // On front-end, load only the CSS of the inherited/specific style for this component.
                    self::$aib_component_style->css_output = self::$aib_component_style->template_css;
                } else {
                    // Otherwise, load all styles related to this component type.
                    global $td_aib_styles;
                    if ( !empty($td_aib_styles) ) {
                        $component_styles = array_filter($td_aib_styles, function ( $style ) use ( $component_type ) {
                            return $style->component == $component_type;
                        });

                        self::$aib_component_style->css_output = '';
                        foreach ( $component_styles as $component_style ) {
                            self::$aib_component_style->css_output .= $component_style->template_css;
                        }
                    }
                }
            }
        }
    }

    public static function sanitize_inline_svg( $svg ) {
        if ( ! is_string( $svg ) || $svg === '' ) {
            return '';
        }

        $svg = preg_replace( '/<\?xml.*?\?>/i', '', $svg );
        $svg = preg_replace( '/<!DOCTYPE.*?>/is', '', $svg );
        $svg = preg_replace( '#</?script[^>]*>#is', '', $svg );

        if ( function_exists( 'wp_kses' ) ) {
            $allowed = array(
                'svg' => array(
                    'xmlns'       => true,
                    'viewbox'     => true,
                    'width'       => true,
                    'height'      => true,
                    'role'        => true,
                    'aria-hidden' => true,
                    'focusable'   => true,
                    'fill'        => true,
                ),
                'g' => array(
                    'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'path' => array(
                    'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'rect' => array(
                    'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true,
                    'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'circle' => array(
                    'cx' => true, 'cy' => true, 'r' => true,
                    'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'polygon' => array(
                    'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'line' => array(
                    'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true,
                    'stroke' => true, 'stroke-width' => true, 'transform' => true,
                ),
                'title' => array(),
                'desc'  => array(),
            );

            return wp_kses( $svg, $allowed );
        }

        return '';
    }

    /**
     * Builds block error for composer.
     *
     * @param string $message
     * @return string
     */
    protected function get_block_error( $message ) {
        $buffy = '<div class="' . $this->get_block_classes($this->additional_classes) . '" ' . $this->get_block_html_atts() . '>';
            $buffy .= td_util::get_block_error('Column title', $message);
        $buffy .= '</div>';

        return $buffy;
    }

}