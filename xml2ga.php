<?php


// ------------------------------ //
//    Get UltraCart order data    //
// ------------------------------ //

$xml_document = file_get_contents( 'php://input' );
$xml = simplexml_load_string( $xml_document );
$json = json_encode( $xml );
$array = json_decode( $json, TRUE );


// ----------------------------- //
//    Detect & Set order data    //
// ----------------------------- //

$type['order'] = NULL;
$type['auto_order'] = NULL;
$type['refund'] = NULL;
$order = $array['order'] ? $array['order'] : NULL;

// detect regular order
if (    $order['payment_status'] === 'Processed' && 
       !$order['auto_order_original_order_id'] && 
   ( ( !$order['shipping_method'] && $order['current_stage'] === 'CO' ) || 
      ( $order['current_stage'] === 'SD' ) ) ) {
  $type['order'] = TRUE;
}
// detect refunded order
if ( $order['payment_status'] === 'Refunded' && 
   ( $order['current_stage'] === 'CO' || 
     $order['current_stage'] === 'SD' || 
     $order['current_stage'] === 'REJ' ) ) {
  $type['refund'] = TRUE;
}
// detect auto order
if ( $array['auto_order_code'] ) { exit('Simple auto order report. No action taken...'); }
if ( $order['auto_order_original_order_id'] ) { $type['auto_order'] = TRUE; }

// create item list
if ( $type['order'] ) {
  if ( !$order['item']['item_id'] ) {
	foreach ( $order['item'] as $key => $value ) {
	  $item_data[$key] = $order['item'][$key]['item_id'];
	}
	$item_list = implode( ',', $item_data );
  }
  else { $item_list = $order['item']['item_id']; }
}


// ------------------------ //
//    Unique ID Function    //
// ------------------------ //

function gen_uuid() { // Generates a UUID. A UUID is required for the measurement protocol.
  return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
	mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
	mt_rand( 0, 0xffff ),
	mt_rand( 0, 0x0fff ) | 0x4000,
	mt_rand( 0, 0x3fff ) | 0x8000,
	mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
  );
}


// -------------------- //
//     GA Post Data     //
// -------------------- //

$google['account_id'] = 'UA-######-###'; // Default account ID (UA-######-###)
$google['base_url'] = 'https://www.google-analytics.com/collect'; // The endpoint to which we'll be sending order data
$google['user_agent'] = 'UltraCart/1.0'; // The user agent used in the HTTP POST request
$google['client_id'] = gen_uuid(); // Default client ID sent to Google
$orderData['v'] = 1; // The version of the measurement protocol
$orderData['tid'] = $google['account_id']; // Google Analytics account ID
$orderEventData['v'] = 1; // The version of the measurement protocol
$orderEventData['tid'] = $google['account_id']; // Google Analytics account ID
$itemData['v'] = 1; // The version of the measurement protocol
$itemData['tid'] = $google['account_id']; // Google Analytics account ID

function set_client_id() {
global $orderData, $orderEventData, $itemData, $google;
  $orderData['cid'] = $google['client_id']; // The client ID or UUID
  $orderEventData['cid'] = $google['client_id']; // The client ID or UUID
  $itemData['cid'] = $google['client_id']; // The client ID or UUID
}


// --------------------------- //
//     Set Order Variables     //
// --------------------------- //

