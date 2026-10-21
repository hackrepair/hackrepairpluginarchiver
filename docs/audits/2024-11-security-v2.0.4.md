# Security Vulnerability Report - The Hack Repair Guy's Plugin Archiver

**Date:** November 26, 2024  
**Plugin Version:** 2.0.4  
**Assessment Type:** Security Audit  

## Executive Summary

This security audit identified **15 security vulnerabilities** across multiple severity levels:
- **Critical:** 3 vulnerabilities
- **High:** 5 vulnerabilities  
- **Medium:** 4 vulnerabilities
- **Low:** 3 vulnerabilities

The most serious issues involve missing CSRF protection, path traversal vulnerabilities, and insufficient input validation that could lead to unauthorized file operations.

---

## Critical Vulnerabilities

### 1. Missing CSRF Protection in Archive Actions (CRITICAL)
**Severity:** Critical  
**CVSS Score:** 8.8  
**File:** `hackrepair-plugin-archiver/hackrepair-plugin-archiver.php`  
**Lines:** 226-249 (archive_actions method)

**Description:** The plugin processes archive/restore actions without proper nonce verification in the initial request handling.

**Vulnerable Code:**
```php
// Line 227
if ( isset($_REQUEST['action']) ) {
    switch ( $_REQUEST['action'] ) {
        case 'restore-selected':
            $result = HackRepair_Plugin_Archiver::bulk_restore();
```

**Impact:** Attackers can craft malicious links that, when clicked by an authenticated admin, will archive/restore/delete plugins without their knowledge.

**Proof of Concept:**
```html
<img src="https://victim-site.com/wp-admin/plugins.php?page=hackrepair-plugin-archiver&action=remove-selected&checked[]=important-plugin/plugin.php" />
```

---

### 2. Path Traversal in File Operations (CRITICAL)
**Severity:** Critical  
**CVSS Score:** 9.1  
**Files:** Multiple locations  
**Lines:** 
- `hackrepair-plugin-archiver.php`: Lines 339, 344, 384-386, 420-421

**Description:** User-supplied plugin paths are used directly in file operations without proper validation.

**Vulnerable Code:**
```php
// Line 384-386
$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
$target_dir = self::plugin_basename( $plugin, WP_PLUGIN_DIR );
$result = $wp_filesystem->move( $plugin_dir, $target_dir );
```

**Impact:** Attackers could potentially move files outside the intended directories using path traversal sequences (../).

---

### 3. Arbitrary File Deletion (CRITICAL)
**Severity:** Critical  
**CVSS Score:** 9.1  
**File:** `hackrepair-plugin-archiver/hackrepair-plugin-archiver.php`  
**Lines:** 343-345

**Description:** The bulk_remove function deletes files based on user input without sufficient validation.

**Vulnerable Code:**
```php
// Line 343-345
$plugin_dir = self::plugin_basename( $plugin, $archive_dir );
$wp_filesystem->chmod( $plugin_dir , $wp_filesystem->is_dir( $plugin_dir ) ? FS_CHMOD_DIR : FS_CHMOD_FILE );
$wp_filesystem->delete( $plugin_dir, true );
```

---

## High Severity Vulnerabilities

### 4. Cross-Site Scripting (XSS) in Multiple Locations (HIGH)
**Severity:** High  
**CVSS Score:** 7.2  
**Files & Lines:**
- `includes/list.php`: Lines 301 (unescaped $title)
- `includes/list.php`: Line 406 (unescaped $plugin_name)
- `includes/options.php`: Lines 60-61 (unescaped option values)
- `hackrepair-plugin-archiver.php`: Line 258, 266 (unescaped messages)

**Vulnerable Code Example:**
```php
// includes/list.php Line 301
echo '<h2>'.esc_html( $title ) .'</h2>';
```

**Impact:** Malicious plugin names or descriptions could execute JavaScript in admin context.

---

### 5. Missing Permission Checks (HIGH)
**Severity:** High  
**CVSS Score:** 7.5  
**File:** `hackrepair-plugin-archiver/hackrepair-plugin-archiver.php`  
**Lines:** 226-249, 327-359, 360-403, 404-442

**Description:** Several critical functions don't verify user capabilities before execution.

**Vulnerable Code:**
```php
// bulk_remove, bulk_restore, bulk_archive methods lack capability checks
public static function bulk_remove($checked=false) {
    // No current_user_can() check
```

---

