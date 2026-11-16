<?php

/**
 * Accumulates incoming socket data and extracts it in delimited or fixed size chunks.
 */
class buffer {
	/**
	 * @var string The accumulated content that has not been read yet
	 */
	private $content;
	/**
	 * @var string The line delimiter used to separate the content into lines
	 */
	private $eol;

	/**
	 * Initializes an empty buffer with a line feed as the end of line delimiter
	 */
	public function __construct() {
		$this->content = '';
		$this->eol = "\n";
	}

	/**
	 * Appends a string to the end of the buffered content
	 *
	 * @param string $str String to append to the buffer
	 *
	 * @return void
	 */
	public function append($str) {
		$this->content .= $str;
	}

	/**
	 * Reads the next line from the buffer and removes it from the content
	 *
	 * @return string|false The line without its delimiter, or false when no complete line is available
	 */
	public function read_line() {
		$ar = explode($this->eol, $this->content, 2);
		if (count($ar) != 2) {
			return false;
		}
		$this->content = $ar[1];
		return $ar[0];
	}

	/**
	 * Reads a fixed number of bytes from the start of the buffer and removes them from the content
	 *
	 * @param int $n Number of bytes to read from the buffer
	 *
	 * @return string|false The requested data, or false when fewer bytes than requested are buffered
	 */
	public function read_n($n) {
		if (strlen($this->content) < $n) {
			return false;
		}
		$s = substr($this->content, 0, $n);
		$this->content = substr($this->content, $n);
		return $s;
	}

	/**
	 * Reads all of the buffered content and empties the buffer
	 *
	 * @param int $n Unused parameter retained for signature compatibility
	 *
	 * @return string All content that was in the buffer
	 */
	public function read_all($n) {
		$tmp = $this->content;
		$this->content = '';
		return $tmp;
	}
}

//$b = new buffer;
//$b->append("hello\nworld\n");
//print($b->read_line());
//print($b->read_line());

/**
 * Subscribes to the event socket of the FreeSWITCH (c) Event Socket Server
 */
class event_socket {
	/**
	 * @var self|null Shared instance used by create()
	 */
	private static $socket = null;
	/**
	 * @var resource|false The FreeSWITCH event socket stream
	 */
	public $fp;
	/**
	 * Used as a flag to determine if the socket should be created automatically
	 *
	 * @var bool
	 */
	protected $auto_create;
	/**
	 * @var buffer Buffer used to accumulate inbound socket data
	 */
	private $buffer;
	/**
	 * @var config Configuration used for the event socket host, port, and password
	 */
	private $config;

	/**
	 * Create a new connection to the socket
	 *
	 * @param resource|false $fp    Existing stream to use instead of creating a new connection
	 * @param config|null    $config Configuration used for the event socket settings, loaded when omitted
	 */
	public function __construct($fp = false, ?config $config = null) {
		$this->buffer = new buffer;
		$this->auto_create = $fp === false;
		$this->fp = $fp;
		$this->config = $config ?? config::load();
	}

	/**
	 * Sends an API command on the socket
	 *
	 * @param string $api_cmd API command to send to FreeSWITCH
	 *
	 * @return string|false Response from server or false if failed
	 * @static
	 */
	public static function api(string $api_cmd) {
		return self::command('api ' . $api_cmd);
	}

	/**
	 * Sends a command on the socket blocking for a response
	 *
	 * @param string $cmd Command to send through the socket
	 *
	 * @return string|false Response from server or false if failed
	 * @static
	 */
	public static function command(string $cmd) {
		return self::create()->request($cmd);
	}

	/**
	 * Send a command to the FreeSWITCH Event Socket Server
	 * <p>Multi-line commands can be sent when separated by '\n'</p>
	 *
	 * @param string $cmd Command to send through the socket
	 *
	 * @return mixed Returns the response from FreeSWITCH or false if not connected
	 */
	public function request($cmd) {
		if (!$this->connected()) {
			return false;
		}

		$cmd_array = explode("\n", $cmd);
		foreach ($cmd_array as $value) {
			fputs($this->fp, $value . "\n");
		}
		fputs($this->fp, "\n"); //second line feed to end the headers

		$event = $this->read_event();

		if (array_key_exists('$', $event)) {
			return $event['$'];
		}
		return $event;
	}

