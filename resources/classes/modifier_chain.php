<?php

/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2025
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Description of modifier_chain
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
final class modifier_chain {

	/**
	 * Link an ordered list of modifiers into a single modifier.
	 */
	public static function link(array $modifiers): modifier {
		// validate
		foreach ($modifiers as $m) {
			if (!($m instanceof modifier)) {
				throw new \InvalidArgumentException('Array must contain modifier objects', 1005);
			}
		}

		// compose into one invokable
		return new class($modifiers) implements modifier {

			/** @var array */
			private $modifiers;

			public function __construct(array $modifiers) {
				$this->modifiers = $modifiers;
			}

			public function __invoke(string $key, &$value, callable $next): void {
				$entry = $next;
				// wrap from right to left so $this->modifiers[0] runs first
				for ($i = count($this->modifiers) - 1; $i >= 0; $i--) {
					$current = $this->modifiers[$i];
					$prev = $entry;

					$entry = static function (string $k, &$v) use ($current, $prev): void {
						$current($k, $v, $prev);
					};
				}

				$entry($key, $value);
			}
		};
	}

	private static function compose(modifier $a, modifier $b): modifier {
		return new class($a, $b) implements modifier {

			private $a;
			private $b;

			public function __construct(modifier $a, modifier $b) {
				$this->a = $a;
				$this->b = $b;
			}

			public function __invoke(string $key, &$value, callable $next) {
				$bridge = function (string $k, &$v) use ($next) {
					return ($this->b)($k, $v, $next);
				};
				return ($this->a)($key, $value, $bridge);
			}
		};
	}

	public static function append(modifier $chain, array $modifiers): modifier {
		foreach ($modifiers as $modifier) {
			if (!($modifier instanceof modifier)) {
				throw new \InvalidArgumentException('Array must contain modifier objects', 1005);
			}
			$chain = self::compose($chain, $modifier);
		}
		return $chain;
	}

	public static function prepend(modifier $chain, array $modifiers): modifier {
		for ($i = count($modifiers) - 1; $i >= 0; $i--) {
			$modifier = $modifiers[$i];
			if (!($modifier instanceof modifier)) {
				throw new \InvalidArgumentException('Array must contain modifier objects', 1005);
			}
			$chain = self::compose($modifier, $chain);
		}
		return $chain;
	}

	public static function last_link() {
		return function (string $key, &$value) { return $value; };
	}
}
