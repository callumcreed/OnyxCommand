# OnyxCommand
WIP WP Plugin

NOTE! ---
------------------------------
This is all very stream of consciousness note-taking. Sorry if it's a lil confusing! 



QA TESTING ---
------------------------------



BUG FIXES NEEDED ---
------------------------------
- When accessing the Manage Modules page, automatically perform a silent scan for new modules.
- Rename the module and zip file to only Deletion Manager, and deletion-manager.zip
- CONVERT the Onyx Control bar to a standalone plugin for use outside of Onyx Command


RECOMMENDED PLUGINS MODULE ---
------------------------------
- Use AI to do an analysis of the current site, its content, what functionality it uses, how the site is used, its necessary features, the plugins already installed and being utilized, etc. Create a comprehensive list of available modules that are recommended to be installed based on the site's apparent needs. Include the name of the module, its description, an explanation of why the module is being recommended, a button to automatically install it, and a button to automatically download it, and a button to reject the module suggestion.


IMAGE RESIZE, COMPRESS, REPLACE, ETC ---
------------------------------
- Bulk Image resize and optimizations
- Add ability to replace an image. When an image is replaced, automatically regenerate its thumbnails.
- An image should be able to be "just replace it" or "replace it with this name and update all links to it across the site"
- All renaming an image file (update through all thumbs and database and links afterward)