function get_order_data() {
global $order, $data, $google;

  $data['total'] = $order['total'];
  $data['shipping_total'] = isset( $order['shipping_handling_total'] ) ? $order['shipping_handling_total'] : NULL;
  $data['first_name'] = isset( $order['bill_to_first_name'] ) ? $order['bill_to_first_name'] : NULL;
  $data['last_name'] = isset( $order['bill_to_last_name'] ) ? $order['bill_to_last_name'] : NULL;
  $data['tax'] = $order['tax'];
  $data['transaction_id'] = $order['order_id'];
  $data['email'] = $order['email'];
  $data['order_type'] = empty( $order['shipping_method'] ) ? 'digital' : 'physical';
  $data['order_stage'] = $order['current_stage'] === 'CO' ? 'completed order' : ( $order['current_stage'] === 'SD' ? 'shipping department' : ( $order['current_stage'] === 'REJ' ? 'rejected' : NULL ) );
  $data['theme'] = isset( $order['screen_branding_theme_code'] ) ? $order['screen_branding_theme_code'] : 'GEN';
  $data['upsell_path'] = isset( $order['upsell_path_code'] ) ? $order['upsell_path_code'] : 'CTRL';
  $data['traffic_source'] = isset( $order['custom_field_1'] ) ? $order['custom_field_1'] : NULL;
  $data['product_category'] = isset( $order['custom_field_2'] ) ? $order['custom_field_2'] : NULL;
  $data['landing_page_url'] = isset( $order['custom_field_3'] ) ? urldecode( $order['custom_field_3'] ) : NULL;
  $data['client_id'] = isset( $order['custom_field_4'] ) ? $order['custom_field_4'] : NULL;
  $data['subid'] = isset( $order['custom_field_5'] ) ? $order['custom_field_5'] : NULL;
  $data['landing_page_query'] = isset( $order['custom_field_7'] ) ? urldecode( $data['custom_field_7'] ) : NULL;
  if ( $data['landing_page_query'] ) { parse_str( $data['landing_page_query'], $data['landing_page_query_array'] ); }
  $data['coupon_code'] = isset( $order['coupon'] ) ? $order['coupon']['coupon_code'] : 'N/A';
  $data['affid'] = isset( $order['tier_1_affiliate_oid'] ) ? $order['tier_1_affiliate_oid'] : NULL;
  if ( !$data['affid'] && isset( $data['landing_page_query_array']['affid'] ) ) { $data['affid'] = $data['landing_page_query_array']['affid']; }
  if ( isset( $order['tier_1_affiliate_sub_id'] ) && !empty( $order['tier_1_affiliate_sub_id'] ) ) { $data['subid'] = $order['tier_1_affiliate_sub_id']; }
  
  $data['refund_total'] = isset( $order['total_refunded'] ) ? $order['total_refunded'] : NULL;
  $data['refund_user'] = isset( $order['refund_by_user'] ) ? $order['refund_by_user'] : NULL;
  $data['merchant_notes'] = isset( $order['merchant_notes'] ) ? $order['merchant_notes'] : NULL;
  
  if ( $data['landing_page_url'] && $data['landing_page_query'] ) { $data['landing_page'] = $data['landing_page_url'] . '?' . $data['landing_page_query']; }
  elseif ( $data['landing_page_url'] && !$data['landing_page_query'] ) { $data['landing_page'] = $data['landing_page_url']; } else { $data['landing_page'] = 'unknown'; }
  
  if ( $data['client_id'] && $data['client_id'] !== 'undefined' ) { $google['client_id'] = $data['client_id']; }

} // END get_order_data()


// ------------------------------------ //
//     'Get Affiliate Data' SECTION     //
// ------------------------------------ //

$affiliate = array ( 
  '200000' => array ( 'company' => 'Affiliate Company Name #1', 'contact' => 'John Smith' ),
  '200001' => array ( 'company' => 'Affiliate Company Name #2', 'contact' => 'Frank Smith' ),
  '200002' => array ( 'company' => 'Affiliate Company Name #3', 'contact' => 'Sally Smith' ),
  '200003' => array ( 'company' => 'Affiliate Company Name #4', 'contact' => 'Jenny Smith' ),
  '200004' => array ( 'company' => 'Affiliate Company Name #5', 'contact' => 'Larry Smith' )
);

function get_affiliate_data() {
  global $data, $affiliate;

  if ( isset( $affiliate[ $data['affid'] ] ) ) { // if the affiliate ID matches the list above
	$data['affiliate_id'] = $data['affid'];
	$data['affiliate_company'] = $affiliate[ $data['affid'] ]['company'];
	$data['affiliate_contact'] = $affiliate[ $data['affid'] ]['contact'];
	$data['affiliate_info'] = $data['affiliate_id'] . ' - ' . $data['affiliate_company'] . ' - ' . $data['affiliate_contact'];
  } elseif ( !empty( $data['affid'] ) ) { $data['affiliate_info'] = $data['affid']; } // if there is any affiliate ID at all
  else { $data['affiliate_info'] = 'No Affiliate'; } // if there is no affiliate ID
}

// END 'Get Affiliate Data' SECTION


// ----------------------------------------- //
//    POST Order Data to GOOGLE ANALYTICS    //
// ----------------------------------------- //

