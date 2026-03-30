# User Class Rewrite Documentation

## Overview

The user class has been completely rewritten to provide a comprehensive, secure, and maintainable user management system for FusionPBX. The new implementation reflects the database structure, implements proper security practices, and provides extensive functionality for user authentication, authorization, and session management.

## Key Features

### 1. **Database Field Reflection**
All fields from the `v_users` table are now represented as private properties:
- `user_uuid` - Primary key
- `domain_uuid` - Domain association
- `contact_uuid` - Contact reference
- `username` - Login username
- `password_hash` - Securely stored password
- `user_email` - Email address
- `user_status` - User status
- `api_key` - API authentication key
- `user_totp_secret` - Two-factor authentication secret
- `user_type` - User type/role
- `user_enabled` - Account enabled flag
- `insert_date`, `insert_user`, `update_date`, `update_user` - Audit fields

### 2. **Secure Authentication**

The `login()` static method provides secure authentication:
```php
public static function login(database $database, string $domain_name, string $username, string $password): ?user
```

**Security Features:**
- Uses PHP's `password_verify()` function (secure against timing attacks)
- Checks for `user_enabled` status
- Verifies domain association
- Detects when password hash needs upgrading
- Returns `null` on failure (no exceptions that could leak information)

**Example Usage:**
```php
$database = database::new();
$user = user::login($database, 'example.com', 'john.doe', 'password123');

if ($user !== null) {
    echo "Login successful! Welcome " . $user->get_username();

    // User is now logged in
    if ($user->is_logged_in()) {
        // Proceed with authenticated operations
    }
} else {
    echo "Login failed: Invalid credentials";
}
```

### 3. **Login State Tracking**

The user object tracks whether the user is logged in:
- `is_logged_in()` - Check if user is currently logged in
- `logout()` - Log out the user

```php
if ($user->is_logged_in()) {
    echo "User is authenticated";
}

// Log out
$user->logout();
```

### 4. **Group Membership**

Check if a user belongs to a specific group:
```php
if ($user->is_member_of('superadmin')) {
    echo "User has superadmin privileges";
}

// Get all groups
$groups = $user->get_groups();
// Returns: ['superadmin' => 'group-uuid-1', 'admin' => 'group-uuid-2']
```

### 5. **Permission Checking**

Check if a user has a specific permission:
```php
if ($user->has_permission('user_edit')) {
    // Allow user editing
    echo "User can edit users";Enable user editing functionality
}

if ($user->has_permission('system_settings')) {
    // Show system settings menu
}

// Get all permissions
$permissions = $user->get_permissions();
// Returns: ['user_view', 'user_edit', 'user_delete', ...]
```

**Note:** Permissions include both direct user permissions and permissions inherited from groups.

### 6. **Comparison and Sorting**

#### Compare Users
```php
$user1 = new user($database, $uuid1);
$user2 = new user($database, $uuid2);

if ($user1->equals($user2)) {
    echo "These are the same user";
}
```

#### Sort by Username
```php
$users = [
    new user($database, $uuid1),
    new user($database, $uuid2),
    new user($database, $uuid3)
];

// Sort ascending
$sorted_users = user::sort_by_username($users);

// Sort descending
$sorted_users = user::sort_by_username($users, false);

// Or use comparison function directly
usort($users, ['user', 'compare_by_username']);
```

#### Sort by Email
```php
// Sort ascending
$sorted_users = user::sort_by_email($users);

// Sort descending
$sorted_users = user::sort_by_email($users, false);
```

### 7. **Getters for All Properties**

All database fields have getter methods:
```php
$user_uuid = $user->get_user_uuid();
$domain_uuid = $user->get_domain_uuid();
$username = $user->get_username();
$email = $user->get_user_email();
$status = $user->get_user_status();
$api_key = $user->get_api_key();
$totp_secret = $user->get_user_totp_secret();
$user_type = $user->get_user_type();
$enabled = $user->get_user_enabled();
$contact_uuid = $user->get_contact_uuid();
$insert_date = $user->get_insert_date();
$update_date = $user->get_update_date();
```

### 8. **Event Interface Implementation**

The class implements both `logout_event` and `login_event` interfaces for extensibility.

#### Logout Events

**Pre-Session Destroy:**
```php
public static function on_logout_pre_session_destroy(settings $settings): void
```

Called before session destruction. Ideal for:
- Logging logout to audit trail
- Cleaning up temporary files
- Invalidating API tokens
- Sending notifications
- Updating user status to "offline"
- Closing realtime connections

**Post-Session Destroy:**
```php
public static function on_logout_post_session_destroy(settings $settings): void
```

Called after session destruction. Ideal for:
- Redirecting to login page
- Clearing cookies
- Final cleanup operations
- Analytics tracking

**Example Implementation in logout.php:**
```php
// Before destroying session
user::on_logout_pre_session_destroy($settings);

// Destroy session
session_destroy();

// After destroying session
user::on_logout_post_session_destroy($settings);
```

#### Login Events

**Pre-Session Create:**
```php
public static function on_login_pre_session_create(settings $settings): void
```

Called before session creation. Ideal for:
- Checking account lock status
- Verifying account expiration
- Checking password expiration
- Two-factor authentication verification
- Rate limiting
- Geo-location checking
- Device fingerprinting
- IP whitelist/blacklist checking
- Concurrent session limits
- Time-based access restrictions

