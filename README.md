Module for webtrees genealogy software. A Research Tasks Report
===============================================================

[![Latest Release](https://img.shields.io/github/release/elysch/webtrees-mitalteli-report-researchtasks.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.0.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.3.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-report-researchtasks/total.svg)]()
[![image](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-report-researchtasks/latest/total)][1]

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?business=EU37HN97QD9EU&no_recurring=0&currency_code=MXN)

Description
------------
This module adds a report of Research Tasks to webtrees.

It overrides some core Webtrees methods, so you can install it as any other module. It's quite ugly the need to access private properties, methods and constants from parent classes (in an object oriented software development perspective). There is no way to avoid using Reflection.

Webtrees 2.2.5 introduces some breaking changes. The module got fixed for them on version 3.1.5.
Webtrees 2.3.0 introduces some new breaking changes. The module got fixed for the development version of them on version 3.3.0.

Since version 3.4.2 the report parameters page has an "Open the report in a new tab" option (HTML or PDF). In webtrees 2.0/2.1 the selected options are also kept when you come back to the page with the browser's back button.

Installation & upgrading
------------------------
Unpack the zip file and place the mitalteli-report-researchtasks folder in the modules_v4 folder of webtrees. Upload the newly added folder to your server. It is activated by default and will work immediately. No additional configuration is required.

*NOTE: The directory name must have a maximum length of 30 characters.*

Default values (administration)
-------------------------------
Administrators can define the values of the parameters page used when it is opened "fresh" (from the menu or a link):
Control panel > Modules > Reports > Research tasks report > Preferences ("Default values").
- Reload and back/forward navigation keep what the visitor had selected; the defaults are not applied then.
- The individual, the family and the dates are not configurable (they depend on each visit).
- "Restore the original values" removes the saved defaults.

Translation
-----------
This module contains a few translatable textstrings. Copy the file es.php in the resources/lang folder and replace the Spanish text with the translation into your own language. Use the official two-letter language code as file name. Look in the webtrees folder resources/lang to find the correct code.

It would be great if you could share to the community the translated file by [creating a new issue on GitHub][3].

Bugs & feature requests
-------------------------
If you experience any bugs you can [create a new issue on GitHub][3].


## Obfuscation (`debug_obfuscate=1`) - public on purpose

`&debug_obfuscate=1` is available to **every user who can run the report**, by design. It is a privacy
feature, not a debug leak: it replaces every word with random letters of the same length so the
*appearance* of a report can be shared with strangers (forums, bug reports, screenshots) without
revealing its content. It only handles data the user can already see and outputs less information than
the real report, so it must not be restricted to admins. It must, however, **fail closed**: if
obfuscation is requested but cannot be honoured, the request must be refused instead of returning real
data. It works for HTML and PDF on every supported webtrees version; on 2.3 the PDF is obfuscated after
layout with width-preserving letters, external links and images are dropped. Limits: word lengths and word count are preserved on purpose (layout fidelity).

 [1]: https://github.com/elysch/webtrees-mitalteli-report-researchtasks/releases/latest
 [2]: https://webtrees.github.io/download
 [3]: https://github.com/elysch/webtrees-mitalteli-report-researchtasks/issues?state=open
