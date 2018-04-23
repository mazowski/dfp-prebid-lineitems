# dfp-prebid-lineitems
Basic PHP script that sets up DFP line items for Prebid for high granularity ($0.01 increments from $0.01 to $20.00). Because of the hard 450 line items limit the script will split these into 5 orders each containing 400 line items. 

Please note that script needs a bit of manual editing to work, it also doesn't support being run multiple times since it will exit if we try to create a line item that already exists. Still, it is faster than setting up 2 000 line items manually. Enjoy :) 

## Getting started ##

* Set up DFP API access via a service account (https://developers.google.com/doubleclick-publishers/docs/authentication#service)
* Download the Googleads PHP library to a new directory (https://github.com/googleads/googleads-php-lib) and follow setup instructions
* Set up Prebid creatives in DFP (http://prebid.org/adops/step-by-step.html#step-2-add-a-creative). Make sure you have a copy for each of the ad units you have on your page. Note the creative IDs. 
* Place PHP file in the root of the new dir and run from the command line. Estimated completion time is a couple of hours.
