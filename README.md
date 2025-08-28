# FP-Digital-Marketing-Suite

A comprehensive digital marketing toolkit built with PHP, following WordPress coding standards and best practices.

## Features

- WordPress Coding Standards compliance
- Static analysis with PHPStan
- Automated CI/CD pipeline
- Security vulnerability scanning
- **Settings Page**: Admin interface for plugin configuration
- **Cliente Management**: Custom post type for client management

## Requirements

- PHP 7.4 or higher
- Composer 2.x

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/franpass87/FP-Digital-Marketing-Suite.git
   cd FP-Digital-Marketing-Suite
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

## Usage

### Settings Page

After plugin activation, the settings page is available in the WordPress admin:

1. **Access Settings**: Navigate to `Settings` > `FP Digital Marketing` in your WordPress admin
2. **General Settings**: Configure basic plugin options including the demo field for testing
3. **API Keys Section**: Placeholder section for future API integrations (Google Analytics, Facebook/Meta Business, Google Ads, etc.)

**For Developers:**
- Settings are saved using WordPress Settings API with proper nonce protection
- All inputs are sanitized and validated before saving
- Settings can be accessed programmatically via the `Settings` class methods

**For Users:**
- The settings page provides a user-friendly interface for plugin configuration
- All settings are automatically saved when you click "Save Settings"
- Input validation ensures data integrity

## Development Tooling

This project uses several tools to maintain code quality and consistency:

### Code Standards (PHPCS)

We follow the WordPress Coding Standards. The configuration is defined in `.phpcs.xml`.

**Run code sniffer:**
```bash
composer run phpcs
```

**Auto-fix code standards issues:**
```bash
composer run phpcbf
```

### Static Analysis (PHPStan)

PHPStan is configured at level 5 for thorough static analysis. Configuration is in `phpstan.neon`.

**Run static analysis:**
```bash
composer run phpstan
```

### Continuous Integration

Our CI pipeline runs automatically on:
- Push to `main` or `develop` branches
- Pull requests to `main` or `develop` branches

The pipeline includes:
- **Code Quality Checks**: PHPCS and PHPStan across multiple PHP versions (7.4, 8.0, 8.1, 8.2)
- **Security Scanning**: Composer audit for dependency vulnerabilities
- **Validation**: Composer configuration validation

### Available Commands

```bash
# Install dependencies
composer install

# Run code sniffer
composer run phpcs

# Fix code standards automatically
composer run phpcbf

# Run static analysis
composer run phpstan

# Check for security vulnerabilities
composer audit
```

## Project Structure

```
├── .github/
│   └── workflows/
│       └── ci.yml          # CI/CD pipeline configuration
├── src/                    # Source code (to be created)
├── tests/                  # Unit tests (to be created)
├── .gitignore             # Git ignore rules
├── .phpcs.xml             # PHPCS configuration
├── phpstan.neon           # PHPStan configuration
├── phpstan-bootstrap.php  # PHPStan WordPress bootstrap
├── composer.json          # PHP dependencies and scripts
└── README.md              # This file
```

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Make your changes following the coding standards
4. Run the quality checks: `composer run phpcs && composer run phpstan`
5. Commit your changes: `git commit -am 'Add some feature'`
6. Push to the branch: `git push origin feature/your-feature-name`
7. Submit a pull request

All pull requests must pass the CI pipeline before being merged.

## License

This project is licensed under the MIT License.