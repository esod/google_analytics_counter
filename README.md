Google Analytics Counter 8.x-1.0-alpha1
---------------------------------------

Table of Contents
-----------------

* Introduction
* Goals
* Installation
* Create a Project in Google.
* The custom Google Analytics Counter field.
* Project status

### Introduction

Google Analytics Counter is a scalable, lightweight page view counter drawing
on data collected by Google Analytics.

The primary use for this module is to:

- Add a block which contains the count of pageviews from Google Analytics API.

The secondary use for this module is to:

- Use the data Drupal is collecting from Google Analytics API for other things, like search.

### Goals

- A comprehensive and understandable solution to storing Google Analytics Pageviews in Drupal.
- A themable block in Drupal which displays Google Analytics Pageviews.
- A filter which adds the pageviews inline.
- Make the pageviews available in views.
  - This is possible two ways:
  - By creating views based on the Google Analytics Counter
  - By adding a custom field to a content type which is populated during cron runs
    which can then be added to views like any other field.

### Installation

1. Copy/upload the google analytics counter module to the modules directory of
   your Drupal installation.

2. Enable the 'Google Analytics Counter' module in 'Extend'.
   (/admin/modules)

3. Set up user permissions. (/admin/people/permissions#module-google_analytics_counter)

4. Go to the authentication page. (/admin/config/system/google-analytics-counter/authentication)

5. Add your Google Project Client ID, Client Secret and Authorized Redirect URI. 
   See "Creating a Project in Google" in the next section of this document 
   for more information on setting up Google Analytics.

6. Optionally add your Google Project's machine name. This field helps to take 
   you directly to your Analytics API page to view Google's quotas.

7. Click Save configuration.

8. Click the large button at the top of the page labeled 'Authenticate With Google'.

9. Select the google account to which you would like to authenticate.

10. Fill in credentials if requested by Google.
    Click Allow.

11. If all goes well, you will be returned to Authorized REdirect URI which
    should be the authentication page. 
    (/admin/config/system/google-analytics-counter/authentication)
    Select a view from the select list in Google Views. Another way to tell
    if all has gone well with authentication is if you see your Google Analytics
    profiles listed in Google Vviews. If you did not successfully authenticate 
    with Google the only option in Google Views is 'Unauthenticated'.
    Click Save configuration.

12. Go to the Dashboard (/admin/config/system/google-analytics-counter/dashboard)

13. Note: most of the numbers are 0 until you run cron.

14. Run cron. Generally speaking, it is a good idea to run cron continuously
    with a scheduler like Jenkins to keep pageviews data up to date.

15. Place a Google Analytics Counter block on your site.
    (/admin/structure/block).

16. Or Enable the text filter (More to come).

17. Or Add the custom Google Analytics Counter field to a content type and use
    the custom field like any other node field; e.g. view displays and views.
    See 'The custom Google Analytics Counter field.' below for more information.

### Create a Project in Google.

1. Go to https://console.developers.google.com/cloud-resource-manager
   Click Create project.

2. Name your project.
   Click Create. Wait several moments for your project to be created.

3. Go to https://console.developers.google.com/apis/dashboard
   You will most likely be directed to your project, or select your project by
   selecting your project's name in the upper left corner of the browser next to
   where it says Google APIS.

4. Click Enable APIS and services on the Google APIs dashboard.
   Search for Analytics API.
   Click Analytics API.
   On the proceeding page, click Enable.

5. You will be sent back to the Google APIs page. Click Credentials in the 
   left-hand column.

6. Click Create credentials. Select OAUTH client ID.

7. Click Configure consent screen.
   Fill out the OAuth consent screen form.
   Click Save.

8. You are sent back to the page where you can select your Application type.
   Select Web application.

9. Name it in the Name field.

10. Leave the Authorized JavaScript origins field blank.

11. Add a url to the Authorized redirect URIs.
    Example: http://localhost/d8/admin/config/system/google-analytics-counter/authentication
    Click Create.
    Click Create again.

12. Note your client ID and client secret.
    You may also get your client ID and client secret by clicking the pencil icon
    on the right side of the Credentials page next to your application name.

13. Copy your client ID client secret, and Authorized redirect URIs from Google
     and add them to analytics authentication form in the module.
     (/admin/config/system/google-analytics-counter/authentication).

### The custom Google Analytics Counter field.

A custom Google Analytics Counter field has been added to version 8.x-3.0-alpha6
and above. Having the Google pageviews in a custom fields makes adding Google
pageviews to Drupal views the same as adding any other node field to a view.

After installing version 8.x-3.0-alpha6 or above or running the module updates
for existing installations with `update.php` or `drush updatedb` The field 
storage for the Google Analytics Counter field (machine name = 
field_google_analytics_counter) is added to the system.

The custom field storage can be ignored without concern. The module will 
continue to store analytics data in the custom tables, but not in any field tables.

#### To add the custom field, follow these steps.

1. Go to a content type's Manage fields Tab.
   E.g. /admin/structure/types/manage/article/fields.

2. Click +Add field.

3. Re-use the field by clicking `- Select an existing field -` and selecting
   the custom field `Text (plain): field_google_analytics_counter`. Change the 
   field Label as you see fit.

4. Click Save and continue.
   Add Help text such as `This field stores Google Analytics pageviews.`
   or anything else as you see fit. Or leave the Help text blank.

5. No need to add a Default value since the field is populated with Data from Google.
   Adding 0 is okay for now, although I'm planning on making this field readonly
   in future releases.

6. Click Save settings and proceed to add the field to Manage form display 
   and Manage display as you see fit.

7. Note that the field has to, at least, be in the Manage form display in order
   for the field to be used in views.

8. Now that the custom field has been added to the content type, the pageviews
   can be added to Drupal views like any other node field.

### Project Status

- [Port google analytics counter module to drupal 8](https://www.drupal.org/project/google_analytics_counter/issues/2695915)
Author: Tomas Fulopp (Vacilando) for Drupal 7, Eric Sod (esod) for Drupal 8.

