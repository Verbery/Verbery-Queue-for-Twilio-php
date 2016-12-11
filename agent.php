<?php

/*
 * This file is assigned to Twilio TwiML App Request Url to respond 
 * when agent tries to connect to queue
 */

$name = $_POST['queueId'];

# Include Twilio PHP helper library.
require('vendor/autoload.php');

use Twilio\Twiml;

# Tell Twilio to expect some XML
header('Content-type: text/xml');

# Create response object.
$response = new Twiml;

# Say something before connecting agent to Customer in the Twilio queue
$response->say("queue: " . $name . ", connecting to Customer");

# Create options array
$options = array();

# Dial into the Queue we placed the caller into to connect agent to
# first person in the Queue.
$dial = $response->dial();
$dial->queue($name, $options);

# Print TwiML
print $response;
