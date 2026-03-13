<?php

/*
 * user class - represents a user and provides authentication, authorization, and session management
 *
 * This class reflects the database structure of v_users table and provides
 * comprehensive user management functionality including authentication,
 * group membership, permissions, and event handling.
 */

class user implements logout_event, login_event {
	// Database fields from v_users table
	private $user_uuid;
	private $domain_uuid;
	private $contact_uuid;
	private $username;
	private $password_hash;
	private $user_email;
	private $user_status;
	private $api_key;
	private $user_totp_secret;
	private $user_type;
	private $user_enabled;
	private $insert_date;
	private $insert_user;
	private $update_date;
	private $update_user;
	// Additional properties for functionality
	private $database;
	private $permissions = [];
	private $groups = [];
	private $is_logged_in = false;

	/**
	 * Constructor for the user class
	 *
	 * @param database $database Database connection instance
	 * @param string|null $user_uuid Optional user UUID to load specific user
	 */
	public function __construct(database $database, $user_uuid = null) {
		$this->database = $database;

		if (!empty($user_uuid) && is_uuid($user_uuid)) {
			$this->load($user_uuid);
		}
	}

	/**
	 * Load a user from the database by user_uuid
	 *
	 * @param string $user_uuid The UUID of the user to load
	 * @return bool True if user loaded successfully, false otherwise
	 */
	private function load(string $user_uuid): bool {
		if (!is_uuid($user_uuid)) {
			return false;
		}

		$sql = "SELECT * FROM v_users WHERE user_uuid = :user_uuid AND user_enabled = 'true'";
		$parameters['user_uuid'] = $user_uuid;
		$row = $this->database->select($sql, $parameters, 'row');

		if (!empty($row)) {
			$this->user_uuid = $row['user_uuid'];
			$this->domain_uuid = $row['domain_uuid'];
			$this->contact_uuid = $row['contact_uuid'] ?? null;
			$this->username = $row['username'];
			$this->password_hash = $row['password'];
			$this->user_email = $row['user_email'] ?? null;
			$this->user_status = $row['user_status'] ?? null;
			$this->api_key = $row['api_key'] ?? null;
			$this->user_totp_secret = $row['user_totp_secret'] ?? null;
			$this->user_type = $row['user_type'] ?? null;
			$this->user_enabled = $row['user_enabled'] ?? 'true';
			$this->insert_date = $row['insert_date'] ?? null;
			$this->insert_user = $row['insert_user'] ?? null;
			$this->update_date = $row['update_date'] ?? null;
			$this->update_user = $row['update_user'] ?? null;

			// Load groups and permissions
			$this->load_groups();
			$this->load_permissions();

			return true;
		}

		return false;
	}

