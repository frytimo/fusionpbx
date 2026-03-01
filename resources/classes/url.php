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
 * Tim Fry <tim@fusionpbx.com>
 */

/**
 * Description of url
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
declare(strict_types=1);

/**
 * Lightweight URL helper.
 * - Mutable; chainable setters return $this.
 * - Query helpers for getting/setting/removing params.
 * - Safe(ish) rebuild using http_build_query (RFC3986).
 *
 * Notes:
 * - Expects already-encoded paths; does not auto-encode segments.
 * - Keeps unknown components as-is (e.g., custom schemes).
 * - Validation is minimal; use filter_var(..., FILTER_VALIDATE_URL) if needed.
 */
class url {

	const SORT_NORMAL = 'natural';
	const SORT_NATURAL = 'natural';
	const SORT_ASC = 'asc';
	const SORT_DSC = 'dsc';
	const BUILD_FORCE_SCHEME = 1;
	const BUILD_FORCE_HOST = 2;
	const BUILD_FORCE_PATH = 4;

	const SAFE = 0;
	const UNSAFE = 1;

	private $parts;
	private $scheme;
	private $host;
	private $port;
	private $path;
	private $params;
	private $unsafe_params;
	private $fragment;

	private $original_url;
	private $username;
	private $password;

	public function __construct(?string $url = null) {
		// initialize object properties
		$this->scheme = '';
		$this->host = '';
		$this->port = '';
		$this->username = '';
		$this->password = '';
		$this->path = '';
		$this->fragment = '';
		$this->params = [];

		$parsed = parse_url(urldecode($url));

		// must be valid
		if ($parsed === false) {
			throw new InvalidArgumentException("Invalid URL: {$url}");
		}

		// we only support http and https
		if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
			throw new InvalidArgumentException("Unsupported scheme");
		}

		// set the schema
		$this->set_scheme($parsed['scheme'] ?? '');

		// set the host
		$this->set_host($parsed['host'] ?? '');

		// set the port
		$this->set_port($parsed['port'] ?? '');

		// set the path
		$this->set_path($parsed['path'] ?? '');

		// sanitize the query parameters
		$this->set_query($parsed['query'] ?? '');

		// set the fragment or ancore
		$this->set_fragment($parsed['fragment'] ?? '');

