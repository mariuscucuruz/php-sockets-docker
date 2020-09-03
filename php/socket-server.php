<?php

include 'inc/common.php';
require_once 'inc/functions.php';

// Making sure we're disconnected from VPN to get public IP
vpnDisconnect();
sleep(2);

/**
 * @todo there's probably a better way to do this
 */
if (!defined('ORIGINAL_PUBLIC_IP')) {
    define('ORIGINAL_PUBLIC_IP', getPublicIP());
}

// alert when the server starts
sendToSlack("$dashed_separator\nStarted with public IP: ".ORIGINAL_PUBLIC_IP);

$loopCounter = 0;
$errQuotaCounter = 0;
$errQuotaMax = 10;
$lastQueryTimestamp = microtime(true);
$disconnectTimestamp= microtime(true);
$minWaitTime = 0.1; //seconds
$checkVPN = true;

vpnConnect();

/**
 * Create a TCP/IP socket.
 * Server will bind to private IP or 127.0.0.1
 */
$privateIP = shell_exec("/sbin/ifconfig eth1 | awk -F ' *|:' '/inet /{print $3}'");
$address = $privateIP ? trim($privateIP) : '127.0.0.1';
$port = getenv('SOCKET_PORT');
$sock = createSocket($address, $port, 'server', true) or exit("Error creating socket.\n");

while (true) {
    /**
     * socket_accept() accepts incoming connection request on given socket
     * and, after accepting the connection from client socket, returns
     * another socket resource responsible for communication with the
     * corresponding client socket.
     */
    $sockClient = socket_accept($sock) or exit('>>> Connection failed! '.getSocketError()."\n");

    // get info about the connected client
    if (socket_getpeername($sockClient, $clientIp, $clientPort)) {
        $msg = "Client $clientIp:$clientPort is now connected to us.";

        echo "\n$dashed_separator\n".
            ">>> $msg\n";

        sendToSlack($msg);
    }

    // send welcome message
    $sayHello = "\n".
        "Hello $clientIp! Welcome to your Whois Client. \n".
        "I'm listening on $address:$port...... \n".
        "Type 'quit' or 'shutdown' to terminate session or service.\n".
        "$dashed_separator\n";

    if (!socketWrite($sockClient, $sayHello)) {
        $msg = "Connection to $clientIp:$clientPort failed! ". getSocketError();

        sendToSlack("$msg\n$dashed_separator\n$sayHello", true);

        exit(">>> $msg\n$dashed_separator");
    }

    while (true) {
        /**
         * Keep reading from incoming connection.
         * Note: with flag `PHP_NORMAL_READ` reading stops at "\n" or "\r"!
         */
        if (!($buffer = @socket_read($sockClient, 1024, PHP_NORMAL_READ))) {
            $msg = 'Client Disconnected: '. getSocketError() .".\n";
            sendToSlack($msg);
            echo ">>> $msg\n";

            break;
        }

        if ('' === ($buffer = trim($buffer))) {
            continue;
        }

        // debugging:
        if (DEBUG) {
            socketWrite($sockClient, ">>> PHP Debug: you (client) said '$buffer'.\n");
        }

        // default reply is reversed input
        $talkBack = strrev($buffer);

        /**
         * Define scenarios for some expected commands.
         */
        // play some ping pong
        if (in_array($buffer, ['ping', 'pong'])) {
            $talkBack = ($buffer === 'ping') ? 'pong' : 'ping';
            socketWrite($sockClient, $talkBack);
            echo ">>> We're playing... $talkBack.\n";

            continue;
        }

        // respond to IP requests
        if ($buffer === 'ip') {
            $talkBack = getPublicIP();
            socketWrite($sockClient, ">>> Current public IP: $talkBack");

            continue;
        }

        if ($buffer === 'quit') {
            $talkBack = ">>> Closing connection now...\n$dashed_separator\n";
            socketWrite($sockClient, $talkBack);

            //send alert to slack
            sendToSlack("Notice! $clientIp:$clientPort `quit` on request.");

            // close current connection
            socket_close($sockClient);

            break;
        }

        if ($buffer === 'shutdown') {
            $talkBack = ">>> Triggered shut down procedure....\n$dashed_separator\n";
            socketWrite($sockClient, $talkBack);
            sendToSlack("Alert! $clientIp:$clientPort initiated `shutdown`.");

            // close connection and socket
            socket_close($sockClient);
            socket_close($sock);
            socket_shutdown($sock, 2);

            break 2;
        }

        echo ">>> Client says: '$buffer'. We say: '" . trim($talkBack) . "'.\n";
    }

    /**
     * In the unlikely scenario we end up here
     * (e.g. connection forcefully closed by client)
     * close connections and free resources up.
     */
    socket_close($sockClient);
    socket_close($sock);
    socket_shutdown($sock, 2);
}