	/**
	 * Load user groups from the database
	 *
	 * @return void
	 */
	private function load_groups(): void {
		if (empty($this->user_uuid)) {
			return;
		}

		$sql = "SELECT group_name, group_uuid FROM v_user_groups WHERE user_uuid = :user_uuid";
		$parameters['user_uuid'] = $this->user_uuid;
		$rows = $this->database->select($sql, $parameters, 'all');

		$this->groups = [];
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$this->groups[$row['group_name']] = $row['group_uuid'];
			}
		}
	}

	/**
	 * Load user permissions from the database
	 * Includes both direct permissions and permissions inherited from groups
	 *
	 * @return void
	 */
	private function load_permissions(): void {
		if (empty($this->user_uuid)) {
			return;
		}

		// Get direct user permissions
		$sql = "SELECT permission_name FROM v_user_permissions WHERE user_uuid = :user_uuid";
		$parameters['user_uuid'] = $this->user_uuid;
		$rows = $this->database->select($sql, $parameters, 'all');

		$this->permissions = [];
		if (!empty($rows)) {
			foreach ($rows as $row) {
				$this->permissions[$row['permission_name']] = true;
			}
		}

		// Get group permissions
		if (!empty($this->groups)) {
			foreach ($this->groups as $group_name => $group_uuid) {
				$sql = "SELECT permission_name FROM v_group_permissions WHERE group_uuid = :group_uuid";
				$parameters['group_uuid'] = $group_uuid;
				$rows = $this->database->select($sql, $parameters, 'all');

				if (!empty($rows)) {
					foreach ($rows as $row) {
						$this->permissions[$row['permission_name']] = true;
					}
				}
			}
		}
	}

	/**
	 * Authenticate and return a logged-in user object
	 *
	 * This is the primary method for user login. It validates credentials,
	 * creates a user object, and marks the user as logged in.
	 *
	 * @param database $database Database connection instance
	 * @param string $domain_name Domain name for the login
	 * @param string $username Username to authenticate
	 * @param string $password Plain text password to verify
	 * @return user|null Returns user object on success, null on failure
	 */
	public static function login(database $database, string $domain_uuid, string $username, string $password): ?user {
		// Query for user with domain check
		$sql = "SELECT u.user_uuid, u.password
				FROM v_users u
				WHERE u.username = :username
				AND u.domain_uuid = :domain_uuid
				AND u.user_enabled = 'true'
		";

		$parameters = [
			'username' => $username,
			'domain_uuid' => $domain_uuid
		];

		$row = $database->select($sql, $parameters, 'row');

		if (empty($row)) {
			// User not found or disabled
			return null;
		}

		// Verify password using PHP's password_verify
		// This function is secure against timing attacks and handles all password hash types
		if (!password_verify($password, $row['password'])) {
			// Password verification failed
			return null;
		}

		// Check if password needs rehashing (e.g., if algorithm has been upgraded)
		if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
			// TODO: Update password hash in database with new algorithm
			// This would be done in a separate update method to keep login focused
		}

		// Create and load the user object
		$user = new user($database, $row['user_uuid']);

		if (empty($user->user_uuid)) {
			// Failed to load user data
			return null;
		}

		// Mark user as logged in
		$user->is_logged_in = true;

		// Trigger login event hooks
		// This allows other parts of the system to respond to login events
		// Examples of what could be implemented:
		// - Log login attempt to audit log with IP address, timestamp, user agent
		// - Update last_login_date field in database
		// - Initialize user preferences and settings
		// - Load user-specific configurations
		// - Send login notification email/SMS if configured
		// - Initialize session variables
		// - Track concurrent sessions
		// - Check for account expiration or password expiration
		// - Apply domain-specific security policies
		// - Initialize user activity tracking

		// Call the pre-session create event (if needed by implementing systems)
		// This would typically be called by the login controller, not here
		// self::on_login_pre_session_create($settings);

		return $user;
	}

	/**
	 * Check if user has a specific permission
	 *
	 * @param string $permission_name Name of the permission to check
	 * @return bool True if user has permission, false otherwise
	 */
	public function has_permission(string $permission_name): bool {
		return isset($this->permissions[$permission_name]);
	}

	/**
	 * Check if user is a member of a specific group
	 *
	 * @param string $group_name Name of the group to check
	 * @return bool True if user is member, false otherwise
	 */
	public function is_member_of(string $group_name): bool {
		return isset($this->groups[$group_name]);
	}

	/**
	 * Get the login status of the user
	 *
	 * @return bool True if user is logged in, false otherwise
	 */
	public function is_logged_in(): bool {
		return $this->is_logged_in;
	}

	/**
	 * Log out the current user
	 * This clears the logged-in flag and can trigger logout events
	 *
	 * @return void
	 */
	public function logout(): void {
		$this->is_logged_in = false;

		// Logout event would typically be triggered by the logout controller
		// which would call the static event methods defined by the logout_event interface
	}

	// Getters for all database fields

	public function get_user_uuid(): ?string {
		return $this->user_uuid;
	}

	public function get_domain_uuid(): ?string {
		return $this->domain_uuid;
	}

	public function get_contact_uuid(): ?string {
		return $this->contact_uuid;
	}

	public function get_username(): ?string {
		return $this->username;
	}

	public function get_user_email(): ?string {
		return $this->user_email;
	}

	public function get_user_status(): ?string {
		return $this->user_status;
	}

	public function get_api_key(): ?string {
		return $this->api_key;
	}

	public function get_user_totp_secret(): ?string {
		return $this->user_totp_secret;
	}

	public function get_user_type(): ?string {
		return $this->user_type;
	}

	public function get_user_enabled(): string {
		return $this->user_enabled ?? 'true';
	}

	public function get_insert_date(): ?string {
		return $this->insert_date;
	}

	public function get_insert_user(): ?string {
		return $this->insert_user;
	}

	public function get_update_date(): ?string {
		return $this->update_date;
	}

	public function get_update_user(): ?string {
		return $this->update_user;
	}

	/**
	 * Get all groups the user belongs to
	 *
	 * @return array Associative array of group_name => group_uuid
	 */
	public function get_groups(): array {
		return $this->groups;
	}

	/**
	 * Get all permissions the user has
	 *
	 * @return array Array of permission names
	 */
	public function get_permissions(): array {
		return array_keys($this->permissions);
	}

	/**
	 * Compare this user with another user object
	 * Two users are considered equal if they have the same user_uuid
	 *
	 * @param user $other_user The user to compare with
	 * @return bool True if users are the same, false otherwise
	 */
	public function equals(user $other_user): bool {
		return $this->user_uuid === $other_user->get_user_uuid();
	}

	/**
	 * Compare two user objects for sorting by username
	 * Returns negative if $a comes before $b, positive if after, 0 if equal
	 *
	 * @param user $a First user
	 * @param user $b Second user
	 * @return int Comparison result
	 */
	public static function compare_by_username(user $a, user $b): int {
		return strcasecmp($a->get_username() ?? '', $b->get_username() ?? '');
	}

	/**
	 * Compare two user objects for sorting by email
	 * Returns negative if $a comes before $b, positive if after, 0 if equal
	 *
	 * @param user $a First user
	 * @param user $b Second user
	 * @return int Comparison result
	 */
	public static function compare_by_email(user $a, user $b): int {
		return strcasecmp($a->get_user_email() ?? '', $b->get_user_email() ?? '');
	}

	/**
	 * Sort an array of user objects by username
	 *
	 * @param array $users Array of user objects
	 * @param bool $ascending Sort in ascending order (default true)
	 * @return array Sorted array of users
	 */
	public static function sort_by_username(array $users, bool $ascending = true): array {
		usort($users, function ($a, $b) use ($ascending) {
			$result = self::compare_by_username($a, $b);

			return $ascending ? $result : -$result;
		});

		return $users;
	}

	/**
	 * Sort an array of user objects by email
	 *
	 * @param array $users Array of user objects
	 * @param bool $ascending Sort in ascending order (default true)
	 * @return array Sorted array of users
	 */
	public static function sort_by_email(array $users, bool $ascending = true): array {
		usort($users, function ($a, $b) use ($ascending) {
			$result = self::compare_by_email($a, $b);

			return $ascending ? $result : -$result;
		});

		return $users;
	}

	/**
	 * Implementation of logout_event interface
	 * Executed before the session is destroyed
	 *
	 * This method is called by the logout controller before destroying the session.
	 * Implementing this allows various subsystems to perform cleanup operations.
	 *
	 * Implementation examples:
	 * - Log the logout event to audit trail with timestamp and IP address
	 * - Update last_logout_date in the database
	 * - Clean up temporary files or cache associated with the user
	 * - Invalidate API tokens or session tokens
	 * - Send logout notifications if configured
	 * - Clear user-specific temporary data
	 * - Update user activity status (e.g., set to "offline")
	 * - Close any open user sessions in realtime systems
	 * - Clean up any locks held by the user
	 * - Trigger webhook notifications for logout events
	 *
	 * @param settings $settings System settings object
	 * @return void
	 */
	public static function on_logout_pre_session_destroy(settings $settings): void {
	}

	/**
	 * Implementation of logout_event interface
	 * Executed after the session is destroyed
	 *
	 * This method is called by the logout controller after destroying the session.
	 * At this point, session data is no longer available.
	 *
	 * Implementation examples:
	 * - Redirect to login page
	 * - Display logout confirmation message
	 * - Clear cookies
	 * - Perform final cleanup that doesn't require session data
	 * - Send final analytics or tracking events
	 *
	 * @param settings $settings System settings object
	 * @return void
	 */
	public static function on_logout_post_session_destroy(settings $settings): void {
		// Drop the remember me cookie
		setcookie('remember', '', time() - 3600, '/');
	}

	/**
	 * Implementation of login_event interface
	 * Executed before the session is created
	 *
	 * This method is called by the login controller before creating the session.
	 * This is useful for validation, logging, and preparation tasks.
	 *
	 * Implementation examples:
	 * - Check if user account is locked due to too many failed attempts
	 * - Verify that user's account hasn't expired
	 * - Check if user needs to change password (e.g., password expired)
	 * - Verify two-factor authentication if enabled
	 * - Check domain-specific login policies
	 * - Rate limiting for login attempts
	 * - Geo-location checking (block logins from unexpected locations)
	 * - Device fingerprinting and verification
	 * - Check for concurrent session limits
	 * - Verify time-based access restrictions
	 * - Log pre-login audit information
	 * - Check if maintenance mode should block this login
	 * - Verify IP whitelist/blacklist
	 * - Initialize pre-session temporary storage
	 * - Send login attempt notification (security alert)
	 *
	 * The method should throw an exception or return false to prevent login.
	 *
	 * @param settings $settings System settings object
	 * @return void
	 */
	public static function on_login_pre_session_create(settings $settings): void {
		// Implementation would go here
		// This is typically called by login.php in the application

		// Example implementation:
		// $database = database::new();
		// $user_uuid = $_POST['user_uuid'] ?? null;
		//
		// if ($user_uuid) {
		//     // Check failed login attempts
		//     $sql = "SELECT COUNT(*) as attempt_count
		//             FROM v_user_logs
		//             WHERE user_uuid = :user_uuid
		//             AND log_type = 'login_failed'
		//             AND log_date > (NOW() - INTERVAL '15 minutes')";
		//     $parameters = ['user_uuid' => $user_uuid];
		//     $row = $database->select($sql, $parameters, 'row');
		//
		//     if ($row['attempt_count'] >= 5) {
		//         throw new Exception('Account temporarily locked due to too many failed login attempts');
		//     }
		//
		//     // Check for password expiration
		//     $sql = "SELECT password_updated_date
		//             FROM v_users
		//             WHERE user_uuid = :user_uuid";
		//     $row = $database->select($sql, $parameters, 'row');
		//
		//     $password_age = strtotime('now') - strtotime($row['password_updated_date']);
		//     $max_password_age = 90 * 24 * 3600; // 90 days
		//
		//     if ($password_age > $max_password_age) {
		//         $_SESSION['password_change_required'] = true;
		//     }
		// }
	}

	/**
	 * Implementation of login_event interface
	 * Executed after the session is created
	 *
	 * This method is called by the login controller after creating the session.
	 * At this point, the user is authenticated and session is established.
	 *
	 * Implementation examples:
	 * - Log successful login to audit trail with IP, timestamp, user agent
	 * - Update last_login_date in the database
	 * - Initialize user preferences in session
	 * - Load user-specific settings and configurations
	 * - Set up user-specific cache
	 * - Send login notification email/SMS if configured
	 * - Update user activity status (e.g., set to "online")
	 * - Initialize user session analytics
	 * - Set session timeout based on user role
	 * - Load dashboard widgets and preferences
	 * - Initialize realtime connection tokens
	 * - Set language preferences
	 * - Load user's recent activity
	 * - Redirect to appropriate landing page based on user role
	 * - Check for system announcements user needs to see
	 * - Initialize user notification queue
	 * - Set up user-specific rate limits
	 * - Generate session token for API access
	 * - Initialize csrf token
	 * - Set cookie for "remember me" if requested
	 *
	 * @param settings $settings System settings object
	 * @return void
	 */
	public static function on_login_post_session_create(settings $settings): void {
		// access the configuration for session validation
		global $conf;

		// get the database connection from settings
		$database = $settings->database();

		// derive project root: resources/classes → resources → project root
		$project_root = dirname(__DIR__, 2);

		// get the domain and user UUIDs from the established session
		$domain_uuid = $_SESSION['domain_uuid'] ?? '';
		$user_uuid   = $_SESSION['user_uuid'] ?? '';

		if (!is_uuid($domain_uuid) || !is_uuid($user_uuid)) {
			return;
		}

		// Build the session server array to validate the session
		if (!isset($conf['session.validate'])) {
			$conf['session.validate'][] = 'HTTP_USER_AGENT';
		} elseif (!is_array($conf['session.validate'])) {
			$conf['session.validate'] = [$conf['session.validate']];
		}
		$server_array = [];
		foreach ($conf['session.validate'] as $name) {
			$server_array[$name] = $_SERVER[$name] ?? '';
		}

		// Save the user hash to be used in check_auth
		$_SESSION["user_hash"] = hash('sha256', implode($server_array));

		// empty the permissions
		if (isset($_SESSION['permissions'])) {
			unset($_SESSION['permissions']);
		}

		// get the groups assigned to the user
		$group = new groups($database, $domain_uuid, $user_uuid);
		$group->session();

		// get the permissions assigned to the user through the assigned groups
		$permission = new permissions($database, $domain_uuid, $user_uuid);
		$permission->session();

		// get the domains
		if (file_exists($project_root . '/app/domains/resources/domains.php') && !is_cli()) {
			require_once $project_root . '/app/domains/resources/domains.php';
		}

		// get the user settings
		$sql  = "select * from v_user_settings ";
		$sql .= "where domain_uuid = :domain_uuid ";
		$sql .= "and user_uuid = :user_uuid ";
		$sql .= "and user_setting_enabled = true ";
		$parameters = [];
		$parameters['domain_uuid'] = $domain_uuid;
		$parameters['user_uuid']   = $user_uuid;
		$user_settings = $database->select($sql, $parameters, 'all');

		// store user settings in the session when available
		if (is_array($user_settings)) {
			foreach ($user_settings as $row) {
				$name        = $row['user_setting_name'];
				$category    = $row['user_setting_category'];
				$subcategory = $row['user_setting_subcategory'];
				if (isset($row['user_setting_value'])) {
					if (empty($subcategory)) {
						if ($name == "array") {
							$_SESSION[$category][] = $row['user_setting_value'];
						} else {
							$_SESSION[$category][$name] = $row['user_setting_value'];
						}
					} else {
						if ($name == "array") {
							$_SESSION[$category][$subcategory][] = $row['user_setting_value'];
						} else {
							$_SESSION[$category][$subcategory][$name] = $row['user_setting_value'];
						}
					}
				}
			}
		}

		// get the extensions that are assigned to this user
		if (file_exists($project_root . '/app/extensions/app_config.php')) {
			if (isset($_SESSION["user"]) && is_uuid($user_uuid) && is_uuid($domain_uuid) && !isset($_SESSION['user']['extension'])) {
				// initialize the extension array
				$_SESSION['user']['extension'] = [];

				// get the user extension list
				$sql  = "select ";
				$sql .= "e.extension_uuid, ";
				$sql .= "e.extension, ";
				$sql .= "e.number_alias, ";
				$sql .= "e.user_context, ";
				$sql .= "e.outbound_caller_id_name, ";
				$sql .= "e.outbound_caller_id_number, ";
				$sql .= "e.description ";
				$sql .= "from ";
				$sql .= "v_extension_users as u, ";
				$sql .= "v_extensions as e ";
				$sql .= "where ";
				$sql .= "e.domain_uuid = :domain_uuid ";
				$sql .= "and e.extension_uuid = u.extension_uuid ";
				$sql .= "and u.user_uuid = :user_uuid ";
				$sql .= "and e.enabled = 'true' ";
				$sql .= "order by ";
				$sql .= "e.extension asc ";
				$parameters = [];
				$parameters['domain_uuid'] = $domain_uuid;
				$parameters['user_uuid']   = $user_uuid;
				$extensions = $database->select($sql, $parameters, 'all');
				if (!empty($extensions)) {
					foreach ($extensions as $x => $row) {
						// set the destination
						$destination = $row['extension'];
						if (!empty($row['number_alias'])) {
							$destination = $row['number_alias'];
						}

						// build the user array
						$_SESSION['user']['extension'][$x]['user']                     = $row['extension'];
						$_SESSION['user']['extension'][$x]['number_alias']             = $row['number_alias'];
						$_SESSION['user']['extension'][$x]['destination']              = $destination;
						$_SESSION['user']['extension'][$x]['extension_uuid']           = $row['extension_uuid'];
						$_SESSION['user']['extension'][$x]['outbound_caller_id_name']  = $row['outbound_caller_id_name'];
						$_SESSION['user']['extension'][$x]['outbound_caller_id_number'] = $row['outbound_caller_id_number'];
						$_SESSION['user']['extension'][$x]['user_context']             = $row['user_context'];
						$_SESSION['user']['extension'][$x]['description']              = $row['description'];

						// set the context
						$_SESSION['user']['user_context'] = $row["user_context"];
						$_SESSION['user_context']         = $row["user_context"];
					}
				}
			}
		}

		// set the time zone
		if (!isset($_SESSION["time_zone"]["user"])) {
			$_SESSION["time_zone"]["user"] = null;
		}
		if (strlen($_SESSION["time_zone"]["user"] ?? '') === 0) {
			// set the domain time zone as the default time zone
			date_default_timezone_set($settings->get('domain', 'time_zone', 'UTC'));
		} else {
			// set the user defined time zone
			date_default_timezone_set($_SESSION["time_zone"]["user"]);
		}

		// add the username to the session
		$_SESSION['username'] = $_SESSION['user']["username"];
	}

	/**
	 * Parse a username to extract the domain if it is in the format username@domain
	 *
	 * The POST data has priority over the URL data, but if the username is in the
	 * format of username@domain, it will attempt to extract the domain from the
	 * username. If the domain is not provided in the POST data, it will attempt
	 * to get it from the URL. This allows for flexible login formats while
	 * maintaining security by validating the domain format.
	 *
	 * @param url $url URL object to get the domain name if not provided
	 *
	 * @return array The extracted username, password, and domain name
	 * @throws Exception If the username format is invalid
	 */
	public static function from_post_and_url(url $url): array {
		$username = $_POST['username'] ?? $url->get_username() ?? '';
		$password = $_POST['password'] ?? $url->get_password() ?? '';
		$domain_name = $_POST['domain_name'] ?? $url->get_domain_name() ?? '';
		if (strpos($username, '@') !== false) {
			$pieces = explode('@', $username);
			$domain_name = $pieces[1];
			$username = $pieces[0];
		}

		// Validate the domain part to prevent injection or invalid formats
		if (preg_match('/^[a-zA-Z0-9-]+$/', $domain_name) !== 1) {
			// Invalid domain format, do not attempt to use it
			return [];
		}

		// Validate the username to prevent injection or invalid formats
		if (preg_match('/^[a-zA-Z0-9._-]+$/', $username) !== 1) {
			// Invalid username format, do not attempt to use it
			return [];
		}

		// Prohibit empty password
		if (empty($password)) {
			return [];
		}

		return [$username, $password, $domain_name];
	}
}
