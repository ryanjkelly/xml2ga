# xml2ga
This is a PHP script that takes XML order data from UltraCart and posts it to Google Analytics via the Measurement Protocol.

https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide

The script should work by just replacing the property ID (UA-######-###) with your own. There are custom dimensions used in this script, but they shouldn't affect your reports if you don't use them.

Refunds are recorded into Google Analytics with **"ref-"** appended to the transaction ID, and auto orders with **"auto-"**, so you can filter them easily in custom reports.

Before using the script, make sure the time zone in your Google Analytics property matches UltraCart's server time zone, which is EST. Also, merchants that get sales 24/7 will notice that daily revenue from Google Analytics and UltraCart sometimes won't match up. This is because, for example, if a customer orders something from UltraCart at 11:57pm, the order may not get recorded into Google Analytics until 12:02am, which it is then the following day.
