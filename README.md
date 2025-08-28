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

## Data Protection and GDPR Compliance

FP Digital Marketing Suite is designed with data protection and GDPR compliance in mind. This section outlines our approach to handling personal data and ensuring privacy compliance.

### Data Processing Principles

This plugin follows GDPR core principles:

- **Lawfulness, fairness and transparency**: All data processing is clearly documented
- **Purpose limitation**: Data is collected only for specific, legitimate purposes
- **Data minimisation**: Only necessary data is collected and processed
- **Accuracy**: Mechanisms are in place to keep data accurate and up-to-date
- **Storage limitation**: Data is retained only as long as necessary
- **Integrity and confidentiality**: Technical and organisational measures protect data
- **Accountability**: The data controller is responsible for demonstrating compliance

### Data We May Collect

When using this plugin, the following types of data may be processed:

#### Client Data (Custom Post Type)
- **Personal Data**: Client names, email addresses, business information
- **Purpose**: Client relationship management and marketing campaign tracking
- **Legal Basis**: Legitimate business interest or contract performance
- **Retention**: Retained as long as the business relationship exists or as required by law

#### API Keys and Configuration
- **Technical Data**: API keys for third-party marketing services (Google Analytics, Facebook Ads, etc.)
- **Purpose**: Integration with external marketing platforms
- **Legal Basis**: Legitimate business interest
- **Retention**: Until revoked or service discontinued

#### Usage Analytics (Future Implementation)
- **Behavioural Data**: Website analytics, campaign performance metrics
- **Purpose**: Marketing analysis and campaign optimization
- **Legal Basis**: Consent or legitimate business interest
- **Retention**: Configurable retention periods based on data type and legal requirements

### Security Measures

We implement comprehensive security measures:

- **Encryption**: Sensitive data is encrypted at rest and in transit
- **Access Control**: Role-based access with proper capability checks
- **Input Validation**: All inputs are sanitized and validated
- **Audit Logging**: Administrative actions are logged for accountability
- **Regular Updates**: Security patches are applied promptly

### User Rights Under GDPR

This plugin supports the following GDPR rights:

- **Right to Access**: Users can request access to their personal data
- **Right to Rectification**: Users can request correction of inaccurate data
- **Right to Erasure**: Users can request deletion of their personal data
- **Right to Restrict Processing**: Users can limit how their data is processed
- **Right to Data Portability**: Users can request their data in a portable format
- **Right to Object**: Users can object to processing based on legitimate interests

### Implementation Notes for Developers

When extending this plugin:

1. **Data Mapping**: Document what personal data your extensions collect
2. **Legal Basis**: Ensure you have a valid legal basis for processing
3. **Consent Management**: Implement proper consent mechanisms where required
4. **Data Subject Requests**: Provide mechanisms to handle user rights requests
5. **Privacy by Design**: Build privacy considerations into new features from the start

### Third-Party Integrations

This plugin may integrate with third-party services:

- **Google Analytics**: Subject to Google's privacy policy
- **Facebook/Meta Business**: Subject to Meta's privacy policy  
- **Google Ads**: Subject to Google's privacy policy
- **Email Marketing Services**: Subject to respective provider policies

**Important**: When using third-party integrations, ensure you have appropriate data processing agreements and inform users about data sharing in your privacy policy.

### Compliance Recommendations

For GDPR compliance when using this plugin:

1. **Update Privacy Policy**: Include information about this plugin's data processing
2. **Obtain Consent**: Where required, implement proper consent mechanisms
3. **Data Processing Agreement**: If you're a processor, ensure proper agreements are in place
4. **Staff Training**: Train staff on data protection responsibilities
5. **Regular Audits**: Conduct regular privacy impact assessments
6. **Incident Response**: Have procedures for handling data breaches

### Contact for Privacy Matters

For privacy-related questions or to exercise data protection rights:
- **Email**: privacy@yourcompany.com (update with your actual privacy contact)
- **Data Protection Officer**: Include DPO contact if applicable

**Note**: This is a framework for GDPR compliance. Organizations using this plugin should conduct their own privacy impact assessments and may need additional measures based on their specific use cases and jurisdictions.

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