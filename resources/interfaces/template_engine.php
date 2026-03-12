<?php

/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2013-2026
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
	Tim Fry <tim@fusionpbx.com>
*/

/**
 * Common contract for all template engine adapters.
 *
 * Each adapter wraps a concrete rendering library (Smarty, Twig, RainTPL, …)
 * and presents a uniform surface to the {@see template} factory class.
 * Adding a new engine never requires changes anywhere else in the codebase —
 * create a class that implements this interface, place it under
 * resources/classes/template/, and configure the engine name in
 * default_settings.
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
interface template_engine {

	/**
	 * Assigns a variable to the template engine so it is available inside
	 * the template file.
	 *
	 * @param string $key   Variable name exposed inside the template.
	 * @param mixed  $value The value to expose.
	 */
	public function assign(string $key, $value): void;

	/**
	 * Renders the named template and returns the result as a string.
	 *
	 * @param string $name Template file name or path, as expected by the
	 *                     underlying engine.
	 * @return string Rendered output.
	 */
	public function render(string $name = ''): string;

	/**
	 * Renders the named template and sends the output directly to the browser.
	 *
	 * @param string $name Template file name or path.
	 */
	public function display(string $name = ''): void;
}
