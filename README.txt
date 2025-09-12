=== Lead Magnet Locker ===
Contributors: gmaccario
Donate link: https://www.giuseppemaccario.com/
Tags: lead, downloads, email capture, forms, gdpr
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Securely lock downloads behind email capture. Simple setup, scheduling, analytics, and protected links.

== Description ==

Transform your WordPress site into a powerful lead generation machine. Protect your valuable digital assets while growing your email list automatically.

**Lead Magnet Locker** is the simple yet powerful solution for WordPress users who want to exchange valuable content for email addresses. Whether you're offering eBooks, templates, guides, or any digital download, this plugin creates a secure gateway that protects your files while building your audience.

Unlike other complex lead generation tools, Lead Magnet Locker focuses on doing one thing exceptionally well: turning visitors into subscribers through irresistible content offers.

### Why Choose Lead Magnet Locker?

#### Security-First Approach
Your files are stored in a protected directory that prevents direct access. Only users who provide their email address receive a unique, time-tracked download link.

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
* **Secure File Protection**: Files stored in protected directories with server-level access control
* **Email Validation**: Built-in verification ensures you collect valid email addresses
* **Unique Download Links**: Every download gets a secure, trackable URL
* **Responsive Design**: Forms look great on desktop, tablet, and mobile devices
* **AJAX-Powered**: Smooth user experience without page reloads

### Advanced Scheduling
* **Date Range Control**: Set start and end dates for your campaigns
* **Time-Based Restrictions**: Control daily availability windows
* **Timezone Support**: Respects your WordPress timezone settings
* **Real-Time Status**: See instantly if your forms are currently active

### Comprehensive Analytics
* **Download Tracking**: Monitor every file request and completion
* **Email Collection**: Build a database of interested prospects
* **Conversion Metrics**: Track success rates and optimize performance
* **Search & Filter**: Find specific records quickly
* **Export Capabilities**: Access your data when you need it

### Administrative Power
* **Clean Dashboard**: Overview of all your lead magnets and performance
* **Bulk Management**: Handle multiple campaigns efficiently
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
* `file` (required): Filename in your `lead-magnet-files` directory
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

**Upload Process: **
1. Access your WordPress admin dashboard.
2. Navigate to **Lead Magnet Locker → Settings**.
3. Upload your files directly via the file manager.
4. Reference the filename in your shortcode.

### Campaign Scheduling

**Time-Limited Offers:**
1. Go to **Lead Magnet → Settings**.
2. Enable **Date & Time Trigger**.
3. Set your start and end dates.
4. Configure daily time windows.
5. Your forms automatically appear and disappear.

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
* Download completion rates
* Individual user activity
* Time-based performance data

**Export & Analysis:**
* Search records by email or filename
* Filter by date ranges
* Monitor conversion trends
* Identify best-performing content

== Frequently Asked Questions ==

= How secure are my files? =
Extremely secure. Files are stored in a protected directory with server-level access controls that prevent direct URL access. Only users with valid download keys can access files.

= Can I customize the form appearance? =
Yes. The plugin includes clean default styling that adapts to most themes. For advanced customization, you can override the CSS styles in your theme.

= Is there a limit to file sizes? =
File size limits are determined by your hosting provider’s PHP settings. The plugin can also enforce limits configured by filters.

= Can I use this for free and paid downloads? =
This plugin is designed for free lead magnets. For paid downloads, consider integrating with an e-commerce solution.

= Will this slow down my website? =
No. The plugin uses efficient database queries and only loads resources when needed. Forms use AJAX for a smooth experience.

= Can I integrate with email marketing services? =
The plugin collects email addresses in your WordPress database. You can export this data to import into your preferred email marketing platform.

= What happens if someone shares their download link? =
Each download link is unique and tracked. You’ll see in your analytics if links are being used by multiple people.

= Do I need coding knowledge? =
Not at all. Everything works through simple shortcodes and an intuitive admin interface.

== Screenshots ==
1. Lead Magnet overview dashboard
2. Create & schedule a lead magnet
3. Front-end form example
4. Analytics & reporting view

== Changelog ==

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

= 1.1.0 =
Major update with advanced scheduling features and a comprehensive admin dashboard.

== Frequently Used Hooks ==
* `lead_magnet_max_file_size` — Filter the max allowed upload size (in bytes).

== Privacy ==
Lead Magnet Locker is designed with privacy in mind:
* Email addresses are stored securely in your WordPress database.
* No data is transmitted to external services.
* You maintain complete control over subscriber information.
* Compatible with GDPR and privacy regulations.
* Includes proper data handling practices.

== Credits ==
Developed for the WordPress community by G.Maccario. Special thanks to all users who provided feedback and feature requests.
