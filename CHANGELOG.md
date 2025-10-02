# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2025-10-02
### Changed
- Hardened the mailer bootstrap and HTTP fallbacks to support headless environments and queue reliability improvements.
- Updated documentation and packaging workflows to streamline private deployments.

### Fixed
- Hardened encryption fallback handling to avoid insecure defaults. (ISSUE-002)
- Ensured Twilio webhook payloads are encoded correctly before dispatch.
- Normalised Meta Ads currency suffixes to keep reporting metrics consistent.

## [0.1.0] - 2025-09-30
### Added
- Initial release with GA4, GSC, Google Ads, Meta Ads CSV, Clarity CSV, and generic CSV connectors.
- Advanced anomaly detection engine with multi-channel notifications and per-client policies.
- REST QA automation harness, WP-CLI commands, overview dashboard, and PDF report scheduling pipeline.
- GitHub Actions workflow for building distributable ZIP archives and checksums.
