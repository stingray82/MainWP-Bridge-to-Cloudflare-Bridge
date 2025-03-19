<?php
// Get plugin file from arguments
if ($argc < 2) {
    die("Usage: php update_plugin_headers.php <plugin_file>\n");
}

$plugin_file = $argv[1];

// Ensure the file exists
if (!file_exists($plugin_file)) {
    die("Error: Plugin file not found.\n");
}

// Read the plugin file
$plugin_content = file_get_contents($plugin_file);

// Detect comment style (supports both `/*` and `/**`)
$comment_start = preg_match("/^\s*\/\*{1,2}/m", $plugin_content) ? "/**" : "/**";

// Define standard headers & defaults
$headers = [
    'Plugin Name'       => '',
    'Tested up to'      => '6.7.2',  // Always updated
    'Description'       => 'No description provided.',
    'Requires at least' => '6.5',    // Always updated
    'Requires PHP'      => '7.4',    // Always updated
    'Version'           => '',       // Always updated
    'Author'            => 'reallyusefulplugins.com',
    'Author URI'        => 'https://reallyusefulplugins.com',
    'License'           => 'GPL-2.0-or-later',
    'License URI'       => 'https://www.gnu.org/licenses/gpl-2.0.html',
    'Text Domain'       => '',
    'Website'           => 'https://reallyusefulplugins.com'
];

$plugin_info = [];

// Extract existing values, allowing flexible input formats
foreach ($headers as $key => $default) {
    if (preg_match("/^ \*? $key:\s*(.+)$/mi", $plugin_content, $matches)) {
        $plugin_info[$key] = trim($matches[1]);
    } else {
        $plugin_info[$key] = null; // Mark as missing
    }
}

// Fetch latest WordPress version for "Tested up to"
$latest_wp_version = file_get_contents('https://api.wordpress.org/core/version-check/1.7/');
$latest_wp_version = json_decode($latest_wp_version, true)['offers'][0]['current'] ?? '6.7.2';

// **Ask for version-related fields every time**
function prompt($message, $default) {
    echo "$message [$default]: ";
    $input = trim(fgets(STDIN));
    return $input !== "" ? $input : $default;
}

$plugin_info['Version'] = prompt("Enter new Version", $plugin_info['Version'] ?? '');
$plugin_info['Requires at least'] = prompt("Enter Requires at least", $plugin_info['Requires at least'] ?? '6.5');
$plugin_info['Tested up to'] = prompt("Enter Tested up to (latest: $latest_wp_version)", $latest_wp_version);
$plugin_info['Requires PHP'] = prompt("Enter Requires PHP", $plugin_info['Requires PHP'] ?? '7.4');

// **Only ask for missing fields**
foreach ($headers as $key => $default) {
    if ($plugin_info[$key] === null || $plugin_info[$key] === '') {
        $plugin_info[$key] = prompt("Enter $key", $default);
    }
}

// **Standardize Output**
$max_length = max(array_map('strlen', array_keys($headers))) + 1;
$spacing_format = " * %-{$max_length}s %s"; // **Proper spacing for alignment**
$formatted_header = "$comment_start\n";

// **Ensure fields are in the correct order and properly formatted**
foreach ($headers as $key => $default) {
    if (!empty($plugin_info[$key])) {
        $formatted_header .= sprintf($spacing_format, $key . ":", trim($plugin_info[$key])) . "\n";
    }
}
$formatted_header .= " * */\n";

// **Replace existing header block with the standardized version**
$plugin_content = preg_replace("/\/\*{1,2}.*?\*\//s", $formatted_header, $plugin_content, 1);

// Save the updated plugin file
file_put_contents($plugin_file, $plugin_content);

echo "✅ Plugin headers standardized successfully!\n";
?>
