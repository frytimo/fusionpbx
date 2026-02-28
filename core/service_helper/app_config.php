<?php

// application details
$apps[$x]['name'] = "Service Helper";
$apps[$x]['uuid'] = "generate-uuid-here";  // Replace with actual UUID
$apps[$x]['category'] = "System";
$apps[$x]['subcategory'] = "";
$apps[$x]['version'] = "1.0";
$apps[$x]['license'] = "Mozilla Public License 1.1";
$apps[$x]['url'] = "https://www.fusionpbx.com";
$apps[$x]['description']['en-us'] = "Web interface for managing service restarts.";
$apps[$x]['description']['en-gb'] = "Web interface for managing service restarts.";

// permissions
$y = 0;
$apps[$x]['permissions'][$y]['name'] = 'service_helper_view';
$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
$y++;
$apps[$x]['permissions'][$y]['name'] = 'service_helper_add';
$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';

// menu
$y = 0;
$apps[$x]['menu'][$y]['title']['en-us'] = 'Service Helper';
$apps[$x]['menu'][$y]['title']['en-gb'] = 'Service Helper';
$apps[$x]['menu'][$y]['uuid'] = "generate-uuid-here";
$apps[$x]['menu'][$y]['parent_uuid'] = "594d99c5-6128-9c88-ca35-4b33392cec0f";  // System menu
$apps[$x]['menu'][$y]['category'] = "internal";
$apps[$x]['menu'][$y]['path'] = "/core/service_helper/service_helper.php";
$apps[$x]['menu'][$y]['groups'][] = 'superadmin';

// database table
$table_index = 0;
$apps[$x]['db'][$table_index]['table']['name'] = 'v_service_restarts';
$apps[$x]['db'][$table_index]['table']['parent'] = '';
$field_index = 0;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'service_restart_uuid';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'uuid';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'text';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'char(36)';
$apps[$x]['db'][$table_index]['fields'][$field_index]['key']['type'] = 'primary';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'service_name';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type'] = 'text';
$apps[$x]['db'][$table_index]['fields'][$field_index]['search_by'] = '1';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'restart_requested';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'datetime';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'restart_completed';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'datetime';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = 'processed_hostname';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type'] = 'text';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "Hostname of the server that processed the restart";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "insert_date";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'datetime';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "insert_user";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = "uuid";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = "text";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = "char(36)";
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "update_date";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = 'timestamptz';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = 'date';
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = 'datetime';
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";
$field_index++;
$apps[$x]['db'][$table_index]['fields'][$field_index]['name'] = "update_user";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['pgsql'] = "uuid";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['sqlite'] = "text";
$apps[$x]['db'][$table_index]['fields'][$field_index]['type']['mysql'] = "char(36)";
$apps[$x]['db'][$table_index]['fields'][$field_index]['description']['en-us'] = "";

// default settings
$y = 0;
$apps[$x]['default_settings'][$y]['default_setting_uuid'] = "generate-uuid-here";  // Replace
$apps[$x]['default_settings'][$y]['default_setting_category'] = "service_helper";
$apps[$x]['default_settings'][$y]['default_setting_subcategory'] = "cron_interval";
$apps[$x]['default_settings'][$y]['default_setting_name'] = "text";
$apps[$x]['default_settings'][$y]['default_setting_value'] = "* * * * *";
$apps[$x]['default_settings'][$y]['default_setting_enabled'] = "true";
$apps[$x]['default_settings'][$y]['default_setting_description'] = "Cron interval for service helper (default every minute).";

