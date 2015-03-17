This plugin adds "Sign-in with Association Online" button on the login page. The first time the user login with a social account, a new Moodle account is created.

### Installation:

1. add the plugin into /auth/association_online/
2. in the Moodle administration, enable the plugin (Admin block > Plugins > Authentication)
3. in the plugin settings, follow the displayed instructions.

#### Displaying login link

In order to display a link to "Login with your _[Association Name]_ account", place the following code in your login template where you would like the button to be shown:

```
<?php
require_once( $CFG->dirroot . '/auth/association_online/lib.php' );
auth_association_online_display_buttons();
?>
```

### (Almost) Automatically log in authenticated users

For users who are authenticated on an AO site, this plugin provides a mechanism to redirect to Moodle
in such a way as to appear to automatically log them in. If you visit the url "/auth/association_online/force_login.php"
on the Moodle site, then it will do the following:

1. Redirect you to the AO single sign on page.
2a. If you are not logged in, it will ask you to sign in.
2b. If you are logged in, it will redirect you back to Moodle with a valid auth token, signing you in to Moodle.

### Troubleshooting

#### "Auth2 connection not setup correctly"

If this error appears, it is either because this plugin is [not enabled on your site](https://docs.moodle.org/28/en/Managing_authentication#Setting_the_authentication_method.28s.29),
or the Client ID has not been specified in your Moodle settings (Administration > Site administration > Plugins > Authentication > Manage authentication > Association Online).

### Credits
* [mouneyrac, developer of original auth_googleoauth2 plugin](https://github.com/mouneyrac/auth_googleoauth2)
* [Contributors](https://github.com/mouneyrac/auth_googleoauth2/graphs/contributors)
