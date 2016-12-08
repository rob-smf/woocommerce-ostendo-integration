<?php
/**
 * Plugin Name: Woocommerce Ostendo Integration
 * Plugin URI: https://github.com/rob-smf/woocommerce-ostendo-integration
 * Description: A plugin to import stock quantities from Ostendo endpoint and send XML sales orders upon payment.
 * Author: Robert Schillinger
 * Author URI: http://sanbornagency.com
 * Version: 1.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

if ( ! class_exists( 'WC_Integration_Ostendo' ) ) :

class WC_Integration_Ostendo {
	/**
	* Construct the plugin.
	*/
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}
	/**
	* Initialize the plugin.
	*/
	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class.
			include_once 'class-wc-ostendo.php';
			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			// throw an admin error if you like
		}
	}
	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_Integration_Ostendo_Integration';
		return $integrations;
	}


}
$WC_Integration_Ostendo = new WC_Integration_Ostendo( __FILE__ );




/**
*  Takes and order ID from woocommerce and puts together
*  an outgoing sales order in XML format
*/

function ostendo_email($order_id){

	// Get config fields
	$ostendoData = get_option('woocommerce_integration-ostendo_settings');

	// Make sure the outgoing sales order is enabled
	if ( $ostendoData['enable_ostendo_sales_order'] == 'yes' ) :

		// Create a new order with the id so we can access values and access all items in order
		$order = new WC_Order( $order_id );
	    $products = $order->get_items();
		$output = [];

		// Put together associative array with our data
		$output['ORDERNO'] = $order->get_order_number();
		$output['ORDERDATE'] = date('m/d/Y', strtotime($order->order_date));
		$output['SUBTOTAL'] = number_format($order->get_subtotal(), 2, '.', '');
		$output['FREIGHT'] = number_format($order->get_total_shipping(), 2, '.', '');
		$output['TOTAL'] = number_format($order->order_total, 2, '.', '');
		$output['PAYMENTMETHOD'] = $order->payment_method_title;
		$output['NOTE'] = $order->customer_message;
		$output['EMAIL'] = $order->billing_email;
		$output['PHONE'] = $order->billing_phone;
		$output['BILLINGADDRESSFIRSTNAME'] = $order->billing_first_name;
		$output['BILLINGADDRESSLASTNAME'] = $order->billing_last_name;
		$output['BILLINGADDRESS1'] = $order->billing_address_1;
		$output['BILLINGADDRESS2'] = $order->billing_address_2;
		$output['BILLINGADDRESSCITY'] = $order->billing_city;
		$output['BILLINGADDRESSSTATE'] = $order->billing_state;
		$output['BILLINGADDRESSPOSTCODE'] = $order->billing_postcode;
		$output['BILLINGADDRESSCOUNTRY'] = countryCodeToName($order->billing_country);
		$output['SHIPPINGADDRESSFIRSTNAME'] = $order->shipping_first_name;
		$output['SHIPPINGADDRESSLASTNAME'] = $order->shipping_last_name;
		$output['SHIPPINGADDRESS1'] = $order->shipping_address_1;
		$output['SHIPPINGADDRESS2'] = $order->shipping_address_2;
		$output['SHIPPINGADDRESSCITY'] = $order->shipping_city;
		$output['SHIPPINGADDRESSSTATE'] = $order->shipping_state;
		$output['SHIPPINGADDRESSPOSTCODE'] = $order->shipping_postcode;
		$output['SHIPPINGADDRESSCOUNTRY'] = countryCodeToName($order->shipping_country);

		// Delete old xml, set top level elements for new doc
		$plugin_path = __DIR__;
		unlink($plugin_path.'xml/WebOrder.xml');
		$xml = new SimpleXMLElement('<SALESORDER></SALESORDER>');
		$webOrder = $xml->addChild('ORDERHEADER');

		// Iterate through XML as <key>value></key>
		foreach($output as $key => $value){
			$webOrder->addChild($key, $value);
		}

		// Now let's walk through each item in the order
		foreach($products as $product){

			// Add new child element and grab variation id
			$orderItem = $webOrder->addChild('ORDERLINE');
			$product_variation_id = $product['variation_id'];

			// If it's got a variation id it's a variable product, if not, simple product
			if ($product_variation_id) {
				$prodCode = new WC_Product($product['variation_id']);
			} else {
				$prodCode = new WC_Product($product['product_id']);
			}

			// Get product sku then set up array of product data for XML
			$sku = $prodCode->get_sku();

			$items['PRODUCTCODE'] = $sku;
			$items['PRODUCTDESC'] = $product['name'];
			$items['QUANTITY'] = $product['qty'];
			$items['UNITPRICE'] = number_format($product['line_subtotal'] / $product['qty'], 2, '.', '');
			$items['LINETOTAL'] = number_format($product['line_total'], 2, '.', '');

			// Print product data to XML
			foreach( $items as $itemKey => $itemValue){
				$orderItem->addChild($itemKey, $itemValue);
			}
		}

		// Create new doc and load XML data
		$dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($xml->asXML());

		// Write, save, close
		$dom->save($plugin_path.'/xml/WebOrder.xml',LIBXML_NOEMPTYTAG);
		$contents = file_get_contents($plugin_path.'/xml/WebOrder.xml');
		$contents = htmlspecialchars_decode($contents);
		$outfile = fopen($plugin_path.'/xml/WebOrder.xml', 'w');
		fwrite($outfile, $contents);
		fclose($outfile);

		// Send XML to with email data specified in Ostendo config.
		$recipient = $ostendoData['email_recipient'];
		$subject = $ostendoData['email_subject'];
		$message = $ostendoData['email_message'];
		$headers = 'From: info@armadillo-co.com<info@armadillo-co.com>'."\r\n".
		'Reply-To: test@test.com'."\r\n" .
		'X-Mailer: PHP/' . phpversion();

	    wp_mail($recipient, $subject, $message, $headers, array($plugin_path.'/xml/WebOrder.xml'));

	endif;
}

