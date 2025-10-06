## 0.1.1 (2025-10-06)


### Bug Fixes

* **security:** harden encryption fallback (ISSUE-002) ([9903016](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/99030166e3155185914267e5037a8ae6258bb177))


### Features

* Add Connection Wizard Quick Start Guide ([17b09f7](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/17b09f7dc90e8bf39b2dd2f26ff7151655b83687))
* Add documentation for background processing and desktop app ([8abe7c6](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/8abe7c6ccacc09fe617735f638167b8e3d4653a2))
* Add documentation for connector connection simplification ([cc889f0](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/cc889f0a2c459f8e78bc20852b47270ee70e6558))
* Add new connection wizard steps for CSV, Clarity, Google Ads, and Meta Ads ([bdf1563](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/bdf15631a6e3baaf683f94c4d6358cbdaf2f9095))
* Add plugin functionality verification report ([cc388eb](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/cc388eb228aebd36cb9237aee819fa50b5113900))
* Implement connection wizard integration ([b91e586](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/b91e586c23c82700defc4c2cd9dc2a4e0e84af9b))
* Implement ConnectorException for structured error handling ([#23](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues/23)) ([1bedcd6](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/1bedcd676e40f29fce00738ec10d72e9c740dee5))
* Implement standalone scheduler and commands ([dbab37a](https://github.com/franpass87/FP-Digital-Marketing-Suite/commit/dbab37ac95ea932d8fd4cbf233662135814d2c65))



# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Added
- Client logos can be selected from the Clients page and are rendered automatically across generated reports.
- Template creation supports curated presets powered by reusable blueprints and drafts.
- GA4 and Google Search Console connectors accept credentials from secure `wp-config.php` constants in addition to JSON uploads.

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
