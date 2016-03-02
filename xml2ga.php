<?php

define( 'DEV', TRUE ); // set to FALSE for production
define( 'TEST', TRUE ); // set to FALSE for production
define( 'DEBUG', TRUE ); // set to FALSE for production

define( 'UA_PROD', 'UA-######-###' ); // Google Analytics property ID for real orders
define( 'UA_TEST', 'UA-######-###' ); // Google Analytics property ID for test orders
define( 'CD_ACTION', '#' ); // Google Analytics custom dimension (scope = hit) index for 'Product Action' (purchase, refund, remove)
define( 'CD_TYPE', '#' ); // Google Analytics custom dimension (scope = hit) index for 'Order Type' (digital order, physical order, auto order)
define( 'CM_REV', '#' ); // Google Analytics custom metric (scope = hit) index for 'Original Revenue'

if ( DEBUG ) {
  error_reporting( E_ALL );
  ini_set( 'display_errors', 1 );
}

// start timer
$mTime = microtime();
$mTime = explode( ' ', $mTime );
$mTime = $mTime[1] + $mTime[0];
$startTime = $mTime;

// set default timezone
date_default_timezone_set( 'America/New_York' );


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
$order = NULL;
if ( isset( $array['order'] ) ) {
  $order = $array['order'];
}
elseif ( isset( $array['auto_order_items'] ) ) {
  $order = $array;
}
if ( !$order ) { exit( 'Order not recognized...' ); }

// order types
if ( isset( $array['order'] ) ) {

  // detect regular order
  if (    $order['payment_status'] === 'Processed' &&
     ( ( !$order['shipping_method'] && $order['current_stage'] === 'CO' ) ||
        ( $order['current_stage'] === 'SD' ) ) ) {
    $type['order'] = TRUE;
  }

  // detect test/dev order
  if ( $order['payment_status'] === 'Processed' &&
       $order['current_stage'] === 'REJ' && DEV ) {
    $type['order'] = TRUE;
  }

  // detect auto orders
  if ( ( isset( $order['order_id'] ) && isset( $order['auto_order_original_order_id'] ) ) &&
       ( $order['order_id'] !== $order['auto_order_original_order_id'] ) ) {
    $type['auto_order'] = TRUE;
    $type['order'] = FALSE;
  }

  // detect refunded order
  if ( $order['payment_status'] === 'Refunded' &&
     ( $order['current_stage'] === 'CO' ||
       $order['current_stage'] === 'SD' ||
       $order['current_stage'] === 'REJ' ) ) {
    $type['refund'] = TRUE;
  }

}

// detect auto order status changes
if ( isset( $order['auto_order_items'] ) ) {
  $type['auto_order_status'] = TRUE;
} else { $type['auto_order_status'] = FALSE; }

// fix item array in regular/auto order
if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {
  $order['items'] = $order['item'];
  unset( $order['item'] );
  if ( isset( $order['items']['item_id'] ) ) {
    $temp_item_array = $order['items'];
    $order['items'] = array();
    $order['items'][] = $temp_item_array;
  }
}

// fix item array in auto order status
if ( $type['auto_order_status'] ) {
  $order['auto_order_items'] = $order['auto_order_items']['auto_order_item'];
  if ( isset( $order['auto_order_items']['original_item_id'] ) ) {
    $temp_item_array = $order['auto_order_items'];
    $order['auto_order_items'] = array();
    $order['auto_order_items'][] = $temp_item_array;
  }
}

// fix transaction details array
if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {
  $order['transaction_gateway'] = NULL;
  $order['transaction_details'] = $order['transaction_details']['transaction_detail'];
  if ( isset( $order['transaction_details']['transaction_id'] ) ) {
    $temp_detail_array = $order['transaction_details'];
    $order['transaction_details'] = array();
    $order['transaction_details'][] = $temp_detail_array;
  }
  foreach ( $order['transaction_details'] as $key => $value ) {
    $order['transaction_details'][$key]['extended_details'] = $order['transaction_details'][$key]['extended_details']['extended_detail'];
  }
  foreach ( $order['transaction_details'][0]['extended_details'] as $key => $value ) {
    if ( $order['transaction_details'][0]['extended_details'][$key]['extended_detail_name'] === 'rotatingTransactionGatewayCode' ) {
      $order['transaction_gateway'] = $order['transaction_details'][0]['extended_details'][$key]['extended_detail_value'];
    }
  }
}

