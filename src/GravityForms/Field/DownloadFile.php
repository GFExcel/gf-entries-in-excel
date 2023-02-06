<?php

namespace GFExcel\GravityForms\Field;

use GFExcel\Action\CountDownloads;
use GFExcel\Action\DownloadCountResetAction;
use GFExcel\Action\DownloadUrlDisableAction;
use Gravity_Forms\Gravity_Forms\Settings\Fields\Base;

/**
 * A field that represents a form to download the file.
 * @since $ver$
 */
class DownloadFile extends Base {
	/**
	 * Whether the form was already rendered.
	 * @since $ver$
	 * @var bool
	 */
	private $is_rendered = false;

	/**
	 * The url for the download form.
	 * @since $ver$
	 * @var string
	 */
	public $url = '';

	/**
	 * @inheritdoc
	 * @since $ver$
	 */
	public function __construct( $props, $settings ) {
		parent::__construct( $props, $settings );

		// Add after settings form.
		add_filter( 'gk-gravityexport-after_feed_edit_page', \Closure::fromCallable( [ $this, 'render_form' ] ) );
	}

	/**
	 * @inheritdoc
	 * @since $ver$
	 */
	public function markup() {
		$form_html = sprintf( '<div class="download-block">
                <div class="date-field">
                    <input placeholder="YYYY-MM-DD" form="%1$s" type="text" id="start_date" name="start_date" />
                    <label for="start_date">' . esc_html__( 'Start', 'gravityforms' ) . '</label>
                </div>

                <div class="date-field">
                    <input placeholder="YYYY-MM-DD" form="%1$s" type="text" id="end_date" name="end_date" />
                    <label for="end_date">' . esc_html__( 'End', 'gravityforms' ) . '</label>
                </div>

                <div class="download-button">
                    <button type="submit" form="%1$s" class="button primary button-primary">' . esc_html__( 'Download',
				'gk-gravityexport-lite' ) . '</button>
                </div>
            </div>', $this->get_parsed_name() );

		$count_html = sprintf(
			'<div class="download-count"><span>%s: %d</span></div>',
			esc_html__( 'Download count', 'gk-gravityexport-lite' ),
			$this->settings->get_value( CountDownloads::KEY_COUNT ) ?: 0 );

		$reset_button_html = sprintf(
			'<button id="download-count-reset" name="gform-settings-save" value="%s" form="gform-settings" class="button button-secondary">%s</button>',
			DownloadCountResetAction::$name,
			esc_attr__( 'Reset count', 'gk-gravityexport-lite' )
		);


		return $form_html . $count_html . $reset_button_html;
	}

	/**
	 * @inheritDoc
	 * @since $ver$
	 */
	public function scripts(): array {
		$script = <<<JS
(function($) {
	$( document ).ready(function() {

		$( '#download-count-reset' ).on( 'click', function( e ) {
			return confirm("%s");
		});
    })
})(jQuery);
JS;

		return [
			[
				'handle'   => 'jquery',
				'callback' => function () use ( $script ) {
					wp_add_inline_script( 'jquery', sprintf(
						$script,
						esc_attr__( 'You are about to reset the download count for this form. This can’t be undone.', 'gk-gravityexport-lite' )
					) );
				},
				'deps' => ['jquery']
			]
		];
	}

	/**
	 * Helper method to render the download form.
	 * @since $ver$
	 */
	private function render_form() {
		if ( $this->is_rendered ) {
			return;
		}

		$this->is_rendered = true;

		printf(
			'<form method="post" action="%s" id="%s" target="_blank"></form>',
			$this->url,
			$this->get_parsed_name()
		);
	}
}
