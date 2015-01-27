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

The

### Credits
* [mouneyrac, developer of original auth_googleoauth2 plugin](https://github.com/mouneyrac/auth_googleoauth2)
* [Contributors](https://github.com/mouneyrac/auth_googleoauth2/graphs/contributors)