	/**
	 * Read the event body from the socket
	 * 
	 * @param int|null $timeout the timeout in microseconds for stream_select, Default 10000
	 * 
	 * @return array|false The event headers as an array with the body under the '$' key when Content-Length is present, or false if not connected
	 */
	public function read_event($timeout = 10000) {
		if (!$this->connected()) {
			return false;
		}

		$b = $this->buffer;
		$content = [];

		while (true) {
			$line = $b->read_line();
			if ($line !== false) {
				if ($line === '') {
					break;
				}
				[$key, $value] = explode(':', $line, 2);
				$content[trim($key)] = trim($value);
			}

			if (feof($this->fp)) {
				break;
			}

			// Check if we have data available
			$read = [$this->fp];
			$write = [];
			$except = [];
			if (stream_select($read, $write, $except, 0, $timeout) > 0) {
				$buffer = fgets($this->fp, 1024);
				if ($buffer !== false) {
					$b->append($buffer);
				}
			}
		}

		if (array_key_exists('Content-Length', $content)) {
			$str = $b->read_n($content['Content-Length']);
			if ($str === false) {
				while (true) {
					if (!$this->connected()) {
						break;
					}

					$buffer = fgets($this->fp, 1024);
					$b->append($buffer);
					$str = $b->read_n($content['Content-Length']);
					if ($str !== false) {
						break;
					}
				}
			}
			if ($str !== false) {
				$content['$'] = $str;
			}
		}

		return $content;
	}

	/**
	 * Create uses a singleton design to return a connected socket to the FreeSWITCH Event Socket Layer
	 *
	 * @param string $host                 Host or IP address of FreeSWITCH event socket server. Defaults to 127.0.0.1
	 * @param string $port                 Port number of FreeSWITCH event socket server. Defaults to 8021
	 * @param string $password             Password of FreeSWITCH event socket server. Defaults to ClueCon
	 * @param int    $timeout_microseconds Number of microseconds before timeout is triggered on socket
	 *
	 * @return self
	 * @global array $conf                 Global configuration used in config.conf
	 * @static
	 */
	public static function create($host = null, $port = null, $password = null, $timeout_microseconds = 30000): self {
		//create the event socket object
		if (self::$socket === null) {
			self::$socket = new event_socket();
		}
		//attempt to connect it
		if (!self::$socket->connected()) {
			self::$socket->connect($host, $port, $password, $timeout_microseconds);
		}
		return self::$socket;
	}

	/**
	 * Connect to the FreeSWITCH (c) event socket server
	 * <p>If the configuration is not loaded then the defaults of
	 * host 127.0.0.1, port of 8021, and default password of ClueCon will be used</p>
	 *
	 * @param null|string     $host                 Host or IP address of FreeSWITCH event socket server. Defaults to
	 *                                              127.0.0.1
	 * @param null|string|int $port                 Port number of FreeSWITCH event socket server. Defaults to 8021
	 * @param null|string     $password             Password of FreeSWITCH event socket server. Defaults to ClueCon
	 * @param int             $timeout_microseconds Number of microseconds before timeout is triggered on socket.
	 *                                              Defaults to 30,000
	 *
	 * @return bool Returns true on success or false if not connected
	 */
	public function connect($host = null, $port = null, $password = null, $timeout_microseconds = 30000) {
		//set the event socket variables in the order of
		//param passed to func, conf setting, old conf setting, default
		$host = $host ?? $this->config->get('switch.event_socket.host', null) ?? $this->config->get('event_socket.ip_address', null) ?? '127.0.0.1';
		$port = intval($port ?? $this->config->get('switch.event_socket.port', null) ?? $this->config->get('event_socket.port', null) ?? '8021');
		$password = $password ?? $this->config->get('switch.event_socket.password', null) ?? $this->config->get('event_socket.password', null) ?? 'ClueCon';

		//if a socket was provided in the constructor, then don't create a new one
		if ($this->fp === false || $this->auto_create) {
			//open the socket connection
			$this->fp = @fsockopen($host, $port, $errno, $errdesc, 3);
		}

		if (!$this->connected()) {
			return false;
		}

		socket_set_timeout($this->fp, 0, $timeout_microseconds);
		socket_set_blocking($this->fp, true);

		//wait auth request and send a response
		while ($this->connected()) {
			$event = $this->read_event();
			if (($event['Content-Type'] ?? '') === 'auth/request') {
				fputs($this->fp, "auth $password\n\n");
				break;
			}
		}

		//wait auth response
		while ($this->connected()) {
			$event = $this->read_event();
			if (($event['Content-Type'] ?? '') === 'command/reply') {
				if (($event['Reply-Text'] ?? '') === '+OK accepted') {
					break;
				} else {
					$this->close();
				}
			}
		}

		return $this->connected();
	}

