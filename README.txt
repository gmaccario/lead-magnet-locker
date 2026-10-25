=== Lead Magnet Locker ===
Contributors: gmaccario
Donate link: https://www.giuseppemaccario.com/
Tags: lead magnet, downloads, email capture, forms, lead generation
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely lock downloads behind email capture. Simple setup, scheduling, analytics, and protected links.

== Description ==

Transform your WordPress site into a powerful lead generation machine. Protect your valuable digital assets while growing your email list automatically.

**Lead Magnet Locker** is the simple yet powerful solution for WordPress users who want to exchange valuable content for email addresses. Whether you're offering eBooks, templates, guides, or any digital download, this plugin creates a secure gateway that protects your files while building your audience.

Unlike other complex lead generation tools, Lead Magnet Locker focuses on doing one thing exceptionally well: turning visitors into subscribers through irresistible content offers.

### What's New in 1.3

* **Your files are safe on every server.** Files now live in a private, randomly named folder, so nobody can download them by guessing a URL, on Apache, Nginx, Caddy or IIS.
* **File names stay hidden.** The form on your page no longer shows which file it offers.
* **Export your leads.** Download your whole list, or just your search results, as a CSV file for Excel, Google Sheets or your email marketing tool.
* **Several forms on one page.** Offer a checklist and a guide side by side: every form works.
* **A cleaner admin.** Statistics at the top, a drag-and-drop upload area, a searchable timezone list and a clear "forms visible / hidden" status.
* **No technical errors for visitors.** If a shortcode points to a missing file, only administrators see the message.
* **Ready for WordPress 7.1** and translatable.

Updating is automatic: your existing files are moved to the private folder, and your shortcodes, download links and statistics keep working.

### Why Choose Lead Magnet Locker?

#### Security-First Approach
Your files are stored in a private, randomly named folder that cannot be reached by direct URL, on Apache, Nginx and other servers alike. The public form never reveals the file name, and only users who provide their email address receive a unique, trackable download link.

#### Dead Simple Setup
Install, upload your files, add a shortcode. That's it. No complicated configurations or technical knowledge required.

#### Complete Control
Set exactly when your offers are available with our powerful scheduling system. Perfect for limited-time promotions or coordinated marketing campaigns.

#### Detailed Analytics
Track every interaction with comprehensive reporting. See who downloaded what and when, monitor conversion rates, and optimize your lead magnets based on real data.

#### Professional Presentation
Clean, responsive forms that match your site's design and convert visitors into subscribers without looking spammy or pushy.

### Perfect For
* **Content Creators** offering free resources to build their audience
* **Course Creators** providing sample materials or bonus content
* **Agencies** collecting leads through valuable templates and guides
* **Bloggers** growing their email list with exclusive downloads
* **Small Businesses** capturing leads with helpful resources
* **Marketing Teams** running time-sensitive campaigns

== Features ==

### Core Functionality
* **Secure File Protection**: Files stored in a private, randomly named folder with server-level access rules; protected on Apache, Nginx, and other servers
* **Email Validation**: Rejects malformed email addresses before a download link is created
* **Unique Download Links**: Every download gets a secure, trackable URL
* **Responsive Design**: Forms look great on desktop, tablet, and mobile devices
* **AJAX-Powered**: Smooth user experience without page reloads

### Advanced Scheduling
* **Date Range Control**: Set start and end dates for your campaigns
* **Start and End Times**: Choose the exact time on the start date and on the end date
* **Timezone Support**: Pick the timezone for your schedule from a searchable list (UTC by default)
* **Real-Time Status**: See instantly if your forms are currently active
* **Site-Wide Schedule**: One schedule applies to all your forms at once

### Comprehensive Analytics
* **Download Tracking**: Monitor every file request and completion
* **Email Collection**: Build a database of interested prospects
* **Conversion Metrics**: Track success rates and optimize performance
* **Search & Filter**: Find specific records quickly
* **CSV Export**: Download your full lead list (or just your search results) as a CSV file, ready for Excel, Google Sheets, or your email marketing tool

### Administrative Power
* **Clean Dashboard**: Overview of all your lead magnets and performance
* **Multiple Forms**: Use as many forms as you like, even several on the same page
* **File Management**: Easy upload and organization system
* **User-Friendly Interface**: Intuitive controls that make sense

== Installation ==

### Automatic Installation
1. Log into your WordPress admin area.
2. Go to **Plugins → Add New**.
3. Search for **“Lead Magnet Locker”**.
4. Click **Install Now** and then **Activate**.

### Manual Installation
1. Download the plugin ZIP file.
2. Upload it to your `/wp-content/plugins/` directory.
3. Extract the files.
4. Activate through the **Plugins** screen.

