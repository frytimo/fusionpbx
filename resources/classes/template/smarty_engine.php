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
 * Smarty template engine adapter.
 *
 * Wraps the Smarty library so it can be used interchangeably with any other
 * engine adapter through the {@see template_engine} interface.
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class smarty_engine implements template_engine {

	/**
	 * Underlying Smarty instance.
	 *
	 * @var \Smarty
	 */
	private $smarty;

	/**
	 * Creates a Smarty instance and configures its directories.
	 *
	 * @param string $template_dir Absolute path to the directory that contains
	 *                             template files.
	 * @param string $cache_dir    Absolute path to the compile / cache directory.
	 */
	public function __construct(string $template_dir, string $cache_dir) {
		require_once PROJECT_ROOT . "/resources/templates/engine/smarty/Smarty.class.php";
		$this->smarty = new Smarty();
		$this->smarty->setTemplateDir($template_dir);
		$this->smarty->setCompileDir($cache_dir);
		$this->smarty->setCacheDir($cache_dir);
		$this->smarty->registerPlugin("modifier", "in_array", "in_array");
		$this->smarty->registerPlugin("modifier", "json", "json_encode");
	}

	/**
	 * {@inheritdoc}
	 */
	public function assign(string $key, $value): void {
		$this->smarty->assign($key, $value);
	}

	/**
	 * {@inheritdoc}
	 */
	public function render(string $name = ''): string {
		return $this->smarty->fetch($name);
	}

	/**
	 * {@inheritdoc}
	 */
	public function display(string $name = ''): void {
		echo $this->render($name);
	}
}