	/**
	 * Sends an API command to FreeSWITCH using asynchronous (non-blocking) mode
	 *
	 * @param string $cmd API command to send
	 *
	 * @return string|false The Job ID for tracking completion status, or false when the command failed
	 * @static
	 */
	public static function async(string $cmd) {
		return self::command('bgapi ' . $cmd);
	}

	/**
	 * Ensures a closed connection on destruction of object
	 */
	public function __destruct() {
		// Only close the resources that we created in this class
		if ($this->auto_create) {
			$this->close();
		}
	}

	/**
	 * Close the socket connection with the FreeSWITCH Event Socket Server.
	 *
	 * @return void
	 */
	public function close() {
		// fp is public access so ensure it is a resource before closing it
		if (is_resource($this->fp)) {
			try {
				// Failing to empty the incoming buffer prevents the socket from closing
				$this->drain();
				// Close the stream with the '@' to suppress warnings (new PHP versions may emit warnings on fclose)
				@fclose($this->fp);
			} catch (Throwable $t) {
				// Silently fail
			}
		}

		// Force fp to be false
		$this->fp = false;
	}

	/**
	 * Drain the inbound data buffer and discard any pending data
	 * 
	 * The drain method ensures that any pending data in the socket buffer is read and discarded, preventing
	 * blocking issues when closing the socket. Some streams may be non-blocking already, in which case this
	 * method will not alter their state. This method is typically used internally before closing the socket
	 * to ensure no data is left unread. Draining the buffer should not be used for normal data processing,
	 * as it discards all incoming data. Drain should not be used with unix domain sockets as it may
	 * interfere with normal socket operations.
	 *
	 * @return void
	 * @throws Throwable if an error occurs while restoring the stream's blocking state
	 */
	public function drain(): void {
		// If the stream is not a valid resource, there is nothing to drain
		if (!is_resource($this->fp)) {
			return;
		}
		
		// The $fp can be passed in the constructor and may be a non-blocking stream, so capture the original blocking state so it is preserved on restore
		$meta = stream_get_meta_data($this->fp);
		$blocking = $meta['blocked'] ?? true;

		// Set the stream to non-blocking when necessary to drain the buffer without blocking
		if ($blocking && !stream_set_blocking($this->fp, false)) {
			// failed to set the stream to non-blocking mode, cannot drain
			return;
		}

		// Limit the maximum number of bytes to read from the stream while draining so we don't get stuck in an infinite loop
		$max_read_bytes = 1 * 1024 * 1024; // 1 MB

		try {
			// Read the data until the eof is reached or no more data is available
			while (!feof($this->fp)) {
				$bytes = fread($this->fp, 8196);
				if ($bytes === false || $bytes === '') {
					break;
				}

				// Decrement the remaining allowed read bytes and check if the limit has been reached
				$max_read_bytes -= strlen($bytes);

				// Stop draining if the maximum allowed read bytes have been reached
				if ($max_read_bytes <= 0) {
					break;
				}
			}
		} finally {
			// Restore the original blocking state of the stream
			stream_set_blocking($this->fp, $blocking);
		}

	}

	/**
	 * Alias of connected()
	 *
	 * @return bool Returns true when connected or false when not connected
	 */
	public function is_connected(): bool {
		return $this->connected();
	}

	/**
	 * Tests if connected to the FreeSWITCH Event Socket Server
	 *
	 * @return bool Returns true when connected or false when not connected
	 */
	public function connected(): bool {
		if (!is_resource($this->fp)) {
			//not connected to the socket
			return false;
		}
		if (feof($this->fp) === true) {
			//not connected to the socket
			return false;
		}
		//connected to the socket
		return true;
	}

	/**
	 * Sets the current socket resource returning the old
	 *
	 * @param resource|bool $fp Sets the current FreeSWITCH resource
	 *
	 * @return mixed Returns the original resource
	 * @deprecated since version 5.1
	 */
	public function reset_fp($fp = false) {
		$tmp = $this->fp;
		$this->fp = $fp;
		return $tmp;
	}
}

// $esl = event_socket::create('127.0.0.1', 8021, 'ClueCon');
// print($esl->request('api sofia status'));
