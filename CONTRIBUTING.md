# Contributing to FP Digital Marketing Suite

Thank you for your interest in contributing to FP Digital Marketing Suite! This document provides guidelines for contributing to this open-source project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Contributing Process](#contributing-process)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Pull Request Process](#pull-request-process)
- [Issue Reporting](#issue-reporting)
- [Security Vulnerabilities](#security-vulnerabilities)

## Code of Conduct

This project adheres to a code of conduct that we expect all contributors to follow:

- **Be respectful**: Treat all contributors with respect and professionalism
- **Be inclusive**: Welcome contributors from all backgrounds and skill levels
- **Be collaborative**: Work together to improve the project
- **Be constructive**: Provide helpful feedback and suggestions
- **Be patient**: Remember that everyone is learning and improving

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- Composer 2.x
- WordPress 5.0 or higher (for testing)
- Git
- Basic understanding of WordPress plugin development

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/FP-Digital-Marketing-Suite.git
   cd FP-Digital-Marketing-Suite
   ```

## Development Setup

### Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install development tools
composer install --dev
```

### Development Tools

We use several tools to maintain code quality:

```bash
# Run code sniffer (check coding standards)
composer run phpcs

# Fix coding standards automatically
composer run phpcbf

# Run static analysis
composer run phpstan

# Run tests
composer run test

# Run tests with coverage
composer run test-coverage
```

## Contributing Process

### 1. Choose an Issue

- Look for issues labeled `good first issue` for beginners
- Check for issues labeled `help wanted` for community contributions
- Comment on the issue to let others know you're working on it

### 2. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b bugfix/issue-number-description
```

### 3. Development Workflow

1. **Make small, focused commits**
2. **Write descriptive commit messages**
3. **Follow coding standards**
4. **Add/update tests as needed**
5. **Update documentation if necessary**

### 4. Test Your Changes

```bash
# Run all quality checks
composer run phpcs
composer run phpstan
composer run test

# Test in a WordPress environment
# Set up a local WordPress installation and test your changes
```

## Coding Standards

### PHP Standards

We follow the **WordPress Coding Standards** with some additions:

- Use **PHP 7.4+ features** (type hints, return types, etc.)
- Follow **PSR-4 autoloading** standards
- Use **meaningful variable and function names**
- Write **comprehensive docblocks**
- Implement **proper error handling**

### Example Code Style

```php
<?php
namespace FP\DigitalMarketing\Analytics;

/**
 * Google Analytics 4 integration handler.
 *
 * @package FP\DigitalMarketing\Analytics
 * @since 1.0.0
 */
class GoogleAnalytics4 {
    
    /**
     * Retrieve analytics data for a specific period.
     *
     * @param int    $client_id    The client ID.
     * @param string $start_date   Start date in Y-m-d format.
     * @param string $end_date     End date in Y-m-d format.
     * @param array  $metrics      Array of metrics to retrieve.
     * @return array|WP_Error      Analytics data or error.
     */
    public function get_analytics_data( int $client_id, string $start_date, string $end_date, array $metrics ): array {
        // Implementation...
    }
}
```

### WordPress-Specific Guidelines

- Use WordPress functions for database operations (`$wpdb`, `get_option()`, etc.)
- Sanitize all inputs using WordPress sanitization functions
- Escape all outputs using WordPress escaping functions
- Use WordPress nonces for form security
- Follow WordPress action/filter patterns

## Testing Guidelines

### Unit Tests

- Write tests for all new functionality
- Use PHPUnit for testing
- Aim for high test coverage
- Test both success and failure scenarios

### Test Structure

```php
<?php
use PHPUnit\Framework\TestCase;

class GoogleAnalytics4Test extends TestCase {
    
    public function test_get_analytics_data_with_valid_input(): void {
        // Arrange
        $ga4 = new GoogleAnalytics4();
        
        // Act
        $result = $ga4->get_analytics_data(1, '2024-01-01', '2024-01-31', ['sessions']);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sessions', $result);
    }
}
```

### Integration Tests

- Test WordPress integration functionality
- Use WordPress test framework when needed
- Test database operations
- Test admin interface functionality

## Pull Request Process

### Before Submitting

1. **Rebase your branch** on the latest main branch
2. **Run all quality checks** and ensure they pass
3. **Update documentation** if you've changed functionality
4. **Add/update tests** for your changes
5. **Test in a WordPress environment**

### Pull Request Template

When creating a pull request, include:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Manual testing completed
- [ ] Code quality checks pass

## Checklist
- [ ] Code follows project coding standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] Tests added/updated
```

### Review Process

1. **Automated checks** must pass (CI/CD pipeline)
2. **Code review** by maintainers
3. **Testing** by reviewers if needed
4. **Approval** and merge by maintainers

## Issue Reporting

### Bug Reports

When reporting bugs, include:

- **WordPress version**
- **PHP version**
- **Plugin version**
- **Steps to reproduce**
- **Expected behavior**
- **Actual behavior**
- **Error messages** (if any)
- **Screenshots** (if helpful)

### Feature Requests

For feature requests, include:

- **Problem description**: What problem does this solve?
- **Proposed solution**: How should it work?
- **Alternatives considered**: What other solutions were considered?
- **Use cases**: Who would benefit from this feature?

## Security Vulnerabilities

**DO NOT** report security vulnerabilities through public GitHub issues.

Instead, please report them privately:
- **Email**: franpass87@example.com
- **Subject**: SECURITY: [Brief Description]

See our [Security Policy](SECURITY.md) for more details.

## Development Roadmap

### Current Priorities

1. **Performance optimization**
2. **Enhanced analytics integrations**
3. **Improved user experience**
4. **Mobile responsiveness**
5. **API extensions**

### Areas for Contribution

- **New integrations**: Social media platforms, email marketing services
- **Performance improvements**: Caching, database optimization
- **User interface**: Admin panel enhancements, mobile optimization
- **Documentation**: Tutorials, API documentation, examples
- **Testing**: Test coverage improvement, integration tests

## Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General questions and community discussion
- **Email**: franpass87@example.com for direct contact

### Recognition

Contributors are recognized in:
- **CHANGELOG.md**: Major contributions listed in release notes
- **README.md**: Contributors section
- **GitHub**: Contributor badge and statistics

## Development Tips

### Useful Commands

```bash
# Quick development check
composer run phpcs && composer run phpstan

# Full quality assurance
composer run phpcs && composer run phpstan && composer run test

# Fix common issues
composer run phpcbf

# Generate test coverage report
composer run test-coverage
```

### IDE Setup

Recommended extensions for development:

- **PHP Intelephense** or **PHP CodeSniffer**
- **PHPStan** integration
- **WordPress** snippet extensions
- **Git** integration

### Debugging

- Use WordPress debugging constants in `wp-config.php`
- Enable error logging for development
- Use the plugin's built-in logging system
- Test with WordPress debug bar plugin

## Questions?

If you have questions about contributing:

1. Check existing [GitHub Issues](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues)
2. Create a new issue with the `question` label
3. Email the maintainers: franpass87@example.com

Thank you for contributing to FP Digital Marketing Suite! 🚀