**Example Implementation Ideas:**
```php
public static function on_login_pre_session_create(settings $settings): void {
    $database = database::new();
    $user_uuid = $_POST['user_uuid'] ?? null;

    if ($user_uuid) {
        // Check failed login attempts in last 15 minutes
        $sql = "SELECT COUNT(*) as attempt_count
                FROM v_user_logs
                WHERE user_uuid = :user_uuid
                AND log_type = 'login_failed'
                AND log_date > (NOW() - INTERVAL '15 minutes')";
        $parameters = ['user_uuid' => $user_uuid];
        $row = $database->select($sql, $parameters, 'row');

        if ($row['attempt_count'] >= 5) {
            throw new Exception('Account temporarily locked due to too many failed attempts');
        }

        // Check password age
        $sql = "SELECT password_updated_date FROM v_users WHERE user_uuid = :user_uuid";
        $row = $database->select($sql, $parameters, 'row');

        $password_age = strtotime('now') - strtotime($row['password_updated_date']);
        $max_age = 90 * 24 * 3600; // 90 days

        if ($password_age > $max_age) {
            $_SESSION['password_change_required'] = true;
        }
    }
}
```

**Post-Session Create:**
```php
public static function on_login_post_session_create(settings $settings): void
```

Called after session creation. Ideal for:
- Logging successful login
- Updating last_login_date
- Initializing user preferences
- Loading settings into session
- Setting session timeout
- Generating API tokens
- Initializing CSRF protection
- Loading dashboard preferences
- Setting "remember me" cookie
- Sending login notifications
- Updating user status to "online"

**Example Implementation Ideas:**
```php
public static function on_login_post_session_create(settings $settings): void {
    if (isset($_SESSION['user_uuid']) && is_uuid($_SESSION['user_uuid'])) {
        $database = database::new();

        // Log successful login
        $sql = "INSERT INTO v_user_logs (user_log_uuid, user_uuid, log_type, log_date, ip_address, user_agent)
                VALUES (:user_log_uuid, :user_uuid, 'login_success', NOW(), :ip_address, :user_agent)";
        $parameters = [
            'user_log_uuid' => uuid(),
            'user_uuid' => $_SESSION['user_uuid'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        $database->execute($sql, $parameters);

        // Update last login
        $sql = "UPDATE v_users SET last_login_date = NOW() WHERE user_uuid = :user_uuid";
        $parameters = ['user_uuid' => $_SESSION['user_uuid']];
        $database->execute($sql, $parameters);

        // Load user data into session
        $user = new user($database, $_SESSION['user_uuid']);
        $_SESSION['username'] = $user->get_username();
        $_SESSION['user_email'] = $user->get_user_email();
        $_SESSION['groups'] = $user->get_groups();
        $_SESSION['permissions'] = $user->get_permissions();
    }
}
```

## Migration from Old Class

### Old Code
```php
// Old constructor
$user = new user($database, $user_uuid);
$user->set_details();

// Old authentication
if ($user->authenticate($password)) {
    $user->authenticated();
}

// Old permission check
if ($user->has_permission('user_edit')) {
    // ...
}

// Old group check
if ($user->member_of('admin')) {
    // ...
}
```

### New Code
```php
// New login (creates and authenticates)
$user = user::login($database, 'example.com', 'username', 'password');

if ($user !== null && $user->is_logged_in()) {
    // User authenticated

    // Permission check (same)
    if ($user->has_permission('user_edit')) {
        // ...
    }

    // Group check (renamed)
    if ($user->is_member_of('admin')) {
        // ...
    }

    // Access user data
    echo $user->get_username();
    echo $user->get_user_email();
}
```

## Security Improvements

1. **Password Verification:** Uses `password_verify()` which is timing-attack safe
2. **Password Hash Upgrading:** Detects and allows for password algorithm upgrades
3. **No Direct Password Access:** Password hash is private and cannot be retrieved
4. **Null Return on Failure:** Login returns `null` instead of throwing exceptions that could leak information
5. **Enabled Check:** Always verifies user is enabled before allowing login
6. **Domain Isolation:** Verifies domain association during login

## Best Practices

1. **Always check login result:**
   ```php
   $user = user::login($database, $domain, $username, $password);
   if ($user === null) {
       // Handle failed login
       return;
   }
   ```

2. **Check logged-in status:**
   ```php
   if ($user->is_logged_in()) {
       // Proceed with authenticated operations
   }
   ```

3. **Use permission-based access control:**
   ```php
   if ($user->has_permission('feature_access')) {
       // Show feature
   }
   ```

4. **Use group-based logic sparingly:**
   ```php
   // Prefer permissions over groups
   if ($user->has_permission('admin_access')) { // Good
   if ($user->is_member_of('admin')) {          // Use with caution
   ```

5. **Implement event handlers in application:**
   ```php
   // In login.php
   user::on_login_pre_session_create($settings);
   // ... create session ...
   user::on_login_post_session_create($settings);

   // In logout.php
   user::on_logout_pre_session_destroy($settings);
   // ... destroy session ...
   user::on_logout_post_session_destroy($settings);
   ```

## Complete Example

```php
<?php
// Login page example
require_once('resources/require.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = database::new();
    $domain_name = $_POST['domain'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Pre-login checks
    user::on_login_pre_session_create($settings);

    // Attempt login
    $user = user::login($database, $domain_name, $username, $password);

    if ($user !== null && $user->is_logged_in()) {
        // Login successful - create session
        session_start();
        $_SESSION['user_uuid'] = $user->get_user_uuid();
        $_SESSION['domain_uuid'] = $user->get_domain_uuid();
        $_SESSION['username'] = $user->get_username();

        // Post-login setup
        user::on_login_post_session_create($settings);

        // Redirect to dashboard
        header('Location: /');
        exit;
    } else {
        // Login failed
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <?php if (isset($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="domain" placeholder="Domain" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</body>
</html>
```

## Conclusion

The rewritten user class provides a robust, secure, and extensible foundation for user management in FusionPBX. It follows modern PHP practices, implements proper security measures, and provides comprehensive functionality for authentication, authorization, and session management.
