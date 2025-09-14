# 📦 WordPress Plugin Builder Workflow

This workflow automatically builds a WordPress-ready ZIP package for the FP Digital Marketing Suite plugin.

## 🚀 How it Works

The workflow is triggered:
- **Automatically** on pushes to `main` or `develop` branches
- **Automatically** on pull requests to `main` or `develop` branches  
- **Manually** via the Actions tab (workflow_dispatch)

## 🔽 Getting Build Artifacts

### For Pull Requests
When you create a PR, the workflow will automatically build the plugin and post a comment with download instructions.

### For Manual Builds
1. Go to the [Actions tab](../../actions)
2. Click on "Build WordPress Plugin"  
3. Click "Run workflow" and optionally specify a version
4. Download the artifact from the completed workflow run

### For Tagged Releases
When you create a Git tag starting with `v` (e.g., `v1.0.1`), the workflow will:
- Build the plugin package
- Create a GitHub release with the ZIP file attached
- Generate release notes

## 📋 What's Included in the Build

The generated ZIP file contains:
- ✅ All plugin source code
- ✅ Production dependencies (via Composer)
- ✅ Required assets (CSS, JS, images)
- ✅ Translation files
- ✅ Essential documentation (README, CHANGELOG, DEPLOYMENT_GUIDE)
- ✅ WordPress plugin headers and metadata
- ❌ Development files (tests, build scripts, etc.)

## 🎯 WordPress Installation

The generated ZIP file is ready for WordPress installation:

1. **Via WordPress Admin:**
   - Go to Plugins → Add New → Upload Plugin
   - Select the downloaded ZIP file
   - Click "Install Now" and then "Activate"

2. **Via FTP:**
   - Extract the ZIP file
   - Upload the `fp-digital-marketing-suite` folder to `/wp-content/plugins/`
   - Activate via WordPress Admin

## 🔧 Manual Override

You can override the plugin version during manual workflow runs:
- Leave empty to use the version from `fp-digital-marketing-suite.php`
- Specify a custom version (e.g., `1.0.1-beta`) for testing builds

## 📊 Build Verification

The workflow automatically verifies:
- ZIP file integrity and structure
- Presence of main plugin file
- Production dependencies installation
- File count and package size
- Checksum generation

## 🚨 Troubleshooting

If a build fails:
1. Check the workflow logs in the Actions tab
2. Ensure all required files are present in the repository
3. Verify `composer.json` dependencies are installable
4. Check that the main plugin file exists and has proper headers

## 🔍 Build Artifacts Contents

Each build generates:
- `fp-digital-marketing-suite-{version}.zip` - WordPress plugin package
- `fp-digital-marketing-suite-{version}.zip.sha256` - Checksum file
- `build-info.md` - Build information and instructions

The artifacts are kept for 30 days and can be downloaded from the workflow run page.