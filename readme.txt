Enrolment in Moodle using Braintree payment gateway for paid courses

This plugin helps admins and webmasters use Braintree as the payment gateway.
Braintree is one of the populer payment gateways. This plugin has all the 
settings for development as well as for production usage. Its
easy to install, set up and effective. Payment Vault Facility is 
available in Braintree payment gateway.

Creating Merchant Account :

1) Create account at https://www.braintreepayments.com/.

2) Complete your merchant profile details from https://signups.braintreepayments.com.

3) Login to your Braintree account to find your Merchant ID. Copy and paste it into your Course enrolment settings for Braintree.

4) Login to your Braintree account to find your Public Key. Copy and paste it into your Course enrolment settings for Braintree.

5) Login to your Braintree account to find your Private Key. Copy and paste it into your Course enrolment settings for Braintree.

6) For testing use the Braintree sandbox.

Now you are done with merchant account set up.

Installation Guidence : 

Login to your moodle site as an “admin user” and follow the steps.

1) Upload the zip package from Site administration > Plugins > Install plugins.
Choose Plugin type 'Enrolment method (enrol)'. Upload the ZIP package, check the
acknowledgement and install.

2) Go to Enrolments > Manage enrol plugins > Enable 'Braintree' from list

3) Click 'Settings' which will lead to the settings page of the plugin

4) Provide merchant credentials for Braintree. Note that, you will get all the details from
your merchant account. Now select the checkbox as per requirement. Save the settings.

5) Select any course from course listing page.

6) Go to Course administration > Users > Enrolment methods > Add method 'Braintree' from 
the dropdown. Set 'Custom instance name', 'Enrol cost' etc and add the method.

This completes all the steps from the administrator end. Now registered users can login
to the Moodle site and view the course after a successful payment.