if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {

  get_order_data();
  set_client_id();
  get_affiliate_data();

  $orderData['t'] = 'transaction'; //-------------------------| Hit Type parameter sent to Google Analytics
  $orderData['dh'] = 'secure.ultracart.com';  //--------------| Sets the document hostname for GA
  $orderData['cd3'] = $data['product_category']; //-----------| Sets a custom dimension (Product Category)
  $orderData['cd4'] = $data['subid']; //----------------------| Sets a custom dimension (Subid)
  $orderData['cd7'] = $data['coupon_code']; //----------------| Sets a custom dimension (Coupon)
  $orderData['cd12'] = $data['landing_page']; //--------------| Sets a custom dimension (Landing Page URL)
  $orderData['cd13'] = $data['affiliate_info']; //------------| Sets a custom dimension (Affiliate Info)
  $orderData['cd14'] = $data['theme']; //---------------------| Sets a custom dimension (Screen Branding Theme)
  $orderData['cd15'] = $data['upsell_path']; //---------------| Sets a custom dimension (Upsell Path)
  $orderData['cd16'] = $data['traffic_source']; //------------| Sets a custom dimension (Traffic Source)
  
  if ( $type['order'] || $type['auto_order'] ) {
	$orderData['dp'] = '/processed'; //-----------------------| Sets the document path for GA
	$orderData['tr'] = $data['total']; //---------------------| Sets the transaction revenue for GA
	$orderData['ts'] = $data['shipping_total']; //------------| Sets the transaction shipping for GA
	$orderData['tt'] = $data['tax']; //-----------------------| Sets the transaction tax for GA
  }
  
  if ( $type['order'] ) {
	$orderData['dt'] = 'Order Processed'; //------------------| Sets the document title for GA
	$orderData['ti'] = $data['transaction_id']; //------------| Sets the transaction ID for GA
  }
  
  if ( $type['auto_order'] ) {
	$orderData['dt'] = 'Auto Order Processed'; //-------------| Sets the document title for GA
	$orderData['ti'] = 'auto-' . $data['transaction_id']; //--| Sets the transaction ID for GA
  }
  
  if ( $type['refund'] ) {
	$orderData['dp'] = '/refunded'; //------------------------| Sets the document path for GA
	$orderData['dt'] = 'Order Refunded'; //-------------------| Sets the document title for GA
	$orderData['ti'] = 'ref-' . $data['transaction_id']; //---| Sets the transaction ID for GA
	$orderData['tr'] = '-' . $data['refund_total']; //--------| Sets the transaction revenue for GA
	$orderData['cd11'] = $data['refund_user']; //-------------| Sets a custom dimension (Refund by User)
	$orderData['cd5'] = $data['merchant_notes']; //-----------| Sets a custom dimension (Merchant Notes)
  }
  
  $orderContent = http_build_query( $orderData ); // The body of the post must include exactly 1 URI encoded payload and must be no longer than 8192 bytes.
  $orderContent = utf8_encode( $orderContent ); // The payload must be UTF-8 encoded.

  $ch['order'] = curl_init();
  curl_setopt( $ch['order'], CURLOPT_USERAGENT, $google['user_agent'] );
  curl_setopt( $ch['order'], CURLOPT_URL, $google['base_url'] );
  curl_setopt( $ch['order'], CURLOPT_HTTPHEADER, array( 'Content-type: application/x-www-form-urlencoded' ) );
  curl_setopt( $ch['order'], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
  curl_setopt( $ch['order'], CURLOPT_POST, TRUE );
  curl_setopt( $ch['order'], CURLOPT_RETURNTRANSFER, TRUE );
  curl_setopt( $ch['order'], CURLOPT_POSTFIELDS, $orderContent );
  $order_response = curl_exec( $ch['order'] );
  curl_close( $ch['order'] );

} // END order


// ---------------------------------------- //
//    POST Item Data to GOOGLE ANALYTICS    //
// ---------------------------------------- //

if ( $type['order'] ) {

  foreach( $order['item'] as $key => $item ) {
  
	$data['item_name'] = $order['item'][$key]['item_id'];
	$data['item_price'] = $order['item'][$key]['total_cost_with_discount'];
	$data['item_quantity'] = $order['item'][$key]['quantity'];
	  
	$itemData['t'] = 'item'; //-----------------------| Hit Type parameter sent to Google Analytics
	$itemData['ti'] = $data['transaction_id']; //-----| Sets the transaction ID for GA
	$itemData['in'] = $data['item_name']; //----------| Sets the item name for GA
	$itemData['ip'] = $data['item_price']; //---------| Sets the item price for GA
	$itemData['iq'] = $data['item_quantity']; //------| Sets the item quantity for GA
	$itemData['cd3'] = $data['product_category']; //--| Sets a custom dimension (Product Category)
	$itemData['cd4'] = $data['subid']; //-------------| Sets a custom dimension (Subid)
	$itemData['cd7'] = $data['coupon_code']; //-------| Sets a custom dimension (Coupon)
	$itemData['cd12'] = $data['landing_page']; //-----| Sets a custom dimension (Landing Page URL)
	$itemData['cd13'] = $data['affiliate_info']; //---| Sets a custom dimension (Affiliate Info)
	$itemData['cd14'] = $data['theme']; //------------| Sets a custom dimension (Screen Branding Theme)
	$itemData['cd15'] = $data['upsell_path']; //------| Sets a custom dimension (Upsell Path)
	$itemData['cd16'] = $data['traffic_source']; //---| Sets a custom dimension (Traffic Source)

	$itemContent = http_build_query( $itemData ); // The body of the post must include exactly 1 URI encoded payload and must be no longer than 8192 bytes. See http_build_query.
	$itemContent = utf8_encode( $itemContent ); // The payload must be UTF-8 encoded.
	
	if ( $data['item_price'] !== '0.00' ) { // filters out kit components
	
	  $ch['item'][$key] = curl_init();
	  curl_setopt( $ch['item'][$key], CURLOPT_USERAGENT, $google['user_agent'] );
	  curl_setopt( $ch['item'][$key], CURLOPT_URL, $google['base_url'] );
	  curl_setopt( $ch['item'][$key], CURLOPT_HTTPHEADER, array( 'Content-type: application/x-www-form-urlencoded' ) );
	  curl_setopt( $ch['item'][$key], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
	  curl_setopt( $ch['item'][$key], CURLOPT_POST, TRUE);
	  curl_setopt( $ch['item'][$key], CURLOPT_RETURNTRANSFER, TRUE );
	  curl_setopt( $ch['item'][$key], CURLOPT_POSTFIELDS, $itemContent );
	  $item_response[$key] = curl_exec( $ch['item'][$key] );
	  curl_close( $ch['item'][$key] );
	
	}
    
  }
  
} // END items


// ----------------------------------------- //
//    POST Event Data to GOOGLE ANALYTICS    //
// ----------------------------------------- //

if ( $type['order'] ) {

  $orderEventData['t'] = 'event'; //----------------------| Hit Type parameter sent to Google Analytics
  $orderEventData['dh'] = 'secure.ultracart.com'; //------| The GA document host name
  $orderEventData['ec'] = 'Sales'; //---------------------| The GA event category
  $orderEventData['ea'] = 'Order Processed'; //-----------| The GA event action
  $orderEventData['el'] = $data['transaction_id']; //-----| The GA event label
  $orderEventData['cd3'] = $data['product_category']; //--| Sets a custom dimension (Product Category)
  $orderEventData['cd4'] = $data['subid']; //-------------| Sets a custom dimension (Subid)
  $orderEventData['cd7'] = $data['coupon_code']; //-------| Sets a custom dimension (Coupon)
  $orderEventData['cd12'] = $data['landing_page']; //-----| Sets a custom dimension (Landing Page URL)
  $orderEventData['cd13'] = $data['affiliate_info']; //---| Sets a custom dimension (Affiliate Info)
  $orderEventData['cd14'] = $data['theme']; //------------| Sets a custom dimension (Screen Branding Theme)
  $orderEventData['cd15'] = $data['upsell_path']; //------| Sets a custom dimension (Upsell Path)
  $orderEventData['cd16'] = $data['traffic_source']; //---| Sets a custom dimension (Traffic Source)
  
  $orderEventContent = http_build_query($orderEventData); // The body of the post must include exactly 1 URI encoded payload and must be no longer than 8192 bytes. See http_build_query.
  $orderEventContent = utf8_encode($orderEventContent); // The payload must be UTF-8 encoded.
  
  $ch['order_event'] = curl_init();
  curl_setopt( $ch['order_event'], CURLOPT_USERAGENT, $google['user_agent'] );
  curl_setopt( $ch['order_event'], CURLOPT_URL, $google['base_url'] );
  curl_setopt( $ch['order_event'], CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded' ) );
  curl_setopt( $ch['order_event'], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
  curl_setopt( $ch['order_event'], CURLOPT_POST, TRUE);
  curl_setopt( $ch['order_event'], CURLOPT_RETURNTRANSFER, TRUE );
  curl_setopt( $ch['order_event'], CURLOPT_POSTFIELDS, $orderEventContent );
  $order_event_response = curl_exec( $ch['order_event'] );
  curl_close( $ch['order_event'] );
	
} // END event



// --------------- //
//     NOTICES     //
// --------------- //

// Notice: Order processed and placed into 'Completed Orders'.
if ( $data['order_stage'] === 'completed order' && $type['order'] && $data['order_type'] === 'digital' ) { 
     echo 'An order has been processed and placed into Completed Orders.' . "\n\n"; }

// Notice: Order processed and placed into 'Shipping Department'.
if ( $data['order_stage'] === 'shipping department' && $type['order'] ) { 
     echo 'An order has been processed and placed into Shipping Department.' . "\n\n"; }

// Notice: Auto Order processed.
if ( $type['auto_order'] ) { 
     echo 'An auto order has been processed.' . "\n\n"; }

// Notice: Duplicate order ignored.
if ( $data['order_stage'] === 'completed order' && $type['order'] && $data['order_type'] === 'physical' ) { 
     echo 'An order from Shipping Department has been ignored.' . "\n\n"; }

// Notice: Order refunded from 'Completed Orders'.
if ( $data['order_stage'] === 'completed order' && $type['refund'] ) { 
     echo 'An order from Completed Orders has been refunded.' . "\n\n" . 'Amount refunded was $' . $data['refund_total'] . '.' . "\n\n" . 'Merchant Notes by ' . $data['refund_user'] . ': ' . $data['merchant_notes'] . '.' . "\n\n"; }

// Notice: Order refunded from 'Shipping Department'.
if ( $data['order_stage'] === 'shipping department' && $type['refund'] ) { 
     echo 'An order from Shipping Department has been refunded.' . "\n\n" . 'Amount refunded was $' . $data['refund_total'] . '.' . "\n\n" . 'Merchant Notes by ' . $data['refund_user'] . ': ' . $data['merchant_notes'] . '.' . "\n\n"; }

// Notice: Order refunded from 'Rejected'.
if ( $data['order_stage'] === 'rejected' && $type['refund'] ) { 
     echo 'An order from Rejected has been refunded.' . "\n\n" . 'Amount refunded was $' . $data['refund_total'] . '.' . "\n\n" . 'Merchant Notes by ' . $data['refund_user'] . ': ' . $data['merchant_notes'] . '.' . "\n\n"; }

// Notice: List of items.
if ( $type['order'] || $type['auto_order'] ) {
  if ( !$order['item']['item_id'] ) {
	foreach( $order['item'] as $key => $item ) {
	  if ( $order['item'][$key]['total_cost_with_discount'] !== '0.00' ) {
		echo $order['item'][$key]['item_id'] . ' ( ' . $order['item'][$key]['quantity'] . ' x $' . $order['item'][$key]['total_cost_with_discount'] . ' )' . "\n\n";
	  }
	}
  }
  else { echo $order['item']['item_id'] . ' ( ' . $order['item']['quantity'] . ' x $' . $order['item']['total_cost_with_discount'] . ' )' . "\n\n"; }
}

// Additional notices
if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {
  echo 'The landing page URL is ' . $data['landing_page'] . '.';
  echo "\n\n";
  echo 'ID is ' . $data['transaction_id'] . ', CID is ' . $google['client_id'] . ' and total is $' . $data['total'] . '. ' ;
  echo "\n\n";
  echo 'Affiliate info is ' . $data['affiliate_info'] . '. ';
  echo "\n\n";
  echo 'Order type is ' . $data['order_type'] . '. ';
  echo "\n\n";
  echo 'Coupon code is ' . $data['coupon_code'] . '.';
  echo "\n\n";
  echo 'Screen Branding Theme is ' . $data['theme'] . '.';
  echo "\n\n";
  echo 'Upsell Path is ' . $data['upsell_path'] . '.';
  echo "\n\n";
}


?>
