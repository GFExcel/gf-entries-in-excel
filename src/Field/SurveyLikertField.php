<?php

namespace GFExcel\Field;

/**
 * A field transformer for the `likert` field from the Gravity Forms Survey Plugin.
 * @since $ver$
 * @see \GF_Field_Likert
 */
class SurveyLikertField extends SeparableField {
	/**
	 * @inheritdoc
	 * @var \GF_Field_Likert
	 */
	protected $field;

	/**
	 * @inheritdoc
	 * @since $ver$
	 */
	public function getFieldValue( $entry, $input_id = '' ) {
		if ( ! $this->useScoreOutput() ) {
			return parent::getFieldValue( $entry, $input_id );
		}

		$value = $entry[ $input_id ?: $this->field->id ] ?? ':';
		// If this is not a multi row value; add a colon, so we can still get $column.
		if ( strpos( $value, ':' ) === false ) {
			$value = ':' . $value;
		}

		[ , $column ] = explode( ':', $value );

		foreach ( $this->field->choices as $choice ) {
			if ( ( $choice['value'] ?? '' ) === $column ) {
				return $choice['score'];
			}
		}

		return 0; // choice not found, so value is 0
	}

	/**
	 * Whether to use the score instead of the text value.
	 * @since $ver$
	 * @return bool Whether to use the score as output.
	 */
	private function useScoreOutput(): bool {
		return gf_apply_filters( [
			'gfexcel_field_likert_use_score',
			$this->field->formId,
			$this->field->id,
		], false, $this->field );
	}

	/**
	 * @inheritdoc
	 *
	 * Overwritten so single row fields will still return the correct columns.
	 *
	 * @since $ver$
	 */
	protected function isSeparationEnabled() {
		return parent::isSeparationEnabled() && $this->hasMultipleRows();
	}

	/**
	 *
	 * @since $ver$
	 */
	protected function hasMultipleRows(): bool {
		return $this->field->gsurveyLikertEnableMultipleRows ?? false;
	}

	/**
	 * @inheritdoc
	 *
	 * Overwritten so single row values will also be retrieved.
	 *
	 * @since $ver$
	 */
	public function getCells( $entry ) {
		return $this->hasMultipleRows()
			? parent::getCells( $entry )
			: BaseField::getCells( $entry );
	}
}
