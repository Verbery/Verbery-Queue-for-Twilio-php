// start> redis-server
// start> node manage_call_queues.js
// _open> agent_app.php

// These vars are your accountSid and authToken from twilio.com/user/account
var accountSid = "";
var authToken = "";
var twilio = require('twilio')(accountSid, authToken);


var express = require('express')
	, app = express()
    , server = require('http').Server(app)
    , io = require('socket.io').listen(server)
    , compress = require('compression')()
///////////////////////////////////////////
    , formidable = require('formidable')
    , redis = require('redis')
    , credis = redis.createClient()

////////////////////////////////////

app.use(compress);
app.disable('x-powered-by');

app.use(function (req, res, next) {

	console.log("adding headers allow-origin");
	res.setHeader('Access-Control-Allow-Origin', "http://"+req.headers.host+":8080");

	res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, PATCH, DELETE');
	res.setHeader('Access-Control-Allow-Headers', 'X-Requested-With,content-type');
	next();
});

var port = process.env.OPENSHIFT_NODEJS_PORT || 8080,
    ip = process.env.OPENSHIFT_NODEJS_IP || "127.0.0.1";

server.listen(port);
//console.log(ip+':'port);
////////////////////////////////////


io.sockets.on('connection', function (socket) {

	console.log("got connection from socket "+socket.id);

	// register each socket with id
	socket.on('register', function (id) {

		var curdate = (new Date).getTime();
		console.log(curdate+": got register event from client with id = "+id + ". Socket id: " + socket.id);

		// send ready signal to notify agent that (s)he is able to accept calls
		console.log("new client (id="+id+") logged in at " + curdate + ". Socket id: " + socket.id);
    	socket.emit('ready');

//    	// DEMO ONLY: notify index page
//    	socket.broadcast.emit('agent_login', id);

		// add agent to the sorted list to be able to easily get the agent with greatest idle time
		var ags = [ 'agents_set', curdate, socket.id ];
		credis.zadd( ags, function (err, response) {
		    console.log('saved agent in the redis sorted list: socketID='+socket.id+' ts='+curdate);
		    if (err) throw err;
		});

		// if there are any queues with size > 0
		// find the queue with highest average wait time
		console.log('going to find queue with max idle time...');

		twilio.queues.list(function(err, data) {

			if (err) throw err;

			var maxWaitTime = 0;
			var queue_max = '';
			var qcnt = 0;

			console.log('There are '+ data.queues.length +' queues, looping...');

			data.queues.forEach(function(queue) {

				console.log('Looping through queues: '+ queue.friendlyName +', sid: '+ queue.sid +', size: '+ queue.currentSize +', average wait time: '+ queue.averageWaitTime);

				if(queue.currentSize != 0) {

					if(queue.averageWaitTime > maxWaitTime) {

						console.log('Found queue with max idle time');
						maxWaitTime = queue.averageWaitTime;
						queue_max = queue.friendlyName;
					}
					qcnt++;
				}
			});

			console.log('There are '+ qcnt +' active queues, maximum wait time is '+ maxWaitTime +' in queue '+ queue_max);
			// and send request to agent to pick a call from this queue
			if(maxWaitTime > 0) {
				io.sockets.socket(socket.id).emit('call to queue', queue_max );
			}
		});
	});

	socket.on('deregister', function (id) {

		// remove agent from redis
		// that means we won't be able to pick this agent to
		// assign to a queue call

		var ags = [ 'agents_set', socket.id ];
		credis.zrem(ags, function (err, response) {
			console.log('removed an agent from redis sorted list: socketID='+socket.id);
			if (err) throw err;
		});
	});

//	// Promote this socket as master
//	socket.on("register master socket", function() {
//
//		// Save the socket id to Redis so that all processes can access it.
//	    credis.set("mastersocket", socket.id, function(err) {
//	      if (err) throw err;
//	      console.log("Master socket is now" + socket.id);
//	    });
//	});

	socket.on("from master, new call in queue", function(queueID) {

		console.log("got new call in queue " + queueID + " from " + socket.id);

		// find agent with longest idle time and return agent`s socket
//		var sid = findAgent_and_sendNotification();

		// find agent with longest idle time and return agent`s socket
		var agent_id;
		var ags = [ 'agents_set', '+inf', '-inf' ];
		credis.zrevrangebyscore( ags, function (err, response) {
			if (err) throw err;

			agent_id = response[response.length-1];
			console.log('get agent with longest idle time from agents_set: ', agent_id);

			if(agent_id != 'undefined')
				io.sockets.socket(agent_id).emit('call to queue', queueID );
	    });
	});

	socket.on("missed queue call", function(queueID) {

		console.log("got message that agent missed call in queue " + queueID + "; Agent: " + socket.id);

		var curdate = (new Date).getTime();

		// update (remove and then add) agent`s idle start time with curdate in redis
		// remove
		var ags = [ 'agents_set', socket.id ];
		credis.zrem(ags, function (err, response) {
			console.log('removed an agent from redis sorted list: socketID='+socket.id);
			if (err) throw err;
		});
		// add
		ags = [ 'agents_set', curdate, socket.id ];
		credis.zadd( ags, function (err, response) {
		    console.log('saved agent in the redis sorted list: socketID='+socket.id+' ts='+curdate);
		    if (err) throw err;
		});

		// find agent with longest idle time and return agent`s socket
		var agent_id;
		var ags = [ 'agents_set', '+inf', '-inf' ];
		credis.zrevrangebyscore( ags, function (err, response) {
			if (err) throw err;

	        agent_id = response[response.length-1];
			console.log('get agent with longest idle time from agents_set: ', agent_id);

			if(agent_id != 'undefined')
				io.sockets.socket(agent_id).emit('call to queue', queueID );
	    });
	});

	socket.on('disconnect', function () {

		console.log('agent '+ socket.id +' disconnected');

		var ags = [ 'agents_set', socket.id ];
		credis.zrem(ags, function (err, response) {
			console.log('removed an agent from redis sorted list: socketID='+socket.id);
			if (err) throw err;
		});
	});

});

function findAgent_and_sendNotification() {

	// find agent with longest idle time and return agent`s socket
	var agent_id;
	var ags = [ 'agents_set', '+inf', '-inf' ];
	credis.zrevrangebyscore( ags, function (err, response) {
		if (err) throw err;

		console.log('get agent with longest idle time from agents_set: ', response[response.length-1]);

        agent_id = response[response.length-1];
    });
	return agent_id;
}