ONYX CONTROL ---
------------------------------
Add an embedded webpage to the top of the ADMIN DASHBOARD page (i.e. https://yoursite.com/wp-admin/index.php). It should be a minimum of 500px high and wide enough to span the page content on the dashboard. Make sure the webpage is seamlessly integrated, with no scrolling, and will SIZE AUTOMATICALLY depending on screen size. HIDE the page title that says "Dashboard" on the page. REPLACE the title Dashboard with the embedded webpage.


MAIL DELIVERY MANAGER ---
------------------------------
- Implement a foolproof mail delivery system for any messages or form data submissions that are emailed from the current site. When ANYTHING is emailed from the site, use this mail delivery system to do so. Allow sending a test email to verify that mail deliveries are working properly.  Allow the option for the user to add their own POP and IMAP settings for sending mail. If the details for the POP and IMPA settings  are provided, use those details to send emails from the site.


SAFETY NET / DELETION MANAGER EXPANSION ---
------------------------------
- Implement a way to roll back an action that was just performed, if the action triggers a critical error and the website becomes inaccessible. If there is a critical error, implement a button on the critical error page that will allow reverting whatever action was just performed that made the critical error- On the Deletion page, for the Expiring in 7 Days text - Update the number of days until expiration to the chosen number, which should be the archive duration based on what the user chose. In  on the Settings tab, add a section for the number of days dropdown. The dropdown number should be where the "7 Days" text is on the Deletion Archive.
- If Onyx Command plugin is DEACTIVATED, restore any native WordPress functionality that the Onyx Command plugin or any of its modules may have replaced or taken over (such as Deletion Archive functions). If Onyx Command is deactivated, WordPress should resume its native functionality, actions and commands.
- When attempting to delete a module from Onyx Command, if the Deletion Archive is installed and activated, intercept the delete action from Onyx Command, and use Deletion Manager's delete process instead. If Deletion Manager is NOT installed, delete the module, AND delete all of it's files and associated database entries.
- Change the Onyx Control module - instead of loading remote content, add an embedded webpage that can only be set in the module settomg. Style the embed to look as seamless as possible, and AUTOMATICALLY SIZE based on the loaded content.
- The Backup & Maintenance content on the homepage, clicking any of the download buttons displays a lightbox with content displayed in the upper left corner of the screen. Please center it in the middle of the screen.
- Add an information box on the Onyx Dashboard that will list the names of any plugins or themes that need to be updated. Add a button at the bottom of the information box that will bring the current user to the Updates page of the current site.
- Disable the default delete action AND Javascript confirmation alert that triggers when a plugin's Delete link is clicked on the Plugins page list. Disable the default delete action for ALL installed plugins.
- Instead, trigger the modal window delete choice/confirmation when the Delete link is clicked.
- Rather than making this module apply only to the Onyx Command plugin, apply the new deletion process to ALL plugins installed. Clicking Delete for ANY plugin should trigger the confirmation modal FIRST, before proceeding to the chosen delete action.
- Depending on which delete option is chosen (Keep Files or Delete All), THEN execute the delete action according to the chosen preference.
- When the delete option is chosen, proceed to an information page, which should list all the details of what was deleted - plugin files, folders, database records, etc. This should happen for both delete options, regardless of choice.
- Allow the ability to revert the deletion of the plugin, ONLY ON THE INFORMATION PAGE. Once the page is left, reverting the deletion is no longer possible.
- Remove this screen from the new delete function process, it is redundant. EXAMPLE URL - /wp-admin/plugins.php?action=delete-selected&checked%5B0%5D=onyx-command%2Fonyx-command.php&plugin_status=all&paged=1&s&_wpnonce=669de35b4b
- Add the ability to preview a deleted page, post, media item (image, document, etc), plugin information, etc. to the Deletion Archive Actions.
- If restoring an image from the Deletion Archive, automatically regenerate thumbnails on restoration.
- After restoring a file or media, remove all folders or files associated with it from the Archive AFTER it is restored to its original state.
- Allow the deletion manager Retention Period to be changed via Settings. By default, it should be 7 days. Other options should be 14 days, 30 days and indefinitely. For indefinitely, retain deletions until they are manually deleted from the archive.
- Incorporate the ability to have archived items backed up to Dropbox, Google Drive, or a downloadable zip file. If backed up, create a zip file of all archival items (sql for pages and posts, original files for deleted media items like images, videos or documents). If there are settings required for this feature such as sign in or authorization, put them on the Deletion Manager settings page.


MAYBE / BRAINSTORMING ---
------------------------------
- Wildcards Capability - to Set a specific wild card to display in meta information automatically (i.e. keyphrase in title and meta desc) etc
- User Audit Log, include tracking the user name that performed the activity, where it was performed, how long the action took to complete, and what exactly was done by the user, and at what date and time. Give me as much useful information as I could need. Records should be more styled to be more compact, almost as if they're in a table. Remove the View All Projects button from the bottom of the User Activity block and instead, add a link to View More on an audit log page, which should display more of the audit log, in a paginated way.
- ADD Application Pulse block, Remove the Runs and Total columns. Instead, add an Errors column that will display the number of times each module has triggered an error (and add a link to display the full error information). For Change Speed, calculate the percentage difference in runtime speed based on the Avg Runtime number and the current live runtime speed. The live runtime speed should be able to be run at any time, by pressing a "refresh" button in the Application Pulse block. Refresh and update the information live when the Refresh button is clicked, using AJAX without the need to refresh the page. 
- ADD in the Application Pulse block, add 2 buttons to click that will scan the site for performance issues. 2 button options - Deep Scan (scan the entire site, all files and all images) or Quick Scan (only scan the database and plugins for slowdowns and bottlenecks). Once a scan is complete, offer smart suggestions to improve any issues
- Keep track of the number of times the site goes offline, for how long, was there an error code and what was it, and when it came back
- Create a heatmap for pages and posts. The heatmap should display the areas on any page or post that have the most activity, like clicks, engagement or interaction. The most highly clicked areas on the page should start with red, and progress download to green based on how many clicks have been tracked on the page.
- AB testing module!
- Add the ability to automatically unlock an account if you are able to enter your account's "unlock" password, which should be able to be set on the user's User Settings page. If the password is correct, unlock the user's account automatically. Otherwise, locked accounts should remain locked for 72 hours, after which they can be automatically removed from the list. ALSO ADD the ability to permanently block ANY IP address that is on the Blocked Accounts page. When a permenant block is made, the user will be required to have the account unlocked via an Administrator or Super Administrator ONLY. They can NOT unlock their account on their own. which needs to remove the ability to unlock the account on your own without the help of a valid and logged in Administrator or Super Administrator.
- Performance monitor - keep track of slow loading pages, pages and posts that are not optimized for SEO, plugin load times, plugin function timers, any critical or fatal errors with details on what caused them, any bottlenecks or slow downs on the site as a whole. Pinpoint the most specific issues on the site that are affecting SEO, site speed, site performance, and the user experience, and WHY that is true. Display the status of all of these checks and statistics on the page and provide the necessary capability to correct them.
- In Onyx Essentials, add a setting to enable downloading plugins individually from the Plugins page.
- Implement user role Onyx Admin and create the account automatically when Onyx Command is installed. The account should have Super Administrator capabilities, in the Onyx Admin role.
- Add View More Details link to each record in the error logs. The link should open a modal with all the information about the error selected.
- Make the Recommended Plugins page use AI to analyze the current site's content, features, goals, and uses, and suggest WordPress plugins that would benefit or enhance the website.
- In the Deletion Archive, there is a block that has a dynamic number and text that says "Expiring in 7 Days". For the text, please replace 7 days with whatever button has been selected on the settings tab. This should happen whenever the days count changes, so that it stays up to date at all times.
- Add any installed page builder plugins to the Builder Controls dropdown options on the Onyx Essentials settings page; it is located in the Content Management box, under Builder Controls. Users should be able to choose the default builder.
- On Onyx Essentials, speed up Scan for Broken URLs as much as possible.
- On Onyx Essentials - add Lazy Load Images and Preload CSS. The setting to activate those options should be on the Optimizer page from Onyx Command.
- Add a global setting on Onyx Essentials that will 
- In Onyx Essentials module, clicking on buttons will open up a black opacity lightbox, but the lightbox is blank and has nothing on it. Fix it.
- In Onyx Essentials, remove the Quickstart Guide and Open Documentation links on the page. 
- Create a Staging environment of the site so that I can troubleshoot issues without affecting the live site. Live staging environments should be listed on the Onyx Command Settings page AND in the Onyx Command menus. When creating a staging environment, make an exact duplicate of the current website, and place it in a subfolder of the live site's root directory. Include the database, SEO and all content when cloning the live site. Staging environments should be able to be easily deleted, database records and all, WITHOUT affecting the live Production site.
- Add the ability to click on an area of the page and leave a comment on it. The comment indicator should be placed where the user clicked, and once clicked, the comment should open in the form of a modal tooltip. In the tooltip there should be a checkbox with the word Resolved on the bottom of the tooltip with the ability to delete the comment also. Resolved button will indicate that the issue has been taken care of, and the comment can be deleted. Also add the ability to respond to a comment, which will add the response below the original comment in the tooltip. The ability to respond to a comment should work as you would think.  The tooltip that displays should be no wider than 300px. All of these actions should be limited to only Administrators and Super Administrators. It can be hidden for anyone else. In addition to the tooltip display, add all comments into a sidebar list, with the same Resolved or Reply functionality as the tooltip. If there are new comments added to a page, allow the user to provide an email or multiple emails to send an alert to about how many unresolved comments have been added.
- On the Onyx Command dashboard, the media file counter is inaccurate.
- In Onyx Essentials, for the Announcement Bar, add styling capability to allow the user to use a basic WYSIWYG tool to format the content of the bar and the bar itself. Display an announcement preview if requested.
- In Onyx Essentials, Scan for Broken URLs, the list of broken URLs should be displayed in a neatly styled list and the width of the box should  fit the page width of the homepage. ALSO add the ability to ignore broken URLs list records. Ignoring them should not allow the broken URLs search to list them as broken again.
- For the Onyx Command - if the plugin is deleted, do NOT redirect to a page. STAY on the current page. Use AJAX to display a success message when the plugin is deleted fully.
- On Onyx Essentials images found in the missing alt text search - the list of images should be paginated using AJAX to prevent the need to refresh the page or open a new one. Pagination should occur every 20 links, and can be clicked through by AJAX to update the visible images without leaving the page.
- In Onyx Essentials for the broken URLs search task, put another button next to Scan Now to Ignore all listed pages. And another button next to Ignore that says Fix Now. The Fix Now button should remove all of the links on the Broken URLs list from the website. ONLY REMOVE THE A HREF TAG, DO NOT remove the contents of the page.
- The Admin login page is not working anymore. Fix it.



ESSENTIALS & ADDONS --- 
------------------------------
- ADD the Clean Up & Reset button should apply ONLY TO THE ONYX COMMAND PLUGIN AND ITS MODULES. Clean Up & Reset should NOT AFFECT THE ENTIRE DATABASE. ONLY THE PARTS OF IT THAT ARE RELATED TO ONYX COMMAND AND ITS MODULES.
- Remove the border radius styling on the plugin and modules.
- Move the Quick Actions buttons up to the top of the Onyx Command dashboard, next to the Run Optimizer Button. They should all appear inline.
- Add the ability to Name , Clone, Edit, Save and Delete any Announcement bars created with Onyx Command. 
- Optimize the Onyx Command plugin and ALL modules currently in the /modules folder for speed and functionality. Ensure that there is no unintended Javascript blocking, code conflicts, or issues that prevent from smooth operation of both the plugin and all modules. Speed is important. Efficiency is important.
- When clicking buttons in Onyx Essentials, a lightbox is displayed but there is nothing on it.
- The admin login screen is not working - it is blank.
- Onyx Command modules should also be handled by the Deletion Manager, if it is installed and active.
- The URL checker should only run when the button is pressed to find broken URLs on the Onyx Command dashboard, OR when the button is pressed from inside a page or post editing screen. Pushing the button from inside a page or post editing screen, should search for broken links ONLY WITHIN THAT POST OR PAGE CONTENT.
- The Recommended Plugins feature is not working. Allow the user to display a list of curated plugins that would be useful on the current site based on the site's current content, functionality and apparent goals.
- Allow changing the slug of the admin login screen from Onyx Essentials settings
- The Recommended Plugins menu item should be on the Onyx Command menu ONLY on top bar and left admin menu bar.
- Onyx Essentials, add allow toggling Featured Image column on pages and posts list pages. Display the featured image assigned to the post or page as a thumbnail in their individual record.
- The Publish, Update and Save buttons on pages and posts are no longer working or are taking a VERY LONG TIME to work. Correct that.
- The buttons to scan for broken URLs are not displayed on pages or posts edit pages, but they SHOULD be. They should also display on any page builder being used, including Gutenberg.
- Allow editing of the Retention Period on the Deletion Archive with a dropdown menu of choices of 3 Days, 5 Days, 14 Days and 30 Days.
- For Onyx Essentials, add the option to allow viewing of a draft page without being logged in to the site.
- If the Deletion Manager is installed and Activated, ENFORCE interception of ALL DELETE ACTIONS on the current site, and handle them with Deletion Manager instead. If/When the Deletion Manager is deactivated, revert this enforcement back to native function.
- Allow changing the URL to the Admin Dashboard Login screen.
- In the Media Library menu, add a button to delete all image thumbnails, but keep the original file. Also add the Regenerate Thumbnails button which should regenerate all thumbnails for every image. Display a progress bar and status for this action.
- On-demand AI generation of a piece of content. Highlighting the content and clicking the Rewrite button should generate a rewritten version of the highlighted sentence. The AI can use the same API key that is in the Onyx Command API Keys settings.
- In Onyx Essentials, allow the creation and scheduling of multiple Announcement Bars instead of just one.
- Regenerate Thumbnails button on Onyx Essentials is not working.
- If the Clear Cache command is clicked in the Onyx Command menu, don't show a popup confirmation ask window. Just clear the cache, and display a small green wheel of progress icon while the cache is being cleared. Clearing cache should also be able to be executed by clicking a button on the individual page and post pages.
- Add the current website's logo and CSS styles to the WordPress admin login screen.
- Add the ability to disable the Gutenberg builder OR the Classic Editor. Also add the ability to Enable them again. ALSO add the ability to choose the default builder to use when editing a post or page.
- Rename the module Plugin Deletion Manager to just Deletion Manager.
- When cloning a page, do not redirect to the page that is cloned. Instead, create the cloned page in draft mode, and display it in the pages or posts list like usual.
- Add a "clean up" function button to the Onyx Command dashboard. Clikcing the "clean up" button will reset all database records for all modules, and return WordPress to its native functioning state. There should be a complete removal of all stored data in the database.
- For Enforce Strong Passwords - allow this to be enforced by role, not across all users. If a user is not an Administrator or Super Administrator, enforce a strong password for their user account. The user MUST reset their password to be compliant at their next login. They SHOULD NOT BE ABLE TO LOG IN until the password is updated.
- IF the AI Alt Text Manager module is installed, for the Scan for Missing Alt Text section of the Onyx Essentials settings, ADD the ability to generate the Alt Text and apply it to the image - this should function the same way that it does when using the AI Alt Text Manager module for the media library.
- Disable File Editing > CHANGE TO > Disable Plugin, Theme & File Editing. Should be ignored if the logged in user is an Administrator or Super Administrator.
- For Backup & Maintenance - All three backups are EXTREMELY slow, try to fix that and speed it up.
- Remove forcing 2FA entirely from Onyx Essentials (READD LATER)
- CHANGE Delete Unattached Media (ask for confirmation to remove all attached media. Answering Yes will delete all unattached media. Answering No should refuse to delete the unattached media and instead, bring the user to the Media Library and automatically filter by Unattached and list the pages. (MAYBE add a one-click method for attaching unattached media to an unlisted / unindexed holding page?)
- CHANGE - SPEED THIS UP! - Scan for broken URLs (image files or videos, internal and external links, and specify where the content is on the site that needs correcting has which in the results) in the content of the entire site. This should be able to be done on individual posts and pages too, by clicking a button.  Clicking the button on an individual page or post, should search all of the current site's content for broken URLs. After gathering all of the broken URL details, display a list of all broken URLs, the URL type (external, internal, image, document, media file, etc) the page or post title, the date the page or post was published, and provide a link to where the broken URLs are. When a user clicks the link that goes to the page with a broken URL on it, highlight the broken item to make it easy to identify by the user when the page is loaded.
- If an account is blocked either for too many login attempts, or using an incorrect username, RECORD THE USER DATA of the locked account in the database: username used, password used, URLs entered or clicked on, Country of the request's origin, and IP address, and WHY the account was locked (too many login attmepts, or nonexistant username). The saved results should be displayed on a "Blocked Accounts" page. Allow an admin to unblock an account from the list. When an account is unblocked, delete the recorded user information.
- Implement a basic "Under Construction" mode. When Under Construction is toggled ON, block the viewing of the site to all visitors unless they are logged in as an administrator of the site. All pages on the site should display a page with a black background, with the text in white that says "Under Construction" in the center of the page.
- When blocking the IP of an attempted administrator login using a username that doesn't exist, let the block expire after 72 hours.
- Add a Clone link on the pages and posts list pages that will clone the selected post or page. The cloned post or page should be created in draft status by default, with no assigned category or tags. The clone should assign a static author name. The new author name should be the name of the current website.
- If an orphan page is found, suggest and display 3 published posts or pages from the current site that could link to the orphaned page. Provide links to the suggested pages. Suggested pages should be based on common content, titles, meta descriptions, categories, etc.
- Add a button to submit non-indexed pages and posts to Google for indexing.
- Disable Javascript alert dialogs sitewide. Instead, use AJAX messages for success, failure, or confirmation/denial requests.
- Make draft pages and posts visible to all site visitors who have the direct URL to the preview of the saved draft. This only applies to pages and posts in Draft status, other statuses should NOT be affected.
- If a page is in draft mode, block it from appearing in search engines. Once it is Published or Scheduled, remove the block.
- Silently clear all site cache after a file is uploaded to the media library or a page or post are saved, published, or edited or a plugin is installed or updated.
- In the Backup & Maintenance section of Onyx Essentials, for each download option, zip the files to be downloaded FIRST, and then download the zip file automatically. Add a progress bar for each process.
- In the Backup & Maintenance section of Onyx Essentials, correct the issue of the Downloads buttons running twice after clicking. The click should only fire ONCE on click. When the backup is successfully zipped and downloading, change the button text of the Download option back to its original state (i.e. Creating backup, please wait... should become Download Files Only again once the download has started). Zip files generated from the Backup & Maintenance buttons should *NOT* save to the website or webhost. Delete any backup files created from the webhost after 24 hours.
- Add an option to add an "announcement bar" to the very top of the current website if this option is toggled on. Allow settings to be configured such as - background color, text color, font family, font size, scrolling marquee or not, automatic "disable on" date and the ability to schedule an announcement automatically. Include a button on the bar too, which can also be configured on the settings - button text, URL, and basic styling should be able to be assigned to the button. Add toggle ability to hide the bar on desktop, tablet, or mobile - whichever are selected.
- Enforce Hotlink Protection - do NOT allow external websites to link to or display images, documents or videos that are hosted on the current website's domain.
- Disable the ability to Right Click on the website. This option can be ignored for users logged in as Administrator or Super Administrator. 
- FIX THIS BUG - When attempting to download anything within the Deletion Archive, the user is taken to a permission denied error at this URL (example) - /wp-content/oc-deletion-archive/backups/backup_2025-11-29_081715.zip. The same is true for the Download All button.
- In the listed filters on the Deletion Archive page, include the number of how many items of what type there are in the archive, and allow filter the list by clicking on one of the filters. For example, if there are 5 deleted plugins, the Plugins like should read Plugins (5).
- If a file in the Deletion Archive is an image, allow a lightbox preview of the image.
- If an image file is restored from the Deletion Archive, automatically regenerate all of its thumbnails again.
- Under the Deletion Archive page title, the text Retention: 7 days should be able to be changed with a dropdown field. The dropdown field options should be 3 days, 7 days, 14 days and 30 days. The retention timer should be adjusted on all files whenever this setting is changed and saved.
- In Onyx Essentials, do not display a popup confirmation request after Clear Entire Site Cache is clicked
- On the Onyx Command 
- In Onyx Essentials, the Scan for Missing Meta Data, Scan for Missing Alt Text, ALL of the Backup & Maintenance block's buttons, and the Submit to Google buttons are NOT functioning when clicked.
- If the Onyx Command plugin does not exist, is not detected, is not installed, is deleted or is removed in ANY WAY, create a zip file of all remaining files, folders, modules, or database records, and place it in the media library and inform the administrators where it can be found and downloaded. If the Onyx Command plugin is installed once again in the future, allow importing settings and modules from the prepared zip file ONLY. ALL functionality or database records to Onyx Command AND its modules, from the current WordPress site AND database. Native functionality on the WordPress site should be reverted to its original state and working order.
- Replace the WordPress logo and URL from the wp-admin login page with the current site's logo
- After uploading an image, automatically compress and optimize the image and all of it's thumbnails. Compression should be as small as possible (IMPORTANT) but should NOT sacrifice quality. Maintain at least 65% quality when compressing images.
