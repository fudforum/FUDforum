Steps to convert a punBB forum to FUDforum:

1. Install and activate the sha2md5_auth.plugin from FUDforum's Plugin Manager Admin
   Control Panel. This plugin will convert passwords from punBB's format to FUDform's 
   format as users try to log in to the form.

2. Edit the punBB.php script and specify the FULL path to your punBB "config.php" file. 
   For example:
	$PUNBB_CFG = "C:/htdocs/punbb/config.php";

3. Run the punBB.php script. If you intend to run this script via the console, please 
   UNLOCK FUDforum's files first. We recommend running this script via the web unless
   the forum you are importing is very large.

4. Login to the forum, go to the "Admin Conrol Panel" -> "Forum Consistency" and execute.

5. Test and report problems on the support forum at http://fudforum.org

IMPORTANT NOTE: DO NOT UPGRADE FUDFORUM UNTIL ALL YOUR USERS ARE MIGRATED TO THE NEW PASSWORD 
                FORMAT. AFTER AN UPGRADE, USERS WILL NOT BE ABLE TO LOGIN WITH THEIR OLD PUNBB
                PASSWORDS ANYMORE AND WILL HAVE TO REQUEST NEW PASSWORDS BY MAIL!

Best of luck!

Frank
