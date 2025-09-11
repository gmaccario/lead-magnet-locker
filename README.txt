=== Lead Magnet Locker ===
Contributors: gmaccario
Tags: lead generation, mailing list, download assets
Requires at least: 4.7
Tested up to: 6.7.3
Stable tag: 1.1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WordPress site into a powerful lead generation machine. Protect your valuable digital assets while growing your email list automatically.

== Description ==

NO-CODE NEEDED! Lead Magnet Locker is a simple yet powerful solution for WordPress users who want to exchange valuable content for email addresses. Whether you're offering eBooks, templates, guides, or any digital download, this plugin creates a secure gateway that protects your files while building your audience.

Unlike other complex lead generation tools, Lead Magnet Locker focuses on doing one thing exceptionally well: turning visitors into subscribers through irresistible content offers.

=== Why Choose Lead Magnet Locker? ===

*Security-First Approach*
Files are stored in a protected directory. Only users who provide an email address receive a unique, time-tracked download link.

*Dead Simple Setup*
Install, upload your files, add a shortcode. No technical knowledge required.

*Complete Control*
Schedule exactly when your offers are available. Perfect for limited-time promotions.

*Detailed Analytics*
Track downloads, conversions, and optimize your lead magnets based on real data.

*Professional Presentation*
Responsive, clean forms that match your site's design and convert visitors into subscribers.

=== Perfect For ===
* Content creators offering free resources
* Course creators providing sample materials
* Agencies collecting leads through templates and guides
* Bloggers growing their email list with exclusive downloads
* Small businesses capturing leads with helpful resources
* Marketing teams running time-sensitive campaigns

== Features ==

=== Core Functionality ===
* Secure file protection in protected directories
* Built-in email validation
* Unique, trackable download links
* Responsive design for all devices
* AJAX-powered smooth user experience

=== Advanced Scheduling ===
* Date range control
* Time-based restrictions
* Timezone support
* Real-time status display

=== Comprehensive Analytics ===
* Download tracking
* Email collection
* Conversion metrics
* Search and filter records
* Export capabilities

=== Administrative Power ===
* Clean dashboard overview
* Bulk campaign management
* Easy file upload and organization
* User-friendly interface

== Installation ==

=== Automatic Installation ===
1. Log into WordPress admin
2. Go to Plugins > Add New
3. Search for "Lead Magnet Locker"
4. Click Install Now and Activate

=== Manual Installation ===
1. Download the plugin ZIP file
2. Upload to `/wp-content/plugins/`
3. Extract the files
4. Activate via the WordPress Plugins screen

=== First Time Setup ===
1. Navigate to **Lead Magnet** in your admin menu
2. Upload your digital asset
3. Add the shortcode to a page/post
4. Start collecting emails

== Usage ==

=== Basic Implementation ===

Add this shortcode anywhere you want to display a lead capture form:

```
[lead_magnet file="your-guide.pdf"]
```

### Customization Options

Make it your own with these parameters:

```
[lead_magnet file="marketing-guide.pdf" title="Get Your Free Marketing Guide" description="Join 5,000+ marketers who use this proven framework" button_text="Download Now"]
```

=== Available Parameters: ===
* `file` (required): Filename in your lead-magnet-files directory
* `title`: Headline text
* `description`: Form description
* `button_text`: Call-to-action text

=== File Management ===
* Supported file types: PDF, ZIP, Word, Excel, PowerPoint, JPG, PNG, GIF, TXT, MP3, MP4
* Upload easily via the WordPress admin dashboard and reference filename in the shortcode

=== Campaign Scheduling ===
* Enable "Date & Time Trigger" in **Lead Magnet > Settings**
* Set start/end dates and daily time windows

=== Analytics & Reporting ===
* Track email submissions, downloads, user activity, and conversion rates
* Filter by date or email, export data for analysis

== Frequently Asked Questions ==

=== How secure are my files? ===
Files are stored in protected directories with server-level access controls. Only users with valid download keys can access files.

=== Can I customize the form appearance? ===
Yes. Default styling adapts to most themes. Advanced users can override CSS in their theme.

=== Is there a limit to file sizes? ===
Limits are set by your hosting provider’s PHP configuration. The plugin imposes no restriction.

=== Can I use this for free and paid downloads? ===
Designed for free lead magnets. Paid downloads require integration with e-commerce solutions.

=== Will this slow down my website? ===
No. The plugin uses efficient queries and AJAX for smooth performance.

=== Can I integrate with email marketing services? ===
Yes. Export collected emails for your preferred platform.

=== What happens if someone shares their download link? ===
Each link is unique and tracked. Analytics show multiple uses if shared.

=== Do I need coding knowledge? ===
No. Everything works via shortcodes and an intuitive admin interface.

== Support ==

* **Documentation**: Available in your admin area
* **Support Forums**: Connect with other users
* **Priority Support**: For immediate assistance

== Contributing ==

Contributions are welcome. Report bugs, suggest features, or submit improvements to help the plugin grow.

== Privacy & Compliance ==

* Emails stored securely in WordPress database
* No external data transmission
* Fully GDPR compliant
* Complete control over subscriber data

== Changelog ==

=== 1.1.0 ===
* Admin dashboard added
* Advanced scheduling system
* Enhanced download tracking
* Improved security
* Bulk management capabilities
* Upload files via backend interface

=== 1.0.0 ===
* Initial release
* Core lead magnet functionality
* Secure file protection
* Email collection
* Basic analytics

== Upgrade Notice ==

=== 1.1.0 ===
Major update with scheduling features and comprehensive admin dashboard.

== Credits ==

Developed by G. Maccario. Thanks to all users who provided feedback. Built on WordPress best practices.
