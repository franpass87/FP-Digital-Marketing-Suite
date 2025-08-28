# Security Policy

## Reporting Security Vulnerabilities

We take the security of FP Digital Marketing Suite seriously. If you discover a security vulnerability, please follow the responsible disclosure process outlined below.

### How to Report

**DO NOT** create a public GitHub issue for security vulnerabilities. Instead, please report security issues privately by:

1. **Email**: Send details to `franpass87@example.com` with the subject line "SECURITY: [Brief Description]"
2. **Include the following information**:
   - Description of the vulnerability
   - Steps to reproduce the issue
   - Potential impact
   - Any suggested fixes (if you have them)

### Response Timeline

- **Initial Response**: We will acknowledge receipt of your report within 48 hours
- **Assessment**: We will assess the vulnerability within 5 business days
- **Resolution**: Critical vulnerabilities will be addressed within 30 days, others within 90 days
- **Disclosure**: After the fix is released, we will coordinate with you on public disclosure

### Supported Versions

We provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.x.x   | :white_check_mark: |
| < 1.0   | :x:                |

### Security Best Practices

When using FP Digital Marketing Suite:

#### API Key Management
- Store API keys securely using WordPress options with proper encryption
- Never commit API keys to version control
- Use environment variables for sensitive configuration in production
- Regularly rotate API keys according to provider recommendations

#### Data Protection
- All user inputs are sanitized and validated
- Nonce verification is implemented for all forms
- Database queries use prepared statements
- Personal data handling follows GDPR requirements

#### WordPress Security
- Keep WordPress core and all plugins updated
- Use strong passwords and enable two-factor authentication
- Implement proper user role management
- Regular security audits and monitoring

### Security Features

This plugin implements the following security measures:

- **Input Sanitization**: All user inputs are sanitized using WordPress functions
- **CSRF Protection**: Nonce verification for all administrative actions
- **SQL Injection Prevention**: Prepared statements for all database operations
- **XSS Prevention**: Output escaping for all dynamic content
- **Capability Checks**: Proper user permission verification
- **Secure API Storage**: Encrypted storage for sensitive API credentials

### Dependencies Security

We regularly audit our dependencies for known vulnerabilities:

```bash
composer audit
```

### Reporting Non-Security Issues

For non-security related bugs and issues, please use our [GitHub Issues](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues) page.

---

**Note**: This security policy is subject to updates. Please check this document regularly for the latest information.