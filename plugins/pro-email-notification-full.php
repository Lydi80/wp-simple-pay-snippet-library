<?php
/**
 * Plugin Name: WP Simple Pay - Email Admin Notification (Extra Data)
 * Plugin URI: https://wpsimplepay.com
 * Author: Sandhills Development, LLC
 * Author URI: https://sandhillsdev.com
 * Description: Notify the site admin of a new purchase.
 * Version: 1.0
 */

/**
 * Email the site admin when a Subscription or PaymentIntent is created.
 *
 * Note: Requires Webhooks.
 * @link https://docs.wpsimplepay.com/articles/webhooks/
 *
 * @link https://stripe.com/docs/api/subscriptions/object
 * @link https://stripe.com/docs/api/payment_intent/object
 *
 * @param \Stripe\Event                              $event Stripe Event.
 * @param \Stripe\Subscription|\Stripe\PaymentIntent $object Stripe Subscription or PaymentIntent
 */
function simpay_custom_email_admin( $event, $object ) {
	$to = get_bloginfo( 'admin_email' );

	// Subscription payment.
	if ( is_a( $object, '\Stripe\Subscription' ) ) {
		$subject = 'New WP Simple Pay Subscription';

		// Get the Plan name.
		$plan = $object->plan->nickname;

		// Find the payment amount.
		try {
			$latest_invoice = \SimplePay\Core\Payments\Stripe_API::request( 'Invoice', 'retrieve', $object->latest_invoice );
			$amount         = $latest_invoice->amount_paid;
		} catch( \Exception $e ) {
			$amount = $object->plan->amount;
		}

		$body = sprintf(
			"A new %1\$s payment to start a subscription of %2\$s was just received.\n\n",
			simpay_format_currency( simpay_convert_amount_to_dollars( $amount ) ),
			$plan
		);
	// Single payment.
	} else {
		$subject = 'New WP Simple Pay Payment';

		// Get the description.
		$description = $object->description;

		// Get the payment amount.
		$amount = $object->charges->data[0]->amount;

		$body = sprintf(
			"A new %1\$s payment for %2\$s was just received.\n\n",
			simpay_format_currency( simpay_convert_amount_to_dollars( $amount ) ),
			$description
		);
	}

	// Add Customer information to body:
	if ( $object->customer->name ) {
		$body .= "{$object->customer->name}\n";
	}

	$body .= "{$object->customer->email}\n";

	if ( $object->customer->phone ) {
		$body .= "{$object->customer->phone}\n";
	}

	if ( isset( $object->customer->address ) ) {
		$address = $object->customer->address;

		$body .= "\n";
		$body .= $address->line1 . ' ' . $address->line2 . "\n";
		$body .= $address->city . ', ' . $address->state . ' ' . $address->postal_code . ' ' . $address->country;
		$body .= "\n";
	}

	$body .= "\nAdditional:\n";

	$internal = array(
		'simpay_form_id',
		'simpay_subscription_key',
		'simpay_charge_max',
		'simpay_charge_count',
	);

	// Add all metadata to body.
	$metadata = $object->toArray()['metadata'];

	foreach ( $metadata as $key => $value ) {
		if ( in_array( $key, $internal, true ) ) {
			continue;
		}

		$body .= sprintf( "%s: %s\n", $key, $value );
	}

	wp_mail( $to, $subject, $body );
}
add_action( 'simpay_webhook_subscription_created', __NAMESPACE__ . '\\simpay_custom_email_admin', 10, 2 );
add_action( 'simpay_webhook_payment_intent_succeeded', __NAMESPACE__ . '\\simpay_custom_email_admin', 10, 2 );
