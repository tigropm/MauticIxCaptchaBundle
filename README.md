# ixCaptcha - GDPR-Compliant reCAPTCHA v3 for Mautic

A GDPR-compliant Google reCAPTCHA v3 plugin for Mautic forms with multilingual support (DE/EN/FR).

## Features

- ✅ **GDPR Compliant**: No Google scripts loaded before explicit user consent
- ✅ **Multilingual**: German, English, and French translations included
- ✅ **Auto-Injection**: Automatically adds reCAPTCHA to all forms (configurable)
- ✅ **Score-Based Validation**: Configurable threshold (default: 0.5)
- ✅ **Submit Button Protection**: Forms cannot be submitted without consent
- ✅ **Badge Position**: Configurable (inline, bottom-right, bottom-left)
- ✅ **Compatible with Borlabs Cookie**: Works independently

## Requirements

- Mautic 4.x or 5.x
- PHP 8.0+
- Google reCAPTCHA v3 API keys

## Installation

### 1. Install the Plugin

```bash
cd /path/to/mautic/plugins/
git clone [your-repo-url] MauticIxCaptchaBundle

# or manually copy the folder to plugins/MauticIxCaptchaBundle/

cd /path/to/mautic/
php bin/console cache:clear
php bin/console mautic:plugins:reload
```

### 2. Get Google reCAPTCHA v3 Keys

1. Visit: https://www.google.com/recaptcha/admin
2. Create a new site with **reCAPTCHA v3**
3. Add your domain(s)
4. Copy the **Site Key** and **Secret Key**

### 3. Configure the Plugin

1. Go to Mautic Admin → Settings → Plugins
2. Click on **ixCaptcha (reCAPTCHA v3)**
3. Click **Yes** to enable the plugin
4. Enter your **Site Key** and **Secret Key**
5. Configure options:
   - **Minimum Score**: 0.5 (recommended) - Higher = stricter bot detection
   - **Auto-inject**: "Add to all forms" (default)
   - **Badge Position**: "Inline (in form)" (GDPR-friendly)
6. Click **Save & Close**

## Usage

### Automatic (Default)

When **Auto-inject** is set to "Add to all forms", the reCAPTCHA field is automatically added to all existing and new forms.

### Manual

If you prefer manual control:

1. Set **Auto-inject** to "Do not add automatically"
2. Edit a form
3. Add field type: **reCAPTCHA v3**
4. Configure custom consent text (optional)
5. Save the form

## Configuration Options

### Admin Settings (Global)

- **Site Key**: Your Google reCAPTCHA v3 site key
- **Secret Key**: Your Google reCAPTCHA v3 secret key
- **Minimum Score**: 0.0 - 1.0 (default: 0.5)
  - 0.0 = Allow all (no protection)
  - 0.5 = Balanced (recommended)
  - 1.0 = Very strict (may block legitimate users)
- **Auto-inject**:
  - None: Manual field addition only
  - All forms: Auto-add to all forms (recommended)
  - New forms only: Only auto-add to newly created forms
- **Badge Position**:
  - Inline: Inside the form (GDPR-friendly)
  - Bottom Right: Fixed at bottom-right of screen
  - Bottom Left: Fixed at bottom-left of screen

### Form Builder Settings (Per Field)

- **Consent Button Text**: Customize button text (multilingual)
- **Consent Notice**: Customize consent message (multilingual)
- **Show Badge**: Display reCAPTCHA badge (yes/no)

## How It Works

### GDPR Compliance

1. **No Automatic Loading**: Google reCAPTCHA script is NOT loaded automatically
2. **Explicit Consent**: User must click "Accept cookies" button
3. **Submit Protection**: Submit button is disabled until consent is given
4. **Clear Information**: Displays notice about data transfer to Google (USA)

### User Flow

1. User opens form with ixCaptcha field
2. Submit button is **disabled** with tooltip "Please accept cookies first"
3. User sees consent notice and button
4. User clicks "Accept cookies and continue"
5. Google reCAPTCHA script loads dynamically
6. Submit button becomes **enabled**
7. On submit, reCAPTCHA token is generated and verified
8. Form submits if score is above threshold

## Multilingual Support

The plugin automatically detects the form's language and displays appropriate translations:

- **German (de_DE)**: Complete translations
- **English (en_US)**: Complete translations
- **French (fr_FR)**: Complete translations

To add more languages, create a file at:
```
Translations/{locale}/messages.ini
```

## Troubleshooting

### Forms don't show reCAPTCHA

1. Check if plugin is enabled: Settings → Plugins → ixCaptcha
2. Verify Site Key and Secret Key are correct
3. Check Auto-inject setting (should be "all" or "new_only")
4. Clear Mautic cache: `php bin/console cache:clear`

### Submit button stays disabled

1. Check browser console for errors
2. Verify Site Key is correct
3. Ensure domain is registered in Google reCAPTCHA admin
4. Check if JavaScript is enabled

### Validation fails

1. Verify Secret Key is correct
2. Check Mautic logs: `var/logs/`
3. Adjust Minimum Score (may be too high)
4. Test with reCAPTCHA test keys for debugging

### Badge not showing

1. Check "Show Badge" setting in field configuration
2. Verify Badge Position is set correctly
3. Clear browser cache

## Google reCAPTCHA Test Keys

For testing purposes, use these official test keys:

**Site Key**: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
**Secret Key**: `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`

These keys always pass validation with a score of 0.9.

## Compatibility

### Borlabs Cookie

The plugin uses its own consent mechanism, independent of Borlabs Cookie. This ensures:
- Works without Borlabs Cookie
- Works alongside Borlabs Cookie
- No dependency on third-party APIs

If you want to integrate with Borlabs Cookie, you can customize the template to detect Borlabs Cookie consent.

## Support

For issues, feature requests, or contributions:
- GitHub Issues: [your-repo-url]/issues
- Documentation: [your-docs-url]

## License

MIT License

## Credits

Developed by [Your Name]
Built for Mautic 4.x/5.x
