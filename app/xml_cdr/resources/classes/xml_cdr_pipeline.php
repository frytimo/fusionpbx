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
 * Static factory and pipeline orchestration helpers for the xml_cdr pipeline.
 *
 * Mirrors the opensms class pattern: all methods are static, the class holds no
 * state, and component discovery uses auto_loader->get_interface_list() so that
 * any FusionPBX application can register its own enrichers, modifiers, listeners,
 * and notifiers simply by placing a class implementing the correct interface in
 * its own resources/classes/ directory.
 */
class xml_cdr_pipeline {
	// Component discovery
	/**
	 * Discover all classes implementing xml_cdr_consumer via the auto_loader.
	 *
	 * @return array Array of class names.
	 */
	public static function discover_consumers(): array {
		$auto_loader = new auto_loader();
		return $auto_loader->get_interface_list('xml_cdr_consumer');
	}

	/**
	 * Discover all classes implementing xml_cdr_enricher via the auto_loader.
	 *
	 * @return array Array of class names.
	 */
	public static function discover_enrichers(): array {
		$auto_loader = new auto_loader();
		return $auto_loader->get_interface_list('xml_cdr_enricher');
	}

	/**
	 * Discover all classes implementing xml_cdr_modifier via the auto_loader.
	 *
	 * @return array Array of class names.
	 */
	public static function discover_modifiers(): array {
		$auto_loader = new auto_loader();
		return $auto_loader->get_interface_list('xml_cdr_modifier');
	}

	/**
	 * Discover all classes implementing xml_cdr_listener via the auto_loader.
	 *
	 * @return array Array of class names.
	 */
	public static function discover_listeners(): array {
		$auto_loader = new auto_loader();
		return $auto_loader->get_interface_list('xml_cdr_listener');
	}

	/**
	 * Discover all classes implementing xml_cdr_notifier via the auto_loader.
	 *
	 * @return array Array of class names.
	 */
	public static function discover_notifiers(): array {
		$auto_loader = new auto_loader();
		return $auto_loader->get_interface_list('xml_cdr_notifier');
	}
	// Chain builders
	/**
	 * Build an ordered enricher callable from an array of enricher class names.
	 * Returns a callable that applies all enrichers in priority order.
	 *
	 * @param array $enricher_classes Array of class names implementing xml_cdr_enricher.
	 *
	 * @return callable fn(settings, xml_cdr_record): void
	 */
	public static function build_enricher_chain(array $enricher_classes): callable {
		$instances = self::instantiate_and_sort($enricher_classes, 'xml_cdr_enricher');

		return function (settings $settings, xml_cdr_record $record) use ($instances): void {
			foreach ($instances as $enricher) {
				$enricher($settings, $record);
			}
		};
	}

	/**
	 * Build an ordered modifier callable from an array of modifier class names.
	 * Returns a callable that applies all modifiers in priority order.
	 *
	 * @param array $modifier_classes Array of class names implementing xml_cdr_modifier.
	 *
	 * @return callable fn(settings, xml_cdr_record): void
	 */
	public static function build_modifier_chain(array $modifier_classes): callable {
		$instances = self::instantiate_and_sort($modifier_classes, 'xml_cdr_modifier');

		return function (settings $settings, xml_cdr_record $record) use ($instances): void {
			foreach ($instances as $modifier) {
				$modifier($settings, $record);
			}
		};
	}

	/**
	 * Instantiate an array of listener class names.
	 * Returns an array of ready-to-call listener instances.
	 *
	 * @param array $listener_classes Array of class names implementing xml_cdr_listener.
	 *
	 * @return xml_cdr_listener[]
	 */
	public static function build_listener_chain(array $listener_classes): array {
		$instances = [];
		foreach ($listener_classes as $class) {
			$instance = new $class();
			if (!($instance instanceof xml_cdr_listener)) {
				throw new InvalidArgumentException("Class $class does not implement xml_cdr_listener");
			}
			$instances[] = $instance;
		}
		return $instances;
	}

