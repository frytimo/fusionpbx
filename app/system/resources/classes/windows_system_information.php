<?php

/*
 * The MIT License
 *
 * Copyright 2025 Tim Fry <tim@fusionpbx.com>.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Description of windows_system_information
 *
 * @author Tim Fry <tim@fusionpbx.com>
 */
class windows_system_information extends system_information {

	/**
	 * Selects the preferred service definition file for Windows.
	 *
	 * @param array $module_files Service definition files for a module.
	 *
	 * @return string|null Preferred file path.
	 */
	protected function select_preferred_service_file(array $module_files): ?string {
		$selected_file = $module_files[0] ?? null;
		if ($selected_file === null) {
			return null;
		}

		foreach ($module_files as $candidate) {
			if (strpos(basename($candidate), 'windows') !== false) {
				$selected_file = $candidate;
				break;
			}
		}

		return $selected_file;
	}

	/**
	 * Retrieves a Windows service identifier from a service definition file.
	 *
	 * @param string $file Path to the service file.
	 *
	 * @return string Service identifier, or empty string if not found.
	 */
	protected function get_service_identifier(string $file): string {
		if (!file_exists($file)) {
			return '';
		}

		$content = file_get_contents($file);
		if ($content === false) {
			return '';
		}

		if (preg_match('/^ExecStart\s*=\s*.*?\s+([^\s]+\.php)\s*$/mi', $content, $matches)) {
			return basename($matches[1], '.php');
		}

		return '';
	}

	/**
	 * Checks if a process with the given name is currently running on Windows.
	 *
	 * @param string $name The name of the process to check for.
	 *
	 * @return array Process status including running flag, PID, and elapsed time.
	 */
	public function is_running(string $name): array {
		$name = trim($name);
		$safe_name = escapeshellarg($name);
		$output = shell_exec("tasklist /FI \"IMAGENAME eq {$safe_name}*\" /FO CSV /NH 2>nul");
		if (!empty($output)) {
			$lines = explode("\n", trim($output));
			foreach ($lines as $line) {
				$fields = str_getcsv($line);
				if (isset($fields[0]) && isset($fields[1])) {
					$image_name = $fields[0];
					$pid = $fields[1];
					if (stripos($image_name, $name) !== false && preg_match('/^\d+$/', $pid)) {
						return ['running' => true, 'pid' => $pid, 'etime' => null];
					}
				}
			}
		}

		return ['running' => false, 'pid' => null, 'etime' => null];
	}

	/**
	 * Returns the number of CPU cores available on the system.
	 *
	 * This method uses wmic to retrieve the number of physical CPU cores.
	 *
	 * @return int The total number of CPU cores
	 */
	public function get_cpu_cores(): int {
		$output = shell_exec('wmic cpu get NumberOfCores /value 2>nul');
		if (!empty($output)) {
			if (preg_match('/NumberOfCores=(\d+)/', $output, $matches)) {
				return (int) $matches[1];
			}
		}

		return 0;
	}

	/**
	 * Returns the current CPU usage as a percentage.
	 *
	 * This method uses typeperf to sample processor counter data and
	 * calculate the average CPU usage across all cores.
	 *
	 * @return float The current CPU usage in percent.
	 */
	public function get_cpu_percent(): float {
		$output = shell_exec('typeperf "\Processor(_Total)\% Processor Time" -sc 1 2>nul');
		if (!empty($output)) {
			$lines = explode("\n", trim($output));
			if (isset($lines[1])) {
				$fields = str_getcsv($lines[1]);
				if (isset($fields[1])) {
					return round((float) trim($fields[1], '"'), 2);
				}
			}
		}

		return 0.0;
	}

	/**
	 * Returns the current system uptime.
	 *
	 * This method uses PowerShell to calculate system uptime in seconds
	 * and returns a formatted string similar to the Linux uptime command.
	 *
	 * @return string The current system uptime.
	 */
	public function get_uptime() {
		$output = shell_exec("powershell -NoProfile -Command \"(Get-CimInstance -ClassName Win32_OperatingSystem).LastBootUpTime.ToString('o')\" 2>nul");
		if (!empty($output)) {
			$last_boot = trim($output);
			$boot_time = strtotime($last_boot);
			if ($boot_time !== false) {
				$uptime_seconds = time() - $boot_time;
				$days = floor($uptime_seconds / 86400);
				$hours = floor(($uptime_seconds % 86400) / 3600);
				$minutes = floor(($uptime_seconds % 3600) / 60);
				return "up " . ($days > 0 ? "$days days, " : "") . "$hours:$minutes";
			}
		}

		return "up unknown";
	}

