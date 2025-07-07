# Rowing Chat Automation Plugin

A WordPress plugin that automates chat interactions for rowing-related websites, providing intelligent responses and user engagement features.

## Features

- **Automated Chat Responses**: Intelligent responses to common rowing-related queries
- **User Engagement**: Automated welcome messages and interaction prompts
- **Admin Dashboard**: Comprehensive settings panel for configuration
- **Chat History**: Track and manage chat interactions
- **Customizable Templates**: Flexible message templates for different scenarios
- **AJAX Integration**: Seamless real-time chat functionality
- **Security**: Nonce verification and sanitized inputs

## Installation

1. Download the plugin files
2. Upload to your WordPress plugins directory: `/wp-content/plugins/rowing-chat-automation/`
3. Activate the plugin through the WordPress admin panel
4. Configure settings in the admin dashboard

## Configuration

### Admin Settings

Access the plugin settings through **WordPress Admin → Rowing Chat Automation**

#### General Settings
- Enable/disable chat automation
- Configure response delays
- Set welcome messages
- Customize chat appearance

#### Response Templates
- Create custom response templates
- Set trigger keywords
- Configure automated responses for common queries

#### Advanced Settings
- AJAX configuration
- Security settings
- Debug mode options

## File Structure

```
rowing-chat-automation/
├── admin/
│   ├── css/
│   │   └── admin-styles.css
│   ├── js/
│   │   └── admin-script.js
│   └── partials/
│       ├── admin-display.php
│       └── settings-form.php
├── includes/
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-rowing-chat-automation.php
│   └── class-admin.php
├── public/
│   ├── css/
│   │   └── public-styles.css
│   ├── js/
│   │   └── public-script.js
│   └── partials/
│       └── public-display.php
├── rowing-chat-automation.php
└── README.md
```

## Usage Examples

### Basic Chat Integration

The plugin automatically integrates with your WordPress site. Once activated, it will:

1. Display chat interface on configured pages
2. Respond to user messages based on your templates
3. Log interactions for review

### Customizing Responses

Configure automated responses in the admin panel:

```php
// Example response template
$response_templates = array(
    'training' => 'Here are some great rowing training tips...',
    'equipment' => 'Let me help you with rowing equipment information...',
    'technique' => 'Proper rowing technique is essential...'
);
```

### AJAX Integration

The plugin uses AJAX for real-time interactions:

```javascript
// Front-end chat submission
jQuery('#chat-form').on('submit', function(e) {
    e.preventDefault();
    // AJAX call to process chat message
    jQuery.post(ajax_object.ajax_url, {
        action: 'process_chat_message',
        message: jQuery('#chat-input').val(),
        nonce: ajax_object.nonce
    });
});
```

## Development

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Hooks and Filters

The plugin provides several hooks for customization:

#### Actions
- `rowing_chat_before_response` - Fired before generating responses
- `rowing_chat_after_response` - Fired after generating responses
- `rowing_chat_message_logged` - Fired when a message is logged

#### Filters
- `rowing_chat_response_templates` - Modify response templates
- `rowing_chat_admin_capabilities` - Modify admin capabilities
- `rowing_chat_public_settings` - Modify public settings

### Database Tables

The plugin creates the following database tables:

- `wp_rowing_chat_messages` - Stores chat messages and responses
- `wp_rowing_chat_settings` - Stores plugin configuration

### Security Features

- Nonce verification for all AJAX requests
- Input sanitization and validation
- Capability checks for admin functions
- SQL injection prevention

## API Reference

### Main Classes

#### `Rowing_Chat_Automation`
Main plugin class that handles initialization and coordination.

#### `Rowing_Chat_Admin`
Handles admin interface and settings management.

#### `Rowing_Chat_Public`
Manages public-facing functionality and chat interface.

### Key Methods

```php
// Process chat messages
Rowing_Chat_Automation::process_message($message, $user_id);

// Get response templates
Rowing_Chat_Admin::get_response_templates();

// Log chat interaction
Rowing_Chat_Automation::log_interaction($message, $response, $user_id);
```

## Troubleshooting

### Common Issues

1. **Chat not appearing**: Check if plugin is activated and configured
2. **AJAX errors**: Verify nonce and AJAX URL configuration
3. **Database errors**: Ensure proper WordPress database permissions

### Debug Mode

Enable debug mode in the admin settings to:
- View detailed error logs
- Track AJAX requests
- Monitor database queries

## Support

For support and feature requests, please contact the plugin developer or check the documentation.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Basic chat automation functionality
- Admin settings panel
- AJAX integration
- Security features