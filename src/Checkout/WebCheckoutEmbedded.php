<?php
// SADAD Payment Gateway SDK for PHP
// Built by Louis Innovations (www.louis-innovations.com)

namespace LouisInnovations\Sadad\Checkout;

/**
 * SADAD Embedded (Secure) Checkout — v2.2
 *
 * Identical to WebCheckoutV2 in all respects except that it posts to the
 * secure embedded checkout URL (v2.2) rather than the standard v2.1 URL.
 */
class WebCheckoutEmbedded extends WebCheckoutV2
{
    protected string $checkoutVersion = 'v2.2';
}
