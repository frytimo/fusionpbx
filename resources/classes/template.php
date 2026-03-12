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
	Copyright (C) 2013
	All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

/**
 * Template factory and facade.
 *
 * Acts as the single public entry-point for all template rendering in
 * FusionPBX.  The concrete rendering work is delegated to an engine adapter
 * (smarty_engine, twig_engine, raintpl_engine, …) that implements the
 * {@see template_engine} interface.
 *
 * Engine selection priority (highest → lowest):
 *   1. Explicit call to {@see setEngine()} after construction.
 *   2. Direct assignment to the public {@see $engine} property followed by
 *      a call to {@see init()} — preserves backward compatibility with all
 *      existing callers.
 *   3. The value stored in default_settings under category "template",
 *      sub-category "engine" (read at construction time when a database
 *      session is available).
 *   4. Hard-coded fallback: 'smarty'.
 *
 * Adding a new engine requires only:
 *   - A new class in resources/classes/template/ that implements
 *     template_engine.
 *   - Setting the "template / engine" default_setting to the new name.
 *   - No changes anywhere else in the codebase.
 */
class template {

	// -----------------------------------------------------------------------
	// Public properties kept for backward compatibility with existing callers
	// that set them directly before calling init().
	// -----------------------------------------------------------------------

	/** @var string Name of the engine to use ('smarty', 'twig', 'raintpl', …). */
	public $engine;

	/** @var string Absolute path to the directory containing template files. */
	public $template_dir;

	/** @var string Absolute path used for compiled / cached templates. */
	public $cache_dir;

	// -----------------------------------------------------------------------
	// Private state
	// -----------------------------------------------------------------------

	/** @var template_engine|null The instantiated engine adapter. */
	private $object = null;

	/** @var string Full path to a specific template file passed to the constructor. */
	private $template_name = '';

	// -----------------------------------------------------------------------
	// Construction
	// -----------------------------------------------------------------------

	/**
	 * @param string $template Optional absolute path to a specific template
	 *                         file.  When supplied the directory part is used
	 *                         as template_dir and the full path is passed to
	 *                         render() when no name is given explicitly.
	 */
	public function __construct(string $template = '') {
		if ($template !== '') {
			$this->template_name = $template;
			$this->template_dir  = dirname($template);
		} else {
			$this->template_dir = PROJECT_ROOT . '/resources/views';
		}

		$this->cache_dir = sys_get_temp_dir();

		// Resolve the engine name from default_settings when a database
		// session already exists; fall back to 'smarty' otherwise.
		$this->engine = $this->resolve_default_engine();
	}

	// -----------------------------------------------------------------------
	// Fluent engine override (new API)
	// -----------------------------------------------------------------------

	/**
	 * Overrides the engine to use and returns $this for fluent chaining.
	 *
	 * Calling this method after construction but before init() / render()
	 * takes precedence over both the default_settings value and any earlier
	 * assignment to the public $engine property.
	 *
	 * @param  string $engine Engine name ('smarty', 'twig', 'raintpl', …).
	 * @return static
	 */
	public function setEngine(string $engine): static {
		$this->engine = $engine;
		// Force the adapter to be re-created on the next call that needs it.
		$this->object = null;
		return $this;
	}

	// -----------------------------------------------------------------------
	// Initialisation (backward-compatible existing API)
	// -----------------------------------------------------------------------

	/**
	 * Instantiates the concrete engine adapter selected by $this->engine.
	 *
	 * Existing callers set $view->engine then call $view->init(); that pattern
	 * continues to work unchanged.  If init() is never called the adapter is
	 * created lazily on the first assign() / render() / display() call.
	 */
	public function init(): void {
		$this->object = $this->create_engine($this->engine);
	}

	// -----------------------------------------------------------------------
	// Core template operations
	// -----------------------------------------------------------------------

	/**
	 * Assigns a variable to the template engine.
	 *
	 * @param string $key   Variable name exposed inside the template.
	 * @param mixed  $value The value to expose.
	 */
	public function assign($key, $value): void {
		$this->ensure_engine();
		$this->object->assign($key, $value);
	}

	/**
	 * Renders the template and returns the result as a string.
	 *
	 * @param string $name Template file name/path.  When omitted the value
	 *                     supplied to the constructor is used.
	 * @return string Rendered output.
	 */
	public function render(string $name = ''): string {
		if ($name === '' && $this->template_name !== '') {
			$name = $this->template_name;
		}
		$this->ensure_engine();
		return $this->object->render($name);
	}

	/**
	 * Renders the template and sends the output directly to the browser.
	 */
	public function display(): void {
		echo $this->render();
	}

	public function __toString(): string {
		return $this->render();
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Ensures the engine adapter has been created, creating it lazily if not.
	 */
	private function ensure_engine(): void {
		if ($this->object === null) {
			$this->object = $this->create_engine($this->engine);
		}
	}

	/**
	 * Factory method — maps an engine name to a concrete adapter instance.
	 *
	 * @param  string $name Engine name.
	 * @return template_engine
	 */
	private function create_engine(string $name): template_engine {
		return match ($name) {
			'twig'    => new twig_engine($this->template_dir, $this->cache_dir),
			'raintpl' => new raintpl_engine($this->template_dir, $this->cache_dir),
			default   => new smarty_engine($this->template_dir, $this->cache_dir),
		};
	}

	/**
	 * Reads the preferred engine from default_settings when the database
	 * session is already available.  Falls back to 'smarty' so the class
	 * works at bootstrap time before any session exists.
	 *
	 * @return string Engine name.
	 */
	private function resolve_default_engine(): string {
		if (!class_exists('settings', false)) {
			return 'smarty';
		}
		try {
			$s = new settings([]);
			return $s->get('template', 'engine', 'smarty') ?: 'smarty';
		} catch (\Throwable $e) {
			return 'smarty';
		}
	}
}