// fix empty arrays
if ( $type['order'] || $type['auto_order'] || $type['refund'] || $type['auto_order_status'] ) {
  $order = array_map( function( $value ) {
    return $value === array() ? NULL : $value;
  }, $order );
  if ( !$type['auto_order_status'] ) {
    foreach ( $order['items'] as $key => $value ) {
      $order['items'][$key] = array_map( function( $value ) {
        return $value === array() ? NULL : $value;
      }, $order['items'][$key] );
    }
  }
}

// create timestamps (ISO-8601 format)
if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {
  $order['order_date'] = date( DATE_ISO8601, strtotime( $order['order_date'] ) );
  $order['payment_date'] = date( DATE_ISO8601, strtotime( $order['payment_date_time'] ) );
  $order['entry_date'] = date( DATE_ISO8601 );
}

// create item lists/arrays
if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {
  foreach( $order['items'] as $key => $item ) {
    $item_data[$key] = $order['items'][$key]['item_id'];
    if ( $order['items'][$key]['kit_component'] === 'N' ) { // filters out kit components
      $paid_item_data[$key] = $order['items'][$key]['item_id'];
    }
  }
  $item_list = implode( ',', $item_data );
  $item_array = $item_data;
  $paid_item_list = implode( ',', $paid_item_data );
  $paid_item_array = $paid_item_data;
} else {
  $item_list = NULL;
  $item_array = array();
  $paid_item_list = NULL;
  $paid_item_array = array();
}

// add missing keys
$order['custom_field_1'] = isset( $order['custom_field_1'] ) ? $order['custom_field_1'] : NULL;
$order['custom_field_2'] = isset( $order['custom_field_2'] ) ? $order['custom_field_2'] : NULL;
$order['custom_field_3'] = isset( $order['custom_field_3'] ) ? $order['custom_field_3'] : NULL;
$order['custom_field_4'] = isset( $order['custom_field_4'] ) ? $order['custom_field_4'] : NULL;
$order['custom_field_5'] = isset( $order['custom_field_5'] ) ? $order['custom_field_5'] : NULL;
$order['custom_field_6'] = isset( $order['custom_field_6'] ) ? $order['custom_field_6'] : NULL;
$order['custom_field_7'] = isset( $order['custom_field_7'] ) ? $order['custom_field_7'] : NULL;
$order['tier_1_affiliate_oid'] = isset( $order['tier_1_affiliate_oid'] ) ? $order['tier_1_affiliate_oid'] : NULL;
$order['tier_1_affiliate_sub_id'] = isset( $order['tier_1_affiliate_sub_id'] ) ? $order['tier_1_affiliate_sub_id'] : NULL;



// --------------------------- //
//     Set Order Variables     //
// --------------------------- //

function get_order_data( $order ) {
  global $google;

  $data['session_id'] = NULL;
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
  if ( isset( $order['custom_field_4'] ) && preg_match( '/^\d+\.\d+$/i', $order['custom_field_4'] ) ) {
    $data['client_id'] = $order['custom_field_4'];
  } else { $data['client_id'] = NULL; }
  $data['subid'] = isset( $order['custom_field_5'] ) ? $order['custom_field_5'] : NULL;
  if ( isset( $order['custom_field_6'] ) && preg_match( '/^E:.+V:.+U:/i', $order['custom_field_6'] ) ) {
    preg_match( '/^E:(.+);V:(.+);U:(.+);?$/i', $order['custom_field_6'], $cs6_matches );
    $data['opz_experiment_id'] = $cs6_matches[1] !== 'NONE' ? $cs6_matches[1] : NULL;
    $data['opz_variation_id'] = $cs6_matches[2] !== 'NONE' ? $cs6_matches[2] : NULL;
    $data['session_id'] = $cs6_matches[3] ? $cs6_matches[3] : NULL;
  } else {
    $data['opz_experiment_id'] = NULL;
    $data['opz_variation_id'] = NULL;
    $data['session_id'] = NULL;
  }
  $data['landing_page_query'] = isset( $order['custom_field_7'] ) ? urldecode( $order['custom_field_7'] ) : NULL;
  if ( $data['landing_page_query'] ) { parse_str( $data['landing_page_query'], $data['landing_page_query_array'] ); }
  $data['coupon_code'] = isset( $order['coupon'] ) ? $order['coupon']['coupon_code'] : 'N/A';
  $data['affid'] = isset( $order['tier_1_affiliate_oid'] ) ? $order['tier_1_affiliate_oid'] : NULL;
  if ( !$data['affid'] && isset( $data['landing_page_query_array']['affid'] ) ) { $data['affid'] = $data['landing_page_query_array']['affid']; }
  if ( isset( $order['tier_1_affiliate_sub_id'] ) && !empty( $order['tier_1_affiliate_sub_id'] ) ) { $data['subid'] = $order['tier_1_affiliate_sub_id']; }

  $data['refund_total'] = isset( $order['total_refunded'] ) ? $order['total_refunded'] : NULL;
  $data['refund_user'] = isset( $order['refund_by_user'] ) ? $order['refund_by_user'] : NULL;
  $data['merchant_notes'] = isset( $order['merchant_notes'] ) && !empty( $order['merchant_notes'] ) ? $order['merchant_notes'] : NULL;

  if ( $data['landing_page_url'] && $data['landing_page_query'] ) { $data['landing_page'] = $data['landing_page_url'] . '?' . $data['landing_page_query']; }
  elseif ( $data['landing_page_url'] && !$data['landing_page_query'] ) { $data['landing_page'] = $data['landing_page_url']; } else { $data['landing_page'] = 'unknown'; }

  return $data;

} // END get_order_data()


