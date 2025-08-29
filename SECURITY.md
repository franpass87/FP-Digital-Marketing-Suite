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

### Security Features

This plugin implements comprehensive security measures:

#### Enhanced Security Features (v1.0+)
- **API Key Encryption**: All sensitive API keys and tokens are encrypted using AES-256-CBC before database storage
- **Enhanced Nonce Verification**: Comprehensive nonce verification with security logging for all administrative actions
- **Security Audit Dashboard**: Built-in security audit tool with comprehensive system checks
- **Security Event Logging**: Detailed logging of all security events for monitoring and analysis
- **Capability Verification**: Enhanced user permission verification with logging
- **Secure Token Storage**: OAuth tokens encrypted before storage with automatic cleanup

#### Core Security Features
- **Input Sanitization**: All user inputs are sanitized using WordPress functions
- **CSRF Protection**: Nonce verification for all administrative actions
- **SQL Injection Prevention**: Prepared statements for all database operations
- **XSS Prevention**: Output escaping for all dynamic content
- **Capability Checks**: Proper user permission verification
- **Session Security**: Secure session handling and state management

### Security Audit System

The plugin includes a comprehensive security audit system accessible via the WordPress admin:

1. **Navigate to**: Settings → FP DMS Security
2. **Run Security Audit**: Performs comprehensive security checks
3. **Review Results**: View security score, critical issues, and warnings
4. **Monitor Logs**: Review security event logs for suspicious activity

#### Audit Checks Include:
- WordPress and PHP version verification
- Security constants validation
- Encryption capability verification
- File permissions review
- API key storage security
- Database security assessment

### Security Best Practices

When using FP Digital Marketing Suite:

#### API Key Management
- Store API keys securely using encrypted WordPress options
- Never commit API keys to version control
- Use environment variables for sensitive configuration in production
- Regularly rotate API keys according to provider recommendations
- Monitor API key usage through the security dashboard

#### Data Protection
- All user inputs are sanitized and validated
- Enhanced nonce verification for all forms and AJAX requests
- Database queries use prepared statements exclusively
- Personal data handling follows GDPR requirements
- Sensitive data is encrypted before storage

#### WordPress Security
- Keep WordPress core and all plugins updated
- Use strong passwords and enable two-factor authentication
- Implement proper user role management
- Regular security audits using the built-in audit tool
- Monitor security logs for suspicious activity

#### Security Monitoring
- Use the built-in security dashboard to monitor system status
- Regularly run security audits to identify potential issues
- Review security logs for unauthorized access attempts
- Set up alerts for critical security events

### Dependencies Security

We regularly audit our dependencies for known vulnerabilities:

```bash
composer audit
```

All dependencies are kept up to date and security patches are applied promptly.

### Security Hardening Checklist

For enhanced security, administrators should:

- [ ] Run initial security audit after installation
- [ ] Configure secure API key storage with encryption
- [ ] Enable security event logging
- [ ] Review security dashboard regularly
- [ ] Monitor security logs for suspicious activity
- [ ] Keep WordPress and plugins updated
- [ ] Use strong passwords and 2FA
- [ ] Implement proper backup procedures
- [ ] Configure security headers where applicable

### Reporting Non-Security Issues

For non-security related bugs and issues, please use our [GitHub Issues](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues) page.

---

**Note**: This security policy is subject to updates. Please check this document regularly for the latest information.