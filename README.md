# xml2ga
This is a PHP script that takes XML order data from UltraCart and posts it to Google Analytics via the Measurement Protocol.

https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide

Before you start, I recommend that you upgrade your server to PHP 5.6 or higher.

## Get Started

1. Enable `Ecommerce` as well as `Enhanced Ecommerce` on your Google Analytics property view.
  * These 2 options are just simple ON/OFF switches in your **Ecommerce Settings**.
2. Create 2 custom dimensions and take note of the index numbers.
  1. Name = `Product Action`, Scope = `Hit`, Active = `Checked`
  2. Name = `Order Type`, Scope = `Hit`, Active = `Checked`
3. Create 1 custom metric and take note of the index number.
  1. Name = `Original Revenue`, Scope = `Hit`, Formatting Type = `Currency (Decimal)`, Active = `Checked`
4. Open xml2ga.php and edit the following constants:
  * UA_PROD = `the ID of your live/production GA property`
  * UA_TEST = `the ID of your test GA property`
  * CD_ACTION = `the index nubmer of your Product Action custom dimension`
  * CD_TYPE = `the index nubmer of your Order Type custom dimension`
  * CM_REV = `the index nubmer of your Original Revenue custom metric`

## Go Live

Upload your **xml2ga.php** file to 2 different locations on your server. Use one as the test script and the other as the live script. **The live script should have the constants `DEV`, `TEST` and `DEBUG` all set to `FALSE`.**

Here is the documentation for pointing UltraCart to your **xml2ga.php** files:

http://docs.ultracart.com/display/ucdoc/XML+Postback#XMLPostback-PostBackConfiguration

Place the URL to your **live script** into the `Transmit to URL when stage changes` field, and the URL to your **test script** into the `Transmit to URL when TEST order stage changes` field.

## Reporting

When creating custom reports in Google Analytics, remember that you can now filter your orders using **Product Action** (purchase, refund, remove) and **Order Type** (digital order, physical order, auto order), as well as see the **Original Revenue** of a transaction after it has been refunded.

## NOTICE!

Before using the script, make sure the time zone in your Google Analytics property matches UltraCart's server time zone, which is EST. Also, merchants that get sales 24/7 will notice that daily revenue from Google Analytics and UltraCart sometimes won't match up. This is because, for example, if a customer orders something from UltraCart at 11:57pm, the order may not get recorded into Google Analytics until 12:02am, which it is then the following day.