### First Time Setup
1. Navigate to **Lead Magnet** in your WordPress admin menu.
2. Upload your first digital asset to the secure folder.
3. Add your shortcode to any page or post.
4. Start collecting emails immediately.

== Usage ==

### Basic Implementation
Add this shortcode anywhere you want to display a lead capture form:
```
[lead_magnet file="your-guide.pdf"]
```

### Customization Options
Make it your own with these parameters:
```
[lead_magnet file="marketing-guide.pdf" title="Get Your Free Marketing Guide" description="Join 5,000+ marketers who use this proven framework" button_text="Download Now"]
```

**Available Parameters:**
* `file` (required): Name of a file uploaded on the Lead Magnet admin page, exactly as shown in the Manage Files list
* `title`: Custom headline for your form
* `description`: Compelling description of your offer
* `button_text`: Call-to-action button text

### File Management

**Supported File Types:**
* PDF, ZIP
* Microsoft Office (Word, Excel, PowerPoint)
* Images (JPG, PNG, GIF)
* Text files
* Audio (MP3)
* Video (MP4)

**Upload Process:**
1. Access your WordPress admin dashboard.
2. Navigate to **Lead Magnet**.
3. Upload your file in the **Upload New File** box.
4. Copy its shortcode from the **Manage Files** list and paste it into a page or post.

### Campaign Scheduling

**Time-Limited Offers:**
1. Go to **Lead Magnet → Settings**.
2. Enable **Date & Time Trigger**.
3. Set your start and end dates.
4. Set the time on the start date and on the end date (forms stay visible continuously in between).
5. Your forms automatically appear and disappear.

The schedule is global: it applies to every lead magnet form on your site, whatever file it offers.

**Use Cases:**
* Launch week promotions
* Webinar follow-up resources
* Seasonal content offers
* Product launch campaigns
* Limited-time bonuses

### Analytics & Reporting

**Track Everything:**
* Total email submissions
* Unique subscriber count
* Download completion (conversion) rate
* Who requested which file, and when they downloaded it

**Export & Analysis:**
* Export the download history to CSV with one click from the History page
* Search records by email or filename, then export only the matching results
* Compare files by searching the history for each file name

== Frequently Asked Questions ==

= Can I export my leads? =
Yes. Go to Lead Magnet → History and click "Export all (CSV)". If you search first, the button becomes "Export results (CSV)" and exports only the matching records. The CSV includes email, file, submission date, download date, and status, and opens directly in Excel or Google Sheets.

= How secure are my files? =
Files are stored in a private, randomly named folder that is never shown on your site, protected by server rules and empty index files. Downloads are streamed through WordPress, and only users with a valid download link can get a file. The public form sends an opaque token instead of the file name.

= Are my files safe on Nginx? =
Yes, since 1.3.0. Nginx ignores .htaccess files, so earlier versions relied on the folder rules alone. Files now live in a secret folder whose URL cannot be guessed. For extra protection you can also block the folder in your Nginx configuration:
`location ^~ /wp-content/uploads/lead-magnet-files/ { deny all; }`

= Can I customize the form appearance? =
Yes. The plugin includes clean default styling that adapts to most themes. For advanced customization, you can override the CSS styles in your theme.

= Is there a limit to file sizes? =
File size limits are determined by your hosting provider’s PHP settings. The plugin can also enforce limits configured by filters.

= Can I use this for free and paid downloads? =
This plugin is designed for free lead magnets. For paid downloads, consider integrating with an e-commerce solution.

= Will this slow down my website? =
No. The plugin adds one small script and one small stylesheet, and its database queries are light. Forms submit with AJAX, without reloading the page.

= Can I integrate with email marketing services? =
The plugin collects email addresses in your WordPress database. You can export this data to import into your preferred email marketing platform.

= What happens if someone shares their download link? =
Each link is unique to one submission and stops working within 24 hours. The History page shows when the file behind each submission was downloaded.

= Can I show more than one form on a page? =
Yes, since 1.3.0. Add as many [lead_magnet] shortcodes as you like; each form offers its own file. Note that the schedule (Settings) is global and applies to all forms.

= Do I need to change anything after updating to 1.3.0? =
No. Your files are moved to the new private folder automatically, and your shortcodes, download links and statistics keep working. If your theme or custom code checks for files in wp-content/uploads/lead-magnet-files/ directly, use the lead_magnet_locker_file_exists() function instead (see Developer Functions).

= Do I need coding knowledge? =
Not at all. Everything works through simple shortcodes and an intuitive admin interface.

== Screenshots ==
1. Lead Magnet overview dashboard
2. Create & schedule a lead magnet
3. Front-end form example
4. Analytics & reporting view

== Changelog ==

