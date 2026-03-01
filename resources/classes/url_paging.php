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
 * URL Paging helper extending url class.
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */

declare(strict_types=1);

/**
 * Paging helper for URLs.
 * Extends url class with paging, sorting, and ordering functionality.
 */
class url_paging extends url {
	private $settings;

	private $page;

	private $rows_per_page;

	private $total_rows;

	public function __construct(settings $settings, ?string $url = null) {
		parent::__construct($url);
		$this->settings = $settings;
		$this->rows_per_page = (int)$settings->get('domain', 'paging', 50);
		$this->total_rows = 0;
		$this->page = (int) $this->get('page', 0);
		$this->set_page($this->page);
	}

	public function offset(): int {
		return $this->page * $this->rows_per_page;
	}

	public function pages(): int {
		if ($this->rows_per_page > 0) {
			return (int) ceil($this->total_rows / $this->rows_per_page);
		}
		return 0;
	}

	public function get_rows_per_page(): int {
		return (int)$this->rows_per_page;
	}

	/**
	 * Set the current page number.
	 *
	 * @return self
	 */
	public function set_page(int $page): self {
		$this->page = max(0, $page);
		if ($this->page > 0) {
			$this->set_query_param('page', $this->page);
		} else {
			$this->unset_query_param('page');
		}
		return $this;
	}

	/**
	 * Get the current page number.
	 *
	 * @return int
	 */
	public function get_page(): int {
		return $this->page;
	}

	/**
	 * Set the settings object.
	 *
	 * @param settings $settings The settings object to set.
	 */
	public function set_settings(settings $settings): void {
		$this->settings = $settings;
	}

	/**
	 * Get the settings object.
	 *
	 * @return settings
	 */
	public function get_settings(): settings {
		return $this->settings;
	}

	/**
	 * Return next-page URL object in URL parts mode.
	 *
	 * @return self
	 */
	public function next(): static {
		$clone = clone $this;
		$page = (int) $clone->get('page', 0);
		$clone->set_query_param('page', $page + 1);

		return $clone;
	}

	/**
	 * Return previous-page URL object in URL parts mode.
	 *
	 * @return self
	 */
	public function prev(): static {
		$clone = clone $this;
		$page = (int) $clone->get_query_param('page', 0) - 1;
		if ($page > 0) {
			$clone->set_query_param('page', $page);
		} else {
			$clone->unset_query_param('page');
		}

		return $clone;
	}

	/**
	 * Return first-page URL object in URL parts mode.
	 *
	 * @return self
	 */
	public function page_first(): static {
		$clone = clone $this;
		$clone->unset_query_param('page');

		return $clone;
	}

	protected function filter_query_modifier(string $key, mixed $value): mixed {
		$filtered = parent::filter_query_modifier($key, $value);

		if ($key === 'page' && !is_numeric($filtered)) {
			$filtered = null;
		}

		return $filtered;
	}

	/**
	 * Get the total number of rows for the current query.
	 *
	 * @return int
	 */
	public function get_total_rows(): int {
		return $this->total_rows;
	}

	/**
	 * Set the total number of rows for the current query.
	 *
	 * @param int $total_rows Total number of rows in the result set.
	 * @return self
	 */
	public function set_total_rows(int $total_rows): self {
		$this->total_rows = max(0, $total_rows);
		return $this;
	}

