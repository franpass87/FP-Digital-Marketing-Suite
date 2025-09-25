# Changelog

All notable changes to the FP Digital Marketing Suite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-04-30

### Added
- **Reporting Workspace** with scheduled PDF/CSV exports, dashboard sharing and trend library.
- **Alert Center** supporting anomaly detection, SLA tracking and acknowledgement audit trails.
- **Documentation Refresh** aligning README, WordPress readme and knowledge base articles with historical release notes.
- **Author Branding Update** to Francesco Passeri with new website and support email.

### Improved
- Extended metrics query API examples for custom tooling and integrations.
- Clarified onboarding guidance and default automation toggles for new installations.

### Fixed
- Harmonized version references across plugin headers, constants and distribution artifacts.

## [1.0.1] - 2024-03-12

### Added
- **Metrics Aggregation Pipeline** with batched ingestion and cross-source normalization rules.
- **Onboarding Wizard** providing setup checklists, contextual help cards and capability recommendations.

### Changed
- Optimized admin UI rendering, list tables and dashboard widgets for large client portfolios.
- Introduced caching layer for computed KPIs to reduce external API calls and improve load times.

### Fixed
- Hardened background processing with retry logic when external data sources temporarily fail.
- Addressed edge cases in conversion tracking when multiple webhooks fire simultaneously.

## [1.0.0] - 2024-01-18

### Added
- **Initial Release** - Complete FP Digital Marketing Suite platform
- **Client Management System** with custom Cliente post type
- **Analytics Dashboard** with comprehensive data visualization
- **Google Analytics 4 Integration** with advanced metrics tracking
- **Google Ads Integration** with campaign performance monitoring
- **Google Search Console Integration** with organic search analytics
- **Microsoft Clarity Integration** for user behavior insights
- **SEO Tools** including metadata management, XML sitemaps, and schema markup
- **Marketing Automation** with UTM tracking and conversion events
- **Performance Optimization** with Core Web Vitals monitoring
- **Security Features** with GDPR compliance framework
- **Alert System** with anomaly detection and email notifications
- **Audience Segmentation** for targeted marketing campaigns
- **Caching System** for optimized performance
- **Translation Support** for English and Italian languages
- **Comprehensive Test Suite** with 39 test files
- **CI/CD Pipeline** with automated code quality checks
- **Deployment Documentation** with step-by-step guides
- **Post-Deployment Verification** script for production validation

### Security
- Input sanitization and validation on all user inputs
- Nonce protection for form submissions
- Capability-based access control
- GDPR compliance framework implementation
- Secure data storage and handling practices

### Technical Features
- WordPress Coding Standards compliance
- PHPStan static analysis integration
- Composer dependency management
- Modern PHP 7.4+ with type declarations
- Efficient database queries with caching
- Optimized asset loading
- Memory usage optimization
- Performance monitoring tools

### Integrations
- Google Analytics 4 API integration
- Google Ads API integration
- Google Search Console API integration
- Microsoft Clarity tracking integration
- Core Web Vitals monitoring
- SMTP email notification system

### Developer Features
- Comprehensive autoloader with error handling
- Extensible architecture with hooks and filters
- Well-documented API endpoints
- Test-driven development approach
- Code quality tools integration
- Continuous integration pipeline

---

## Version Support

- **Current Version**: 1.1.0
- **Minimum WordPress**: 5.0
- **Minimum PHP**: 7.4
- **Tested up to WordPress**: 6.4
- **Tested PHP versions**: 7.4, 8.0, 8.1, 8.2

## Upgrade Path

### From 1.0.1 to 1.1.0
Review the new alerting policies and reporting schedules introduced in 1.1.0. Existing automation rules remain intact, but administrators should validate thresholds and notification recipients after updating.

### From 1.0.0 to 1.0.1
The 1.0.1 performance update installs automatically. Validate caching settings and cron schedules if they were previously customized.

### From Pre-Release to 1.0.0
This is the initial stable release. Clean installation recommended.

### Future Upgrades
- Database migrations will be handled automatically
- Settings and data will be preserved during updates
- Backup recommendations will be provided for major versions

## Support

For technical support and feature requests:
- GitHub Issues: [https://github.com/franpass87/FP-Digital-Marketing-Suite/issues](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues)
- Website: [https://francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- Documentation: See DEPLOYMENT_GUIDE.md and readme.txt
- Verification: Run `verify-deployment.php` after installation
