<?php

/*
 * This file is assigned to Twilio number to respond as an IVR script.
 * When caller calls it places caller into the Twilio queue "Queue Demo" and 
 * notifies nodejs about it
 */
$name = "Queue Demo"; // queue name

use ElephantIO\Client
	, ElephantIO\Engine\SocketIO\Version1X;

require ('vendor/autoload.php');

/*
 * notify nodejs Queue Manager about the incoming call in the queue so the agent
 * with longest idle time can pickup the call
 */
$host = 'https://' . getenv('SOCKETIO_HOST');
$client = new Client(
					new Version1X($host));

$client->initialize();
$client->emit( 'incoming call in queue', [$name] );
$client->close();

/*
 * place call into queue
 */

// Tell Twilio to expect some XML
header ( 'Content-type: text/xml' );

// Create response object.
$response = new Services_Twilio_Twiml ();

// Place incoming caller in a Queue
$response->enqueue ( $name );

// Print TwiML
print $response;



// # OpenVBX plugin implementation of enqueue
/////////////////////////////////////////////
// $options = array();
// if(!empty($waitUrl))
// $options['waitUrl'] = $waitUrl;

// $response = new TwimlResponse;
// $response->enqueue($name, $options);

// if(!empty($next))
// 		$response->redirect($next);

// $response->respond();
