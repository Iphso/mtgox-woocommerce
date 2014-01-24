![MtGox](https://payment.mtgox.com/img/mt.gox.png)

MtGox Payment Gateway for WooCommerce
=========

This is a WordPress plug-in that integrates with WooCommerce and does two things:

1. It adds MtGox as a payment gateway that your customers can use to pay for purchases
1. It adds Bitcoin as a currency that you can configure for your shop

Download it now and start accepting Bitcoin payments using the world's largest Bitcoin exchange as your payment processor.

You can customize many settings to make the plug-in work the way you want it. See the screenshots a little further down the page for more information on what you can configure.

Installation
----

First you need to get an API key from MtGox. This is easy. Visit <https://www.mtgox.com> and sign in to your account. Next, go to the _Security Center_ and scroll down to _Advanced API Key Creation_ to get started. Simply follow the instructions and make a note of the _API Key_ and _Secret_. You'll need these later.

Once you have your API key and secret ready, follow these steps:

1. Create a new folder in your `wordpress/wp-content/plugins` folder and call it `woocommerce-mtgox`
1. Place the `assets` folder and the `class-wc-gateway-mtgox.php` file into the folder
1. Log in to WordPress and enable the plug-in on the _Plugins_ page
1. Change to the WooCommerce settings page, and on the _Payment Gateways_ tab, click _MtGox_
1. Change the settings to your liking and make sure to fill in _API Key_ and _API Secret_ with the values you obtained from MtGox

That's it. You can now let your customers start using Bitcoin to pay for their purchases. Enjoy!


Version
----
1.0 BETA

Please note that the plug-in is currently a beta version. It has passed initial testing and in-house verification.

Support
----
Please report any problems on GitHub, or contact <nils@tibanne.com>.

License
----

Modified BSD license. Please see the `LICENSE` file for details.

Screenshots
----
* [Settings](http://i.imgur.com/BiN6KTV.png)
* [Checkout](http://i.imgur.com/S4LeG5R.png)