	/**
	 * Instantiate an array of notifier class names.
	 *
	 * @param array $notifier_classes Array of class names implementing xml_cdr_notifier.
	 *
	 * @return xml_cdr_notifier[]
	 */
	public static function build_notifier_chain(array $notifier_classes): array {
		$instances = [];
		foreach ($notifier_classes as $class) {
			$instance = new $class();
			if (!($instance instanceof xml_cdr_notifier)) {
				throw new InvalidArgumentException("Class $class does not implement xml_cdr_notifier");
			}
			$instances[] = $instance;
		}
		return $instances;
	}
	// Pipeline runner
	/**
	 * Run the full enricher → modifier → listener pipeline for one record.
	 *
	 * Returns the outcome string: 'stored', 'skipped', 'discarded', or 'failed'.
	 *
	 * The try/catch structure:
	 *  - xml_cdr_skip_exception    → outcome 'skipped',   source file kept
	 *  - xml_cdr_discard_exception → outcome 'discarded', source file deleted
	 *  - Throwable                 → outcome 'failed',    source file moved to failed/
	 *
	 * Notifiers are fired on every non-happy outcome.
	 *
	 * @param xml_cdr_record $record    The CDR record to process.
	 * @param callable       $enrich    Built enricher chain.
	 * @param callable       $modify    Built modifier chain.
	 * @param array          $listeners Instantiated xml_cdr_listener objects.
	 * @param array          $notifiers Instantiated xml_cdr_notifier objects.
	 * @param settings       $settings  Application settings.
	 *
	 * @return string One of: 'stored', 'skipped', 'discarded', 'failed'.
	 */
	public static function run_pipeline(
		xml_cdr_record $record,
		callable $enrich,
		callable $modify,
		array $listeners,
		array $notifiers,
		settings $settings
	): string {
		try {
			// Enrichment phase: resolve external data (domain, extension, etc.)
			$enrich($settings, $record);

			// Modification phase: transform fields, check filters
			$modify($settings, $record);

			// Listener phase: persistence and side effects
			foreach ($listeners as $listener) {
				$listener->on_cdr($settings, $record);
			}

			return 'stored';

		} catch (xml_cdr_skip_exception $e) {
			// Keep the source file; another host or retry may handle it later
			self::fire_notifiers(
				$notifiers, $settings, $record, 'skipped', $e->getMessage(), $e
			);
			return 'skipped';

		} catch (xml_cdr_discard_exception $e) {
			// Delete the source file; the record is intentionally not stored
			if (!empty($record->source_file) && file_exists($record->source_file)) {
				unlink($record->source_file);
			}
			self::fire_notifiers(
				$notifiers, $settings, $record, 'discarded', $e->getMessage(), $e
			);
			return 'discarded';

		} catch (Throwable $e) {
			// Unexpected error; let the caller (service) handle file relocation
			self::fire_notifiers(
				$notifiers, $settings, $record, 'error', $e->getMessage(), $e
			);
			// Re-throw so the service can move the file to failed/
			throw $e;
		}
	}
	// Notifier dispatch
	/**
	 * Fire all notifiers with a new event. Notifier exceptions are swallowed
	 * to prevent one bad notifier from blocking others.
	 *
	 * @param array          $notifiers
	 * @param settings       $settings
	 * @param xml_cdr_record $record
	 * @param string         $event_type
	 * @param string         $reason
	 * @param Throwable|null $exception
	 *
	 * @return void
	 */
	public static function fire_notifiers(
		array $notifiers,
		settings $settings,
		xml_cdr_record $record,
		string $event_type,
		string $reason,
		?Throwable $exception = null
	): void {
		$event = xml_cdr_event::create($record, $event_type, $reason, $exception);
		foreach ($notifiers as $notifier) {
			try {
				$notifier->on_event($settings, $event);
			} catch (Throwable $ignored) {
				// Notifiers must not disrupt processing of other notifiers.
			}
		}
	}
	// Private helpers
	/**
	 * Instantiate class names and sort by priority() ascending.
	 *
	 * @param array  $classes        Array of class name strings.
	 * @param string $interface_name Interface name for validation error messages.
	 *
	 * @return array Sorted array of instances.
	 */
	private static function instantiate_and_sort(array $classes, string $interface_name): array {
		$instances = [];
		foreach ($classes as $class) {
			$instance = new $class();
			if (!($instance instanceof $interface_name)) {
				throw new InvalidArgumentException("Class $class does not implement $interface_name");
			}
			$instances[] = $instance;
		}

		usort($instances, function ($a, $b) {
			return $a->priority() <=> $b->priority();
		});

		return $instances;
	}

}
