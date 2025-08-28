# FP-Digital-Marketing-Suite

A comprehensive Digital Marketing Suite for WordPress, providing tools and features for modern digital marketing workflows.

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- Composer
- Node.js (optional, for frontend development)

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/franpass87/FP-Digital-Marketing-Suite.git
   cd FP-Digital-Marketing-Suite
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

### Development Tools

This project uses several tools to maintain code quality and consistency:

#### Code Standards (PHPCS)

We follow WordPress Coding Standards. To check your code:

```bash
# Check coding standards
composer run phpcs

# Automatically fix fixable issues
composer run phpcbf
```

#### Static Analysis (PHPStan)

PHPStan helps catch potential bugs and improve code quality:

```bash
# Run static analysis
composer run phpstan
```

#### Continuous Integration

The project uses GitHub Actions for automated testing:

- **Code Quality**: Runs PHPCS and PHPStan on multiple PHP versions (7.4, 8.0, 8.1, 8.2)
- **Security**: Checks for known security vulnerabilities in dependencies

All pushes and pull requests to `main` and `develop` branches trigger the CI pipeline.

### Configuration Files

- `.phpcs.xml` - PHP CodeSniffer configuration with WordPress standards
- `phpstan.neon` - PHPStan static analysis configuration
- `.github/workflows/ci.yml` - GitHub Actions CI pipeline
- `composer.json` - PHP dependencies and scripts

### Development Workflow

Before committing your changes:

1. **Check code standards:**
   ```bash
   composer run phpcs
   ```

2. **Fix automatically fixable issues:**
   ```bash
   composer run phpcbf
   ```

3. **Run static analysis:**
   ```bash
   composer run phpstan
   ```

4. **Ensure all checks pass before pushing** - the CI pipeline will reject PRs that don't meet quality standards.

### Contributing

1. Fork the repository
2. Create a feature branch from `develop`
3. Make your changes following the coding standards
4. Follow the [Development Workflow](#development-workflow) to validate your changes
5. Submit a pull request

The CI pipeline will automatically run on your PR to ensure code quality standards are met.