// ----------------------- //
//     'Data' Variable     //
// ----------------------- //

if ( $type['order'] || $type['refund'] || $type['auto_order'] ) {

  $data = get_order_data( $order );

  $data['user_agent'] = 'UltraCart/1.0';

  $data['affiliate_info'] = $data['affiliate'];
  $data['affiliate_id'] = $data['affid'];
  $data['affiliate_name'] = NULL;
  $data['affiliate_first_name'] = NULL;
  $data['affiliate_last_name'] = NULL;

}


// -------------------------------- //
//     'Google Analytics' Class     //
// -------------------------------- //

function gen_uuid() { // Generates a UUID. A UUID is required for the measurement protocol.
  return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
  mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
  mt_rand( 0, 0xffff ),
  mt_rand( 0, 0x0fff ) | 0x4000,
  mt_rand( 0, 0x3fff ) | 0x8000,
  mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
  );
}

$google['account_id'] = ( DEV || DEBUG ) ? UA_TEST : UA_PROD; // Google Analytics property IDs
$google['user_agent'] = $data['user_agent']; // The user agent used in the HTTP POST request
$google['client_id'] = $data['client_id'] ? $data['client_id'] : gen_uuid(); // Client ID sent to Google

if ( !isset( $order['custom_field_4'] ) ||
   ( isset( $order['custom_field_4'] ) && empty( $order['custom_field_4'] ) ) ) {
     $orderData['cid'] = $google['client_id']; // UUID
     $orderEventData['cid'] = $google['client_id']; // UUID
     $itemData['cid'] = $google['client_id']; // UUID
} else {
  $orderData['cid'] = $order['custom_field_4']; // client ID
  $orderEventData['cid'] = $order['custom_field_4']; // client ID
  $itemData['cid'] = $order['custom_field_4']; // client ID
}

class Google {

  function post( $payload, $debug = FALSE ) {

    global $error_log_array;

    if ( $debug ) {
      $url = 'https://www.google-analytics.com/debug/collect';
    } else {
      $url = 'https://www.google-analytics.com/collect';
    }

    $fields = http_build_query( $payload );
    $fields = utf8_encode( $fields );

    $ch = curl_init();
    curl_setopt_array( $ch, array(
      CURLOPT_USERAGENT => $payload['ua'],
      CURLOPT_URL => $url,
      CURLOPT_HTTPHEADER => array( 'Content-type: application/x-www-form-urlencoded' ),
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_POST => TRUE,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_POSTFIELDS => $fields,
      CURLOPT_TIMEOUT => 10
    ));
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $ch_number = curl_errno( $ch );
    $ch_error = curl_error( $ch );

    try {
      if ( $http_code < 200 && $http_code > 226 ) {
        throw new Exception( 'HTTP error: ' . $response );
      }
      if ( $ch_number > 0 ) {
        throw new Exception( 'Unable to connect to ' . $url . ' Error: ' . $ch_error );
      }
    }

    catch( Exception $e ) {
      $error_log_array = isset( $error_log_array ) ? $error_log_array : array();
      $error_log_array[] = $e->getMessage();
    }

    curl_close( $ch );

    if ( $ch_number == 0 && ( $http_code >= 200 && $http_code <= 226 ) ) {
      if ( $debug ) {
        echo 'SUCCESS RESPONSE ('. $payload['t'] . "):\n";
        echo $response;
        echo "\n\n";
      }
      return TRUE;
    }
    else {
      if ( $debug ) {
        echo 'ERROR RESPONSE ('. $payload['t'] . "):\n";
        echo $response;
        echo "\n\n";
      }
      return FALSE;
    }

  }

}