add_action('woocommerce_payment_complete', 'ostendo_email');
/**
*  Hits the API endpoint specified in Ostendo config,
*  travels through response and updates stock quantities
*/


function ostendo_import(){

	// Get config fields
	$ostendoData = get_option('woocommerce_integration-ostendo_settings');

	// Make sure it's enabled
	if ( $ostendoData['enable_ostendo_import'] == 'yes' ):

		// Clean up URL, set up timestamp and log
		$url = htmlspecialchars_decode($ostendoData['api_endpoint']);
		$timestamp = "Date: ".date('Y-m-d H:i:s')."\nAPI Endpoint: ".$url."\n";
		$log = '';
		$success = 0;
		$int_failure = 0;
		$sku_failure = 0;

		// Start call
		$ch = curl_init();
		// Will return the response
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Set the url
		curl_setopt($ch, CURLOPT_URL,$url);
		$result=curl_exec($ch);
		curl_close($ch);

		// Decode the response and turn into assoc. array
		$data = json_decode($result, true);

		if (is_array($data)){
		// Start walking
			foreach($data as $item){

				$rug = $item['ITEMCODE'];
				$new_qty = $item['FREEQTY'];
				$str_new_qty = (string)$new_qty;
				$rug_id = wc_get_product_id_by_sku($rug);

				// Make sure the item code exists, will return 0 if it doesn't, then check manually mapped SKUs
				if ( $rug_id != 0 ){

					$backorders = get_post_meta($rug_id, '_backorders', 1);
					// If less than 0, change stock to 0, then change stock status depending on backorder
					if ($new_qty <= 0){

						update_post_meta($rug_id, '_stock', '0');

						if ($backorders = 'no' ){

							update_post_meta($rug_id, '_stock_status', 'outofstock');
							$log .= "ITEMCODE: ".$rug." imported successfully. Stock updated to 0. \n\n";
							$success++;

						} elseif ( ($backorders = 'notify') || ($backorders = 'yes') ){

							update_post_meta($rug_id, '_stock_status', 'instock');
							$log .= "ITEMCODE: ".$rug." imported successfully. Stock updated to 0. \n\n";
							$success++;

						}
					// Above 0, update with new quantity
					} elseif ($new_qty > 0){

						update_post_meta($rug_id, '_stock', $str_new_qty);
						update_post_meta($rug_id, '_stock_status', 'instock');
						$log .= "ITEMCODE: ".$rug." imported successfully. Stock updated to ".$new_qty.".\n\n";
						$success++;

					} else {
						$log .= "ERROR! ITEMCODE: ".$rug." import failed. \nCAUSE: FREEQTY was not an integer.\n\n";
						$int_failure++;
					}

				} else {
					$log .= "ERROR! ITEMCODE: ".$rug." import failed. \nCAUSE: ITEMCODE does not exist on website.\n\n";
					$sku_failure++;
				}
			}
			$items_processed = $success." items successfully imported.\n"
								.$int_failure." failed import because FREEQTY was not an integer.\n"
								.$sku_failure." failed imported because SKU does not exist on site.\n\n";
		} else {
			$items_processed = "Issue with API endpoint caused 0 items to be imported. Please verify URL is correct and is not timing out.\n";
		}

		// Delete old log, write new log with timestamp and errors
		$plugin_path = __DIR__;
		unlink($plugin_path.'/log/e.txt');
		$filelog = fopen($plugin_path.'/log/e.txt', 'w');
		fwrite($filelog, $timestamp.$items_processed.$log);
		fclose($filelog);

		$recipient = $ostendoData['log_recipient'];
		$subject = "Ostendo Import: " . date('Y-m-d H:i:s');
		$message = "Ostendo import completed: " . date('Y-m-d H:i:s');
		$headers = 'From: info@armadillo-co.com<info@armadillo-co.com>'."\r\n".
		'Reply-To: test@test.com'."\r\n" .
		'X-Mailer: PHP/' . phpversion();

	    wp_mail($recipient, $subject, $message, $headers, array($plugin_path.'/log/e.txt'));

	endif;
}

