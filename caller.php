<?php 

/*
 * This file is assigned to Twilio number to respond as an IVR script when caller calls
 * It places caller into the Twilio queue "Queue Demo" and notifies nodejs about it
 */

$name = "Queue Demo"; // queue name

////////////////////////////////////////////////////////////////////////////////////////
// send a POST request to node.js socket.io server
// with message that there is a new call $callID in the queue $queueID
// notifyNodeJS($name);
////////////////////////////////////////////////////////////////////////////////////////
require('vendor/wisembly/elephant.io/src/Client.php');
use ElephantIO\Client as Elephant;

$elephant = new Elephant('http://verbery-queue-for-twilio-node.herokuapp.com', 'socket.io', 1, true, true, true);

$elephant->init();
$elephant->emit('from master, new call in queue', $name);
// 	$elephant->send(
// 			ElephantIOClient::TYPE_EVENT,
// 			null,
// 			null,
// 			json_encode(array('callID' => $cid, 'queueID' => $name))
// 	);
$elephant->close();
//////////////////////////////////////////////////////////////////////////////////////////
// Implementation on ElephantIO 3.0.0
/////////////////////////////////////
// require_once('libs/ElephantIO/Client.php');
// require_once('libs/ElephantIO/EngineInterface.php');
// require_once('libs/ElephantIO/Engine/AbstractSocketIO.php');
// require_once('libs/ElephantIO/Engine/SocketIO/Version1X.php');
// require_once('libs/ElephantIO/Exception/ServerConnectionFailureException.php');

// use ElephantIO\Client,
// ElephantIO\Engine\SocketIO\Version1X;

// $client = new Client(new Version1X('http://127.0.0.1:8080/socket.io/'));

// $client->initialize();
// $client->emit('from master, new call in queue', $name);
// $client->close();
//////////////////////////////////////////////////////////////////////////////////////////

### OpenVBX plugin implementation of enqueue
// $options = array();
// if(!empty($waitUrl))
//   $options['waitUrl'] = $waitUrl;

// $response = new TwimlResponse;
// $response->enqueue($name, $options);

// if(!empty($next))
//   $response->redirect($next);

// $response->respond();


### Twilio HowTo implementation of enqueue
# Include Twilio PHP helper library.
require('libs/Services/Twilio.php');

# Tell Twilio to expect some XML
header('Content-type: text/xml');

# Create response object.
$response = new Services_Twilio_Twiml();

# Place incoming caller in a Queue
$response->enqueue($name);

# Print TwiML
print $response;
