<?php 

header('Access-Control-Allow-Origin: *');

/*
 * SETUP environment vars application in Heroku
 * heroku config:set SID=Azzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz
 * heroku config:set TOKEN=Azzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz
 * heroku config:set APPSID=Azzzzzzzzzzzzzzzzzzzzzzzzzzzzzz
 * heroku config:set SOCKETIO_HOST=verbery-queue-for-twilio-nodejs.heroku.com
 * 
 */

// require_once('vendor/twilio/sdk/Services/Twilio/Capability.php');
require('vendor/autoload.php');

// put your Twilio API credentials here
$accountSid = getenv('SID');
$authToken  = getenv('TOKEN');

// put your Twilio Application Sid here
$appSid	 = getenv('APPSID');

// get agent id from the address line
if(isset($_GET['agent'])) {
	$agent_id = $_GET['agent'];
} else {
	// if undefined, use something random
	$agent_id = rand(1,1000);
}

$capability = new Services_Twilio_Capability($accountSid, $authToken);
$capability->allowClientOutgoing($appSid);
$capability->allowClientIncoming($agent_id);
$token = $capability->generateToken();

/*
 * HTML code given below is just an example of the page that can be created
 * using the library provided. You will probably need to adjust it to your needs.
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Verbery Agent</title>

<!-- 	<link rel="stylesheet" href="css/bootstrap.min.css"> -->
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/agent_app.css">

	<script type="text/javascript" src="https://static.twilio.com/libs/twiliojs/1.1/twilio.min.js"></script>

	<script src="http://<?php echo getenv('SOCKETIO_HOST'); ?>/socket.io/socket.io.js"></script>
	<script type="text/javascript">

		var connection = null;										// store Twilio connection 
		var isOnline = false;											// to check if agent is Online or not
		var interval = null;											// to stop 'ringing' (or button flashing)
		var curr_repeitions = 0;									// when button is flashing it's number of repetitions made (or seconds passed)
		var repetitions = 15;											// const variable that defines ring time before sending queue to another agent
																							// multiple the value by 2 to get actual number of seconds before the call goes
																							// to the next available agent. E.g. if repetitions = 15, then ring time is 30 sec
		var token = "<?php echo $token; ?>";			// const variable that stores php variable token
		var agent_id = '<?php echo $agent_id; ?>';// const variable that stores php variable agent_id





		/* ********************************************************************
		 * connect to the nodejs socket.io
		 */
		var socket = io.connect('http://<?php echo getenv('SOCKETIO_HOST'); ?>');





		/* ********************************************************************
		 * Twilio callbacks
		 */

		Twilio.Device.setup(token);
		 
		Twilio.Device.ready(function (device) {
			$("#log").append("\nTwilio.Device.ready(): Twilio.Device.status = '"+ Twilio.Device.status() +"'");
			isOnline = true;
			$("#nav-btn-state").text(Twilio.Device.status());
			$("#nav-btn-status").html('Online');
		});

		Twilio.Device.offline(function (device) {
			$("#log").append("\nTwilio.Device.offline(): Twilio.Device.status = '"+ Twilio.Device.status() +"'");
			isOnline = false;
			$("#nav-btn-state").text(Twilio.Device.status());
		});

// 		Twilio.Device.presence(function(presenceEvent) {
// 			$("#presence").text("Presence Event: " + presenceEvent.from + " " + presenceEvent.available);
// 		});

		Twilio.Device.error(function (error) {
			$("#log").append("\nError: " + error.message);
			isOnline = false;
			$("#nav-btn-state").text(Twilio.Device.status());
		});
		 
		Twilio.Device.connect(function (conn) {
			$("#log").append("\nSuccessfully established call");
			connection = conn;
			$("#nav-btn-state").text(Twilio.Device.status());
		});
		 
		Twilio.Device.disconnect(function (conn) {
			$("#log").append("\nCall ended");
			$("#nav-btn-state").text(Twilio.Device.status());
			// register as an agent in ready state
			socket.emit('register', agent_id);
		});

		Twilio.Device.incoming(function (conn) {

			Twilio.Device.sounds.incoming( false );

			if(isOnline) {

				Twilio.Device.sounds.incoming( true );

				$("#log").append("\nIncoming connection from " + conn.parameters.From);
				// accept the incoming connection and start two-way audio
				conn.accept();
				// enable accept call button
				$("#nav-btn-accept").removeClass('disabled');
			} else {
				conn.reject();
				$("#log").append("\nIncoming call has been rejected");
			}
			$("#nav-btn-state").text(Twilio.Device.status());
		});





		/* ********************************************************************
		 * Other functions
		 */