$ggl = new Google();



// ------------------------ //
//    POST Order Data to    //
//     GOOGLE ANALYTICS     //
// ------------------------ //

if ( $type['order'] || $type['auto_order'] || $type['refund'] ) {

  $orderData['v'] = 1; //-------------------------------------| The version of the measurement protocol
  $orderData['cid'] = $google['client_id']; //----------------| Google Analytics 'Client ID'
  $orderData['tid'] = $google['account_id']; //---------------| Google Analytics account ID
  $orderData['t'] = 'transaction'; //-------------------------| Hit Type parameter sent to Google Analytics
  $orderData['ua'] = $google['user_agent']; //----------------| User Agent string sent to Google Analytics
  $orderData['dh'] = 'secure.ultracart.com';  //--------------| Sets the document hostname for GA
  $orderData['ta'] = $data['affiliate_info']; //--------------| Sets the 'Affiliation' for GA

  if ( ( $type['order'] || $type['auto_order'] ) && !$type['refund'] ) {
    $orderData['dp'] = '/processed'; //-----------------------| Sets the document path for GA
    $orderData['ts'] = $data['shipping_total']; //------------| Sets the transaction shipping for GA
    $orderData['tt'] = $data['tax']; //-----------------------| Sets the transaction tax for GA
    $orderData['tr'] = $data['total']; //---------------------| Sets the transaction revenue for GA
    $orderData['cm'.CM_REV] = $data['total']; //--------------| Sets a custom metric (Original Revenue)
  }

  if ( $type['order'] && !$type['refund'] ) {
    $orderData['dt'] = 'Order Processed'; //------------------| Sets the document title for GA
    $orderData['pa'] = 'purchase'; //-------------------------| Sets the 'Product Action' for GA
    $orderData['cd'.CD_ACTION] = 'purchase'; //----------------------| Sets a custom dimension (Product Action)
    $orderData['cd'.CD_TYPE] = $data['order_type'] . ' order'; //----| Sets a custom dimension (Order Type)
    $orderData['tcc'] = $data['coupon_code']; //--------------| Sets the 'Coupon Code' for GA
    $orderData['ti'] = $data['transaction_id']; //------------| Sets the transaction ID for GA
    if ( $data['opz_experiment'] ) {
      $orderData['xid'] = $data['opz_experiment_id']; //------| Sets the 'Experiment ID' for GA
      $orderData['xvar'] = $data['opz_variation_id']; //------| Sets the 'Experiment Variant' for GA
    }
  }

  if ( $type['auto_order'] && !$type['refund'] ) {
    $orderData['dt'] = 'Auto Order Processed'; //-------------| Sets the document title for GA
    $orderData['pa'] = 'purchase'; //-------------------------| Sets the 'Product Action' for GA
    $orderData['cd'.CD_ACTION] = 'purchase'; //---------------| Sets a custom dimension (Product Action)
    $orderData['cd'.CD_TYPE] = 'auto order'; //---------------| Sets a custom dimension (Order Type)
    $orderData['ti'] = $data['transaction_id']; //------------| Sets the transaction ID for GA
  }

  if ( $order['payment_status'] === 'Refunded' ) {
    $orderData['dp'] = '/refunded'; //------------------------| Sets the document path for GA
    $orderData['dt'] = 'Order Refunded'; //-------------------| Sets the document title for GA
    $orderData['pa'] = 'refund'; //---------------------------| Sets the 'Product Action' for GA
    $orderData['cd'.CD_ACTION] = 'refund'; //-----------------| Sets a custom dimension (Product Action)
    $orderData['tcc'] = $data['coupon_code']; //--------------| Sets the 'Coupon Code' for GA
    $orderData['ti'] = $data['transaction_id']; //------------| Sets the transaction ID for GA
    $orderData['tr'] = '-' . $data['refund_total']; //--------| Sets the transaction revenue for GA
  }

  if ( $data['order_stage'] === 'rejected' ) {
    $orderData['dp'] = '/rejected'; //------------------------| Sets the document path for GA
    $orderData['dt'] = 'Order Rejected'; //-------------------| Sets the document title for GA
    $orderData['pa'] = 'remove'; //---------------------------| Sets the 'Product Action' for GA
    $orderData['cd'.CD_ACTION] = 'remove'; //--------------------------| Sets a custom dimension (Product Action)
  }

  $ggl->post( $orderData, DEBUG );

} // END order