= 1.3.0 =
**Safer files**
* Files are stored in a private, randomly named folder: they cannot be downloaded by direct URL, even on servers that ignore .htaccess (Nginx, Caddy, IIS)
* The public form no longer reveals the file name (it sends an opaque token)
* Existing files move to the private folder automatically; shortcodes, download links and statistics keep working
* If a file cannot be moved (e.g. folder permissions), administrators see a notice and the move is retried automatically
* Stronger folder rules (Apache 2.4 with 2.2 fallback) and empty index files block directory listings
* Downloads send X-Content-Type-Options: nosniff and a properly encoded file name; form messages are always shown as plain text
* Pages cached before the update keep working for 30 days, then only the new secure form data is accepted

**New and improved**
* Several [lead_magnet] forms on the same page now all work (before, only the first one appeared)
* Shortcode errors (e.g. file not found) are shown to administrators only; visitors no longer see technical messages
* The form shows a clear error instead of doing nothing when its security check fails (e.g. an outdated cached page)
* Paging through History search results keeps the search
* Clearer Settings page: it explains that the schedule applies to all forms, and that start and end times apply to the start and end dates
* Front-end texts are translatable
* New developer function lead_magnet_locker_file_exists() for themes that need to know whether a file is available

**Under the hood**
* Internals rewritten following Clean Architecture and covered by automated end-to-end, unit, architecture and static-analysis tests, for fewer bugs and safer future updates; no change to shortcodes, settings, hooks or stored data

= 1.2.0 =
* Added CSV export of the download history (respects the current search)
* Redesigned admin: statistics at the top of the dashboard, upload and instructions side by side, full-width cards, a drag-and-drop upload area
* Redesigned Settings page with a clear "forms visible / hidden" status and a searchable timezone list (UTC by default)
* Tested with WordPress 7.1
* Security: the timezone setting is validated (an invalid value can no longer break pages showing the form)
* Security: shortcode file paths are checked to stay inside the protected folder
* Security: History search treats "%" and "_" literally
* Fixed: the "Download File" button in History (admins can download a record's file without affecting the statistics)
* Fixed: the form button no longer stays on "Processing..." after an error

= 1.1.1 =
* Made the plugin compliant with the WordPress coding standards
* Fix issues after first volunteer's review

= 1.1.0 =
* Added a comprehensive admin dashboard
* Implemented a date/time scheduling system
* Enhanced download tracking and analytics
* Improved security measures
* Added bulk management capabilities
* Refactored classes to separate concerns

= 1.0.0 =
* Initial release
* Core lead magnet functionality
* Secure file protection
* Email collection system
* Basic analytics tracking

== Upgrade Notice ==

= 1.3.0 =
Recommended: protects your files on every server (incl. Nginx), hides file names, adds CSV export and several forms per page. Files move automatically and shortcodes keep working. Themes checking uploads/lead-magnet-files/ should use lead_magnet_locker_file_exists().

= 1.2.0 =
Adds CSV export of the download history and confirms compatibility with WordPress 7.1.

= 1.1.0 =
Major update with advanced scheduling features and a comprehensive admin dashboard.

== Frequently Used Hooks ==
* `lead_magnet_max_file_size` — Filter the max allowed upload size (in bytes).
* `lead_magnet_accept_legacy_file_field` — Filter (bool). Whether to accept the pre-1.3.0 `file` form field, sent by pages cached before the update. Default: `true` for 30 days after the upgrade moved your files (always `true` with `LML_DISABLE_STORAGE_MIGRATION`), `false` otherwise. Return `false` to stop accepting it once your page cache has been cleared, or `true` to keep accepting it.

== Developer Functions ==
* `lead_magnet_locker_file_exists( $file )` — Returns `true` when a lead magnet file (the shortcode's `file` value) is available. Use it instead of checking a path under `wp-content/uploads/`: since 1.3.0 files live in a private folder. Example: `if ( function_exists( 'lead_magnet_locker_file_exists' ) && lead_magnet_locker_file_exists( 'guide.pdf' ) ) { ... }`

== Configuration Constants ==
* `LML_DISABLE_STORAGE_MIGRATION` — Define as `true` in `wp-config.php` to keep files in the pre-1.3.0 folder (no private folder, no migration). Only needed if you must stay able to downgrade to 1.2.x; it removes the extra protection on Nginx.

== Privacy ==
Lead Magnet Locker is designed with privacy in mind:
* Email addresses are stored in your own WordPress database.
* No data is transmitted to external services.
* You maintain complete control over subscriber information: search, export (CSV), and delete records from the History page.

Lead Magnet Locker collects personal data (email addresses). Depending on where you and your visitors are, laws such as the GDPR may require you to inform visitors and obtain their consent; make sure your privacy policy covers this data. The plugin does not yet add a consent checkbox or integrate with the WordPress personal data export/erase tools.

== Credits ==
Developed for the WordPress community by G.Maccario. Special thanks to all users who provided feedback and feature requests.
