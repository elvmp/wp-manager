# WordPress Instances Panel

A PHP-based WordPress management tool to create, manage, and delete multiple WordPress instances.
This project simplifies the process of setting up WordPress installations, allowing users to manage their sites from a central panel.

---

## Features

- **User Authentication**: One day.
- **Instance Management**:
  - Create multiple WordPress instances at once: For now working on index.php but not yet implemented on panel.php.
  - Delete WordPress instances, including their associated databases.
- **Logs Viewer**: View detailed logs for instance creation and management.
- **Customization**: "Easily" extendable for new features.

---

## Getting Started

### Prerequisites

- **PHP**: Ensure PHP 8.1 or later is installed.
- **MySQL**: A working MySQL server.
- **WP-CLI**: Install WP-CLI for WordPress management.
- **Web Server**: Apache, Nginx, or another PHP-compatible web server.

---

### Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-username/wordpress-instances-panel.git
   cd wordpress-instances-panel

2. **Set Up Permissions Ensure the wordpress_instances and .wp-cli directories are writable**
   ```bash
   chmod -R 755 wordpress_instances .wp-cli
3. Configure WP-CLI

4. Update Database Credentials in panel.php