	/**
	 * Returns the current CPU usage as a percentage per core.
	 *
	 * This method uses PowerShell to sample processor counter data per core.
	 *
	 * @return array An array where the keys are the core numbers (starting at 0)
	 *               and the values are the current CPU usage for each core in percent.
	 */
	public function get_cpu_percent_per_core(): array {
		$output = shell_exec("powershell -NoProfile -Command \"Get-CimInstance -ClassName Win32_PerfFormattedData_PerfOS_Processor | Where-Object { \$_.Name -ne '_Total' } | Select-Object -Property Name,PercentProcessorTime | ConvertTo-Csv -NoTypeInformation\" 2>nul");
		$results = [];

		if (!empty($output)) {
			$lines = explode("\n", trim($output));
			for ($i = 1; $i < count($lines); $i++) {
				$fields = str_getcsv($lines[$i]);
				if (isset($fields[0]) && isset($fields[1])) {
					$core_name = trim($fields[0], '"');
					$percent = trim($fields[1], '"');
					if (preg_match('/^\d+$/', $core_name)) {
						$results[(int) $core_name] = round((float) $percent, 2);
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Returns the current network speed for the specified interface.
	 *
	 * This method uses typeperf to read network interface counters and calculates
	 * the network speed from the received and transmitted bytes.
	 *
	 * @param string $interface The network interface to read stats from. Defaults to 'eth0'.
	 *
	 * @return array An array containing the current receive (rx_bps) and transmit (tx_bps) speeds in bits per second.
	 */
	public function get_network_speed(string $interface = 'eth0'): array {
		static $last = [];

		$rx_counter = '\Network Interface(' . $interface . ')\Bytes Received/sec';
		$tx_counter = '\Network Interface(' . $interface . ')\Bytes Sent/sec';
		$counters = '"' . $rx_counter . '";"' . $tx_counter . '"';
		$output = shell_exec("typeperf $counters -sc 1 2>nul");

		if (!empty($output)) {
			$lines = explode("\n", trim($output));
			if (isset($lines[1])) {
				$fields = str_getcsv($lines[1]);
				if (isset($fields[1]) && isset($fields[2])) {
					$rx_bytes = (float) trim($fields[1], '"');
					$tx_bytes = (float) trim($fields[2], '"');
					$rx_bits = $rx_bytes * 8;
					$tx_bits = $tx_bytes * 8;

					$now = microtime(true);

					if (!isset($last[$interface])) {
						$last[$interface] = ['rx' => $rx_bits, 'tx' => $tx_bits, 'time' => $now];
						return ['rx_bps' => 0, 'tx_bps' => 0];
					}

					$delta_time = $now - $last[$interface]['time'];
					$delta_rx = $rx_bits - $last[$interface]['rx'];
					$delta_tx = $tx_bits - $last[$interface]['tx'];

					$last[$interface] = ['rx' => $rx_bits, 'tx' => $tx_bits, 'time' => $now];

					return [
						'rx_bps' => $delta_rx / $delta_time,
						'tx_bps' => $delta_tx / $delta_time
					];
				}
			}
		}

		return ['rx_bps' => 0, 'tx_bps' => 0];
	}

	/**
	 * Attempts to detect the network card on Windows.
	 *
	 * @param string|null $default_value Default network card.
	 *
	 * @return string|null
	 */
	public function get_network_card(?string $default_value = null): ?string {
		// Use Get-NetRoute to find the interface associated with the default route
		$output = shell_exec("powershell -NoProfile -Command \"(Get-NetRoute -DestinationPrefix '0.0.0.0/0' | Select-Object -First 1).InterfaceAlias\" 2>nul");
		if (!empty($output)) {
			$interface = trim($output);
			if (!empty($interface)) {
				$default_value = $interface;
			}
		}

		// Fallback: use route print to find the interface associated with the default gateway
		if ($default_value === null) {
			$output = shell_exec('route print 0.0.0.0 2>nul');
			if (!empty($output)) {
				$lines = explode("\n", $output);
				foreach ($lines as $line) {
					if (preg_match('/\s*0\.0\.0\.0\s+0\.0\.0\.0\s+\d+\.\d+\.\d+\.\d+\s+(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
						$ip = $matches[1];
						$name = shell_exec("powershell -NoProfile -Command \"(Get-NetIPAddress -IPAddress $ip -ErrorAction SilentlyContinue).InterfaceAlias\" 2>nul");
						if (!empty(trim((string) $name))) {
							$default_value = trim($name);
						}
						break;
					}
				}
			}
		}

		return $default_value;
	}
}