// ----------------------- //
//    POST Item Data to    //
//    GOOGLE ANALYTICS     //
// ----------------------- //

if ( ( $type['order'] || $type['auto_order'] ) && !$type['refund'] ) {

  foreach( $order['items'] as $key => $item ) {

    $data['item_name'] = $order['items'][$key]['item_id'];
    $data['item_price'] = $order['items'][$key]['total_cost_with_discount'];
    $data['item_quantity'] = $order['items'][$key]['quantity'];

    $itemData['v'] = 1; //----------------------------| The version of the measurement protocol
    $itemData['tid'] = $google['account_id']; //------| Google Analytics account ID
    $itemData['t'] = 'item'; //-----------------------| Hit Type parameter sent to Google Analytics
    $itemData['pa'] = 'purchase'; //------------------| Sets the 'Product Action' for GA
    $itemData['cd'.CD_ACTION] = 'purchase'; //--------| Sets a custom dimension (Product Action)
    $itemData['ua'] = $google['user_agent']; //-------| User Agent string sent to Google Analytics
    $itemData['ti'] = $data['transaction_id']; //-----| Sets the transaction ID for GA
    $itemData['in'] = $data['item_name']; //----------| Sets the item name for GA
    $itemData['ip'] = $data['item_price']; //---------| Sets the item price for GA
    $itemData['iq'] = $data['item_quantity']; //------| Sets the item quantity for GA
    $itemData['tcc'] = $data['coupon_code']; //-------| Sets the 'Coupon Code' for GA
    $itemData['ta'] = $data['affiliate_info']; //-----| Sets the 'Affiliation' for GA

    if ( $type['order'] ) {
      if ( $data['opz_experiment'] ) {
        $itemData['xid'] = $data['opz_experiment_id']; //------| Sets the 'Experiment ID' for GA
        $itemData['xvar'] = $data['opz_variation_id']; //------| Sets the 'Experiment Variant' for GA
      }
    }

    if ( $type['auto_order'] ) {
      $itemData['ti'] = 'auto-' . $data['transaction_id']; //--| Sets the transaction ID for GA
    }

    if ( $order['items'][$key]['kit_component'] === 'N' ) { // filters out kit components

      $ggl->post( $itemData, DEBUG );

    }

  }

}

// END items


// ----------------------- //
//    POST Event Data to   //
//    GOOGLE ANALYTICS     //
// ----------------------- //

if ( $type['order'] ) {

  $orderEventData['v'] = 1; //------------------------------| The version of the measurement protocol
  $orderEventData['tid'] = $google['account_id']; //--------| Google Analytics account ID
  $orderEventData['t'] = 'event'; //------------------------| Hit Type parameter sent to Google Analytics
  $orderEventData['ua'] = $google['user_agent']; //---------| User Agent string sent to Google Analytics
  $orderEventData['dh'] = 'secure.ultracart.com'; //--------| The GA document host name
  $orderEventData['ec'] = 'Sales'; //-----------------------| The GA event category
  $orderEventData['ea'] = 'Order Processed'; //-------------| The GA event action
  $orderEventData['el'] = $data['transaction_id']; //-------| The GA event label
  $orderEventData['ev'] = $data['total']; //----------------| The GA event value
  $orderEventData['tcc'] = $data['coupon_code']; //---------| Sets the 'Coupon Code' for GA
  $orderEventData['ta'] = $data['affiliate_info']; //-------| Sets the 'Affiliation' for GA
  if ( $data['opz_experiment'] ) {
    $orderEventData['xid'] = $data['opz_experiment_id']; //-| Sets the 'Experiment ID' for GA
    $orderEventData['xvar'] = $data['opz_variation_id']; //-| Sets the 'Experiment Variant' for GA
  }

  $ggl->post( $orderEventData, DEBUG );

} // END event


// end timer
$mTime = microtime();
$mTime = explode( ' ', $mTime );
$mTime = $mTime[1] + $mTime[0];
$endTime = $mTime;
$totalTime = ( $endTime - $startTime );
echo "\n\n";
echo 'Task completed in ' . preg_replace( '/(.+\.\d\d).+/i', '$1', $totalTime ) . ' seconds.';

echo "\n\n\n"; echo 'ORDER ARRAY:'; echo "\n\n";
print_r( $order );
