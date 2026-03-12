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
 * Twig template engine adapter.
 *
 * Wraps the Twig library so it can be used interchangeably with any other
 * engine adapter through the {@see template_engine} interface.
 *
 * The Twig lexer is configured to use Smarty-style delimiters
 * ({$var}, {block}, {* comment *}) so that existing theme files require
 * minimal changes when switching engines.
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class twig_engine implements template_engine {

	/**
	 * Underlying Twig_Environment instance.
	 *
	 * @var \Twig_Environment
	 */
	private $twig;

	/**
	 * Variables accumulated via {@see assign()} until {@see render()} is called.
	 *
	 * @var array
	 */
	private $vars = [];

	/**
	 * Creates a Twig environment and configures its loader and custom lexer.
	 *
	 * @param string $template_dir Absolute path to the directory that contains
	 *                             template files.
	 * @param string $cache_dir    Absolute path to the compile / cache directory.
	 */
	public function __construct(string $template_dir, string $cache_dir) {
		require_once PROJECT_ROOT . "/resources/templates/engine/Twig/Autoloader.php";
		Twig_Autoloader::register();
		$loader = new Twig_Loader_Filesystem($template_dir);
		$this->twig = new Twig_Environment($loader, ['cache' => $cache_dir]);
		$lexer = new Twig_Lexer($this->twig, [
			'tag_comment'  => ['{*', '*}'],
			'tag_block'    => ['{', '}'],
			'tag_variable' => ['{$', '}'],
		]);
		$this->twig->setLexer($lexer);
	}

	/**
	 * {@inheritdoc}
	 */
	public function assign(string $key, $value): void {
		$this->vars[$key] = $value;
	}

	/**
	 * {@inheritdoc}
	 */
	public function render(string $name = ''): string {
		return $this->twig->render($name, $this->vars);
	}

	/**
	 * {@inheritdoc}
	 */
	public function display(string $name = ''): void {
		echo $this->render($name);
	}
}
