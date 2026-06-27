# SaaS Profile Dashboard

A modern WordPress plugin that transforms the standard user profile page into a beautiful, feature-rich SaaS-style dashboard.

## Features

- **Modern Dashboard Interface**: Beautiful, responsive profile dashboard with customizable layouts
- **Custom Tabs System**: Create unlimited custom tabs with shortcodes or external URLs
- **WooCommerce Integration**: Full integration with order history and statistics
- **User Currency System**: Users can view prices in their preferred currency (30+ currencies)
- **Profile Picture Upload**: Users can upload custom profile pictures
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices
- **Color Customization**: Full color control with preset themes
- **Two Layout Options**: Sidebar or top menu navigation
- **Quick Actions**: Customizable quick action buttons on dashboard

## Installation

1. Upload the `saas-profile-dashboard` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Profile Dashboard > Setup & Settings**
4. Add the shortcode `[saas_profile_dashboard]` to any page
5. Configure settings in **Profile Dashboard > General Settings**

## Usage

### Main Shortcode
```
[saas_profile_dashboard]
```
Add this to any page to display the full profile dashboard.

### Content Shortcodes
```
[spd_dashboard]      - Dashboard widgets
[spd_profile_form]   - Profile editing form
[spd_user_settings]  - User settings
[spd_woo_orders]     - WooCommerce orders (requires WooCommerce)
```

## Configuration

### Layout Options
- **Sidebar Layout**: Traditional sidebar with content area
- **Top Menu Layout**: Horizontal menu with full-width content

### Currency System
- Users can select their preferred currency from 30+ options
- Prices display in user's currency across the site
- Payments always process in base WooCommerce currency
- Exchange rates update daily automatically

### Color Customization
- Primary Color: Buttons, links, active states
- Secondary Color: Sidebar, navigation
- Accent Color: Error states, highlights
- Background Color: Main page background
- Card Background: Content area background

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- WooCommerce (optional, for order integration)

## Support

For support, please visit the plugin support forum or contact the developer.

## Changelog

### 1.0.0
- Initial release
- Custom tabs system
- WooCommerce integration
- User currency selection
- Profile picture upload
- Responsive design
- Color customization
