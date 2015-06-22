=== Email Verification for WooCommerce  ===
Contributors: subhansanjaya, Tonny Keuken
Donate link: http://www.greenpeace.org/
Tags: woocommerce account verification, email validation, woocommerce email verification, account activation, woocommerce, email, checkout, woocommerce register, email verification, single site
Requires at least: 4.0
Tested up to: 4.2.2
Stable tag: 3.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


== Description ==

The Email Verification for WooCommerce plugin sends a verification link to a customer' e-mailaddress for activating their account after registration, this hooks in before proceeding to the checkout.


**Features include:**

1. No noncens straightforward, redirect customer to "my account" page in Woocommerce if their not logged in and checkout for guests is turned off.
2. Ready for translations.
3. Auto login and redirect to the checkout page, after activation.
4. Auto clean-up unvalidated accounts (emailaddresses) after 30 days.
5. Auto clean-up options, trash the page used, and the right database table, when un-installing.


**Translations:**

- Dutch [nl_NL] by [Tonny Keuken] (tonny.keuken@tidl.nl)
- English [en_EN] by [Tonny Keuken] (tonny.keuken@tidl.nl) (template)
- email-verification-for-woocommerce.pot for your own translations



== Installation == 

**Installation Instruction & Configuration**  	

1. In the admin panel plugins page click Add New. Then click on "upload" link. Select the downloaded zip file and install it OR
Download the zip file and extract it. Upload "Email Verification for WooCommerce" folder  to your plugins directory (wp-content/plugins/).

2. Activate the plugin through the “Plugins” menu in WordPress. 	
A new page ** "Account validation" will be created for you with a shortcode [email-verification-for-woocommerce] in it.
** depending on the translation-files

3. You can change the 'slug' or the post_name to your need under Pages on your Dashboard.
   This will result that the url for activation also has the page_name you widh to use...

== Changelog ==

**Version 3.1.1**
- Fixed a bug that caused that a verification failed and the user was not created.

**Version 3.1.0**

- Changed the methode dat transports userdata from and to the database-table, now it uses the get_userdata.
- The message that an email has been send, is working now.
- A message on succesful activation, is working now as it should be.
- The link in the email, send to the customer has a nice url for verification, now, and its translateble if you like.
- Added a .pot file for translations, included an empty english translation also.
- Updated the translation files.


**Version 3.0.0**

- Revamped release of the free version "WooCommerce Email Verification " created by subhansanjaya


== Frequently Asked Questions == 

1.  Question: Is there a way to do not send the email verification link if a user register with a social account. I only want to use the verification link for users that don't register with social login.
    Answer: Yes there is, create or modify the woocommerce template "my account page". In Woocommerce there is a template available for this page, and also for the register page.
    I suggest you create your own template for the login/registration page and add in there the option that customers can also register or login with their social accounts.
    Check out this guide: http://docs.woothemes.com/document/template-structure/
    So customers, when register by email verification or by your social login (plugin) and that are logged in can do their checkouts.
    
    The plugin only checks is a user is logged in and Woocommerce do not accept guest checkouts.




== Upgrade Notice ==

1. None, yet.


== Screenshots ==

1. None, yet.