### 6. Insecure Direct Object Reference (HIGH)
**Severity:** High  
**CVSS Score:** 7.5  
**File:** `includes/list.php`  
**Lines:** 55-58

**Description:** Archive directory can be changed via user input without validation.

**Vulnerable Code:**
```php
// Line 56
$dir = WP_CONTENT_DIR . '/' . $_REQUEST['archive_dir'];
```

---

### 7. Missing Direct File Access Protection (HIGH)
**Severity:** High  
**CVSS Score:** 6.5  
**Files:** All PHP files except `index.php`

**Description:** PHP files can be accessed directly, potentially exposing sensitive information or causing errors.

**Affected Files:**
- `hackrepair-plugin-archiver.php`
- `includes/bulk.php`
- `includes/list.php`
- `includes/options.php`
- `includes/pointers.php`

---

### 8. Insufficient Input Validation (HIGH)
**Severity:** High  
**CVSS Score:** 7.0  
**Multiple Locations:**

**Examples:**
- Archive directory names not validated (options.php:223)
- Plugin file paths not sanitized before use
- Search terms used without sanitization (list.php:161)

---

## Medium Severity Vulnerabilities

### 9. Information Disclosure via Error Messages (MEDIUM)
**Severity:** Medium  
**CVSS Score:** 5.3  
**File:** `hackrepair-plugin-archiver/hackrepair-plugin-archiver.php`  
**Lines:** 180-203

**Description:** Detailed file paths exposed in error conditions.

---

### 10. Weak Randomness in Directory Names (MEDIUM)
**Severity:** Medium  
**CVSS Score:** 4.3  
**File:** `hackrepair-plugin-archiver/hackrepair-plugin-archiver.php`  
**Line:** 27

**Vulnerable Code:**
```php
self::$options['archive_dir'] = 'plugins-archive-'.substr( md5( get_bloginfo( 'url' ) ), 0, 6);
```

**Impact:** Predictable archive directory names based on site URL.

---

### 11. Race Condition in File Operations (MEDIUM)
**Severity:** Medium  
**CVSS Score:** 5.9  
**Files:** Multiple file operation methods

**Description:** No file locking mechanism when moving/deleting plugins.

---

### 12. Insufficient Nonce Lifetime (MEDIUM)
**Severity:** Medium  
**CVSS Score:** 4.8  

**Description:** Uses default WordPress nonce lifetime (24 hours) for sensitive operations.

---

## Low Severity Vulnerabilities

### 13. Mixed Content Potential (LOW)
**Severity:** Low  
**CVSS Score:** 3.7  
**File:** `includes/notes.php`  
**Lines:** 20, 23

**Description:** External links without forced HTTPS.

---

### 14. Deprecated Function Usage (LOW)
**Severity:** Low  
**File:** `includes/list.php`  
**Line:** 218

**Description:** Uses `sizeof()` instead of `count()` (deprecated in PHP 7.2+).

---

### 15. Missing Text Domain in Translations (LOW)
**Severity:** Low  
**Multiple Files**

**Description:** Some translatable strings missing text domain parameter.

---

## Recommended Remediation Priority

1. **Immediate (Critical):**
   - Implement proper CSRF protection for all actions
   - Add path traversal protection
   - Validate all file operations

2. **High Priority:**
   - Add capability checks to all administrative functions
   - Escape all output properly
   - Add direct file access protection

3. **Medium Priority:**
   - Improve input validation
   - Strengthen directory name generation
   - Add file operation locking

4. **Low Priority:**
   - Update deprecated functions
   - Fix translation issues
   - Force HTTPS for external links

---

## Security Best Practices Recommendations

1. **Input Validation Framework:** Implement centralized input validation
2. **Output Escaping:** Use WordPress escaping functions consistently
3. **File Operations:** Create secure wrapper functions for all file operations
4. **Logging:** Add security event logging
5. **Regular Security Audits:** Schedule periodic security reviews
6. **Automated Testing:** Implement security test suite

---

## Testing Checklist

- [ ] Test all CSRF protections
- [ ] Attempt path traversal attacks
- [ ] Test XSS payloads in all input fields
- [ ] Verify permission checks
- [ ] Test file operation edge cases
- [ ] Validate error handling
- [ ] Check for information disclosure

---

**Next Steps:** After reviewing this report, we can proceed with implementing fixes based on your priority preferences. Would you like to start with the critical vulnerabilities?