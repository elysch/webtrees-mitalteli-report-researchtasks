Module for webtrees genealogy software. A Research Tasks Report
===============================================================

[![Latest Release](https://img.shields.io/github/release/elysch/webtrees-mitalteli-report-researchtasks.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-report-researchtasks/total.svg)]()
[![image](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-report-researchtasks/latest/total)][1]

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?business=EU37HN97QD9EU&no_recurring=0&currency_code=MXN)

Description
------------
This module adds a report of Research Tasks to webtrees.

It overrides some core Webtrees methods, so you can install it as any other module. It's quite ugly the need to access private properties, methods and constants from parent classes (in an object oriented software development perspective). There is no way to avoid using Reflection.

Installation & upgrading
------------------------
Unpack the zip file and place the mitalteli-report-researchtasks folder in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default and will work immediately. No additional configuration is required.

*NOTE: The directory name must have a maximum length of 30 characters.*

Translation
-----------
This module contains a few translatable textstrings. Copy the file es.php in the resources/lang folder and replace the Spanish text with the translation into your own language. Use the official two-letter language code as file name. Look in the webtrees folder resources/lang to find the correct code.

It would be great if you could share to the community the translated file by [creating a new issue on GitHub][3].

Bugs & feature requests
-------------------------
If you experience any bugs you can [create a new issue on GitHub][3].

 [1]: https://github.com/elysch/webtrees-mitalteli-report-researchtasks/releases/latest
 [2]: https://webtrees.github.io/download
 [3]: https://github.com/elysch/webtrees-mitalteli-report-researchtasks/issues?state=open
