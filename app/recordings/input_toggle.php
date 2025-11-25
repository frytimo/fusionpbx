<?php

class input_toggle {

	/**
	 * Creates an HTML select element with two options: 'true' and 'false'.
	 *
	 * @param string  $id         The unique identifier for the form element.
	 * @param boolean $enabled    The default selected value, either true or false.
	 * @param string  $form_class (Optional) The CSS class name for the form. Default value 'formfld'
	 *
	 * @access public static
	 * @return string HTML code representing the select element with options.
	 */
	public static function create(string $id, bool $enabled, string $form_class = 'formfld'): string {
		global $text, $input_toggle_style_switch;
		$html = '';
		if ($input_toggle_style_switch) {
			$html .= "	<span class='switch'>\n";
		}
		$html .= "	<select class='$form_class' id='$id' name='$id'>\n";
		$html .= "		<option value='true' " . ($enabled == true ? "selected='selected'" : '') . ">" . $text['option-true'] . "</option>\n";
		$html .= "		<option value='false' " . ($enabled == false ? "selected='selected'" : '') . ">" . $text['option-false'] . "</option>\n";
		$html .= "	</select>\n";
		if ($input_toggle_style_switch) {
			$html .= "		<span class='slider'></span>\n";
			$html .= "	</span>\n";
		}
		return $html;
	}
}