	/**
	 * Build paging controls HTML from stored paging state.
	 *
	 * @param bool $mini Render mini controls.
	 * @return string
	 */
	public static function html_paging_controls(url_paging $url, bool $mini = false): string {
		global $text;

		if ($url->get_total_rows() <= 0) {
			return '';
		}

		$max_page = $url->pages();
		if ($url->pages() < 1) {
			return '';
		}

		$page_number = $url->get_page();

		$label_back = $text['button-back'] ?? 'Back';
		$label_next = $text['button-next'] ?? 'Next';
		$label_page = $text['label-page'] ?? 'Page';

		$prev_link = $url->prev()->build();
		$next_link = $url->next()->build();

		if (class_exists('button')) {
			if ($page_number > 0) {
				$prev = button::create([
					'type'  => 'button',
					'label' => (!$mini ? $label_back : null),
					'icon'  => 'chevron-left',
					'link'  => $prev_link,
					'title' => $label_page . ' ' . $page_number,
				]);
			} else {
				$prev = button::create([
					'type'  => 'button',
					'label' => (!$mini ? $label_back : null),
					'icon'  => 'chevron-left',
					'style' => 'opacity: 0.4; -moz-opacity: 0.4; cursor: default;',
					'onclick' => 'return false;',
				]);
			}

			$next_is_enabled = ($page_number < $max_page - 1);

			if ($next_is_enabled) {
				$next = button::create([
					'type' => 'button',
					'label' => (!$mini ? $label_next : null),
					'icon' => 'chevron-right',
					'link' => $next_link,
					'title' => $label_page . ' ' . ($page_number + 2),
				]);
			} else {
				$next = button::create([
					'type' => 'button',
					'label' => (!$mini ? $label_next : null),
					'icon' => 'chevron-right',
					'onclick' => 'return false;',
					'style' => 'opacity: 0.4; -moz-opacity: 0.4; cursor: default;',
				]);
			}
		} else {
			$prev = "<a href='" . htmlspecialchars($prev_link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($label_back, ENT_QUOTES, 'UTF-8') . "</a>";
			$next = "<a href='" . htmlspecialchars($next_link, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($label_next, ENT_QUOTES, 'UTF-8') . "</a>";
		}

		$html = '';
		if ($max_page > 1) {
			if ($mini) {
				$html = "<span style='white-space: nowrap;'>" . $prev . $next . "</span>\n";
			} else {
				$page_input_id = 'paging_page_num_' . substr(md5((string) $url->get_host() . ':' . $max_page), 0, 8);
				$html .= "<script>\n";
				$html .= "function fusionpbx_paging_go_" . $page_input_id . "(e) {\n";
				$html .= "\tvar page_num = document.getElementById('" . $page_input_id . "').value;\n";
				$html .= "\tvar do_action = false;\n";
				$html .= "\tif (e != null) {\n";
				$html .= "\t\tvar keyevent = window.event ? e.keyCode : e.which;\n";
				$html .= "\t\tif (keyevent == 13) { do_action = true; }\n";
				$html .= "\t\telse { return true; }\n";
				$html .= "\t}\n";
				$html .= "\telse { do_action = true; }\n";
				$html .= "\tif (do_action) {\n";
				$html .= "\t\tif (page_num < 1) { page_num = 1; }\n";
				$html .= "\t\tif (page_num > " . $max_page . ") { page_num = " . $max_page . "; }\n";
				$go_url = $url->set('page', 0)->build();
				$go_url = preg_replace('/([?&])page=0(&|$)/', '$1', $go_url);
				$go_url = rtrim((string) $go_url, '?&');
				$join = (strpos((string) $go_url, '?') !== false) ? '&' : '?';
				$html .= "\t\tdocument.location.href = '" . htmlspecialchars((string) $go_url, ENT_QUOTES, 'UTF-8') . $join . "page='+(--page_num);\n";
				$html .= "\t\treturn false;\n";
				$html .= "\t}\n";
				$html .= "}\n";
				$html .= "</script>\n";

				$html .= "<center style='white-space: nowrap;'>";
				$html .= $prev;
				$html .= "&nbsp;&nbsp;&nbsp;";
				$html .= "<input id='" . $page_input_id . "' class='formfld' style='max-width: 50px; min-width: 50px; text-align: center;' type='text' value='" . ($page_number + 1) . "' onfocus='this.select();' onkeypress='return fusionpbx_paging_go_" . $page_input_id . "(event);'>";
				$html .= "&nbsp;&nbsp;<strong>" . $max_page . "</strong>";
				$html .= "&nbsp;&nbsp;&nbsp;";
				$html .= $next;
				$html .= "</center>\n";
			}
		}

		return $html;
	}

	/**
	 * Build mini paging controls HTML from stored paging state.
	 *
	 * @return string
	 */
	public static function html_paging_mini_controls(url $url): string {
		return self::html_paging_controls($url, true);
	}
}