// 		/*
// 		 * if you want to make an outbound call from agent's softphone 
// 		 */
// 		function call() {
//			Twilio.Device.connect();
//			$("#nav-btn-state").text(Twilio.Device.status());
// 		}

		/*
		 * disconnect agent's softphone
		 */
		function hangup() {
			Twilio.Device.disconnectAll();
			$("#nav-btn-state").text(Twilio.Device.status());
		}

		/*
		 * 'accept call' button pressed in the browser
		 */
		function acceptCall() {

			// stop button flashing
			clearInterval(interval);
			$("#nav-btn-accept").removeClass('flashing');
			$("#nav-btn-accept").addClass('disabled');

			// notify master nodejs that agent is on call and can't accept calls
			socket.emit("deregister", agent_id);

			// get the call type: either 'direct' or 'queue'
			type = $("#calltype").val();

			$("#log").append("\nagent accepted "+ type +" call");
			$("#log").append("\nagent has been deregistered - not able to accept calls");
			
			// accept call if it's a direct call to agent
			if(type == 'direct') {

				/*
				 * OpenVBX requires to send digit 1 to accept call 
				 * so if you'd like to integrate it with OpenVBX, uncomment the following
				 * for direct call connection (not related to queue calls functionality)
				 */
				// connection.sendDigits("1");

			// call to the Twilio queue: if it's a request to serve new Customer in the queue
			} else if(type == 'queue') {

				// get Twilio queue name, unique identifier
				var qid = $("#queueid").val();

				$("#log").append("\ntrying to put agent into the queue '" + qid + "'");

				// connect agent's softphone to the queue qid
				Twilio.Device.connect({
					// agent_calling_queue: true,
					queueId: qid
				});
			}

			$("#nav-btn-state").text(Twilio.Device.status());
		}

		/*
		 * handler for softphone status button: Online/Offline
		 */
		function toggleDeviceStatus() {

			if( $("#nav-btn-status").html() == 'Online' ) {

				$("#nav-btn-status").html('Offline');
				isOnline = false;
				socket.emit("deregister", agent_id);

			} else {

				$("#nav-btn-status").html('Online');
				isOnline = true;

				// register as an agent in ready state
				socket.emit('register', agent_id);
			}
		}

		/*
		 * make 'accept call' button flash, imitation of ringing phone
		 * you might want to use different notification method for production application
		 */
		function flash_btn() {

			interval = setInterval(function() {

				$("#nav-btn-accept").toggleClass('flashing');

				// if agent haven't accepted the call: haven't pressed 'accept call' button
				// within 30 seconds
				if (++curr_repeitions >= repetitions) {
	
					// stop ringing/flashing
					window.clearInterval(interval);
					$("#nav-btn-accept").removeClass('flashing');
					$("#nav-btn-accept").addClass('disabled');

					// reset counter
					curr_repeitions = 0;

					/*
					 * if agent haven't accepted the call we need to reassign call to another agent,
					 * thus we just need to notify master nodejs that agent is not able to pick the call
					 * and the master nodejs will initiate a new attempt to find next available agent
					 */
					socket.emit('missed queue call', $("#queueid").val());
				}
			}, 500);
		}





		/* ********************************************************************
		 * Socket.io callbacks
		 */

		// register as an agent in ready state
		socket.emit('register', agent_id);

		// listen to the ready event
		socket.on('ready', function () {
			isOnline = true;
			$("#nav-btn-status").html('Online');
			// $("#nav-btn-accept").removeClass('disabled');
		});

		// listen to the new call in the queue event
		socket.on('call to queue', function(qid) {

			$("#queueid").val(qid);
			$("#calltype").val("queue");
			$("#nav-btn-accept").removeClass('disabled');
			// $("#nav-btn-status").html('Ringing');
			flash_btn();
		});

//		$(window).on('beforeunload', function(){
//		    socket.close();
//		});

	</script>
</head>


<body>
<!--
	<h3 class="panel-title">Agent #<?php echo $agent_id; ?></h3>
-->
	<div id="statuses">
		<button id="nav-btn-status" class="btn" onclick="toggleDeviceStatus();">Status</button>
		<button id="nav-btn-state"  class="btn disabled">| | |</button>
	</div>
	<div id="buttons">
		<button id="nav-btn-accept" class="btn" onclick="acceptCall();"><i class="fa fa-phone-square"></i> Accept</button>
		<button id="nav-btn-hangup" class="btn" onclick="hangup();"><i class="fa fa-ban"></i> Hang up</button>
		<input type='hidden' id='calltype' value='direct' />
		<input type='hidden' id='queueid' value='' />
	</div>
	<div id="log"></div>

	<script type="text/javascript" src="js/jquery.min.js"></script>

</body>
</html>