add_action('import_ostendo_stock', 'ostendo_import');

// Swaps out country codes to names
function countryCodeToName($code) {
    switch ($code) {
        case 'AF': return 'Afghanistan';
        case 'AX': return 'Aland Islands';
        case 'AL': return 'Albania';
        case 'DZ': return 'Algeria';
        case 'AS': return 'American Samoa';
        case 'AD': return 'Andorra';
        case 'AO': return 'Angola';
        case 'AI': return 'Anguilla';
        case 'AQ': return 'Antarctica';
        case 'AG': return 'Antigua and Barbuda';
        case 'AR': return 'Argentina';
        case 'AM': return 'Armenia';
        case 'AW': return 'Aruba';
        case 'AU': return 'Australia';
        case 'AT': return 'Austria';
        case 'AZ': return 'Azerbaijan';
        case 'BS': return 'Bahamas the';
        case 'BH': return 'Bahrain';
        case 'BD': return 'Bangladesh';
        case 'BB': return 'Barbados';
        case 'BY': return 'Belarus';
        case 'BE': return 'Belgium';
        case 'BZ': return 'Belize';
        case 'BJ': return 'Benin';
        case 'BM': return 'Bermuda';
        case 'BT': return 'Bhutan';
        case 'BO': return 'Bolivia';
        case 'BA': return 'Bosnia and Herzegovina';
        case 'BW': return 'Botswana';
        case 'BV': return 'Bouvet Island (Bouvetoya)';
        case 'BR': return 'Brazil';
        case 'IO': return 'British Indian Ocean Territory (Chagos Archipelago)';
        case 'VG': return 'British Virgin Islands';
        case 'BN': return 'Brunei Darussalam';
        case 'BG': return 'Bulgaria';
        case 'BF': return 'Burkina Faso';
        case 'BI': return 'Burundi';
        case 'KH': return 'Cambodia';
        case 'CM': return 'Cameroon';
        case 'CA': return 'Canada';
        case 'CV': return 'Cape Verde';
        case 'KY': return 'Cayman Islands';
        case 'CF': return 'Central African Republic';
        case 'TD': return 'Chad';
        case 'CL': return 'Chile';
        case 'CN': return 'China';
        case 'CX': return 'Christmas Island';
        case 'CC': return 'Cocos (Keeling) Islands';
        case 'CO': return 'Colombia';
        case 'KM': return 'Comoros the';
        case 'CD': return 'Congo';
        case 'CG': return 'Congo the';
        case 'CK': return 'Cook Islands';
        case 'CR': return 'Costa Rica';
        case 'CI': return 'Cote d\'Ivoire';
        case 'HR': return 'Croatia';
        case 'CU': return 'Cuba';
        case 'CY': return 'Cyprus';
        case 'CZ': return 'Czech Republic';
        case 'DK': return 'Denmark';
        case 'DJ': return 'Djibouti';
        case 'DM': return 'Dominica';
        case 'DO': return 'Dominican Republic';
        case 'EC': return 'Ecuador';
        case 'EG': return 'Egypt';
        case 'SV': return 'El Salvador';
        case 'GQ': return 'Equatorial Guinea';
        case 'ER': return 'Eritrea';
        case 'EE': return 'Estonia';
        case 'ET': return 'Ethiopia';
        case 'FO': return 'Faroe Islands';
        case 'FK': return 'Falkland Islands (Malvinas)';
        case 'FJ': return 'Fiji the Fiji Islands';
        case 'FI': return 'Finland';
        case 'FR': return 'France, French Republic';
        case 'GF': return 'French Guiana';
        case 'PF': return 'French Polynesia';
        case 'TF': return 'French Southern Territories';
        case 'GA': return 'Gabon';
        case 'GM': return 'Gambia the';
        case 'GE': return 'Georgia';
        case 'DE': return 'Germany';
        case 'GH': return 'Ghana';
        case 'GI': return 'Gibraltar';
        case 'GR': return 'Greece';
        case 'GL': return 'Greenland';
        case 'GD': return 'Grenada';
        case 'GP': return 'Guadeloupe';
        case 'GU': return 'Guam';
        case 'GT': return 'Guatemala';
        case 'GG': return 'Guernsey';
        case 'GN': return 'Guinea';
        case 'GW': return 'Guinea-Bissau';
        case 'GY': return 'Guyana';
        case 'HT': return 'Haiti';
        case 'HM': return 'Heard Island and McDonald Islands';
        case 'VA': return 'Holy See (Vatican City State)';
        case 'HN': return 'Honduras';
        case 'HK': return 'Hong Kong';
        case 'HU': return 'Hungary';
        case 'IS': return 'Iceland';
        case 'IN': return 'India';
        case 'ID': return 'Indonesia';
        case 'IR': return 'Iran';
        case 'IQ': return 'Iraq';
        case 'IE': return 'Ireland';
        case 'IM': return 'Isle of Man';
        case 'IL': return 'Israel';
        case 'IT': return 'Italy';
        case 'JM': return 'Jamaica';
        case 'JP': return 'Japan';
        case 'JE': return 'Jersey';
        case 'JO': return 'Jordan';
        case 'KZ': return 'Kazakhstan';
        case 'KE': return 'Kenya';
        case 'KI': return 'Kiribati';
        case 'KP': return 'Korea';
        case 'KR': return 'Korea';
        case 'KW': return 'Kuwait';
        case 'KG': return 'Kyrgyz Republic';
        case 'LA': return 'Lao';
        case 'LV': return 'Latvia';
        case 'LB': return 'Lebanon';
        case 'LS': return 'Lesotho';
        case 'LR': return 'Liberia';
        case 'LY': return 'Libyan Arab Jamahiriya';
        case 'LI': return 'Liechtenstein';
        case 'LT': return 'Lithuania';
        case 'LU': return 'Luxembourg';
        case 'MO': return 'Macao';
        case 'MK': return 'Macedonia';
        case 'MG': return 'Madagascar';
        case 'MW': return 'Malawi';
        case 'MY': return 'Malaysia';
        case 'MV': return 'Maldives';
        case 'ML': return 'Mali';
        case 'MT': return 'Malta';
        case 'MH': return 'Marshall Islands';
        case 'MQ': return 'Martinique';
        case 'MR': return 'Mauritania';
        case 'MU': return 'Mauritius';
        case 'YT': return 'Mayotte';
        case 'MX': return 'Mexico';
        case 'FM': return 'Micronesia';
        case 'MD': return 'Moldova';
        case 'MC': return 'Monaco';
        case 'MN': return 'Mongolia';
        case 'ME': return 'Montenegro';
        case 'MS': return 'Montserrat';
        case 'MA': return 'Morocco';
        case 'MZ': return 'Mozambique';
        case 'MM': return 'Myanmar';
        case 'NA': return 'Namibia';
        case 'NR': return 'Nauru';
        case 'NP': return 'Nepal';
        case 'AN': return 'Netherlands Antilles';
        case 'NL': return 'Netherlands the';
        case 'NC': return 'New Caledonia';
        case 'NZ': return 'New Zealand';
        case 'NI': return 'Nicaragua';
        case 'NE': return 'Niger';
        case 'NG': return 'Nigeria';
        case 'NU': return 'Niue';
        case 'NF': return 'Norfolk Island';
        case 'MP': return 'Northern Mariana Islands';
        case 'NO': return 'Norway';
        case 'OM': return 'Oman';
        case 'PK': return 'Pakistan';
        case 'PW': return 'Palau';
        case 'PS': return 'Palestinian Territory';
        case 'PA': return 'Panama';
        case 'PG': return 'Papua New Guinea';
        case 'PY': return 'Paraguay';
        case 'PE': return 'Peru';
        case 'PH': return 'Philippines';
        case 'PN': return 'Pitcairn Islands';
        case 'PL': return 'Poland';
        case 'PT': return 'Portugal, Portuguese Republic';
        case 'PR': return 'Puerto Rico';
        case 'QA': return 'Qatar';
        case 'RE': return 'Reunion';
        case 'RO': return 'Romania';
        case 'RU': return 'Russian Federation';
        case 'RW': return 'Rwanda';
        case 'BL': return 'Saint Barthelemy';
        case 'SH': return 'Saint Helena';
        case 'KN': return 'Saint Kitts and Nevis';
        case 'LC': return 'Saint Lucia';
        case 'MF': return 'Saint Martin';
        case 'PM': return 'Saint Pierre and Miquelon';
        case 'VC': return 'Saint Vincent and the Grenadines';
        case 'WS': return 'Samoa';
        case 'SM': return 'San Marino';
        case 'ST': return 'Sao Tome and Principe';
        case 'SA': return 'Saudi Arabia';
        case 'SN': return 'Senegal';
        case 'RS': return 'Serbia';
        case 'SC': return 'Seychelles';
        case 'SL': return 'Sierra Leone';
        case 'SG': return 'Singapore';
        case 'SK': return 'Slovakia (Slovak Republic)';
        case 'SI': return 'Slovenia';
        case 'SB': return 'Solomon Islands';
        case 'SO': return 'Somalia, Somali Republic';
        case 'ZA': return 'South Africa';
        case 'GS': return 'South Georgia and the South Sandwich Islands';
        case 'ES': return 'Spain';
        case 'LK': return 'Sri Lanka';
        case 'SD': return 'Sudan';
        case 'SR': return 'Suriname';
        case 'SJ': return 'Svalbard & Jan Mayen Islands';
        case 'SZ': return 'Swaziland';
        case 'SE': return 'Sweden';
        case 'CH': return 'Switzerland, Swiss Confederation';
        case 'SY': return 'Syrian Arab Republic';
        case 'TW': return 'Taiwan';
        case 'TJ': return 'Tajikistan';
        case 'TZ': return 'Tanzania';
        case 'TH': return 'Thailand';
        case 'TL': return 'Timor-Leste';
        case 'TG': return 'Togo';
        case 'TK': return 'Tokelau';
        case 'TO': return 'Tonga';
        case 'TT': return 'Trinidad and Tobago';
        case 'TN': return 'Tunisia';
        case 'TR': return 'Turkey';
        case 'TM': return 'Turkmenistan';
        case 'TC': return 'Turks and Caicos Islands';
        case 'TV': return 'Tuvalu';
        case 'UG': return 'Uganda';
        case 'UA': return 'Ukraine';
        case 'AE': return 'United Arab Emirates';
        case 'GB': return 'United Kingdom';
        case 'US': return 'USA';
        case 'UM': return 'United States Minor Outlying Islands';
        case 'VI': return 'United States Virgin Islands';
        case 'UY': return 'Uruguay, Eastern Republic of';
        case 'UZ': return 'Uzbekistan';
        case 'VU': return 'Vanuatu';
        case 'VE': return 'Venezuela';
        case 'VN': return 'Vietnam';
        case 'WF': return 'Wallis and Futuna';
        case 'EH': return 'Western Sahara';
        case 'YE': return 'Yemen';
        case 'ZM': return 'Zambia';
        case 'ZW': return 'Zimbabwe';
    }
    return false;
}

endif;