		// save the original URL provided
		$this->original_url = $url;
	}

	/**
	 * Creates a URL object using a URL string
	 * @param string $url
	 * @return self
	 */
	public static function from_string(string $url): self {
		return new self($url);
	}

	/**
	 * Creates a URL object using an associative array of URL parts
	 * @param array $parts
	 * @return self
	 */
	public static function from_parts(array $parts): self {
		$u = new self();
		// more validation needed here
		$u->parts = $parts;
		return $u;
	}

	/**
	 * Returns the URL used to create this object
	 * @return string|null
	 */
	public function get_original_url(): ?string {
		return $this->original_url;
	}

	/**
	 * Scheme of the link
	 * @return string
	 */
	public function get_scheme(): string {
		return $this->scheme;
	}

	/**
	 * User of the link
	 * @return string
	 */
	public function get_username(): string {
		return $this->username;
	}

	/**
	 * Password of the link
	 * @return string
	 */
	public function get_password(): string {
		return $this->password;
	}

	/**
	 * Host or domain of the link
	 * @return string
	 */
	public function get_host(): string {
		return $this->host;
	}

	/**
	 * Port in the link
	 * @return int
	 */
	public function get_port(): string {
		return $this->port;
	}

	/**
	 * Path in the link
	 * @return string
	 */
	public function get_path(): string {
		return $this->path;
	}

	/**
	 * Query in the link
	 *
	 * @param bool $unsafe Whether to return the unsafe (original) query parameters or the sanitized ones. Default is false (sanitized).
	 *
	 * @return string
	 */
	public function get_query(bool $unsafe = self::SAFE): string {
		$params = $unsafe ? $this->unsafe_params : $this->params;
		return implode('&', array_map(function ($param, $key) {
			return "$key=$param";
		}, $params, array_keys($params)));
	}

	/**
	 * Fragment or ancore in the link
	 * @return string
	 */
	public function get_fragment(): string {
		return $this->fragment;
	}

	/**
	 * Sets the scheme part used for the URL link
	 * @param string $scheme
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function set_scheme(string $scheme = ''): self {
		if (strlen($scheme)) {
			$scheme = strtolower($scheme);
			if (!in_array($scheme, ['http', 'https'], true)) {
				throw new InvalidArgumentException("Unsupported scheme");
			}
		}
		$this->scheme = $scheme;
		return $this;
	}

	/**
	 * Sets the user part of the URL
	 * @param string $username
	 * @return self
	 */
	public function set_username(string $username): self {
		$this->username = $username;
		return $this;
	}

	/**
	 * Sets the password part of the URL
	 * @param string $password
	 * @return self
	 */
	public function set_password(string $password): self {
		$this->password = $password;
		return $this;
	}

	/**
	 * Sets the host part of the URL sanitizing the host name before it is stored. If the host part is empty, the host will be removed from the URL.
	 * @param string $host
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function set_host(string $host = ''): self {
		if (strlen($host)) {
			// Use PHP features from 8.3 or higher when available to filter the domain
			if (defined('FILTER_SANITIZE_DOMAIN')) {
				// PHP 8.3 or higher
				$host = filter_var($host, FILTER_SANITIZE_DOMAIN);
			} else {
				// PHP < 8.3
				$host = self::sanitize_host($host);
			}
			if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
				throw new InvalidArgumentException("Invalid host");
			}
		}
		$this->host = $host;
		return $this;
	}

	/**
	 * Helper function to sanitize a domain name. This function is not used when using PHP 8.3 or higher.
	 * @param string $domain
	 * @return string
	 */
	public static function sanitize_host(string $domain): string {
		return preg_replace('/[^a-z0-9.-]/i', '', strtolower($domain));
	}

	/**
	 * Sets the port part of the URL. When the URL is using the scheme of HTTPS or HTTP and the port matches, it will be omitted. If the port is set to zero the port is removed from the URL.
	 * @param int|string $port
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function set_port($port = ''): self {
		if (strlen("$port")) {
			$port = (int)$port;
			if ($port < 1 || $port > 65535) {
				throw new InvalidArgumentException("Invalid port");
			}
			if ($port == 443 && $this->get_scheme() == 'https' || $port == 80 && $this->get_scheme() == 'http') {
				// Port setting is already implicitly set
				$port = '';
			}
		}
		$this->port = "$port";
		return $this;
	}

	/**
	 * Sets the path part of the URL sanitizing before it is stored using the filter_var function.
	 * @param string $path
	 * @return self
	 * @see filter_var()
	 */
	public function set_path(string $path = ''): self {
		// Strip out suspicious characters but keep slashes
		$this->path = filter_var($path, FILTER_SANITIZE_URL);
		return $this;
	}

	/**
	 * Sets the query part of the URL using a string. When an empty string is provided it will unset all parameters.
	 *
	 * @param string $query Full parameter string without the scheme or domain or path parts
	 *
	 * @return self
	 *
	 * @see self::set_query_param()
	 */
	public function set_query(string $query = ''): self {
		if (strlen($query)) {
			$pos = strpos($query, '#');
			if ($pos > 0) {
				$parts = explode('#', $query, 2);
				if (count($parts) > 1) {
					$this->set_fragment($parts[1]);
				}
				if (count($parts) > 0) {
					$query = $parts[0];
				} else {
					$query = '';
				}
			}
			$params = [];
			$query = parse_str($query, $params);
			foreach ($params as $key => $value) {
				$this->set_query_param($key, $value);
			}
		} else {
			$this->remove_parameters();
		}
		return $this;
	}

	/**
	 * Sets the fragment or ancore of the URL sanitizing using filter_var before it is stored
	 * @param string $fragment
	 * @return self
	 * @see filter_var()
	 */
	public function set_fragment(string $fragment = ''): self {
		if (strlen($fragment)) {
			$fragment = filter_var($fragment, FILTER_SANITIZE_URL);
		}
		$this->fragment = $fragment;
		return $this;
	}

	/**
	 * Returns an associative array of current queries in the parts
	 * @return array
	 */
	public function get_query_array(): array {
		// return the array
		return $this->params;
	}

	public function get(string $key, mixed $default = null, bool $unsafe = false): mixed {
		if ($unsafe) {
			return $this->get_query_param($key, $default);
		}
		return $this->get_query_param($key, $default);
	}

	/**
	 * Returns the query parameter using the key
	 * @param string $key Key is converted to lowercase
	 * @param mixed $default
	 * @return mixed
	 */
	public function get_query_param(string $key, mixed $default = null, bool $unsafe = false): mixed {
		// framework specific to use lowercase only for param keys
		$key = strtolower($key);

		// filter is 0 for safe (sanitized) and 1 for unsafe (original)
		$filter = (int)$unsafe;

		// return the value if it exists, otherwise return the default
		return isset($this->params[$key][$filter]) ? $this->params[$key][$filter] : $default;
	}

	/**
	 * Sets a query parameter sanitizing the value before it is added to the query part
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function set_query_param(string $key, mixed $value): self {

		$key = strtolower($key);
		if (!strlen($key)) {
			throw new \InvalidArgumentException("Key must not be empty", 500);
		}

		// Sanitize the value for the safe parameters
		$filtered = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

		// Remove keys that have invalid values from the safe parameters but keep them in the unsafe parameters for reference
		if ($key === 'sort' && !in_array($filtered, [self::SORT_ASC, self::SORT_DSC, self::SORT_NATURAL])) {
			// Wipe it from memory
			unset($filtered);
		} elseif ($key === 'page' && !is_numeric($filtered)) {
			// Wipe it from memory
			unset($filtered);
		}

		// Only set the safe param if it is valid after the filter
		if (isset($filtered)) {
			$this->params[$key][self::SAFE] = $filtered;
		}

		// Store the unsafe param for reference even if the value is invalid for the safe parameters
		$this->params[$key][self::UNSAFE] = $value;

		// Allow chaining
		return $this;
	}

	/**
	 * Removes a query parameter using the key
	 * @param string $key string of the key value
	 * @return self
	 */
	public function unset_query_param(string $key): self {
		unset($this->params[$key]);
		return $this;
	}

	/**
	 * Returns the path segments that were set
	 * @return array
	 */
	public function get_path_segments(): array {
		$segments = [];
		$path = $this->get_path();
		if (!empty($path)) {
			$segments = array_values(array_filter(explode('/', $path), function($s) { return $s !== '';}));
		}
		return $segments;
	}

	/**
	 * Sets the path segments using the strval function
	 * @param array $segments
	 * @return self
	 */
	public function set_path_segments(array $segments): self {
		// Assumes segments are already encoded how you want them
		$this->path = '/' . implode('/', array_map('strval', $segments));
		return $this;
	}

	/**
	 * Appends a path to the current path
	 * @param string $segment
	 * @return self
	 */
	public function append_path(string $segment): self {
		$path = rtrim((string) ($this->path ?? ''), '/');
		$segment = ltrim($segment, '/');
		$this->path = ($path === '' ? '' : $path . '/') . $segment;
		if (!str_starts_with($this->path, '/')) {
			$this->path = '/' . $this->path;
		}
		return $this;
	}

	public function to_array(): array {
		$parts = [];

		$scheme = $this->get_scheme();
		if (strlen($scheme)) {
			$parts['scheme'] = $scheme;
		}

		$host = $this->get_host();
		$user = $this->get_username();
		$port = $this->get_port();
		if (strlen($host) || strlen($user) || strlen("$port")) {
			if (strlen($user)) {
				$parts['username'] = $user;
				// password cannot be present without a user
				$pass = $this->get_password();
				if (strlen($pass)) {
					$parts['password'] = $pass;
				}
			}

			if (strlen($host)) {
				$parts['host'] = $host;
			}

			if (strlen("$port")) {
				$parts['port'] = $port;
			}
		}

		$path = $this->get_path();
		if (strlen($path)) {
			$parts['path'] = $path;
		}

		$query = $this->get_query();
		if (strlen($query)) {
			$parts['query'] =  $query;
		}

		$fragment = $this->get_fragment();
		if (strlen($fragment)) {
			$parts['fragment'] = $fragment;
		}
		return $parts;
	}

	/**
	 * Builds a URL from the parts in to a string
	 * @return string URL with all available parts
	 */
	public function build(int $flags = 0): string {
		$string_buffer = '';

		$scheme = $this->get_scheme();
		if (!strlen($scheme) && $flags & self::BUILD_FORCE_SCHEME) {
			$scheme = $_REQUEST['REQUEST_SCHEME'] ?? 'https';
		}
		$host = $this->get_host();
		if (!strlen($host) && $flags & self::BUILD_FORCE_HOST) {
			$host = $_SERVER['SERVER_NAME'];
		}
		$user = $this->get_username();
		$port = $this->get_port();
		$pass = $this->get_password();
		$path = $this->get_path();
		if (!strlen($path) && $flags & self::BUILD_FORCE_PATH) {
			$path = '/';
		}
		$query = $this->get_query();
		$fragment = $this->get_fragment();

		if (strlen($scheme)) {
			$string_buffer .= $scheme . ':';
		}

		if (strlen($host) || strlen($user) || strlen("$port")) {
			$string_buffer .= '//';

			if (strlen($user)) {
				$string_buffer .= $user;
				// password cannot be present without a user
				if (strlen($pass)) {
					$string_buffer .= ':' . $pass;
				}
				$string_buffer .= '@';
			}

			if (strlen($host)) {
				$string_buffer .= $host;
			}

			if (strlen("$port") && !($scheme == 'https' && "$port" == "443") && !($scheme == 'http' && "$port" == "80")) {
				$string_buffer .= ':' . $port;
			}
		}

		if (strlen($path)) {
			$string_buffer .= $path;
		}

		if (strlen($query)) {
			$string_buffer .= '?' . $query;
		}

		if (strlen($fragment)) {
			$string_buffer .= '#' . $fragment;
		}

		return $string_buffer;
	}

	public function build_absolute(): string {
		return $this->build(self::BUILD_FORCE_SCHEME ^ self::BUILD_FORCE_HOST ^ self::BUILD_FORCE_PATH);
	}

	public function build_relative(): string {
		$url = clone $this;
		$url->set_scheme()->set_host()->set_port();
		return $url->build();
	}

	/**
	 * Returns a link that is built
	 * @return string
	 */
	public function __toString(): string {
		try {
			return $this->build();
		} catch (Throwable) {
			return '';
		}
	}

	/**
	 * Returns a new URL object with the page link incremented by one
	 * @return self
	 */
	public function page_next(): self {
		$url = clone $this;
		$page = (int)$this->get_query_param('page', 0);
		$url->set_query_param('page', ++$page);
		return $url;
	}

	/**
	 * Returns a new URL object with the page link decremented by one
	 * @return self
	 */
	public function page_prev(): self {
		// create a copy
		$url = clone $this;
		// get the page
		$page = (int)$this->get_query_param('page', 0);
		// above zero set a page
		if (--$page > 0) {
			$url->set_query_param('page', $page);
		} else {
			// remove the page parameter
			$url->unset_query_param('page');
		}
		// return the new object with the modified page number
		return $url;
	}

	public function page_first(): self {
		$url = clone $this;
		$url->unset_query_param('page');
		return $url;
	}

	public function page_set($page): self {
		$url = clone $this;
		$url->set_query_param('page', $page);
		return $url;
	}

	/**
	 * Sets the order_by query parameter
	 * @param string $order_by
	 * @return self
	 */
	public function set_order_by(string $order_by = ''): self {
		// Create a clone
		$url = clone $this;
		if (strlen($order_by) > 0) {
			$url->unset_query_param('order_by');
		} else {
			// set the order_by in the new object
			$url->set_query_param('order_by', $order_by);
		}
		return $url;
	}

	public function get_order_by(): string {
		return $this->get_query_param('order_by', '');
	}

	/**
	 * Sets the sort query parameter
	 * @param string $sort
	 * @return self
	 * @throws \InvalidArgumentException
	 */
	public function set_sort(string $sort = self::SORT_NATURAL): self {
		// Create a clone
		$url = clone $this;
		if ($sort === self::SORT_NATURAL) {
			// natural sorting means no sort
			$url->unset_query_param('sort');
		} else {
			// set the sort param in the new object
			$url->set_query_param('sort', $sort);
		}
		return $url;
	}

	public function get_sort(): string {
		return $this->get_query_param('sort', self::SORT_NATURAL);
	}

	public function sort_asc(): self {
		return $this->set_sort(self::SORT_ASC);
	}

	public function sort_desc(): self {
		return $this->set_sort(self::SORT_DSC);
	}

	public function remove_parameters(): self {
		$this->params = [];
		$this->fragment = '';
		return $this;
	}
}
