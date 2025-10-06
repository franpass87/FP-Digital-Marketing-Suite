---
name: Build Problem
about: Report issues with automatic build system
title: '[BUILD] '
labels: 'build, automation'
assignees: ''
---

## Build Problem Report

### What happened?
<!-- Describe the build issue -->

### Where did it happen?
- [ ] Local build (`./build.sh`)
- [ ] Local git hook (post-commit)
- [ ] GitHub Actions workflow
  - Which workflow? ________________

### Steps to reproduce
1. 
2. 
3. 

### Expected behavior
<!-- What should have happened? -->

### Actual behavior
<!-- What actually happened? -->

### Build Log
```
Paste relevant build log here
```

### Environment
- **OS:** (macOS, Linux, Windows WSL)
- **Node version:** `node --version`
- **npm version:** `npm --version`
- **PHP version:** `php --version`
- **Composer version:** `composer --version`

### Checklist
- [ ] `npm run build` works locally
- [ ] `./build.sh` works locally
- [ ] Git hooks are installed (`git config core.hooksPath`)
- [ ] Dependencies are installed (`npm install`, `composer install`)

### Additional context
<!-- Any other information -->
